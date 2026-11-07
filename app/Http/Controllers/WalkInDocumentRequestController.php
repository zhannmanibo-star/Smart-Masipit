<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Support\SubmitDocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WalkInDocumentRequestController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('process-document-requests');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'resident' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) $request->input('search', ''));

        $residents = Resident::query()
            ->whereNull('date_of_death')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('sitio', 'like', "%{$search}%")
                        ->orWhere('resident_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        $selectedResident = $request->filled('resident')
            ? Resident::find($request->integer('resident'))
            : null;

        if (
            $request->filled('resident')
            && (! $selectedResident || $selectedResident->isDeceased())
        ) {
            return redirect()
                ->route('staff.documents.walk-in.create', ['search' => $search])
                ->withErrors([
                    'resident_id' =>
                        'Select an unarchived resident without a recorded death.',
                ]);
        }

        return view('staff.documents.walk-in', [
            'residents' => $residents,
            'selectedResident' => $selectedResident,
            'search' => $search,
            'types' => DocumentRequest::TYPES,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = SubmitDocumentRequest::handle($request, 'walk_in');

        return redirect()->route('staff.documents.show', $item)->with(
            'success',
            'Walk-in request recorded. Reference: ' . $item->reference_number
        );
    }
}