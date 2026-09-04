/**
 * Virtual Try-On (LiveLook) frontend.
 *
 * Captures the webcam, lets the user cycle the product catalog, sends the
 * current frame + selected product to the PHP proxy (includes/tryon-ajax.php),
 * and shows the AI-generated "wearing it" result. All AI work is server-side;
 * this file only handles capture, UI state and graceful failure paths.
 *
 * Config is injected by try-on.php via window.TRYON_CONFIG:
 *   { products: [{id,name,price,image}], csrfToken, endpoints: {tryOn, save} }
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

    async function doTryOn() {
        if (busy || products.length === 0) return;

        busy = true;
        btnTryOn.disabled = true;

        // Live camera: give the user a few seconds to step back and pose.
        // Uploaded photo: no need to pose, capture immediately.
        if (!uploadedImage) {
            await runCountdown(COUNTDOWN_SECONDS);
        }

        const frame = captureFrame();
        if (!frame) {
            showToast('Camera is not ready yet. Please wait a moment.', true);
            btnTryOn.disabled = false;
            busy = false;
            return;
        }
        lastBefore = frame;

        if (loadingText) loadingText.textContent = 'Generating your try-on… this usually takes a few seconds.';
        showOverlay(loadingOverlay);

        try {
            const res = await fetch(endpoints.tryOn, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ personImage: frame, productId: products[currentIndex].id }),
            });
            const data = await res.json().catch(() => ({ success: false, error: 'Unexpected server response.' }));

            if (!data.success) {
                showToast(data.error || 'Try-on failed. Please try again.', true);
                return;
            }

            lastAfter = data.resultImage;
            resultImg.src = data.resultImage;
            resultImg.style.display = 'block';
            liveVideo.style.display = 'none';
            uploadImg.style.display = 'none';
            liveTag.textContent = 'TRY-ON';
            liveTag.querySelector('.live-dot')?.style.setProperty('background', 'var(--tryon-accent)');
            setResultControls();
        } catch (err) {
            showToast('Network error. Check your connection and try again.', true);
        } finally {
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
            liveTag.textContent = 'PHOTO';
        } else {
            liveVideo.style.display = 'block';
            liveTag.textContent = 'LIVE';
        }
        liveTag.querySelector('.live-dot')?.style.setProperty('background', 'var(--tryon-live)');
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
                liveTag.textContent = 'PHOTO';
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
        liveTag.textContent = 'LIVE';
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
        if (loadingText) loadingText.textContent = 'Finding the looks that suit you best…';
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
            if (!data.success) {
                showToast(data.error || 'Could not get suggestions.', true);
                return;
            }
            showSuggestions(data.suggestions || []);
        } catch (err) {
            showToast('Network error. Check your connection and try again.', true);
        } finally {
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

    // Adds the currently-shown catalog product to the shopping cart
    // (same localStorage format used by shop.php / cart.php).
    function addCurrentToCart() {
        if (products.length === 0) return;
        const p = products[currentIndex];

        let cart = [];
        try { cart = JSON.parse(localStorage.getItem('cart')) || []; } catch (e) { cart = []; }

        const existing = cart.find((it) => it.id == p.id && it.color === '' && it.size === 'N/A');
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({ id: p.id, name: p.name, price: Number(p.price), quantity: 1, color: '', size: 'N/A' });
        }
        localStorage.setItem('cart', JSON.stringify(cart));
        showToast(p.name + ' added to cart.');
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
                triggerDownload(resultImg.src);
            }
        };
        img.onerror = () => triggerDownload(resultImg.src);
        img.src = resultImg.src;
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
            } else {
                showToast(data.error || 'Could not save look.', true);
            }
        } catch (err) {
            showToast('Network error while saving.', true);
        } finally {
            btnSave.disabled = false;
        }
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
            const grid = document.createElement('div');
            grid.className = 'tryon-drawer-grid';
            looks.forEach((l) => {
                const fig = document.createElement('div');
                fig.className = 'tryon-look';

                const a = document.createElement('a');
                a.href = l.url;
                a.target = '_blank';
                a.rel = 'noopener';
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

    function closeDrawer() {
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
    }

    // -------------------------------------------------------------------
    // Wire up
    // -------------------------------------------------------------------
    function init() {
        renderCatalog();
        updateBadge();
        // Camera starts OFF for a cleaner first impression; the user turns it on.
        showCameraOff();
        syncCameraBtn();
        updateActionButtons();

        btnTryOn.addEventListener('click', doTryOn);
        btnLive.addEventListener('click', backToLive);
        btnSave.addEventListener('click', saveLook);
        btnDownload.addEventListener('click', downloadResult);
        btnAddCart.addEventListener('click', addCurrentToCart);
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
        btnEnd.addEventListener('click', endSession);
        btnCart.addEventListener('click', openDrawer);
        drawerClose.addEventListener('click', closeDrawer);
        drawerBackdrop.addEventListener('click', closeDrawer);

        // Keyboard: left/right arrows cycle the catalog.
        document.addEventListener('keydown', (e) => {
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
