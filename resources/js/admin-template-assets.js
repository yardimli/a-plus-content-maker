const templateAssetsNode = document.getElementById('template-assets-data');

if (templateAssetsNode) {
    const boot = JSON.parse(templateAssetsNode.textContent || '{}');
    const state = { assets: boot.assets || [], field: null, selected: null, crop: null };
    const elements = {
        dialog: document.getElementById('template-asset-dialog'),
        file: document.getElementById('template-asset-file'),
        search: document.getElementById('template-asset-search'),
        requirement: document.getElementById('template-asset-requirement'),
        library: document.getElementById('template-asset-library'),
        detailsDialog: document.getElementById('template-asset-details-dialog'),
        detailsForm: document.getElementById('template-asset-details-form'),
        preview: document.getElementById('template-asset-detail-preview'),
        name: document.getElementById('template-asset-detail-name'),
        dimensions: document.getElementById('template-asset-detail-dimensions'),
        alt: document.getElementById('template-asset-alt'),
        detailsSave: document.getElementById('template-asset-details-save'),
        detailsUse: document.getElementById('template-asset-details-use'),
        cropDialog: document.getElementById('template-crop-dialog'),
        cropCanvas: document.getElementById('template-crop-canvas'),
        cropZoom: document.getElementById('template-crop-zoom'),
        cropTarget: document.getElementById('template-crop-target-label'),
        cropApply: document.getElementById('template-crop-apply'),
    };
    const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));

    const renderLibrary = (filter = '') => {
        const query = filter.trim().toLowerCase();
        const assets = state.assets.filter((asset) => `${asset.original_name || ''} ${asset.alt_text || ''}`.toLowerCase().includes(query));
        elements.library.innerHTML = assets.length ? '' : '<div class="asset-library-empty"><strong>No template images found</strong><span>Upload a JPG, PNG, or WebP image to get started.</span></div>';
        assets.forEach((asset) => {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'asset-card';
            button.innerHTML = `<span><img src="${escapeHtml(asset.url)}" alt="${escapeHtml(asset.alt_text || '')}"></span><strong>${escapeHtml(asset.original_name || `Image ${asset.id}`)}</strong><small>${asset.width} × ${asset.height}</small>`;
            button.addEventListener('click', () => showDetails(asset));
            elements.library.append(button);
        });
    };

    const openLibrary = (field) => {
        state.field = field; elements.search.value = '';
        elements.requirement.textContent = `Target: ${field.dataset.width} × ${field.dataset.height}px. Images are cropped to fit exactly.`;
        renderLibrary();
        if (!elements.dialog.open) elements.dialog.showModal();
    };

    const showDetails = (asset) => {
        state.selected = asset;
        if (elements.dialog.open) elements.dialog.close();
        elements.preview.innerHTML = `<img src="${escapeHtml(asset.preview_url || asset.url)}" alt="">`;
        elements.name.textContent = asset.original_name || `Image ${asset.id || ''}`;
        elements.dimensions.textContent = `${asset.width} × ${asset.height}px${asset.size_bytes ? ` · ${Math.max(1, Math.round(asset.size_bytes / 1024))} KB` : ''}`;
        elements.alt.value = asset.alt_text || '';
        elements.detailsSave.textContent = asset.pending ? 'Save to library' : 'Save details';
        if (!elements.detailsDialog.open) elements.detailsDialog.showModal();
    };

    const openFile = (file) => {
        if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 10 * 1024 * 1024) {
            window.showToast('Choose a JPG, PNG, or WebP image up to 10 MB.'); return;
        }
        const previewUrl = URL.createObjectURL(file); const image = new Image();
        image.onload = () => showDetails({ pending: true, file, preview_url: previewUrl, original_name: file.name, width: image.naturalWidth, height: image.naturalHeight, size_bytes: file.size, alt_text: '' });
        image.onerror = () => { URL.revokeObjectURL(previewUrl); window.showToast('That file could not be read as an image.'); };
        image.src = previewUrl;
    };

    const upload = async (blob, name, altText) => {
        const body = new FormData(); body.append('image', blob, name); body.append('alt_text', altText);
        const payload = await window.apiFetch(boot.routes.store, { method: 'POST', body });
        state.assets.unshift(payload.data); return payload.data;
    };

    const saveDetails = async () => {
        const asset = state.selected; const altText = elements.alt.value.trim();
        if (!altText) { elements.alt.reportValidity(); return null; }
        if (asset.pending) {
            const uploaded = await upload(asset.file, asset.original_name, altText); state.selected = uploaded; return uploaded;
        }
        if (altText !== asset.alt_text) {
            const payload = await window.apiFetch(`${boot.routes.assetBase}/${asset.id}`, { method: 'PATCH', body: JSON.stringify({ alt_text: altText }) });
            Object.assign(asset, payload.data);
        }
        return asset;
    };

    const updateField = (asset) => {
        const path = asset.template_path;
        if (!path) { window.showToast('This image is not available as a template asset.'); return; }
        const input = state.field.querySelector('[data-template-image-value]');
        input.value = path;
        state.field.querySelector('[data-template-choose-image]').innerHTML = `<img src="${escapeHtml(asset.url)}" alt="${escapeHtml(asset.alt_text || '')}"><span>Change image</span>`;
        state.field.querySelector('[data-template-clear-image]').hidden = false;
        [elements.cropDialog, elements.detailsDialog, elements.dialog].forEach((dialog) => { if (dialog.open) dialog.close(); });
        window.showToast('Template image fitted and selected');
    };

    const loadImage = (source) => new Promise((resolve, reject) => { const image = new Image(); image.onload = () => resolve(image); image.onerror = reject; image.src = source; });
    const cropMetrics = () => {
        const crop = state.crop; const canvas = elements.cropCanvas; const quarterTurn = Math.abs(crop.rotation % 180) === 90;
        const rotatedWidth = quarterTurn ? crop.image.naturalHeight : crop.image.naturalWidth;
        const rotatedHeight = quarterTurn ? crop.image.naturalWidth : crop.image.naturalHeight;
        const scale = Math.max(canvas.width / rotatedWidth, canvas.height / rotatedHeight) * crop.zoom;
        return { scale, renderedWidth: rotatedWidth * scale, renderedHeight: rotatedHeight * scale };
    };
    const drawCrop = () => {
        if (!state.crop) return;
        const metrics = cropMetrics(); const crop = state.crop; const canvas = elements.cropCanvas;
        crop.x = Math.max((canvas.width - metrics.renderedWidth) / 2, Math.min((metrics.renderedWidth - canvas.width) / 2, crop.x));
        crop.y = Math.max((canvas.height - metrics.renderedHeight) / 2, Math.min((metrics.renderedHeight - canvas.height) / 2, crop.y));
        const context = canvas.getContext('2d'); context.clearRect(0, 0, canvas.width, canvas.height); context.save();
        context.translate(canvas.width / 2 + crop.x, canvas.height / 2 + crop.y); context.rotate(crop.rotation * Math.PI / 180);
        context.drawImage(crop.image, -crop.image.naturalWidth * metrics.scale / 2, -crop.image.naturalHeight * metrics.scale / 2, crop.image.naturalWidth * metrics.scale, crop.image.naturalHeight * metrics.scale); context.restore();
    };
    const openCrop = async (asset) => {
        try {
            const image = await loadImage(asset.preview_url || asset.url);
            state.crop = { asset, image, rotation: 0, zoom: 1, x: 0, y: 0 };
            elements.cropCanvas.width = Number(state.field.dataset.width); elements.cropCanvas.height = Number(state.field.dataset.height);
            elements.cropCanvas.style.aspectRatio = `${elements.cropCanvas.width} / ${elements.cropCanvas.height}`;
            elements.cropTarget.textContent = `${elements.cropCanvas.width} × ${elements.cropCanvas.height}px`;
            elements.cropZoom.value = '1'; elements.detailsDialog.close(); elements.cropDialog.showModal(); drawCrop();
        } catch { window.showToast('The selected image could not be opened for cropping.'); }
    };

    document.addEventListener('click', (event) => {
        const choose = event.target.closest('[data-template-choose-image]');
        if (choose) openLibrary(choose.closest('[data-template-image-field]'));
        const clear = event.target.closest('[data-template-clear-image]');
        if (clear) {
            const field = clear.closest('[data-template-image-field]'); field.querySelector('[data-template-image-value]').value = '';
            field.querySelector('[data-template-choose-image]').innerHTML = '<span><strong>Choose image</strong><small>Upload or select from the template library</small></span>'; clear.hidden = true;
        }
    });
    document.querySelectorAll('[data-template-close-dialog]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.templateCloseDialog)?.close()));
    elements.file.addEventListener('change', () => { const file = elements.file.files?.[0]; elements.file.value = ''; if (file) openFile(file); });
    elements.search.addEventListener('input', () => renderLibrary(elements.search.value));
    document.getElementById('template-asset-details-back').addEventListener('click', () => { elements.detailsDialog.close(); openLibrary(state.field); });
    elements.detailsForm.addEventListener('submit', async (event) => { event.preventDefault(); try { const asset = await saveDetails(); if (asset) { showDetails(asset); renderLibrary(); window.showToast('Image details saved'); } } catch (error) { window.showToast(error.message); } });
    elements.detailsUse.addEventListener('click', async () => {
        if (!elements.detailsForm.reportValidity()) return;
        try {
            let asset = state.selected; asset.alt_text = elements.alt.value.trim();
            if (Number(asset.width) === Number(state.field.dataset.width) && Number(asset.height) === Number(state.field.dataset.height)) { asset = await saveDetails(); if (asset) updateField(asset); }
            else await openCrop(asset);
        } catch (error) { window.showToast(error.message); }
    });
    document.getElementById('template-crop-back').addEventListener('click', () => { elements.cropDialog.close(); showDetails(state.selected); });
    elements.cropZoom.addEventListener('input', () => { state.crop.zoom = Number(elements.cropZoom.value); drawCrop(); });
    document.getElementById('template-crop-rotate-left').addEventListener('click', () => { state.crop.rotation = (state.crop.rotation - 90) % 360; state.crop.x = 0; state.crop.y = 0; drawCrop(); });
    document.getElementById('template-crop-rotate-right').addEventListener('click', () => { state.crop.rotation = (state.crop.rotation + 90) % 360; state.crop.x = 0; state.crop.y = 0; drawCrop(); });
    let pointer = null;
    elements.cropCanvas.addEventListener('pointerdown', (event) => { pointer = { id: event.pointerId, x: event.clientX, y: event.clientY }; elements.cropCanvas.setPointerCapture(event.pointerId); });
    elements.cropCanvas.addEventListener('pointermove', (event) => { if (!pointer || pointer.id !== event.pointerId) return; const rect = elements.cropCanvas.getBoundingClientRect(); state.crop.x += (event.clientX - pointer.x) * elements.cropCanvas.width / rect.width; state.crop.y += (event.clientY - pointer.y) * elements.cropCanvas.height / rect.height; pointer.x = event.clientX; pointer.y = event.clientY; drawCrop(); });
    elements.cropCanvas.addEventListener('pointerup', () => { pointer = null; });
    elements.cropApply.addEventListener('click', () => {
        elements.cropApply.disabled = true; elements.cropApply.textContent = 'Cropping…';
        elements.cropCanvas.toBlob(async (blob) => {
            try {
                if (!blob) throw new Error('The cropped image could not be created.');
                const original = state.crop.asset.original_name || 'template-image';
                const name = `${original.replace(/\.[^.]+$/, '')}-${elements.cropCanvas.width}x${elements.cropCanvas.height}.png`;
                const asset = await upload(blob, name, elements.alt.value.trim()); updateField(asset);
            } catch (error) { window.showToast(error.message); }
            finally { elements.cropApply.disabled = false; elements.cropApply.textContent = 'Crop and use'; }
        }, 'image/png');
    });
}
