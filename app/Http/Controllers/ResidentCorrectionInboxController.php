<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResidentCorrectionInboxController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->fresh();

        abort_unless(
            $user
                && $user->canAccessServices()
                && $user->role === 'captain',
            403
        );

        Gate::forUser($user)->authorize('manage-residents');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        $search = trim((string) $request->input('search', ''));

        // Default to pending. An empty filter means all statuses.
        $status = $request->has('status')
            ? (string) $request->input('status', '')
            : 'pending';

        $statuses = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];

        $pendingCount = DB::table('resident_status_corrections')
            ->where('status', 'pending')
            ->count();

        $query = DB::table('resident_status_corrections as corrections')
            ->join(
                'residents',
                'residents.id',
                '=',
                'corrections.resident_id'
            )
            ->join(
                'users as requester',
                'requester.id',
                '=',
                'corrections.requested_by'
            )
            ->leftJoin(
                'users as reviewer',
                'reviewer.id',
                '=',
                'corrections.reviewed_by'
            )
            ->select(
                'corrections.*',
                'residents.resident_number',
                'residents.first_name',
                'residents.middle_name',
                'residents.last_name',
                'residents.deleted_at as resident_deleted_at',
                'requester.name as requester_name',
                'reviewer.name as reviewer_name'
            )
            ->when($status !== '', function ($query) use ($status) {
                $query->where('corrections.status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('residents.first_name', 'like', "%{$search}%")
                        ->orWhere('residents.middle_name', 'like', "%{$search}%")
                        ->orWhere('residents.last_name', 'like', "%{$search}%")
                        ->orWhere('residents.resident_number', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $query->orWhere('corrections.id', $search);
                    }
                });
            });

        // Pending requests: oldest first. History: most recently reviewed first.
        if ($status === 'pending') {
            $query->orderBy('corrections.created_at')
                ->orderBy('corrections.id');
        } elseif (in_array($status, ['approved', 'rejected'], true)) {
            $query->orderByDesc('corrections.reviewed_at')
                ->orderByDesc('corrections.id');
        } else {
            $query->orderByDesc('corrections.id');
        }

        $corrections = $query
            ->paginate(10)
            ->withQueryString();

        return view('residents.corrections.index', compact(
            'corrections',
            'pendingCount',
            'search',
            'status',
            'statuses'
        ));
    }
}