@extends('layouts.app')

@section('title', 'SMART MASIPIT Assistant')
@section('description', 'Ask about using barangay services in SMART MASIPIT.')

@section('actions')
    <a class="button secondary" href="{{ route('dashboard') }}">
        Back to dashboard
    </a>
@endsection

@section('content')
    <section class="card">
        <p>
            Maaari kang magtanong sa Filipino, English, o Taglish.
            Hindi nito nakikita ang personal request status o health records.
        </p>

        <p class="muted">
            Huwag ilagay ang passwords, reset links, o medical details.
        </p>

        <div
            id="chat-log"
            role="log"
            aria-live="polite"
            aria-relevant="additions"
            aria-label="Chat messages"
            style="height: 340px; overflow-y: auto; padding: 16px;
                   border: 1px solid #d4e2da; border-radius: 10px;"
        >
            <p>
                <strong>Assistant:</strong>
                Magandang araw! Ano ang maitutulong ko sa paggamit
                ng SMART MASIPIT?
            </p>
        </div>

        <form id="chat-form" style="margin-top: 20px;">
            @csrf

            <label for="chat-message">Your question</label>

            <input
                id="chat-message"
                name="message"
                type="text"
                maxlength="500"
                autocomplete="off"
                placeholder="Halimbawa: Paano mag-request ng clearance?"
                required
                style="width: 100%; padding: 12px; margin-top: 8px;"
            >

            <button id="chat-send" type="submit">
                Send
            </button>
        </form>

        <p id="chat-status" role="status" class="muted"></p>
    </section>

    <script>
        (() => {
            const form = document.getElementById('chat-form');
            const input = document.getElementById('chat-message');
            const button = document.getElementById('chat-send');
            const log = document.getElementById('chat-log');
            const status = document.getElementById('chat-status');
            let busy = false;

            function appendMessage(sender, message) {
                const paragraph = document.createElement('p');
                const label = document.createElement('strong');
                const text = document.createElement('span');

                paragraph.style.whiteSpace = 'pre-wrap';
                paragraph.style.overflowWrap = 'anywhere';

                label.textContent = sender + ': ';
                text.textContent = message;

                paragraph.append(label, text);
                log.appendChild(paragraph);

                while (log.children.length > 60) {
                    log.firstElementChild.remove();
                }

                log.scrollTop = log.scrollHeight;
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                const message = input.value.trim();

                if (busy || !message) return;

                busy = true;
                button.disabled = true;
                input.disabled = true;

                appendMessage('You', message);
                input.value = '';
                status.textContent = 'Naghihintay ng sagot…';

                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), 15000);

                try {
                    const response = await fetch(
                        @json(route('chatbot.reply')),
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            signal: controller.signal,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN':
                                    form.querySelector('[name="_token"]').value
                            },
                            body: JSON.stringify({ message })
                        }
                    );

                    if (response.status === 429) {
                        throw new Error(
                            'Maraming requests. Maghintay muna ng isang minuto.'
                        );
                    }

                    if ([401, 403, 419].includes(response.status)) {
                        throw new Error(
                            'I-refresh ang page o mag-sign in muli.'
                        );
                    }

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(
                            data.reply || 'Hindi maiproseso ang tanong ngayon.'
                        );
                    }

                    if (typeof data.reply !== 'string') {
                        throw new Error('Hindi valid ang natanggap na sagot.');
                    }

                    appendMessage('Assistant', data.reply);
                } catch (error) {
                    appendMessage(
                        'System',
                        error.name === 'AbortError'
                            ? 'Matagal ang response. Subukan muli mamaya.'
                            : (
                                error instanceof TypeError
                                    ? 'Hindi makakonekta. Suriin ang connection.'
                                    : error.message
                            )
                    );
                } finally {
                    clearTimeout(timer);
                    busy = false;
                    button.disabled = false;
                    input.disabled = false;
                    status.textContent = '';
                    input.focus();
                }
            });
        })();
    </script>
@endsection