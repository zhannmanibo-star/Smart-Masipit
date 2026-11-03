<p>
    <strong>Status:</strong>
    {{ $statuses[$entry->finding_status] ?? $entry->finding_status }}
</p>

@if ($entry->details)
    <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $entry->details }}</p>
@endif

<p>
    <strong>Information source:</strong>
    {{ $sources[$entry->information_source] ?? $entry->information_source }}
</p>

<p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $entry->source_details }}</p>

<p>
    <strong>Interview / source document date:</strong>
    {{ $entry->source_date->format('M d, Y') }}
</p>

<p>
    <strong>Reason for this entry:</strong>
</p>

<p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $entry->change_reason }}</p>

<p class="muted">
    Version {{ $entry->version }} ·
    Recorded by {{ $entry->recordedBy?->name ?? 'Unavailable' }}
    on
    {{ $entry->created_at->copy()
        ->timezone('Asia/Manila')
        ->format('M d, Y · h:i A') }}
    (Philippine time)
</p>