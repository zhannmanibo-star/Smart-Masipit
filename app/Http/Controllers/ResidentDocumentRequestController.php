<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Support\SubmitDocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ResidentDocumentRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('request-documents');

        $documentRequests = DocumentRequest::query()
            ->where('resident_id', $request->user()->resident_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('documents.index', [
            'documentRequests' => $documentRequests,
            'types' => DocumentRequest::TYPES,
            'statuses' => DocumentRequest::STATUSES,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('request-documents');

        $resident = $request->user()->resident()->first();

        if (! $resident || $resident->isDeceased()) {
            return redirect()->route('documents.index')->withErrors([
                'resident' =>
                    'A new request cannot be submitted for this resident record. '
                    . 'Contact the Barangay Secretary for review.',
            ]);
        }

        return view('documents.create', [
            'resident' => $resident,
            'types' => DocumentRequest::TYPES,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = SubmitDocumentRequest::handle($request, 'online');

        return redirect()->route('documents.index')->with(
            'success',
            'Request recorded. Reference: ' . $item->reference_number
                . '. View your request list for its current status.'
        );
    }
}