<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Models\ResidentApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();

        $databaseTimezone = config('app.timezone', 'UTC');

        $dayStart = $today->setTimezone($databaseTimezone);
        $dayEnd = $today->addDay()->setTimezone($databaseTimezone);

        $summaryCards = [];

        if (
    Gate::allows('manage-residents')
    && auth()->user()->role === 'captain'
) {
    $summaryCards[] = [
        'label' => 'Pending resident corrections',
        'count' => DB::table('resident_status_corrections')
            ->where('status', 'pending')
            ->count(),
        'description' =>
            'Requests to correct death information awaiting Captain review.',
        'url' => route('resident-corrections.index'),
    ];
}

        if (Gate::allows('manage-residents')) {
            $summaryCards[] = [
                'label' => 'Active residents',
                'count' => Resident::query()
                    ->whereNull('date_of_death')
                    ->count(),
                'description' =>
                    'Unarchived resident records without a recorded death.',
                'url' => route('residents.index', [
                    'status' => 'no_recorded_death',
                ]),
            ];

            $summaryCards[] = [
                'label' => 'Deceased residents',
                'count' => Resident::query()
                    ->whereNotNull('date_of_death')
                    ->count(),
                'description' =>
                    'Unarchived resident records with a recorded death.',
                'url' => route('residents.index', [
                    'status' => 'deceased',
                ]),
            ];

            if (Gate::allows('access-admin')) {
                $summaryCards[] = [
                    'label' => 'Archived residents',
                    'count' => Resident::onlyTrashed()->count(),
                    'description' =>
                        'Archived records, including any with a recorded death. '
                        . 'Excluded from the other resident counts.',
                    'url' => route('residents.deleted'),
                ];
            }
        }

        if (Gate::allows('verify-residents')) {
            $pendingApplications = ResidentApplication::query()
                ->whereHas('user', function ($query) {
                    $query->where('role', 'resident')
                        ->where('account_status', 'pending');
                })
                ->count();

            $summaryCards[] = [
                'label' => 'Pending account verification',
                'count' => $pendingApplications,
                'description' => 'Resident applications awaiting verification.',
                'url' => route('verification.index'),
            ];
        }

        if (Gate::allows('process-document-requests')) {
            $counts = DocumentRequest::query()
                ->select('status')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $statusCards = [
                'submitted' => [
                    'label' => 'Submitted requests',
                    'description' => 'Requests with a recorded Submitted status.',
                ],
                'under_review' => [
                    'label' => 'Under review',
                    'description' => 'Requests awaiting an approval decision.',
                ],
                'approved' => [
                    'label' => 'Approved for preparation',
                    'description' =>
                        'Approved requests not yet marked ready for pickup.',
                ],
                'ready_for_release' => [
                    'label' => 'Ready for pickup',
                    'description' =>
                        'Requests marked ready. Eligibility is rechecked before release.',
                ],
            ];

            foreach ($statusCards as $status => $details) {
                $summaryCards[] = [
                    'label' => $details['label'],
                    'count' => (int) ($counts[$status] ?? 0),
                    'description' => $details['description'],
                    'url' => route('staff.documents.index', [
                        'status' => $status,
                    ]),
                ];
            }

            $releasedToday = DocumentRequest::query()
                ->where('status', 'released')
                ->where('released_at', '>=', $dayStart)
                ->where('released_at', '<', $dayEnd)
                ->count();

            $summaryCards[] = [
                'label' => 'Released today',
                'count' => $releasedToday,
                'description' => 'Recorded releases for today in Philippine time.',
                'url' => route('staff.documents.index', [
                    'status' => 'released',
                    'released_on' => $today->toDateString(),
                ]),
            ];
        }

        return view('dashboard', [
            'summaryCards' => $summaryCards,
            'dashboardDate' => $today->format('F d, Y'),
        ]);
    }
}