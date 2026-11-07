<?php

namespace App\Http\Controllers;

use App\Models\ResidentApplication;
use App\Models\User;
use App\Support\IdentityEvidence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class IdentityEvidenceController extends Controller
{
    public function edit(Request $request): View
    {
        $this->assertPendingResident($request->user());

        $application = ResidentApplication::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return view('account.identity', compact('application'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertPendingResident($request->user());

        $applicationId = ResidentApplication::query()
            ->where('user_id', $request->user()->id)
            ->value('id');

        abort_unless($applicationId, 404);

        $data = $request->validate(
            IdentityEvidence::rules(),
            [],
            [
                'id_document' => 'ID front photo',
                'id_back' => 'ID back photo',
                'selfie' => 'camera selfie',
            ]
        );

        $evidence = IdentityEvidence::store($data);

        try {
            $oldEvidence = DB::transaction(function () use (
                $request,
                $applicationId,
                $evidence
            ) {
                $application = ResidentApplication::query()
                    ->lockForUpdate()
                    ->findOrFail($applicationId);

                $user = User::query()
                    ->lockForUpdate()
                    ->findOrFail($request->user()->id);

                $this->assertPendingResident($user);

                abort_unless(
                    (int) $application->user_id === (int) $user->id,
                    403
                );

                if (
                    DB::table('account_verification_reviews')
                        ->where('resident_application_id', $application->id)
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'identity' =>
                            'This application already has a final review. '
                            . 'Contact the barangay office.',
                    ]);
                }

                $old = [
                    'id_document_path' => $application->id_document_path,
                    'id_back_path' => $application->id_back_path,
                    'selfie_path' => $application->selfie_path,
                ];

                foreach ($evidence as $field => $value) {
                    $application->{$field} = $value;
                }

                $application->save();

                return $old;
            }, 3);
        } catch (Throwable $exception) {
            IdentityEvidence::cleanup($evidence);

            throw $exception;
        }

        IdentityEvidence::cleanup($oldEvidence);

        return redirect()
            ->route('account.status')
            ->with(
                'success',
                'ID front, ID back, and selfie submitted. '
                . 'Please wait for barangay verification.'
            );
    }

    public function show(
        ResidentApplication $application,
        string $kind,
        string $version
    ): Response {
        Gate::authorize('verify-residents');

        $columns = [
            'id' => 'id_document_path',
            'id-back' => 'id_back_path',
            'selfie' => 'selfie_path',
        ];

        abort_unless(array_key_exists($kind, $columns), 404);

        abort_unless(
            $application->identity_evidence_version
            && hash_equals(
                (string) $application->identity_evidence_version,
                $version
            ),
            404
        );

        $path = $application->{$columns[$kind]};

        abort_unless(
            is_string($path)
            && $path !== ''
            && IdentityEvidence::disk()->exists($path),
            404
        );

        $bytes = IdentityEvidence::read($path);

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        // PNG remains readable for older ID submissions.
        abort_unless(
            in_array($mime, ['image/jpeg', 'image/png'], true),
            404
        );

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    private function assertPendingResident(User $user): void
    {
        abort_unless(
            $user->role === 'resident'
            && $user->account_status === 'pending',
            403,
            'Only pending resident applicants may submit identity evidence.'
        );
    }
}