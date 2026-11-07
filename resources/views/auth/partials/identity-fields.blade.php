<fieldset class="identity-fields">
    <legend>Identity verification</legend>

    <p>
        Take three photos using your camera:
        the front of your valid ID, the back of the same ID,
        and a selfie.
    </p>

    <p class="muted">
        Place your ID on a flat surface with good lighting.
        Include all corners, keep the text readable, and avoid glare.
        Do not photograph another person's ID.
    </p>

    <div id="identity-camera-panel" class="identity-camera-panel" hidden>
        <h3 id="identity-camera-title">Camera</h3>

        <video
            id="identity-video"
            autoplay
            muted
            playsinline
            aria-label="Live camera preview"
        ></video>

        <div class="identity-buttons">
            <button type="button" id="identity-capture" disabled>
                Take photo
            </button>

            <button type="button" id="identity-stop">
                Close camera
            </button>
        </div>
    </div>

    <canvas id="identity-canvas" hidden></canvas>

    <p
        id="identity-camera-status"
        role="status"
        aria-live="polite"
        tabindex="-1"
    >
        Capture all three photos before submitting.
    </p>

    <div class="identity-photo-grid">
        <section class="identity-photo-card">
            <h3>1. ID front *</h3>

            <p class="muted">
                Photograph the front of your valid ID.
            </p>

            <img
                id="identity-preview-id_document"
                alt="Captured front of your ID"
                hidden
            >

            <p id="identity-state-id_document">Not captured</p>

            <button
                type="button"
                class="identity-open"
                data-field="id_document"
            >
                Capture ID front
            </button>
        </section>

        <section class="identity-photo-card">
            <h3>2. ID back *</h3>

            <p class="muted">
                Turn over the same ID and photograph its back.
            </p>

            <img
                id="identity-preview-id_back"
                alt="Captured back of your ID"
                hidden
            >

            <p id="identity-state-id_back">Not captured</p>

            <button
                type="button"
                class="identity-open"
                data-field="id_back"
            >
                Capture ID back
            </button>
        </section>

        <section class="identity-photo-card">
            <h3>3. Selfie *</h3>

            <p class="muted">
                Face the camera. Keep your face clear and fully visible.
            </p>

            <img
                id="identity-preview-selfie"
                alt="Your captured selfie"
                hidden
            >

            <p id="identity-state-selfie">Not captured</p>

            <button
                type="button"
                class="identity-open"
                data-field="selfie"
            >
                Capture selfie
            </button>
        </section>
    </div>

    <p id="identity-progress" class="muted" aria-live="polite">
        0 of 3 photos captured.
    </p>

    <label class="identity-consent">
        <input
            type="checkbox"
            name="identity_consent"
            value="1"
            required
        >

        <span>
            I confirm that both ID photos show my own ID and
            that the selfie shows me. I understand that these images
            will be stored privately and reviewed by authorized
            barangay reviewers for account verification.
        </span>
    </label>

    <p class="muted">
        You can retake each photo before submitting.
        If the form returns validation errors, capture the three
        photos again. Contact the barangay office if you cannot
        use a camera.
    </p>

    <noscript>
        <p role="alert">
            JavaScript is required to capture and submit these photos.
        </p>
    </noscript>
</fieldset>

<style>
    .identity-fields {
        min-width: 0;
        margin: 24px 0;
        padding: 20px 0;
        border: 0;
        border-top: 1px solid #dfe5e9;
    }

    .identity-fields legend {
        font-size: 19px;
        font-weight: 700;
    }

    .identity-photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
        gap: 16px;
        margin: 20px 0;
    }

    .identity-photo-card {
        padding: 16px;
        border: 1px solid #dfe5e9;
        border-radius: 10px;
        background: #f8faf9;
    }

    .identity-photo-card h3 {
        margin-top: 0;
    }

    .identity-photo-card img {
        display: block;
        width: 100%;
        max-height: 260px;
        object-fit: contain;
        margin: 12px 0;
        border-radius: 7px;
        background: #e8eeeb;
    }

    .identity-camera-panel {
        padding: 16px;
        border: 1px solid #bac8c1;
        border-radius: 10px;
        margin: 20px 0;
    }

    #identity-video {
        display: block;
        width: 100%;
        max-width: 640px;
        max-height: 480px;
        object-fit: contain;
        background: #172b24;
        border-radius: 8px;
    }

    .identity-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 16px;
    }

    .identity-fields button {
        width: auto;
        margin: 0;
    }

    .identity-fields button:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .identity-consent {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-weight: 400;
    }

    .identity-consent input {
        width: auto;
        margin-top: 6px;
        flex-shrink: 0;
    }

    .identity-fields [hidden] {
        display: none !important;
    }

    #identity-camera-status {
        padding: 12px;
        background: #eff7f2;
        border-radius: 8px;
    }
</style>

<script>
(() => {
    const root = document.querySelector('.identity-fields');
    const form = root.closest('form');

    const panel = document.getElementById('identity-camera-panel');
    const title = document.getElementById('identity-camera-title');
    const video = document.getElementById('identity-video');
    const canvas = document.getElementById('identity-canvas');
    const captureButton = document.getElementById('identity-capture');
    const stopButton = document.getElementById('identity-stop');
    const status = document.getElementById('identity-camera-status');
    const progress = document.getElementById('identity-progress');

    const settings = {
        id_document: {
            label: 'ID front',
            facingMode: 'environment',
            maxDimension: 1600,
            maxBytes: 4 * 1024 * 1024,
            minWidth: 320,
            minHeight: 200
        },
        id_back: {
            label: 'ID back',
            facingMode: 'environment',
            maxDimension: 1600,
            maxBytes: 4 * 1024 * 1024,
            minWidth: 320,
            minHeight: 200
        },
        selfie: {
            label: 'selfie',
            facingMode: 'user',
            maxDimension: 960,
            maxBytes: 2 * 1024 * 1024,
            minWidth: 240,
            minHeight: 240
        }
    };

    const photos = {};
    const previewUrls = {};

    let stream = null;
    let currentField = null;
    let generation = 0;

    function updateProgress() {
        const count = Object.keys(settings)
            .filter(field => photos[field]).length;

        progress.textContent = `${count} of 3 photos captured.`;
    }

    function stopCamera() {
        generation++;

        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }

        stream = null;
        currentField = null;
        video.srcObject = null;
        panel.hidden = true;
        captureButton.disabled = true;
    }

    function clearPhoto(field) {
        delete photos[field];

        if (previewUrls[field]) {
            URL.revokeObjectURL(previewUrls[field]);
            delete previewUrls[field];
        }

        const preview = document.getElementById(`identity-preview-${field}`);
        const state = document.getElementById(`identity-state-${field}`);
        const button = root.querySelector(`[data-field="${field}"]`);

        preview.removeAttribute('src');
        preview.hidden = true;
        state.textContent = 'Not captured';
        button.textContent = `Capture ${settings[field].label}`;

        updateProgress();
    }

    async function openCamera(field) {
        stopCamera();

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            status.textContent =
                'Camera access requires HTTPS or localhost and a supported browser.';
            status.focus();
            return;
        }

        const config = settings[field];
        const requestGeneration = generation;

        currentField = field;
        title.textContent = `Capture ${config.label}`;
        panel.hidden = false;

        status.textContent =
            `Opening camera for ${config.label}. Allow camera access if asked.`;

        panel.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        try {
            const newStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: config.facingMode },
                    width: {
                        ideal: field === 'selfie' ? 960 : 1600
                    },
                    height: {
                        ideal: field === 'selfie' ? 720 : 1200
                    }
                },
                audio: false
            });

            if (requestGeneration !== generation) {
                newStream.getTracks().forEach(track => track.stop());
                return;
            }

            stream = newStream;
            video.srcObject = stream;

            await video.play();

            if (requestGeneration !== generation) {
                return;
            }

            captureButton.disabled = false;

            status.textContent = field === 'selfie'
                ? 'Face the camera, then select Take photo.'
                : `Show the ${config.label} clearly with all corners visible, then select Take photo.`;
        } catch (error) {
            if (requestGeneration !== generation) {
                return;
            }

            stopCamera();

            status.textContent =
                'Unable to open the camera. Check camera permissions, '
                + 'close other apps using it, and try again.';
        }
    }

    root.querySelectorAll('.identity-open').forEach(button => {
        button.addEventListener('click', () => {
            openCamera(button.dataset.field);
        });
    });

    captureButton.addEventListener('click', () => {
        if (
            !currentField
            || !stream
            || !stream.getVideoTracks().some(track => track.readyState === 'live')
            || !video.videoWidth
            || !video.videoHeight
        ) {
            status.textContent =
                'Wait for the live camera preview before taking the photo.';
            return;
        }

        const field = currentField;
        const config = settings[field];
        const requestGeneration = generation;

        const scale = Math.min(
            1,
            config.maxDimension / Math.max(
                video.videoWidth,
                video.videoHeight
            )
        );

        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);

        if (
            canvas.width < config.minWidth
            || canvas.height < config.minHeight
        ) {
            status.textContent =
                'The camera resolution is too low. Try another camera or device.';
            return;
        }

        const context = canvas.getContext('2d');

        if (!context) {
            status.textContent =
                'Your browser could not capture the image. Try another browser.';
            return;
        }

        context.drawImage(
            video,
            0,
            0,
            canvas.width,
            canvas.height
        );

        captureButton.disabled = true;

        canvas.toBlob(blob => {
            if (requestGeneration !== generation) {
                return;
            }

            if (!blob || blob.size > config.maxBytes) {
                captureButton.disabled = false;

                status.textContent =
                    'Unable to save this photo. Please try taking it again.';

                return;
            }

            clearPhoto(field);

            photos[field] = blob;
            previewUrls[field] = URL.createObjectURL(blob);

            const preview = document.getElementById(
                `identity-preview-${field}`
            );

            const state = document.getElementById(
                `identity-state-${field}`
            );

            const button = root.querySelector(
                `[data-field="${field}"]`
            );

            preview.src = previewUrls[field];
            preview.hidden = false;
            state.textContent = 'Captured — review the photo below.';
            button.textContent = `Retake ${config.label}`;

            stopCamera();
            updateProgress();

            status.textContent =
                `${config.label} captured. Review its clarity before submitting.`;

            preview.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }, 'image/jpeg', 0.92);
    });

    stopButton.addEventListener('click', () => {
        stopCamera();

        status.textContent =
            'Camera closed. Previously captured photos are kept.';
    });

    form.addEventListener('submit', event => {
        const missing = Object.keys(settings).filter(field => !photos[field]);

        if (missing.length > 0) {
            event.preventDefault();

            status.textContent =
                'Please capture: '
                + missing.map(field => settings[field].label).join(', ')
                + '.';

            status.focus();
            return;
        }

        if (stream) {
            event.preventDefault();

            status.textContent =
                'Finish taking the current photo or close the camera before submitting.';

            status.focus();
        }
    });

    form.addEventListener('formdata', event => {
        Object.keys(settings).forEach(field => {
            if (photos[field]) {
                event.formData.set(
                    field,
                    photos[field],
                    `${field}.jpg`
                );
            }
        });
    });

    window.addEventListener('pagehide', stopCamera);

    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            stopCamera();

            Object.keys(settings).forEach(clearPhoto);

            status.textContent =
                'Please capture all three photos again before submitting.';
        }
    });
})();
</script>
<style>
    .identity-fields {
        min-width: 0;
        margin: 24px 0;
        padding: 20px 0;
        border: 0;
        border-top: 1px solid #dfe5e9;
    }

    .identity-fields legend {
        font-size: 19px;
        font-weight: 700;
    }

    .identity-fields input[type="file"] {
        width: 100%;
        padding: 12px;
        border: 1px solid #bac8c1;
        border-radius: 7px;
    }

    #identity-video,
    #identity-preview {
        display: block;
        width: 100%;
        max-width: 480px;
        margin: 16px 0;
        border: 1px solid #bac8c1;
        border-radius: 10px;
        background: #eef3f0;
    }

    #identity-video[hidden],
    #identity-preview[hidden],
    .identity-buttons button[hidden] {
        display: none;
    }

    .identity-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 16px 0;
    }

    .identity-buttons button {
        width: auto;
        margin: 0;
    }

    .identity-buttons button:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .identity-fields .checkbox {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-weight: 400;
    }

    .identity-fields .checkbox input {
        width: auto;
        margin-top: 6px;
    }
</style>

<script>
(() => {
    const video = document.getElementById('identity-video');
    const preview = document.getElementById('identity-preview');
    const canvas = document.getElementById('identity-canvas');
    const startButton = document.getElementById('identity-start');
    const captureButton = document.getElementById('identity-capture');
    const retakeButton = document.getElementById('identity-retake');
    const stopButton = document.getElementById('identity-stop');
    const status = document.getElementById('identity-camera-status');
    const idInput = document.getElementById('id_document');
    const form = idInput.closest('form');

    let stream = null;
    let selfie = null;
    let previewUrl = null;
    let generation = 0;

    function stopCamera() {
        generation++;

        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }

        stream = null;
        video.srcObject = null;
        video.hidden = true;
        captureButton.disabled = true;
        stopButton.hidden = true;
        startButton.disabled = false;
    }

    function clearSelfie() {
        selfie = null;
        preview.hidden = true;
        preview.removeAttribute('src');
        retakeButton.hidden = true;

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
    }

    async function startCamera() {
        stopCamera();
        clearSelfie();

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            status.textContent =
                'Camera access requires HTTPS or localhost and a supported browser.';
            return;
        }

        const currentGeneration = generation;
        startButton.disabled = true;
        status.textContent = 'Allow camera access when your browser asks.';

        try {
            const newStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 960 },
                    height: { ideal: 720 }
                },
                audio: false
            });

            if (currentGeneration !== generation) {
                newStream.getTracks().forEach(track => track.stop());
                return;
            }

            stream = newStream;
            video.srcObject = stream;
            video.hidden = false;
            stopButton.hidden = false;

            await video.play();

            if (currentGeneration !== generation) {
                return;
            }

            captureButton.disabled = false;
            status.textContent = 'Camera ready. Face the camera, then take your photo.';
        } catch (error) {
            if (currentGeneration !== generation) {
                return;
            }

            stopCamera();
            status.textContent =
                'Unable to open the camera. Check browser permissions and camera availability.';
        }
    }

    captureButton.addEventListener('click', () => {
        if (
            !stream
            || !stream.getVideoTracks().some(track => track.readyState === 'live')
            || !video.videoWidth
            || !video.videoHeight
        ) {
            status.textContent = 'Open the camera and wait for its preview first.';
            return;
        }

        const scale = Math.min(
            1,
            960 / Math.max(video.videoWidth, video.videoHeight)
        );

        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);

        if (canvas.width < 240 || canvas.height < 240) {
            status.textContent = 'Camera resolution is too low. Please use another camera.';
            return;
        }

        canvas.getContext('2d').drawImage(
            video,
            0,
            0,
            canvas.width,
            canvas.height
        );

        captureButton.disabled = true;
        const currentGeneration = generation;

        canvas.toBlob(blob => {
            if (currentGeneration !== generation) {
                return;
            }

            if (!blob || blob.size > 2 * 1024 * 1024) {
                captureButton.disabled = false;
                status.textContent = 'Unable to capture the photo. Please try again.';
                return;
            }

            clearSelfie();
            selfie = blob;
            previewUrl = URL.createObjectURL(blob);
            preview.src = previewUrl;
            preview.hidden = false;
            retakeButton.hidden = false;

            stopCamera();

            status.textContent =
                'Selfie captured. Review it or select Retake photo.';
        }, 'image/jpeg', 0.85);
    });

    startButton.addEventListener('click', startCamera);
    retakeButton.addEventListener('click', startCamera);

    stopButton.addEventListener('click', () => {
        stopCamera();
        status.textContent = selfie
            ? 'Selfie captured.'
            : 'Camera closed. Capture a selfie before submitting.';
    });

    form.addEventListener('submit', event => {
        const idFile = idInput.files[0];

        if (idFile && idFile.size > 4 * 1024 * 1024) {
            event.preventDefault();
            status.textContent = 'The ID image must not exceed 4 MB.';
            idInput.focus();
            return;
        }

        if (!selfie) {
            event.preventDefault();
            status.textContent = 'Take a camera selfie before submitting.';
            startButton.focus();
        }
    });

    // Attach the captured JPEG as a real multipart upload.
    form.addEventListener('formdata', event => {
        if (selfie) {
            event.formData.set('selfie', selfie, 'camera-selfie.jpg');
        }
    });

    window.addEventListener('pagehide', stopCamera);

    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            stopCamera();
            clearSelfie();
            status.textContent = 'Please capture a new selfie before submitting.';
        }
    });
})();
</script>