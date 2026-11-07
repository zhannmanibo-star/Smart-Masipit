<?php

namespace App\Http\Controllers;

use App\Models\ResidentApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Notifications\AccountVerificationResult;
use Illuminate\Support\Facades\Log;
use App\Support\IdentityEvidence;

class AccountVerificationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('verify-residents');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) $request->input('search', ''));

        $applications = ResidentApplication::query()
            ->whereHas('user', function ($query) {
                $query->where('role', 'resident')
                    ->where('account_status', 'pending');
            })
            ->with('user:id,name,email,role,account_status')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('sitio', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where(
                                'email',
                                'like',
                                "%{$search}%"
                            );
                        });
                });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view(
            'verification.index',
            compact('applications', 'search')
        );
    }
    public function history(Request $request): View
{
    Gate::authorize('verify-residents');

    $request->validate([
        'search' => ['nullable', 'string', 'max:100'],
        'decision' => ['nullable', 'in:approved,rejected'],
    ]);

    $search = trim((string) $request->input('search', ''));
    $decision = $request->input('decision') ?? '';

    $reviews = DB::table('account_verification_reviews as reviews')
        ->join(
            'resident_applications as applications',
            'applications.id',
            '=',
            'reviews.resident_application_id'
        )
        ->join(
            'users as applicants',
            'applicants.id',
            '=',
            'applications.user_id'
        )
        ->join(
            'users as reviewers',
            'reviewers.id',
            '=',
            'reviews.reviewer_id'
        )
        ->leftJoin(
            'residents',
            'residents.id',
            '=',
            'reviews.resident_id'
        )
        ->select([
            'reviews.id',
            'reviews.resident_application_id',
            'reviews.decision',
            'reviews.note',
            'reviews.reviewed_at',
            'applications.first_name',
            'applications.middle_name',
            'applications.last_name',
            'applicants.email as applicant_email',
            'reviewers.name as reviewer_name',
            'residents.resident_number',
            'residents.deleted_at as resident_deleted_at',
        ])
        ->when($decision !== '', function ($query) use ($decision) {
            $query->where('reviews.decision', $decision);
        })
        ->when($search !== '', function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where(
                    'applications.first_name',
                    'like',
                    "%{$search}%"
                )
                    ->orWhere(
                        'applications.middle_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'applications.last_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'applicants.email',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'reviewers.name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'residents.resident_number',
                        'like',
                        "%{$search}%"
                    );
            });
        })
        ->orderByDesc('reviews.reviewed_at')
        ->orderByDesc('reviews.id')
        ->paginate(10)
        ->withQueryString();

    return view(
        'verification.history',
        compact('reviews', 'search', 'decision')
    );
}

    public function show(
    Request $request,
    ResidentApplication $application
): View|RedirectResponse {
    Gate::authorize('verify-residents');

    $application->load('user');

    if (
        $application->user->role !== 'resident'
        || $application->user->account_status !== 'pending'
    ) {
        return redirect()
            ->route('verification.index')
            ->withErrors([
                'application' => 'This application is no longer pending.',
            ]);
    }

    $request->validate([
        'resident_reference' => ['nullable', 'uuid'],
    ]);

    $reference = $request->old(
        'resident_reference',
        $request->input('resident_reference', '')
    );

    $reference = is_string($reference) ? trim($reference) : '';

    $resident = $reference !== ''
        ? Resident::where('resident_number', $reference)->first()
        : null;

    $alreadyLinked = $resident
        ? User::where('resident_id', $resident->id)->exists()
        : false;

    return view('verification.show', compact(
        'application',
        'reference',
        'resident',
        'alreadyLinked'
    ));
}

public function createResident(
    Request $request,
    ResidentApplication $application
): RedirectResponse {
    Gate::authorize('verify-residents');

    $request->validate([
        'residency_confirmed' => ['accepted'],
    ], [
        'residency_confirmed.accepted' =>
            'Confirm that you have verified the applicant’s '
            . 'identity and barangay residency.',
    ]);

   $resident = DB::transaction(function () use ($application, $request) {
        $currentApplication = ResidentApplication::query()
            ->lockForUpdate()
            ->findOrFail($application->id);
            $this->assertIdentityReviewed($request, $currentApplication);

        $applicant = User::query()
            ->lockForUpdate()
            ->findOrFail($currentApplication->user_id);

        if (
            $applicant->role !== 'resident'
            || $applicant->account_status !== 'pending'
        ) {
            throw ValidationException::withMessages([
                'application' =>
                    'This application is no longer pending.',
            ]);
        }

        $normalize = fn (string $value): string =>
            Str::lower(Str::squish($value));

        // Include deleted records to avoid recreating them accidentally.
        $matches = Resident::withTrashed()
            ->whereDate(
                'birth_date',
                $currentApplication->birth_date->format('Y-m-d')
            )
            ->get()
            ->filter(function ($candidate) use (
                $currentApplication,
                $normalize
            ) {
                return $normalize($candidate->first_name)
                        === $normalize($currentApplication->first_name)
                    && $normalize($candidate->last_name)
                        === $normalize($currentApplication->last_name);
            });

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'resident' =>
                    'Multiple possible resident records were found. '
                    . 'Review the registry and select the correct record '
                    . 'using its reference. No new record was created.',
            ]);
        }

        if ($matches->count() === 1) {
            $existing = $matches->first();

            if ($existing->trashed()) {
                throw ValidationException::withMessages([
                    'resident' =>
                        'A possible matching record exists in Deleted Residents. '
                        . 'Ask an administrator to review it first. Reference: '
                        . $existing->resident_number,
                ]);
            }

            // Return the existing record for comparison, not automatic approval.
            return $existing;
        }

        $resident = new Resident();

        $resident->resident_number = (string) Str::uuid();
        $resident->first_name = Str::squish(
            $currentApplication->first_name
        );

        $resident->middle_name = $currentApplication->middle_name
            ? Str::squish($currentApplication->middle_name)
            : null;

        $resident->last_name = Str::squish(
            $currentApplication->last_name
        );

        $resident->birth_date = $currentApplication->birth_date
            ->format('Y-m-d');

        $resident->sex = $currentApplication->sex;
        $resident->sitio = $currentApplication->sitio;

        // Application address details are optional.
        // The existing residents table requires a non-null string.
        $resident->address = $currentApplication->address ?? '';

        $resident->contact_number = $currentApplication->contact_number;
        $resident->save();

        return $resident;
    }, 3);

    $message = $resident->wasRecentlyCreated
        ? 'Resident record created from the application. '
            . 'Review the loaded record before approving the account.'
        : 'A possible matching resident already exists. '
            . 'It has been loaded for comparison. No new record was created.';

    return redirect()
        ->route('verification.show', [
            'application' => $application->id,
            'resident_reference' => $resident->resident_number,
        ])
        ->with('success', $message);
}

public function decide(
    Request $request,
    ResidentApplication $application
): RedirectResponse {
    Gate::authorize('verify-residents');

    $data = $request->validate([
        'decision' => ['required', 'in:approved,rejected'],

        'resident_reference' => [
            'required_if:decision,approved',
            'nullable',
            'uuid',
        ],

        'review_note' => ['required', 'string', 'min:10', 'max:2000'],
        'review_confirmed' => ['accepted'],
    ]);

    try {
        $notificationData = DB::transaction(function () use (
    $request,
    $application,
    $data
) {
            $currentApplication = ResidentApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            $applicant = User::query()
                ->lockForUpdate()
                ->findOrFail($currentApplication->user_id);

            if (
                $applicant->role !== 'resident'
                || $applicant->account_status !== 'pending'
            ) {
                throw ValidationException::withMessages([
                    'application' =>
                        'This application has already been reviewed '
                        . 'or is no longer eligible for review.',
                ]);
            }

            $hasReview = DB::table('account_verification_reviews')
                ->where('resident_application_id', $currentApplication->id)
                ->exists();

            if ($hasReview) {
                throw ValidationException::withMessages([
                    'application' =>
                        'A final review already exists for this application.',
                ]);
            }

            $resident = null;
            $reviewedAt = now();

            if ($data['decision'] === 'approved') {
                $this->assertIdentityReviewed($request, $currentApplication);
                $resident = Resident::query()
                    ->where('resident_number', $data['resident_reference'])
                    ->lockForUpdate()
                    ->first();

                if (! $resident) {
                    throw ValidationException::withMessages([
                        'resident_reference' =>
                            'Select an existing active resident record.',
                    ]);
                }

                $alreadyLinked = User::query()
                    ->where('resident_id', $resident->id)
                    ->exists();

                if ($alreadyLinked) {
                    throw ValidationException::withMessages([
                        'resident_reference' =>
                            'This resident is already linked to an account. '
                            . 'Do not create a second account for the same person.',
                    ]);
                }

                $applicant->resident_id = $resident->id;
                $applicant->verified_by = $request->user()->id;
                $applicant->verified_at = $reviewedAt;
                $applicant->rejection_reason = null;
                $currentApplication->identity_reviewed_at = $reviewedAt;
                $currentApplication->identity_reviewed_by = $request->user()->id;
                $currentApplication->save();
            } else {
                $applicant->resident_id = null;
                $applicant->verified_by = null;
                $applicant->verified_at = null;
                $applicant->rejection_reason = $data['review_note'];
            }

            $applicant->account_status = $data['decision'];
            $applicant->save();

            DB::table('account_verification_reviews')->insert([
                'resident_application_id' => $currentApplication->id,
                'reviewer_id' => $request->user()->id,
                'resident_id' => $resident?->id,
                'decision' => $data['decision'],
                'note' => $data['review_note'],
                'reviewed_at' => $reviewedAt,
            ]);
            return [
    'applicant' => $applicant,
    'decision' => $data['decision'],
    'reviewed_at' => $reviewedAt->copy()
        ->timezone('Asia/Manila')
        ->format('M d, Y · h:i A'),
];
        }, 3);
    } catch (QueryException $exception) {
        $message = $exception->getMessage();

        if (
            str_contains($message, 'users.resident_id')
            || str_contains($message, 'users_resident_id_unique')
        ) {
            throw ValidationException::withMessages([
                'resident_reference' =>
                    'This resident was linked to another account. '
                    . 'Refresh the review page before continuing.',
            ]);
        }

        throw $exception;
    }

   $successMessage = $data['decision'] === 'approved'
    ? 'Resident account approved and linked successfully.'
    : 'Application rejected. The reason is available to the applicant.';

try {
    $notificationData['applicant']->notify(
        new AccountVerificationResult(
            $notificationData['decision'],
            $notificationData['reviewed_at']
        )
    );
} catch (\Throwable $exception) {
    Log::error('Account verification email could not be queued.', [
        'application_id' => $application->id,
        'user_id' => $notificationData['applicant']->id,
        'exception_class' => $exception::class,
    ]);

    return redirect()
        ->route('verification.index')
        ->with('success', $successMessage)
        ->withErrors([
            'notification' =>
                'The decision was saved, but the notification email '
                . 'could not be queued. Do not submit the decision again. '
                . 'Ask the system administrator to check email delivery.',
        ]);
}

return redirect()
    ->route('verification.index')
    ->with(
        'success',
        $successMessage . ' An email notification has been queued.'
    );
}

private function assertIdentityReviewed(
    Request $request,
    ResidentApplication $application
): void {
    $reviewer = $request->user()->fresh();

    abort_unless($reviewer, 403);

    Gate::forUser($reviewer)->authorize('verify-residents');

    $data = $request->validate([
        'identity_review_confirmed' => ['accepted'],
        'identity_evidence_version' => ['required', 'uuid'],
    ], [
        'identity_review_confirmed.accepted' =>
            'Confirm that you inspected the ID front, ID back, '
            . 'and camera selfie.',
    ]);

    if (! IdentityEvidence::available($application)) {
        throw ValidationException::withMessages([
            'identity' =>
                'The applicant must submit the front and back '
                . 'of their valid ID and a camera selfie '
                . 'before verification can proceed.',
        ]);
    }

    if (! hash_equals(
        (string) $application->identity_evidence_version,
        $data['identity_evidence_version']
    )) {
        throw ValidationException::withMessages([
            'identity' =>
                'The applicant replaced their identity images. '
                . 'Refresh this page and review the latest images first.',
        ]);
    }
}
}