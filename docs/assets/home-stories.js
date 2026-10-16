(() => {
    'use strict';
    const key = Symbol.for('flex-fields.docs.home-stories');
    window[key]?.dispose();
    const lifecycle = new AbortController();
    const mounted = new Map();
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');

    function mount(root) {
        const events = new AbortController();
        const animations = new Set();
        const segmentTracks = [...root.querySelectorAll('.fff-media-segment-track')];
        function positionSegmentIndicators() {
            segmentTracks.forEach(track => {
                const selected = track.querySelector('button[aria-pressed="true"]');
                const indicator = track.querySelector('.fff-media-segment-indicator');
                if (!selected || !indicator || !track.offsetWidth) return;
                indicator.style.transform = `translate(${selected.offsetLeft}px, ${selected.offsetTop}px)`;
                indicator.style.width = `${selected.offsetWidth}px`;
                indicator.style.height = `${selected.offsetHeight}px`;
                track.dataset.ready = 'true';
            });
        }
        const segmentResize = segmentTracks.length ? new ResizeObserver(positionSegmentIndicators) : null;
        positionSegmentIndicators();
        segmentTracks.forEach(track => {
            segmentResize.observe(track);
            track.querySelectorAll('button').forEach(button => segmentResize.observe(button));
        });
        // Create the native top-layer preview here: Mintlify filters dialog tags
        // from MDX. This instance is removed with its owning illustration.
        const previewDialog = root.dataset.productStory === 'media' ? document.createElement('dialog') : null;
        if (previewDialog) {
            previewDialog.className = 'fff-doc-media-preview';
            previewDialog.dataset.docMediaDialog = '';
            previewDialog.setAttribute('aria-label', 'Image preview');
            const bar = document.createElement('div');
            bar.className = 'fff-doc-media-preview__bar';
            const filename = document.createElement('strong');
            filename.dataset.docMediaFilename = '';
            const close = document.createElement('button');
            close.type = 'button';
            close.dataset.docMediaPreviewClose = '';
            close.autofocus = true;
            close.setAttribute('aria-label', 'Close image preview');
            const icon = document.createElement('span');
            icon.className = 'fff-media-file-action-icon';
            icon.dataset.uploadIcon = 'close';
            icon.setAttribute('aria-hidden', 'true');
            close.append(icon);
            bar.append(filename, close);
            const stage = document.createElement('div');
            stage.className = 'fff-doc-media-preview__stage';
            const image = document.createElement('img');
            image.dataset.docMediaImage = '';
            image.alt = '';
            stage.append(image);
            previewDialog.append(bar, stage);
            root.append(previewDialog);
        }
        if (previewDialog) {
            previewDialog.addEventListener('click', event => {
                if (event.target === previewDialog || event.target === previewDialog.querySelector('.fff-doc-media-preview__stage')) previewDialog.close();
            }, { signal: events.signal });
            previewDialog.addEventListener('close', () => {
                if (previewDialog.open) return;
                previewDialog.getAnimations({ subtree: true }).forEach(animation => animation.cancel());
                previewDialog.querySelector('[data-doc-media-image]').removeAttribute('src');
            }, { signal: events.signal });
        }
        const pauseAll = document.getElementById('fff-landing-motion');
        let visible = false;
        let paused = false;
        let instances = 3;
        const instanceTemplate = root.querySelector('[data-instances]>span')?.cloneNode(true);
        const motionAllowed = () => !reduced.matches && !pauseAll?.checked;
        const animate = (element, frames, options = {}) => {
            if (!element || !motionAllowed()) return;
            const animation = element.animate(frames, { duration: 450, easing: 'cubic-bezier(.22,1,.36,1)', ...options });
            animations.add(animation);
            animation.onfinish = () => animations.delete(animation);
            animation.oncancel = () => animations.delete(animation);
        };
        const reveal = element => animate(element, [{ opacity: .2, translate: '0 8px' }, { opacity: 1, translate: '0 0' }]);
        function setExampleUploadPaused(paused) {
            root.querySelectorAll('[data-media-example-upload-toggle]').forEach(control => {
                control.setAttribute('aria-label', paused ? 'Resume example upload' : 'Pause example upload');
                control.querySelector('[data-upload-icon]').dataset.uploadIcon = paused ? 'play' : 'pause';
            });
            root.querySelector('[data-media-example-upload-status]').textContent = paused ? 'Paused' : 'Uploading';
            root.querySelector('[data-media-example-upload-percentage]').hidden = paused;
            const row = root.querySelector('.fff-media-file-row[data-media-example-file="brief"]');
            row.querySelector('.fff-media-file-progress').hidden = paused;
            root.querySelectorAll('[data-media-example-file="brief"] [role="progressbar"]').forEach(progress => {
                progress.setAttribute('aria-label', `${paused ? 'Paused upload' : 'Uploading'} brief.pdf`);
            });
        }
        function setExampleRetrying(retrying) {
            const row = root.querySelector('[data-media-example-retry-row]');
            row.classList.toggle('is-failed', !retrying);
            row.classList.toggle('is-uploading', retrying);
            row.querySelector('[data-media-example-retry-status]').textContent = retrying ? 'Uploading' : 'Upload failed';
            row.querySelector('[data-media-example-retry]').hidden = retrying;
            for (const selector of ['[data-media-example-retry-percentage]', '[data-media-example-retry-progress]', '[data-media-example-retry-cancel]']) row.querySelector(selector).hidden = !retrying;
        }
        function syncExampleReordering() {
            root.querySelectorAll('[data-preview-layout="grid"], [data-preview-layout="list"]').forEach(view => {
                const multiple = view.querySelectorAll('[data-media-example-file]:not([hidden])').length > 1;
                view.querySelectorAll('[data-media-example-move]').forEach(handle => { handle.hidden = !multiple; });
            });
        }
        function syncMotion() {
            root.dataset.motion = String(visible && !document.hidden && motionAllowed() && !paused);
            const button = root.querySelector('[data-motion-toggle]');
            if (button) {
                const label = paused ? 'Play motion' : 'Pause motion';
                button.setAttribute('aria-label', `${paused ? 'Play' : 'Pause'} Media OS animations`);
                button.setAttribute('aria-pressed', String(paused));
                root.querySelector('[data-motion-label]').textContent = label;
            }
        }
        function updatePicks() {
            const selected = [...root.querySelectorAll('[data-pick][aria-pressed="true"]')];
            root.querySelector('[data-pick-count]').textContent = selected.length;
            const output = root.querySelector('[data-pick-output]');
            const names = selected.length ? selected.map(button => button.dataset.pick) : ['Shared foundation only'];
            output.replaceChildren(...names.map(name => {
                const chip = document.createElement('span');
                chip.textContent = name;
                return chip;
            }));
            reveal(output);
        }
        function updateInstances() {
            const names = ['Owner', 'Team', 'Reviewers', 'Assignee'];
            const container = root.querySelector('[data-instances]');
            container.replaceChildren(...names.slice(0, instances).map(name => {
                const instance = instanceTemplate.cloneNode(true);
                instance.querySelector('small').textContent = name;
                return instance;
            }));
            root.querySelector('[data-instance-count]').textContent = `${instances} independent field${instances === 1 ? '' : 's'}`;
            root.querySelector('[data-remove-instance]').disabled = instances === 1;
            root.querySelector('[data-add-instance]').disabled = instances === 4;
            reveal(container);
        }
        const extensionInput = root.querySelector('[data-extension-name]');
        const extensionSearch = root.querySelector('[data-extension-search]');
        const filterExtensionOwners = () => {
            const query = extensionSearch?.value.trim().toLocaleLowerCase() || '';
            const choices = [...root.querySelectorAll('[data-extension-owner]')];
            choices.forEach(choice => { choice.hidden = !choice.dataset.extensionOwner.toLocaleLowerCase().includes(query); });
            const empty = root.querySelector('[data-extension-empty]');
            if (empty) empty.hidden = choices.some(choice => !choice.hidden);
        };
        const openExtensionSelect = () => {
            root.querySelector('[data-extension-options]').hidden = false;
            root.querySelector('[data-extension-select]').setAttribute('aria-expanded', 'true');
            if (extensionSearch) { extensionSearch.value = ''; filterExtensionOwners(); extensionSearch.focus(); }
        };
        const closeExtensionSelect = () => {
            const trigger = root.querySelector('[data-extension-select]');
            const options = root.querySelector('[data-extension-options]');
            if (trigger && options) { trigger.setAttribute('aria-expanded', 'false'); options.hidden = true; }
        };
        if (extensionInput) {
            root.addEventListener('input', event => {
                if (event.target === extensionSearch) { filterExtensionOwners(); return; }
                if (event.target !== extensionInput) return;
                root.querySelectorAll('[data-extension-output-name]').forEach(item => { item.textContent = extensionInput.value.trim() || 'Untitled project'; });
            }, { signal: events.signal });
            document.addEventListener('click', event => {
                if (!event.target.closest('.fff-extension-select') || !root.contains(event.target)) closeExtensionSelect();
            }, { signal: events.signal });
            root.addEventListener('keydown', event => {
                const options = root.querySelector('[data-extension-options]');
                const select = root.querySelector('[data-extension-select]');
                if (!event.target.closest('.fff-extension-select')) return;
                if (event.key === 'Escape') { closeExtensionSelect(); select.focus(); event.preventDefault(); }
                if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
                event.preventDefault();
                options.hidden = false;
                select.setAttribute('aria-expanded', 'true');
                const choices = [...options.querySelectorAll('[data-extension-owner]:not([hidden])')];
                if (!choices.length) return;
                const current = choices.indexOf(document.activeElement);
                const next = current < 0 ? (event.key === 'ArrowDown' ? 0 : choices.length - 1) : (current + (event.key === 'ArrowDown' ? 1 : -1) + choices.length) % choices.length;
                choices[next].focus();
            }, { signal: events.signal });
        }
        root.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button || !root.contains(button)) return;
            if (button.hasAttribute('data-doc-media-preview')) {
                const file = button.closest('[data-media-example-file]');
                const image = file?.querySelector('img') ?? root.querySelector('.fff-media-avatar-image');
                if (!previewDialog || !image) return;
                const name = button.dataset.docMediaPreview || `${file.dataset.mediaExampleFile}.jpg`;
                const previewImage = previewDialog.querySelector('[data-doc-media-image]');
                previewImage.src = image.currentSrc || image.src;
                previewImage.alt = name;
                previewDialog.querySelector('[data-doc-media-filename]').textContent = name;
                if (!previewDialog.open) previewDialog.showModal();
                animate(previewImage, [{ opacity: 0, scale: '.97' }, { opacity: 1, scale: '1' }], { duration: 220 });
            } else if (button.hasAttribute('data-doc-media-preview-close')) {
                previewDialog?.close();
            } else if (button.hasAttribute('data-media-example-select')) {
                root.querySelector('button[data-media-stage="present"]').click();
                root.querySelector('button[data-media-layout="grid"]').click();
            } else if (button.hasAttribute('data-pick')) {
                button.setAttribute('aria-pressed', String(button.getAttribute('aria-pressed') !== 'true'));
                animate(button, [{ scale: '.94' }, { scale: '1' }], { duration: 240 });
                updatePicks();
            } else if (button.hasAttribute('data-add-instance') || button.hasAttribute('data-remove-instance')) {
                instances = Math.min(4, Math.max(1, instances + (button.hasAttribute('data-add-instance') ? 1 : -1)));
                updateInstances();
            } else if (button.hasAttribute('data-replay-publish')) {
                root.querySelectorAll('.fff-package-sheets>span').forEach((sheet, index) => {
                    animate(sheet, [{ translate: '0 0' }, { translate: '0 -25px', offset: .4 }, { translate: '0 0' }], { duration: 750, delay: index * 90 });
                });
            } else if (button.hasAttribute('data-motion-toggle')) {
                paused = !paused;
                syncMotion();
            } else if (button.hasAttribute('data-extension-surface')) {
                const surface = button.dataset.extensionSurface;
                closeExtensionSelect();
                root.querySelectorAll('[data-extension-surface]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
                root.querySelectorAll('[data-extension-view]').forEach(item => { item.hidden = item.dataset.extensionView !== surface; });
                reveal(root.querySelector(`[data-extension-view="${surface}"]`));
            } else if (button.hasAttribute('data-extension-child')) {
                const child = button.dataset.extensionChild;
                root.dataset.extensionChild = child;
                root.querySelectorAll('button[data-extension-child]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
                root.querySelectorAll('[data-extension-code]').forEach(item => { item.hidden = item.dataset.extensionCode !== child; });
                reveal(root.querySelector(`[data-extension-code="${child}"]`));
            } else if (button.hasAttribute('data-extension-select')) {
                const options = root.querySelector('[data-extension-options]');
                if (options.hidden) openExtensionSelect(); else closeExtensionSelect();
            } else if (button.hasAttribute('data-extension-owner')) {
                const name = button.dataset.extensionOwner;
                root.querySelector('[data-extension-owner-label]').textContent = name;
                root.querySelectorAll('[data-extension-output-owner]').forEach(item => { item.textContent = name; });
                root.querySelectorAll('[data-extension-initials]').forEach(item => { item.textContent = button.dataset.extensionInitialsValue; });
                root.querySelectorAll('[data-extension-owner]').forEach(item => item.setAttribute('aria-selected', String(item === button)));
                closeExtensionSelect();
                root.querySelector('[data-extension-select]').focus();
            } else if (button.hasAttribute('data-instance-feature')) {
                const enabled = button.getAttribute('aria-pressed') !== 'true';
                button.setAttribute('aria-pressed', String(enabled));
                const enabledCount = root.querySelectorAll('[data-instance-feature][aria-pressed="true"]').length;
                root.querySelector('[data-instance-feature-summary]').textContent = enabledCount ? `+ ${enabledCount} optional feature${enabledCount === 1 ? '' : 's'} for A` : 'No optional features enabled';
                if (enabled) animate(button, [{ scale: '.94' }, { scale: '1' }], { duration: 180 });
            } else if (button.hasAttribute('data-media-example-retry')) {
                setExampleRetrying(true);
            } else if (button.hasAttribute('data-media-example-upload-toggle')) {
                setExampleUploadPaused(button.getAttribute('aria-label') === 'Pause example upload');
            } else if (button.hasAttribute('data-media-example-move')) {
                const key = button.dataset.mediaExampleMove;
                root.querySelectorAll('[data-preview-layout="grid"], [data-preview-layout="list"]').forEach(view => {
                    const file = view.querySelector(`[data-media-example-file="${key}"]`);
                    if (file) view.append(file);
                });
            } else if (button.hasAttribute('data-media-example-remove')) {
                const key = button.dataset.mediaExampleRemove;
                root.querySelectorAll('[data-media-example-file]').forEach(file => {
                    if (file.dataset.mediaExampleFile === key) file.hidden = true;
                });
                if (key === 'avatar') root.querySelector('.fff-media-avatar-shell').dataset.mediaAvatarFilled = 'false';
                syncExampleReordering();
                root.querySelector('.fff-media-example-reset').hidden = false;
            } else if (button.hasAttribute('data-media-example-reset')) {
                root.querySelectorAll('[data-media-example-file]').forEach(file => { file.hidden = false; });
                root.querySelector('.fff-media-avatar-shell').dataset.mediaAvatarFilled = 'true';
                setExampleUploadPaused(false);
                setExampleRetrying(false);
                syncExampleReordering();
                root.querySelector('.fff-media-example-reset').hidden = true;
            } else if (button.hasAttribute('data-media-stage')) {
                const stage = button.dataset.mediaStage;
                root.querySelector('.fff-media-editorial').dataset.mediaStage = stage;
                root.querySelectorAll('button[data-media-stage]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
                root.querySelectorAll('[data-media-scene]').forEach(scene => { scene.hidden = scene.dataset.mediaScene !== stage; });
                root.querySelector('[data-media-stage-caption]').textContent = stage[0].toUpperCase() + stage.slice(1);
                positionSegmentIndicators();
                reveal(root.querySelector(`[data-media-scene="${stage}"]`));
            } else if (button.hasAttribute('data-capture-source')) {
                const source = button.dataset.captureSource;
                root.querySelectorAll('[data-capture-source]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
                positionSegmentIndicators();
                root.querySelectorAll('[data-capture-panel]').forEach(panel => { panel.hidden = panel.dataset.capturePanel !== source; });
                reveal(root.querySelector(`[data-capture-panel="${source}"]`));
            } else if (button.hasAttribute('data-media-layout')) {
                const layout = button.dataset.mediaLayout;
                root.querySelectorAll('[data-media-layout]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
                positionSegmentIndicators();
                const preview = root.querySelector('[data-media-layout-preview]');
                preview.dataset.mediaLayoutPreview = layout;
                preview.querySelectorAll('[data-preview-layout]').forEach(view => { view.hidden = view.dataset.previewLayout !== layout; });
                reveal(preview.querySelector(`[data-preview-layout="${layout}"]`));
            } else if (button.hasAttribute('data-media-processing')) {
                root.querySelectorAll('[data-media-processing]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
                root.querySelector('[data-media-processing-detail]').textContent = button.dataset.mediaProcessing === 'inline' ? 'Generate configured conversions before the save workflow completes.' : 'Save the upload, then generate configured conversions in the background. Requires a running worker.';
            } else if (button.hasAttribute('data-media-backend')) {
                root.querySelectorAll('button[data-media-backend]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
                root.querySelector('.fff-media-editorial').dataset.mediaBackend = button.dataset.mediaBackend;
                const detail = root.querySelector('[data-media-storage-detail]');
                detail.textContent = button.dataset.mediaBackend === 'native' ? 'Store files on a local, S3 or other configured Laravel disk. Choose directories and visibility.' : 'Use your Spatie collections and model conversions. Enable the optional Media Library integration.';
                reveal(detail);
            }
        }, { signal: events.signal });
        const observer = new IntersectionObserver(entries => {
            visible = entries[0].isIntersecting;
            syncMotion();
        }, { threshold: .05 });
        observer.observe(root);
        document.addEventListener('visibilitychange', syncMotion, { signal: events.signal });
        const preferencesChanged = () => {
            if (!motionAllowed()) animations.forEach(animation => animation.cancel());
            syncMotion();
        };
        reduced.addEventListener('change', preferencesChanged, { signal: events.signal });
        pauseAll?.addEventListener('change', preferencesChanged, { signal: events.signal });
        syncMotion();
        return () => {
            previewDialog?.close();
            previewDialog?.remove();
            segmentResize?.disconnect();
            events.abort(); observer.disconnect();
            animations.forEach(animation => animation.cancel());
            delete root.dataset.motion;
        };
    }
    function refresh() {
        mounted.forEach((dispose, root) => { if (!root.isConnected) { dispose(); mounted.delete(root); } });
        document.querySelectorAll('[data-product-story]').forEach(root => { if (!mounted.has(root)) mounted.set(root, mount(root)); });
    }
    function clear() { mounted.forEach(dispose => dispose()); mounted.clear(); }
    // Mintlify may mount MDX after the custom scripts have already executed.
    // Observe only structural arrivals/removals of our own demo boundaries.
    let refreshQueued = false;
    const boundarySelector = '[data-product-story]';
    const containsBoundary = node => node.nodeType === Node.ELEMENT_NODE
        && (node.matches(boundarySelector) || node.querySelector(boundarySelector));
    const domObserver = new MutationObserver(records => {
        if (refreshQueued || !records.some(record => [...record.addedNodes, ...record.removedNodes].some(containsBoundary))) return;
        refreshQueued = true;
        queueMicrotask(() => { refreshQueued = false; refresh(); });
    });
    domObserver.observe(document.documentElement, { childList: true, subtree: true });
    window.addEventListener('mintlify:navigate', refresh, { signal: lifecycle.signal });
    window.addEventListener('pageshow', refresh, { signal: lifecycle.signal });
    window.addEventListener('pagehide', clear, { signal: lifecycle.signal });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refresh, { once: true, signal: lifecycle.signal });
    else refresh();
    window[key] = { dispose() { lifecycle.abort(); domObserver.disconnect(); clear(); } };
})();
