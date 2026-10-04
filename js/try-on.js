/**
 * Virtual Try-On (LiveLook) frontend.
 *
 * Captures the webcam, lets the user cycle the product catalog, sends the
 * current frame + selected product to the PHP proxy (includes/tryon-ajax.php),
 * and shows the AI-generated "wearing it" result. All AI work is server-side;
 * this file only handles capture, UI state and graceful failure paths.
 *
 * Config is injected by try-on.php via window.TRYON_CONFIG:
 *   { products: [{id,name,price,image,gender,sizes,colors}], csrfToken, endpoints: {tryOn, save, suggest} }
 */
(function () {
    'use strict';

    const cfg = window.TRYON_CONFIG || {};
    const allProducts = Array.isArray(cfg.products) ? cfg.products : [];
    let products = allProducts.slice();   // current view (after gender filter)
    const endpoints = cfg.endpoints || {};
    const CSRF = cfg.csrfToken || '';

    // Largest dimension we send to the API — keeps payloads small and fast.
    const MAX_CAPTURE_DIM = 768;
    const JPEG_QUALITY = 0.88;

    // Countdown (seconds) shown before the frame is captured, so the user has
    // time to step back and pose.
    const COUNTDOWN_SECONDS = 5;

    // ---- Element refs ----
    const el = (id) => document.getElementById(id);
    const liveVideo = el('tryonLive');
    const thumbVideo = el('tryonThumb');
    const resultImg = el('tryonResult');
    const stage = el('tryonStage');
    const loadingOverlay = el('tryonLoading');
    const errorOverlay = el('tryonError');
    const errorText = el('tryonErrorText');
    const liveTag = el('tryonLiveTag');

    const btnTryOn = el('btnTryOn');
    const btnLive = el('btnLive');
    const btnSave = el('btnSave');
    const btnDownload = el('btnDownload');
    const btnAddCart = el('btnAddCart');
    const btnStylist = el('btnStylist');
    const btnUpload = el('btnUpload');
    const btnSwitchCam = el('btnSwitchCam');
    const btnUseCamera = el('btnUseCamera');
    const btnCamera = el('btnCamera');
    const btnCompare = el('btnCompare');
    const uploadInput = el('uploadInput');
    const uploadImg = el('tryonUpload');
    const suggestOverlay = el('tryonSuggest');
    const suggestList = el('suggestList');
    const suggestClose = el('suggestClose');
    const loadingText = loadingOverlay.querySelector('p');

    // Before/after compare elements
    const compareEl = el('tryonCompare');
    const compareBefore = el('compareBefore');
    const compareAfter = el('compareAfter');
    const compareHandle = el('compareHandle');

    const catImg = el('catalogImg');
    const catName = el('catalogName');
    const catPrice = el('catalogPrice');
    const catCounter = el('catalogCounter');
    const btnPrev = el('catalogPrev');
    const btnNext = el('catalogNext');

    // Failed try-on panel
    const failOverlay = el('tryonFail');
    const failText = el('tryonFailText');
    const failRetry = el('failRetry');
    const failLooks = el('failLooks');
    const failClose = el('failClose');

    // Colour / size picker for "Add to Cart"
    const pickPanel = el('tryonPick');
    const pickColors = el('pickColors');
    const pickSizes = el('pickSizes');
    const pickColorGroup = el('pickColorGroup');
    const pickSizeGroup = el('pickSizeGroup');
    const pickConfirm = el('pickConfirm');
    const pickCancel = el('pickCancel');

    // AI size finder (inside the Size group of the picker)
    const sizeFindToggle = el('sizeFindToggle');
    const sizeFind = el('sizeFind');
    const sizeHeight = el('sizeHeight');
    const sizeWeight = el('sizeWeight');
    const sizeFit = el('sizeFit');
    const sizeFindGo = el('sizeFindGo');
    const sizeFindResult = el('sizeFindResult');

    // Saved-look viewer
    const lightbox = el('tryonLightbox');
    const lbImg = el('lbImg');
    const lbCaption = el('lbCaption');
    const lbPrev = el('lbPrev');
    const lbNext = el('lbNext');
    const lbClose = el('lbClose');
    const lbDownload = el('lbDownload');

    const btnEnd = el('btnEnd');
    const btnCart = el('btnCart');
    const cartBadge = el('cartBadge');
    const drawer = el('tryonDrawer');
    const drawerBackdrop = el('tryonDrawerBackdrop');
    const drawerClose = el('drawerClose');
    const drawerBody = el('drawerBody');
    const toast = el('tryonToast');

    let stream = null;
    let currentIndex = 0;
    let busy = false;
    let savedCount = 0;
    let currentFacing = 'user';   // 'user' (front) or 'environment' (back)
    let uploadedImage = null;     // dataURL when a photo is uploaded instead of live camera
    let lastBefore = null;        // captured "before" frame, for the compare slider
    let lastAfter = null;         // generated "after" image, for the compare slider
    let currentGender = 'all';    // catalog filter: all | mens | womens | kids
    let cameraReady = false;      // true once the camera (or an upload) is usable
    let cameraOn = false;         // whether the live camera is currently running
    let sessionEnded = false;     // END was pressed: late AI answers are ignored

    // The stage tag (LIVE / PHOTO / TRY-ON). Setting textContent would delete
    // its coloured dot, so the dot is rebuilt with the label every time.
    function setStageTag(label, dotColor) {
        liveTag.innerHTML = '<span class="live-dot" aria-hidden="true"></span> ';
        liveTag.appendChild(document.createTextNode(label));
        liveTag.querySelector('.live-dot').style.background = dotColor;
    }

    // -------------------------------------------------------------------
    // Camera
    // -------------------------------------------------------------------
    // Front camera reads like a mirror; back camera shows the true orientation.
    function applyMirror() {
        const mirror = currentFacing === 'user';
        liveVideo.classList.toggle('mirrored', mirror);
        thumbVideo.style.transform = mirror ? 'scaleX(-1)' : 'none';
    }

    // Opens (or re-opens) the camera stream with the given facing mode. Throws
    // on failure so callers can react (e.g. revert on a failed switch).
    async function openStream(facing) {
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
        }
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: facing }, width: { ideal: 1280 }, height: { ideal: 960 } },
            audio: false,
        });
        currentFacing = facing;
        applyMirror();
        liveVideo.srcObject = stream;
        thumbVideo.srcObject = stream;
        await liveVideo.play().catch(() => {});
        await thumbVideo.play().catch(() => {});
        // Camera is live now.
        cameraOn = true;
        cameraReady = true;
        hideOverlay(errorOverlay);
        updateActionButtons();
        syncCameraBtn();
    }

    // Placeholder shown while the camera is off (the default state).
    function showCameraOff() {
        errorText.textContent = 'Camera is off. Tap “Camera On” to start, or use “Upload Photo”.';
        showOverlay(errorOverlay);
    }

    // Reflect the current camera state on the toggle button.
    function syncCameraBtn() {
        if (!btnCamera) return;
        btnCamera.innerHTML = cameraOn
            ? '<i class="fas fa-video-slash" aria-hidden="true"></i> Camera Off'
            : '<i class="fas fa-video" aria-hidden="true"></i> Camera On';
    }

    // Turn the live camera on or off.
    async function toggleCamera() {
        if (cameraOn) {
            stopCamera();
            cameraOn = false;
            cameraReady = false;
            showCameraOff();
            updateActionButtons();
            syncCameraBtn();
        } else {
            await startCamera(); // sets state + hides overlay on success
        }
    }

    async function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showPermissionError('Your browser does not support camera access.');
            return;
        }
        try {
            await openStream(currentFacing);
        } catch (err) {
            if (err && (err.name === 'NotAllowedError' || err.name === 'SecurityError')) {
                showPermissionError('Camera permission was denied. Enable it in your browser settings, then reload.');
            } else if (err && err.name === 'NotFoundError') {
                showPermissionError('No camera was found on this device.');
            } else {
                showPermissionError('Could not start the camera. Please check your device and reload.');
            }
        }
    }

    // Toggle between front and back camera, reverting if the other isn't available.
    async function switchCamera() {
        const prev = currentFacing;
        const next = prev === 'user' ? 'environment' : 'user';
        try {
            await openStream(next);
            showToast(next === 'environment' ? 'Back camera' : 'Front camera');
        } catch (err) {
            try { await openStream(prev); } catch (e) {}
            showToast('Could not switch — only one camera available.', true);
        }
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
        }
        liveVideo.srcObject = null;
        thumbVideo.srcObject = null;
    }

    // -------------------------------------------------------------------
    // Catalog
    // -------------------------------------------------------------------
    function renderCatalog() {
        closePicker();
        if (products.length === 0) {
            catName.textContent = 'No items for this filter';
            catPrice.textContent = '';
            catCounter.textContent = '0/0';
            catImg.removeAttribute('src');
            btnPrev.disabled = btnNext.disabled = true;
            return;
        }
        btnPrev.disabled = btnNext.disabled = false;
        const p = products[currentIndex];
        catImg.src = 'images/products/' + p.image;
        catImg.alt = p.name;
        catName.textContent = p.name;
        catPrice.textContent = '₱' + Number(p.price).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        catCounter.textContent = (currentIndex + 1) + '/' + products.length;
    }

    function cycle(delta) {
        if (products.length === 0) return;
        currentIndex = (currentIndex + delta + products.length) % products.length;
        renderCatalog();
    }

    // Enable Try On / AI Stylist only when there's a usable source and products.
    function updateActionButtons() {
        const ready = (cameraReady || !!uploadedImage) && products.length > 0;
        btnTryOn.disabled = !ready;
        btnStylist.disabled = !ready;
    }

    // Filter the catalog (and the AI Stylist) by gender: all | mens | womens | kids.
    function applyGenderFilter(gender) {
        currentGender = gender;
        products = (gender === 'all')
            ? allProducts.slice()
            : allProducts.filter((p) => p.gender === gender);
        currentIndex = 0;
        renderCatalog();
        updateActionButtons();
    }

    // -------------------------------------------------------------------
    // Capture + try-on request
    // -------------------------------------------------------------------
    function captureFrame() {
        // An uploaded photo is already a resized data URL — use it directly.
        if (uploadedImage) return uploadedImage;

        const vw = liveVideo.videoWidth;
        const vh = liveVideo.videoHeight;
        if (!vw || !vh) return null;

        const scale = Math.min(1, MAX_CAPTURE_DIM / Math.max(vw, vh));
        const cw = Math.round(vw * scale);
        const ch = Math.round(vh * scale);

        const canvas = document.createElement('canvas');
        canvas.width = cw;
        canvas.height = ch;
        const ctx = canvas.getContext('2d');
        // Un-mirror: draw the frame as the camera actually sees it, so the AI
        // gets a natural (non-flipped) photo of the person.
        ctx.drawImage(liveVideo, 0, 0, cw, ch);
        return canvas.toDataURL('image/jpeg', JPEG_QUALITY);
    }

    // The AI takes a while; walk the user through what it is doing instead of
    // one static line. It stays on the last step if the AI takes longer.
    const TRYON_STEPS = [
        'Analyzing your photo…',
        'Finding your pose and fit…',
        'Fitting the garment on you…',
        'Matching the fabric and light…',
        'Adding the final details…',
    ];
    const STYLIST_STEPS = [
        'Looking at your style…',
        'Checking the catalog…',
        'Picking the looks that suit you best…',
    ];
    let stepTimer = null;
    function startSteps(list) {
        let i = 0;
        if (loadingText) loadingText.textContent = list[0];
        clearInterval(stepTimer);
        stepTimer = setInterval(() => {
            i = Math.min(i + 1, list.length - 1);
            if (loadingText) loadingText.textContent = list[i];
        }, 3000);
    }
    function stopSteps() {
        clearInterval(stepTimer);
        stepTimer = null;
    }

    // Try-on failed: say why, and offer another go with the same photo (no
    // new countdown) or the saved looks, so there is always something to show.
    function showFailure(message) {
        failText.textContent = message;
        failLooks.hidden = savedCount <= 0;
        showOverlay(failOverlay);
    }

    // Shows a big 5…4…3…2…1 countdown over the stage, resolving when it ends.
    function runCountdown(seconds) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'tryon-countdown';
            const num = document.createElement('span');
            overlay.appendChild(num);
            stage.appendChild(overlay);

            let remaining = seconds;
            const render = () => {
                num.textContent = remaining;
                // Restart the pop animation on each tick.
                num.style.animation = 'none';
                void num.offsetWidth;
                num.style.animation = '';
            };
            render();

            const timer = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(timer);
                    overlay.remove();
                    resolve();
                } else {
                    render();
                }
            }, 1000);
        });
    }

    // `retryFrame` (a data URL) re-sends a photo already taken, with no countdown.
    async function doTryOn(retryFrame) {
        if (busy || products.length === 0) return;

        busy = true;
        btnTryOn.disabled = true;
        hideOverlay(failOverlay);
        const reuse = typeof retryFrame === 'string' ? retryFrame : null;   // a click passes an Event

        // Live camera: give the user a few seconds to step back and pose.
        // Uploaded photo: no need to pose, capture immediately.
        if (!reuse && !uploadedImage) {
            await runCountdown(COUNTDOWN_SECONDS);
            if (sessionEnded) { busy = false; return; }
        }

        const frame = reuse || captureFrame();
        if (!frame) {
            showToast('Camera is not ready yet. Please wait a moment.', true);
            btnTryOn.disabled = false;
            busy = false;
            return;
        }
        lastBefore = frame;

        startSteps(TRYON_STEPS);
        showOverlay(loadingOverlay);

        try {
            const res = await fetch(endpoints.tryOn, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ personImage: frame, productId: products[currentIndex].id }),
            });
            const data = await res.json().catch(() => ({ success: false, error: 'Unexpected server response.' }));
            if (sessionEnded) return;   // END was pressed while the AI was working

            if (!data.success) {
                showFailure(data.error || 'Try-on failed. Please try again.');
                return;
            }

            lastAfter = data.resultImage;
            resultImg.src = data.resultImage;
            resultImg.style.display = 'block';
            liveVideo.style.display = 'none';
            uploadImg.style.display = 'none';
            setStageTag('TRY-ON', 'var(--tryon-accent)');
            setResultControls();
        } catch (err) {
            if (!sessionEnded) showFailure('Network error. Check your connection and try again.');
        } finally {
            stopSteps();
            hideOverlay(loadingOverlay);
            btnTryOn.disabled = false;
            busy = false;
        }
    }

    function backToLive() {
        closeCompare();
        resultImg.style.display = 'none';
        resultImg.removeAttribute('src');
        if (uploadedImage) {
            uploadImg.style.display = 'block';
            setStageTag('PHOTO', 'var(--tryon-live)');
        } else {
            liveVideo.style.display = 'block';
            setStageTag('LIVE', 'var(--tryon-live)');
        }
        setLiveControls();
    }

    // ---- Control visibility (live/photo mode vs result mode) ----
    function setLiveControls() {
        btnTryOn.style.display = 'inline-flex';
        btnStylist.style.display = 'inline-flex';
        btnUpload.style.display = 'inline-flex';
        btnCamera.style.display = uploadedImage ? 'none' : 'inline-flex';
        btnSwitchCam.style.display = uploadedImage ? 'none' : 'inline-flex';
        btnUseCamera.style.display = uploadedImage ? 'inline-flex' : 'none';
        btnLive.style.display = 'none';
        btnSave.style.display = 'none';
        btnDownload.style.display = 'none';
        btnCompare.style.display = 'none';
    }
    function setResultControls() {
        // A new result can be saved.
        btnSave.disabled = false;
        btnSave.innerHTML = '<i class="fas fa-bookmark" aria-hidden="true"></i> Save look';
        btnTryOn.style.display = 'none';
        btnStylist.style.display = 'none';
        btnUpload.style.display = 'none';
        btnCamera.style.display = 'none';
        btnSwitchCam.style.display = 'none';
        btnUseCamera.style.display = 'none';
        btnLive.style.display = 'inline-flex';
        btnSave.style.display = 'inline-flex';
        btnDownload.style.display = 'inline-flex';
        btnCompare.style.display = 'inline-flex';
    }

    // -------------------------------------------------------------------
    // Upload photo (instead of live camera)
    // -------------------------------------------------------------------
    function handleUpload(file) {
        if (!file) return;
        if (!file.type || file.type.indexOf('image/') !== 0) {
            showToast('Please choose an image file.', true);
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            const img = new Image();
            img.onload = () => {
                // Resize down to keep the payload small (same cap as live capture).
                const scale = Math.min(1, MAX_CAPTURE_DIM / Math.max(img.naturalWidth, img.naturalHeight));
                const cw = Math.round(img.naturalWidth * scale);
                const ch = Math.round(img.naturalHeight * scale);
                const canvas = document.createElement('canvas');
                canvas.width = cw;
                canvas.height = ch;
                canvas.getContext('2d').drawImage(img, 0, 0, cw, ch);
                uploadedImage = canvas.toDataURL('image/jpeg', JPEG_QUALITY);

                uploadImg.src = uploadedImage;
                uploadImg.style.display = 'block';
                liveVideo.style.display = 'none';
                resultImg.style.display = 'none';
                closeCompare();
                hideOverlay(errorOverlay); // allow use even if the camera was denied
                setStageTag('PHOTO', 'var(--tryon-live)');
                updateActionButtons();
                setLiveControls();
                showToast('Photo loaded. Pick a product, then Try On.');
            };
            img.onerror = () => showToast('Could not read that image.', true);
            img.src = reader.result;
        };
        reader.onerror = () => showToast('Could not read that file.', true);
        reader.readAsDataURL(file);
    }

    function useCamera() {
        uploadedImage = null;
        uploadInput.value = '';
        uploadImg.style.display = 'none';
        uploadImg.removeAttribute('src');
        liveVideo.style.display = 'block';
        setStageTag('LIVE', 'var(--tryon-live)');
        setLiveControls();
        // If the camera isn't running, show the "camera off" prompt again.
        if (!cameraOn) showCameraOff();
        updateActionButtons();
    }

    // -------------------------------------------------------------------
    // Before / after compare slider
    // -------------------------------------------------------------------
    function setComparePct(pct) {
        pct = Math.max(0, Math.min(100, pct));
        compareBefore.style.clipPath = 'inset(0 ' + (100 - pct) + '% 0 0)';
        compareHandle.style.left = pct + '%';
    }

    function openCompare() {
        if (!lastBefore || !lastAfter) return;
        compareBefore.src = lastBefore;
        compareAfter.src = lastAfter;
        setComparePct(50);
        compareEl.style.display = 'block';
    }

    function closeCompare() {
        compareEl.style.display = 'none';
    }

    function toggleCompare() {
        if (compareEl.style.display === 'block') closeCompare();
        else openCompare();
    }

    let comparing = false;
    function compareFromEvent(e) {
        const rect = compareEl.getBoundingClientRect();
        const clientX = (e.touches && e.touches[0]) ? e.touches[0].clientX : e.clientX;
        setComparePct(((clientX - rect.left) / rect.width) * 100);
    }

    // -------------------------------------------------------------------
    // AI Stylist (auto-suggest)
    // -------------------------------------------------------------------
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    async function runStylist() {
        if (busy || products.length === 0) return;
        const frame = captureFrame();
        if (!frame) {
            showToast('Camera is not ready yet. Please wait a moment.', true);
            return;
        }

        busy = true;
        btnStylist.disabled = true;
        btnTryOn.disabled = true;
        startSteps(STYLIST_STEPS);
        showOverlay(loadingOverlay);

        try {
            const res = await fetch(endpoints.suggest, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({
                    personImage: frame,
                    gender: currentGender === 'all' ? '' : currentGender,
                }),
            });
            const data = await res.json().catch(() => ({ success: false, error: 'Unexpected server response.' }));
            if (sessionEnded) return;
            if (!data.success) {
                showToast(data.error || 'Could not get suggestions.', true);
                return;
            }
            showSuggestions(data.suggestions || []);
        } catch (err) {
            showToast('Network error. Check your connection and try again.', true);
        } finally {
            stopSteps();
            hideOverlay(loadingOverlay);
            updateActionButtons();
            busy = false;
        }
    }

    function showSuggestions(list) {
        const medals = ['🥇', '🥈', '🥉'];
        suggestList.innerHTML = '';
        let shown = 0;
        list.forEach((s, i) => {
            const idx = products.findIndex((p) => p.id == s.id);
            if (idx === -1) return;
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'suggest-item';
            row.innerHTML =
                '<span class="suggest-rank">' + (medals[i] || (i + 1)) + '</span>' +
                '<span class="suggest-info">' +
                    '<span class="suggest-name">' + escapeHtml(s.name) + '</span>' +
                    '<span class="suggest-reason">' + escapeHtml(s.reason) + '</span>' +
                '</span>' +
                '<span class="suggest-go"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Try</span>';
            row.addEventListener('click', () => pickSuggestion(idx));
            suggestList.appendChild(row);
            shown += 1;
        });

        if (shown === 0) {
            showToast('No matching suggestions found. Try again.', true);
            return;
        }
        showOverlay(suggestOverlay);
    }

    // User picked a suggested item → jump the catalog to it and try it on.
    function pickSuggestion(idx) {
        hideOverlay(suggestOverlay);
        currentIndex = idx;
        renderCatalog();
        doTryOn();
    }

    // "Add to Cart" asks for colour and size first, like the product page;
    // an order without a size cannot be fulfilled. A choice with only one
    // option is made for the customer.
    let pickChoice = { color: '', size: '' };
    const listOf = (v) => (Array.isArray(v) ? v : []);

    function openPicker() {
        if (products.length === 0) return;
        const p = products[currentIndex];
        const colors = listOf(p.colors);
        const sizes = listOf(p.sizes);
        if (!colors.length && !sizes.length) {
            addToCart(p, '', '');
            return;
        }
        pickChoice = { color: colors.length === 1 ? colors[0] : '', size: sizes.length === 1 ? sizes[0] : '' };
        renderChips(pickColors, colors, 'color');
        renderChips(pickSizes, sizes, 'size');
        pickColorGroup.hidden = !colors.length;
        pickSizeGroup.hidden = !sizes.length;
        syncPickConfirm();
        resetSizeFinder();
        btnAddCart.style.display = 'none';
        pickPanel.hidden = false;
    }

    // -------------------------------------------------------------------
    // AI size finder: height + weight + fit → one of this product's sizes
    // -------------------------------------------------------------------
    const SIZE_PROFILE_KEY = 'tryonSizeProfile';
    let sizeFitChoice = 'regular';

    // Measurements are remembered on this device so the next product only
    // needs one tap. Storage can be blocked; the finder still works without.
    function loadSizeProfile() {
        try {
            const p = JSON.parse(localStorage.getItem(SIZE_PROFILE_KEY)) || {};
            if (p.height) sizeHeight.value = p.height;
            if (p.weight) sizeWeight.value = p.weight;
            if (p.fit) setSizeFit(p.fit);
        } catch (e) { /* no saved profile */ }
    }

    function setSizeFit(fit) {
        sizeFitChoice = fit;
        sizeFit.querySelectorAll('.pick-chip').forEach((c) => c.classList.toggle('active', c.dataset.fit === fit));
    }

    function resetSizeFinder() {
        sizeFind.hidden = true;
        sizeFindToggle.setAttribute('aria-expanded', 'false');
        sizeFindResult.hidden = true;
    }

    function showSizeResult(html, isError) {
        sizeFindResult.innerHTML = html;
        sizeFindResult.classList.toggle('is-error', !!isError);
        sizeFindResult.hidden = false;
    }

    async function findSize() {
        const p = products[currentIndex];
        if (!p) return;
        const height = parseFloat(sizeHeight.value);
        const weight = parseFloat(sizeWeight.value);
        if (!(height >= 80 && height <= 230)) {
            showSizeResult('Please enter your height in cm (80–230).', true);
            sizeHeight.focus();
            return;
        }
        if (!(weight >= 10 && weight <= 250)) {
            showSizeResult('Please enter your weight in kg (10–250).', true);
            sizeWeight.focus();
            return;
        }
        try {
            localStorage.setItem(SIZE_PROFILE_KEY, JSON.stringify({ height: height, weight: weight, fit: sizeFitChoice }));
        } catch (e) { /* not saved, fine */ }

        sizeFindGo.disabled = true;
        showSizeResult('<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Finding your size…');
        try {
            const res = await fetch(endpoints.size, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ productId: p.id, height: height, weight: weight, fit: sizeFitChoice }),
            });
            const data = await res.json().catch(() => ({ success: false, error: 'Unexpected server response.' }));
            // The customer may have closed the picker or changed product meanwhile.
            if (pickPanel.hidden || products[currentIndex] !== p) return;
            if (!data.success) {
                showSizeResult(escapeHtml(data.error || 'Could not get a size.'), true);
                return;
            }
            pickChoice.size = data.size;
            pickSizes.querySelectorAll('.pick-chip').forEach((c) => c.classList.toggle('active', c.textContent === data.size));
            syncPickConfirm();
            showSizeResult('We recommend <strong>' + escapeHtml(data.size) + '</strong>' +
                (data.reason ? ' — ' + escapeHtml(data.reason) : '') + ' It is selected for you.');
        } catch (err) {
            showSizeResult('Network error. Check your connection and try again.', true);
        } finally {
            sizeFindGo.disabled = false;
        }
    }

    function closePicker() {
        if (!pickPanel) return;
        pickPanel.hidden = true;
        btnAddCart.style.display = '';
    }

    function renderChips(box, values, key) {
        box.innerHTML = '';
        values.forEach((v) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'pick-chip' + (pickChoice[key] === v ? ' active' : '');
            b.textContent = v;
            b.addEventListener('click', () => {
                pickChoice[key] = v;
                box.querySelectorAll('.pick-chip').forEach((c) => c.classList.toggle('active', c === b));
                syncPickConfirm();
            });
            box.appendChild(b);
        });
    }

    function syncPickConfirm() {
        const p = products[currentIndex];
        pickConfirm.disabled = !p
            || (listOf(p.colors).length > 0 && !pickChoice.color)
            || (listOf(p.sizes).length > 0 && !pickChoice.size);
    }

    // Same localStorage 'cart' and item shape as shop.php / product.php / cart.php.
    function addToCart(p, color, size) {
        let cart = [];
        try { cart = JSON.parse(localStorage.getItem('cart')) || []; } catch (e) { cart = []; }

        const existing = cart.find((it) => it.id == p.id && it.color === color && it.size === size);
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({ id: p.id, name: p.name, price: Number(p.price), quantity: 1, color: color, size: size });
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        updateNavCartCount();
        const detail = [color, size].filter(Boolean).join(', ');
        showToast(p.name + (detail ? ' (' + detail + ')' : '') + ' added to cart.');
    }

    // The cart count in the header (the same badge product.php updates).
    function updateNavCartCount() {
        let cart = [];
        try { cart = JSON.parse(localStorage.getItem('cart')) || []; } catch (e) { cart = []; }
        const count = cart.reduce((n, i) => n + (parseInt(i.quantity, 10) || 0), 0);
        const badge = document.getElementById('navCartCount');
        if (badge) {
            badge.style.display = count > 0 ? 'inline-block' : 'none';
            badge.textContent = count;
        }
    }

    // Triggers a browser download for a given image data URL.
    function triggerDownload(dataUrl) {
        const a = document.createElement('a');
        a.href = dataUrl;
        a.download = 'tryon_' + Date.now() + '.png';
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    // Downloads the generated try-on image with a "Thread & Press Hub" watermark
    // baked into the bottom-right corner (free marketing when shared).
    function downloadResult() {
        if (!resultImg.src) return;
        downloadWithWatermark(resultImg.src);
    }

    // The same, for any image of this site (the result, or a saved look).
    function downloadWithWatermark(src) {
        const img = new Image();
        img.onload = () => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);

                const pad = Math.round(canvas.width * 0.025);
                const fontSize = Math.max(16, Math.round(canvas.width * 0.038));
                ctx.font = '700 ' + fontSize + 'px Arial, Helvetica, sans-serif';
                ctx.textBaseline = 'bottom';
                ctx.textAlign = 'right';
                const text = 'Thread & Press Hub';
                const x = canvas.width - pad;
                const y = canvas.height - pad;

                ctx.shadowColor = 'rgba(0, 0, 0, 0.65)';
                ctx.shadowBlur = Math.round(fontSize * 0.35);
                ctx.shadowOffsetX = 0;
                ctx.shadowOffsetY = 1;
                ctx.fillStyle = '#ffffff';
                ctx.fillText(text, x, y);

                triggerDownload(canvas.toDataURL('image/png'));
            } catch (e) {
                // Tainted canvas or other failure — fall back to the raw image.
                triggerDownload(src);
            }
        };
        img.onerror = () => triggerDownload(src);
        img.src = src;
    }

    // -------------------------------------------------------------------
    // Save look + drawer (cart)
    // -------------------------------------------------------------------
    async function saveLook() {
        if (!resultImg.src) return;
        btnSave.disabled = true;
        try {
            const res = await fetch(endpoints.save, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ image: resultImg.src }),
            });
            const data = await res.json();
            if (data.success) {
                savedCount += 1;
                updateBadge();
                showToast('Look saved to your collection.');
                // Saved: the button stays off until there is a new result,
                // so the same look is not saved twice.
                btnSave.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Saved';
                return;
            }
            showToast(data.error || 'Could not save look.', true);
        } catch (err) {
            showToast('Network error while saving.', true);
        }
        btnSave.disabled = false;   // failed: allow another try
    }

    async function openDrawer() {
        drawer.classList.add('open');
        drawerBackdrop.classList.add('show');
        drawerBody.innerHTML = '<p class="empty">Loading…</p>';
        try {
            const res = await fetch(endpoints.save, { method: 'GET' });
            const data = await res.json();
            const looks = (data && data.looks) || [];
            savedCount = looks.length;
            updateBadge();
            if (looks.length === 0) {
                drawerBody.innerHTML = '<p class="empty">No saved looks yet. Generate a try-on and tap “Save look”.</p>';
                return;
            }
            currentLooks = looks.slice();
            const grid = document.createElement('div');
            grid.className = 'tryon-drawer-grid';
            looks.forEach((l) => {
                const fig = document.createElement('div');
                fig.className = 'tryon-look';

                // Opens the look in the viewer on this page (not a new tab).
                const a = document.createElement('button');
                a.type = 'button';
                a.className = 'tryon-look-open';
                a.setAttribute('aria-label', 'View saved look' + (l.time ? ' from ' + l.time : ''));
                a.addEventListener('click', () => openLightbox(currentLooks.findIndex((x) => x.file === l.file)));
                const img = document.createElement('img');
                img.src = l.url;
                img.alt = 'Saved look from ' + (l.time || '');
                a.appendChild(img);

                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'tryon-look-del';
                del.title = 'Delete look';
                del.innerHTML = '<i class="fas fa-trash" aria-hidden="true"></i>';
                del.addEventListener('click', () => deleteLook(l.file, fig));

                fig.appendChild(a);
                fig.appendChild(del);
                grid.appendChild(fig);
            });
            drawerBody.innerHTML = '';
            drawerBody.appendChild(grid);
        } catch (err) {
            drawerBody.innerHTML = '<p class="empty">Could not load saved looks.</p>';
        }
    }

    // Deletes one saved look (server-side file + its tile in the drawer).
    async function deleteLook(file, node) {
        if (!file) return;
        if (!window.confirm('Delete this saved look?')) return;
        try {
            const res = await fetch(endpoints.save, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ action: 'delete', file: file }),
            });
            const data = await res.json();
            if (data.success) {
                node.remove();
                currentLooks = currentLooks.filter((x) => x.file !== file);
                savedCount = Math.max(0, savedCount - 1);
                updateBadge();
                showToast('Look deleted.');
                if (savedCount === 0) {
                    drawerBody.innerHTML = '<p class="empty">No saved looks yet. Generate a try-on and tap “Save look”.</p>';
                }
            } else {
                showToast(data.error || 'Could not delete look.', true);
            }
        } catch (err) {
            showToast('Network error while deleting.', true);
        }
    }

    // ---- Saved-look viewer ----
    let currentLooks = [];   // the looks shown in the drawer: [{url, file, time}]
    let lbIndex = -1;

    function openLightbox(i) {
        if (i < 0 || i >= currentLooks.length) return;
        lbIndex = i;
        showLook();
        lightbox.hidden = false;
        lbClose.focus();
    }

    function showLook() {
        const l = currentLooks[lbIndex];
        lbImg.src = l.url;
        lbImg.alt = 'Saved look' + (l.time ? ' from ' + l.time : '');
        lbCaption.textContent = (l.time ? l.time + ' · ' : '') + (lbIndex + 1) + ' of ' + currentLooks.length;
        lbPrev.hidden = lbNext.hidden = currentLooks.length < 2;
    }

    function stepLightbox(delta) {
        if (currentLooks.length < 2) return;
        lbIndex = (lbIndex + delta + currentLooks.length) % currentLooks.length;
        showLook();
    }

    function closeLightbox() {
        if (lightbox.hidden) return;
        lightbox.hidden = true;
        lbImg.removeAttribute('src');
        lbIndex = -1;
    }

    // How many looks are saved, for the badge (and the failure panel's button).
    function loadSavedCount() {
        fetch(endpoints.save, { method: 'GET' })
            .then((r) => r.json())
            .then((d) => { savedCount = ((d && d.looks) || []).length; updateBadge(); })
            .catch(() => {});
    }

    function closeDrawer() {
        closeLightbox();
        drawer.classList.remove('open');
        drawerBackdrop.classList.remove('show');
    }

    function updateBadge() {
        if (savedCount > 0) {
            cartBadge.textContent = String(savedCount);
            cartBadge.style.display = 'inline-flex';
        } else {
            cartBadge.style.display = 'none';
        }
    }

    // -------------------------------------------------------------------
    // Overlays / toast helpers
    // -------------------------------------------------------------------
    function showOverlay(node) { node.classList.add('show'); }
    function hideOverlay(node) { node.classList.remove('show'); }

    function showPermissionError(msg) {
        errorText.textContent = msg;
        showOverlay(errorOverlay);
        cameraOn = false;
        cameraReady = false;
        updateActionButtons();
        syncCameraBtn();
    }

    let toastTimer = null;
    function showToast(msg, isError) {
        toast.textContent = msg;
        toast.classList.toggle('error', !!isError);
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 4000);
    }

    // -------------------------------------------------------------------
    // END session
    // -------------------------------------------------------------------
    function endSession() {
        sessionEnded = true;
        stopSteps();
        hideOverlay(loadingOverlay);
        stopCamera();
        showPermissionError('Session ended. Reload the page to start again.');
        btnTryOn.style.display = 'none';
        btnLive.style.display = 'none';
        btnSave.style.display = 'none';
        btnDownload.style.display = 'none';
        btnStylist.style.display = 'none';
        btnUpload.style.display = 'none';
        btnCamera.style.display = 'none';
        btnSwitchCam.style.display = 'none';
        btnUseCamera.style.display = 'none';
        btnCompare.style.display = 'none';
        closeCompare();
        hideOverlay(suggestOverlay);
        hideOverlay(failOverlay);
        closePicker();
    }

    // -------------------------------------------------------------------
    // Wire up
    // -------------------------------------------------------------------
    function init() {
        // "Try On" on a shop card links here with ?product=ID: start on that item.
        const wanted = parseInt(new URLSearchParams(window.location.search).get('product'), 10);
        const wantedIndex = wanted > 0 ? products.findIndex((p) => p.id === wanted) : -1;
        if (wantedIndex >= 0) currentIndex = wantedIndex;
        renderCatalog();
        if (wantedIndex >= 0) {
            showToast('Ready to try on: ' + products[wantedIndex].name + '. Turn the camera on or upload a photo.');
        }
        updateBadge();
        loadSavedCount();
        // Camera starts OFF for a cleaner first impression; the user turns it on.
        showCameraOff();
        syncCameraBtn();
        updateActionButtons();

        btnTryOn.addEventListener('click', doTryOn);
        btnLive.addEventListener('click', backToLive);
        btnSave.addEventListener('click', saveLook);
        btnDownload.addEventListener('click', downloadResult);
        btnAddCart.addEventListener('click', openPicker);
        pickCancel.addEventListener('click', closePicker);
        sizeFindToggle.addEventListener('click', () => {
            sizeFind.hidden = !sizeFind.hidden;
            sizeFindToggle.setAttribute('aria-expanded', String(!sizeFind.hidden));
            if (!sizeFind.hidden && !sizeHeight.value) sizeHeight.focus();
        });
        sizeFit.addEventListener('click', (e) => {
            const chip = e.target.closest('[data-fit]');
            if (chip) setSizeFit(chip.dataset.fit);
        });
        sizeFindGo.addEventListener('click', findSize);
        [sizeHeight, sizeWeight].forEach((i) => i.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); findSize(); }
        }));
        loadSizeProfile();
        pickConfirm.addEventListener('click', () => {
            if (pickConfirm.disabled || pickPanel.hidden || products.length === 0) return;
            addToCart(products[currentIndex], pickChoice.color, pickChoice.size);
            closePicker();
        });

        // Failed try-on panel
        failRetry.addEventListener('click', () => doTryOn(lastBefore || undefined));
        failLooks.addEventListener('click', () => { hideOverlay(failOverlay); openDrawer(); });
        failClose.addEventListener('click', () => hideOverlay(failOverlay));
        btnStylist.addEventListener('click', runStylist);
        suggestClose.addEventListener('click', () => hideOverlay(suggestOverlay));

        // Upload photo / camera controls
        btnUpload.addEventListener('click', () => uploadInput.click());
        uploadInput.addEventListener('change', (e) => handleUpload(e.target.files && e.target.files[0]));
        btnUseCamera.addEventListener('click', useCamera);
        btnSwitchCam.addEventListener('click', switchCamera);
        btnCamera.addEventListener('click', toggleCamera);

        // Compare slider
        btnCompare.addEventListener('click', toggleCompare);
        compareEl.addEventListener('pointerdown', (e) => { comparing = true; compareFromEvent(e); });
        window.addEventListener('pointermove', (e) => { if (comparing) compareFromEvent(e); });
        window.addEventListener('pointerup', () => { comparing = false; });

        btnPrev.addEventListener('click', () => cycle(-1));
        btnNext.addEventListener('click', () => cycle(1));

        // Gender catalog filter (also scopes the AI Stylist).
        document.querySelectorAll('#tryonGender .tg-btn').forEach((b) => {
            b.addEventListener('click', () => {
                document.querySelectorAll('#tryonGender .tg-btn').forEach((x) => x.classList.remove('active'));
                b.classList.add('active');
                applyGenderFilter(b.dataset.gender);
            });
        });
        // Saved-look viewer
        lbClose.addEventListener('click', closeLightbox);
        lbPrev.addEventListener('click', () => stepLightbox(-1));
        lbNext.addEventListener('click', () => stepLightbox(1));
        lbDownload.addEventListener('click', () => { if (lbIndex >= 0) downloadWithWatermark(currentLooks[lbIndex].url); });
        lightbox.addEventListener('click', (e) => { if (e.target === lightbox) closeLightbox(); });   // backdrop

        btnEnd.addEventListener('click', endSession);
        btnCart.addEventListener('click', openDrawer);
        drawerClose.addEventListener('click', closeDrawer);
        drawerBackdrop.addEventListener('click', closeDrawer);

        // Keyboard: left/right arrows cycle the catalog - but not while typing
        // (the header search box uses the arrow keys too).
        document.addEventListener('keydown', (e) => {
            if (e.altKey || e.ctrlKey || e.metaKey) return;
            // While the saved-look viewer is open, the arrows move through the looks.
            if (!lightbox.hidden) {
                if (e.key === 'Escape') closeLightbox();
                else if (e.key === 'ArrowLeft') stepLightbox(-1);
                else if (e.key === 'ArrowRight') stepLightbox(1);
                return;
            }
            const t = e.target;
            if (t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable)) return;
            if (e.key === 'ArrowLeft') cycle(-1);
            if (e.key === 'ArrowRight') cycle(1);
        });

        window.addEventListener('beforeunload', stopCamera);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* =====================================================================
     * CHATBOT MOUNT POINT — intentionally left empty.
     * A floating support-chat widget can be mounted here later (separate
     * build). Do NOT implement it in this file.
     * ===================================================================== */
})();
