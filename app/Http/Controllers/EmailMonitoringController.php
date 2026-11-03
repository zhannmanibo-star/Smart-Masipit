<?php

namespace App\Http\Controllers;

use App\Notifications\AccountVerificationResult;
use App\Notifications\DocumentReadyForPickup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmailMonitoringController extends Controller
{
    private const TYPES = [
        AccountVerificationResult::class => 'Account verification result',
        DocumentReadyForPickup::class => 'Document ready for pickup',
    ];

    private function databaseConnection(): string
    {
        $connection = config('queue.connections.database.connection')
            ?: config('database.default');

        $failedConnection = config('queue.failed.database')
            ?: config('database.default');

        abort_unless(
            config('queue.connections.database.driver') === 'database'
                && config('queue.failed.driver') === 'database-uuids'
                && $connection === $failedConnection
                && config("database.connections.{$connection}.driver") === 'mysql',
            503,
            'Email monitoring requires the database queue and failed jobs '
                . 'to use the same MySQL connection.'
        );

        return $connection;
    }

    private function failedTable(): string
    {
        return config('queue.failed.table', 'failed_jobs');
    }

    public function index(): Response
    {
        Gate::authorize('access-admin');

        $connection = $this->databaseConnection();

        $jobs = DB::connection($connection)
            ->table($this->failedTable())
            ->where('connection', 'database')
            ->where('queue', 'mail')
            ->whereIn(
                'payload->displayName',
                array_keys(self::TYPES)
            )
            ->where(
                'payload->data->commandName',
                'Illuminate\\Notifications\\SendQueuedNotifications'
            )
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->paginate(15, [
                'uuid',
                'failed_at',
                'payload',
            ]);

        // Pass only safe display fields to the Blade template.
        $jobs->through(function ($job) {
            $payload = json_decode(
                $job->payload,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            return (object) [
                'uuid' => $job->uuid,
                'failed_at' => $job->failed_at,
                'label' => self::TYPES[$payload['displayName']],
            ];
        });

        return response()
            ->view('admin.email-monitoring', compact('jobs'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function retry(
        Request $request,
        string $uuid
    ): RedirectResponse {
        Gate::authorize('access-admin');

        $request->validate([
            'confirmed' => ['accepted'],
        ]);

        $connection = $this->databaseConnection();

        try {
            DB::connection($connection)->transaction(function () use (
                $request,
                $connection,
                $uuid
            ) {
                $actor = $request->user()->fresh();

                abort_unless($actor, 403);
                Gate::forUser($actor)->authorize('access-admin');

                $job = DB::connection($connection)
                    ->table($this->failedTable())
                    ->where('uuid', $uuid)
                    ->lockForUpdate()
                    ->first();

                if (! $job) {
                    throw ValidationException::withMessages([
                        'retry' =>
                            'This failed job is no longer available. '
                            . 'It may already have been queued for retry.',
                    ]);
                }

                $payload = json_decode(
                    $job->payload,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $supported = $job->connection === 'database'
                    && $job->queue === 'mail'
                    && isset(self::TYPES[$payload['displayName'] ?? ''])
                    && ($payload['data']['commandName'] ?? null)
                        === 'Illuminate\\Notifications\\SendQueuedNotifications';

                abort_unless(
                    $supported,
                    403,
                    'This job is not eligible for retry through this page.'
                );

                // Both queue tables use the same database transaction.
                // The row lock prevents simultaneous retries from this page.
                $exitCode = Artisan::call('queue:retry', [
                    'id' => [$uuid],
                ]);

                $stillFailed = DB::connection($connection)
                    ->table($this->failedTable())
                    ->where('uuid', $uuid)
                    ->exists();

                if ($exitCode !== 0 || $stillFailed) {
                    throw new \RuntimeException(
                        'Failed job was not successfully requeued.'
                    );
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Email retry could not be completed.', [
                'job_uuid' => $uuid,
                'actor_id' => $request->user()->id,
                'exception_class' => $exception::class,
            ]);

            return back()->withErrors([
                'retry' =>
                    'The email could not be queued for retry. '
                    . 'Ask the system administrator to check the queue configuration.',
            ]);
        }

        Log::info('Administrator retried a failed email job.', [
            'job_uuid' => $uuid,
            'actor_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.email-monitoring.index')
            ->with(
                'success',
                'The email job has been queued for another attempt. '
                . 'This does not confirm inbox delivery.'
            );
    }
}