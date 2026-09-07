const dataNode = document.getElementById('builder-data');

if (dataNode) {
    const boot = JSON.parse(dataNode.textContent);
    const state = { ...boot, modules: [...boot.modules].sort((a, b) => a.position - b.position), activeAsset: null, selectedAsset: null, crop: null, aiModule: null, stockSearchResults: [], stockSearchPage: 1 };
    const elements = {
        list: document.getElementById('module-list'), outline: document.getElementById('module-outline'), empty: document.getElementById('builder-empty'),
        gallery: document.getElementById('module-gallery'), moduleDialog: document.getElementById('module-dialog'), search: document.getElementById('module-search'), sampleContent: document.getElementById('populate-module-samples'),
        assetDialog: document.getElementById('asset-dialog'), assetFile: document.getElementById('asset-file'), assetLibrary: document.getElementById('asset-library'), assetSearch: document.getElementById('asset-search'),
        assetAlt: document.getElementById('asset-alt'), assetRequirement: document.getElementById('asset-requirement'), assetDetailsDialog: document.getElementById('asset-details-dialog'), assetDetailsForm: document.getElementById('asset-details-form'), assetDetailPreview: document.getElementById('asset-detail-preview'), assetDetailName: document.getElementById('asset-detail-name'), assetDetailDimensions: document.getElementById('asset-detail-dimensions'), assetDetailsUse: document.getElementById('asset-details-use'), assetDetailsSave: document.getElementById('asset-details-save'),
        assetAiDialog: document.getElementById('asset-ai-dialog'), assetAiForm: document.getElementById('asset-ai-form'), assetAiPrompt: document.getElementById('asset-ai-prompt'), assetAiAlt: document.getElementById('asset-ai-alt'), assetAiGenerate: document.getElementById('asset-ai-generate'),
        stockOpen: document.getElementById('asset-stock-search-open'), stockDialog: document.getElementById('asset-stock-search-dialog'), stockForm: document.getElementById('asset-stock-search-form'), stockQuery: document.getElementById('asset-stock-search-query'), stockProvider: document.getElementById('asset-stock-search-provider'), stockPageSize: document.getElementById('asset-stock-search-page-size'), stockSubmit: document.getElementById('asset-stock-search-submit'), stockSummary: document.getElementById('asset-stock-search-summary'), stockResults: document.getElementById('asset-stock-search-results'), stockPrevious: document.getElementById('asset-stock-search-previous'), stockNext: document.getElementById('asset-stock-search-next'), stockPage: document.getElementById('asset-stock-search-page'),
        cropDialog: document.getElementById('crop-dialog'), cropCanvas: document.getElementById('crop-canvas'), cropZoom: document.getElementById('crop-zoom'), cropTargetLabel: document.getElementById('crop-target-label'), cropApply: document.getElementById('crop-apply'), save: document.getElementById('save-state'),
        aiDialog: document.getElementById('ai-dialog'), aiForm: document.getElementById('ai-form'), aiPrompt: document.getElementById('ai-prompt'), aiResult: document.getElementById('ai-result'), preview: document.getElementById('preview-content'),
        moduleLimitMessage: document.getElementById('module-limit-message'), addModuleButtons: document.querySelectorAll('[data-open-module-dialog]'),
    };
    const timers = new Map();

    const esc = window.escapeHtml || ((value) => String(value));
    const assetById = (id) => state.assets.find((asset) => String(asset.id) === String(id) || String(asset.template_path || '') === String(id));
    const assetReference = (asset) => state.mode.startsWith('template') ? asset.template_path : asset.id;
    const fieldValue = (value) => value ?? '';

    function renderGallery(filter = '') {
        elements.gallery.innerHTML = Object.entries(state.registry).filter(([, definition]) => `${definition.name} ${definition.description} ${definition.category}`.toLowerCase().includes(filter.toLowerCase())).map(([key, definition]) => `
            <button type="button" class="gallery-module" data-add-module="${key}">
                <span class="gallery-module-title"><strong>${esc(definition.name)}</strong>${definition.ai_ready ? '<em class="ai-badge">AI ready</em>' : ''}</span>
                <span class="gallery-module-preview">
                    <img class="gallery-module-rendered" src="/images/modules/rendered/${key}.webp" alt="Filled ${esc(definition.name)} preview" loading="lazy">
                    <img class="gallery-module-form" src="/images/modules/${key}.png" alt="${esc(definition.name)} editing form" loading="lazy">
                    <span class="gallery-preview-hint">Hover: editing fields</span>
                </span>
                <span class="gallery-module-copy"><small>${esc(definition.description)}</small><em>${esc(definition.category)}</em></span>
            </button>`).join('');
    }

    function moduleGlyph(type) {
        if (type.includes('text')) return '¶'; if (type.includes('comparison') || type.includes('specification')) return '▤'; if (type.includes('logo')) return 'A+'; return '▧';
    }

    function filledModulePreview(type) {
        const romance = '/images/templates/romance-letters-low-tide.png';
        const fantasy = '/images/templates/fantasy-crown-of-briars.png';
        const scifi = '/images/templates/scifi-orbit-of-ash.png';
        const image = (src = fantasy, className = '') => `<img class="mini-art ${className}" src="${src}" alt="">`;
        const copy = (title = 'Enter the story', text = 'A cinematic journey filled with unforgettable characters and a world readers will want to explore.') => `<span class="mini-copy"><b>${title}</b><small>${text}</small><i></i><i></i></span>`;
        const three = [romance, fantasy, scifi].map((src, index) => `<span class="mini-feature">${image(src)}<b>${['Heart', 'World', 'Adventure'][index]}</b><small>Discover more inside.</small></span>`).join('');

        switch (type) {
            case 'company_logo': return `<span class="mini-logo"><b>A+ AUTHOR STUDIO</b><small>STORIES MADE VISIBLE</small></span>`;
            case 'comparison_chart': return `<span class="mini-comparison"><span class="mini-books">${[romance, scifi, fantasy].map((src, index) => `<span>${image(src, 'cover')}<b>Book ${index + 1}</b><small>★★★★★</small></span>`).join('')}</span><span class="mini-metrics"><i>Reading order</i><i>Series world</i><i>Available formats</i></span></span>`;
            case 'four_image_text': return `<span class="mini-four"><b>Meet the world</b><span>${three}<span class="mini-feature">${image(romance)}<b>Promise</b><small>A story to remember.</small></span></span></span>`;
            case 'four_image_quadrant': return `<span class="mini-quadrant">${[romance, fantasy, scifi, romance].map((src, index) => `<span>${image(src)}${copy(['Characters', 'Setting', 'Stakes', 'Themes'][index], 'A focused glimpse into the book.')}</span>`).join('')}</span>`;
            case 'dark_text_overlay': return `<span class="mini-overlay dark">${image(fantasy)}<span><b>A kingdom on the brink</b><small>Magic has a price. Destiny has other plans.</small></span></span>`;
            case 'light_text_overlay': return `<span class="mini-overlay light">${image(romance)}<span><b>Some shores call us home</b><small>A sweeping story of hope, memory, and second chances.</small></span></span>`;
            case 'image_header_text': return `<span class="mini-header">${image(romance)}${copy('Beyond the horizon', 'A vivid introduction followed by the emotional promise of the story.')}</span>`;
            case 'multiple_image_a': return `<span class="mini-multiple">${image(fantasy, 'main')}<span>${copy('Choose your path', 'One world. Four journeys.')}<span class="mini-thumbs">${image(romance)}${image(scifi)}${image(fantasy)}</span></span></span>`;
            case 'product_description_text': return `<span class="mini-long-copy"><b>An unforgettable reading experience</b><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>`;
            case 'single_image_highlights': return `<span class="mini-highlights">${image(fantasy)}<span>${copy('It pays to dream bigger')} ${copy('A world of wonder', 'Rich atmosphere and immersive detail.')}${copy('A story with heart', 'Characters worth following.')}</span><aside><b>Highlights</b><small>• Epic adventure</small><small>• Found family</small><small>• High stakes</small></aside></span>`;
            case 'single_image_sidebar': return `<span class="mini-sidebar">${image(romance)}<span>${copy('A story worth discovering')}<i></i><i></i><i></i></span><aside>${image(fantasy)}<b>Inside the world</b><small>Places, people, and promises.</small></aside></span>`;
            case 'single_image_specs_detail': return `<span class="mini-spec-detail">${image(scifi)}<span>${copy('How the mission begins')} ${copy('What is at stake')}</span><aside><b>Story details</b><small>Setting — Deep space</small><small>Tone — Cinematic</small><small>Theme — Survival</small></aside></span>`;
            case 'single_left_image': return `<span class="mini-single left">${image(romance)}${copy('A love that rewrites everything')}</span>`;
            case 'single_right_image': return `<span class="mini-single right">${copy('The journey starts here')}${image(scifi)}</span>`;
            case 'technical_specifications': return `<span class="mini-table"><b>Book details</b>${['Genre|Epic fantasy', 'Reading order|Book one', 'Setting|A divided kingdom', 'Themes|Courage and loyalty', 'Tone|Sweeping and emotional'].map(row => `<span><i>${row.split('|')[0]}</i><small>${row.split('|')[1]}</small></span>`).join('')}</span>`;
            case 'standard_text': return `<span class="mini-standard-text"><b>A story readers will carry with them</b><i></i><i></i><i></i><i></i><i></i><small>Perfect for readers who believe the greatest adventures begin with one impossible choice.</small></span>`;
            case 'three_images_text': return `<span class="mini-three"><b>Three reasons to begin</b><span>${three}</span></span>`;
            default: return `<span class="mini-fallback">${moduleGlyph(type)}</span>`;
        }
    }

    function render() {
        const moduleLimitReached = state.modules.length >= boot.moduleLimit;
        const modulesLocked = !state.routes.moduleStore;
        elements.addModuleButtons.forEach((button) => {
            button.disabled = moduleLimitReached || modulesLocked;
            button.setAttribute('aria-disabled', String(moduleLimitReached || modulesLocked));
        });
        elements.moduleLimitMessage.hidden = !moduleLimitReached;
        elements.empty.hidden = state.modules.length > 0;
        elements.list.innerHTML = '';
        elements.outline.innerHTML = '';
        state.modules.forEach((module, index) => {
            const definition = state.registry[module.module_type];
            const card = document.createElement('article'); card.className = 'module-editor'; card.dataset.uuid = module.uuid;
            card.innerHTML = `<header class="module-editor-head"><span>${String(index + 1).padStart(2, '0')}</span><h3>${esc(definition.name)}</h3>${definition.ai_ready ? '<span class="ai-badge">AI ready</span>' : ''}<div class="module-editor-actions">${definition.ai_ready && state.routes.aiText ? '<button type="button" data-ai title="Draft copy with AI">✦ Generate</button>' : ''}<button type="button" data-move="up" title="Move up">↑</button><button type="button" data-move="down" title="Move down">↓</button><button type="button" data-delete title="Remove">×</button></div></header><div class="module-fields"></div>`;
            const fields = card.querySelector('.module-fields');
            (definition.fields || []).forEach((field) => fields.append(renderField(field, module.content[field.key], (value) => { module.content[field.key] = value; queueSave(module); })));
            (definition.repeaters || []).forEach((repeater) => fields.append(renderRepeater(module, repeater)));
            elements.list.append(card);
            const outline = document.createElement('li'); outline.innerHTML = `<span>${String(index + 1).padStart(2, '0')}</span>${esc(definition.name)}`; outline.addEventListener('click', () => card.scrollIntoView({ behavior: 'smooth', block: 'start' })); elements.outline.append(outline);
        });
        bindCardActions(); renderPreview();
    }

    function renderField(field, value, onChange, context = {}) {
        const wrapper = document.createElement('div'); wrapper.className = `module-field ${['richtext', 'image'].includes(field.type) ? 'full' : ''}`;
        const required = field.required ? ' *' : '';
        if (field.type === 'checkbox') {
            wrapper.innerHTML = `<label class="check-row"><input type="checkbox" ${value ? 'checked' : ''}> ${esc(field.label)}</label>`;
            wrapper.querySelector('input').addEventListener('change', (event) => onChange(event.target.checked)); return wrapper;
        }
        wrapper.innerHTML = `<label>${esc(field.label)}${required}</label>`;
        if (field.type === 'richtext') {
            const editor = document.createElement('div'); editor.className = 'rich-editor'; editor.innerHTML = `<div class="rich-toolbar"><button type="button" data-command="bold">B</button><button type="button" data-command="italic"><i>I</i></button><button type="button" data-command="underline"><u>U</u></button><button type="button" data-command="insertUnorderedList">•≡</button><button type="button" data-command="insertOrderedList">1≡</button></div><div class="rich-content" contenteditable="true" data-placeholder="Enter ${esc(field.label.toLowerCase())}">${value || ''}</div>`;
            editor.querySelectorAll('[data-command]').forEach((button) => button.addEventListener('click', () => { document.execCommand(button.dataset.command); editor.querySelector('.rich-content').focus(); }));
            editor.querySelector('.rich-content').addEventListener('input', (event) => onChange(event.currentTarget.innerHTML)); wrapper.append(editor);
        } else if (field.type === 'image') {
            const asset = assetById(value); const button = document.createElement('button'); button.type = 'button'; button.className = 'image-field';
            const isBanner = field.width >= 600 && field.width / field.height >= 2;
            wrapper.classList.add('image-module-field');
            if (isBanner) wrapper.classList.add('image-module-field-banner');
            button.style.setProperty('--image-target-width', `${field.width}px`);
            button.style.setProperty('--image-target-height', `${field.height}px`);
            button.style.setProperty('--image-aspect-ratio', `${field.width} / ${field.height}`);
            button.innerHTML = asset ? `<img src="${esc(asset.url)}" alt="${esc(asset.alt_text || '')}">` : `<span><span class="image-symbol">▧</span><strong>${field.width} × ${field.height}</strong><small>Click to add image</small></span>`;
            button.addEventListener('click', () => openAsset(field, (id) => onChange(id)));
            button.addEventListener('dragover', (event) => { event.preventDefault(); button.classList.add('dragging-over'); });
            button.addEventListener('dragleave', () => button.classList.remove('dragging-over'));
            button.addEventListener('drop', (event) => { event.preventDefault(); button.classList.remove('dragging-over'); const file = event.dataTransfer.files?.[0]; if (!file) return; state.activeAsset = { field, callback: (id) => onChange(id) }; openNewFile(file); });
            wrapper.append(button);
        } else if (field.type === 'asin') {
            const control = document.createElement('div'); control.className = 'asin-control';
            const input = document.createElement('input'); input.type = 'text'; input.maxLength = 10; input.value = fieldValue(value); input.placeholder = 'Enter 10-character ASIN';
            const button = document.createElement('button'); button.type = 'button'; button.className = 'button button-secondary'; button.textContent = 'Replace cover'; button.hidden = !state.routes.asinImport;
            const hint = document.createElement('small'); hint.textContent = 'Looks up this book and replaces the title and cover in this slot.';
            input.addEventListener('input', (event) => onChange(event.target.value.toUpperCase()));
            button.addEventListener('click', async () => {
                const asin = input.value.trim().toUpperCase();
                if (!/^[A-Z0-9]{10}$/.test(asin)) { window.showToast('Enter a valid 10-character ASIN.'); return; }
                control.classList.add('loading'); button.textContent = 'Looking up…';
                try {
                    const payload = await window.apiFetch(state.routes.asinImport, { method: 'POST', body: JSON.stringify({ asin }) });
                    state.assets.push(payload.data.asset); context.row.asin = payload.data.product.asin; context.row.title = payload.data.product.title; context.row.image = payload.data.asset.id;
                    queueSave(context.module); render(); window.showToast(`${payload.data.product.title} cover added`);
                } catch (error) { window.showToast(error.message); }
                finally { control.classList.remove('loading'); button.textContent = 'Replace cover'; }
            });
            control.append(input, button, hint); wrapper.append(control);
        } else if (field.type === 'select') {
            const select = document.createElement('select'); Object.entries(field.options || {}).forEach(([key, label]) => { const option = new Option(label, key, false, String(value ?? field.default) === String(key)); select.add(option); }); select.addEventListener('change', (event) => onChange(event.target.value)); wrapper.append(select);
        } else {
            const input = document.createElement('input'); input.type = 'text'; input.value = fieldValue(value); input.placeholder = `Enter ${field.label.toLowerCase()}`; input.addEventListener('input', (event) => onChange(event.target.value)); wrapper.append(input);
        }
        return wrapper;
    }

    function renderRepeater(module, repeater) {
        const wrapper = document.createElement('section'); wrapper.className = 'module-repeater';
        const rows = Array.isArray(module.content[repeater.key]) ? module.content[repeater.key] : (module.content[repeater.key] = []);
        wrapper.innerHTML = `<div class="repeater-head"><strong>${esc(repeater.label)} <small>(${repeater.min || 0}–${repeater.max})</small></strong><button type="button" class="button button-secondary" data-add-row ${rows.length >= repeater.max ? 'disabled' : ''}>＋ Add</button></div><div class="repeater-rows"></div>`;
        const rowContainer = wrapper.querySelector('.repeater-rows');
        rows.forEach((row, rowIndex) => {
            const rowElement = document.createElement('div'); rowElement.className = 'repeater-row';
            repeater.fields.forEach((field) => rowElement.append(renderField(field, row[field.key], (value) => { row[field.key] = value; queueSave(module); }, { module, row })));
            if (rows.length > (repeater.min || 0)) { const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'row-remove'; remove.textContent = 'Remove row'; remove.addEventListener('click', () => { rows.splice(rowIndex, 1); queueSave(module); render(); }); rowElement.append(remove); }
            rowContainer.append(rowElement);
        });
        wrapper.querySelector('[data-add-row]').addEventListener('click', () => { if (rows.length >= repeater.max) return; const row = {}; repeater.fields.forEach((field) => row[field.key] = field.type === 'checkbox' ? false : null); rows.push(row); queueSave(module); render(); });
        return wrapper;
    }

    function bindCardActions() {
        document.querySelectorAll('.module-editor').forEach((card) => {
            const module = state.modules.find((item) => String(item.uuid) === card.dataset.uuid);
            card.querySelector('[data-delete]')?.addEventListener('click', () => removeModule(module));
            card.querySelectorAll('[data-move]').forEach((button) => button.addEventListener('click', () => moveModule(module, button.dataset.move)));
            card.querySelector('[data-ai]')?.addEventListener('click', () => { state.aiModule = module; elements.aiResult.innerHTML = ''; elements.aiPrompt.value = ''; elements.aiDialog.showModal(); });
        });
    }

    async function addModule(type) {
        if (!state.routes.moduleStore) { window.showToast('Save the gallery details before adding modules.'); return; }
        if (state.modules.length >= boot.moduleLimit) {
            elements.moduleDialog.close();
            window.showToast('Amazon limits A+ Content to 5 modules.');
            return;
        }

        try {
            const payload = await window.apiFetch(state.routes.moduleStore, { method: 'POST', body: JSON.stringify({ module_type: type, populate_samples: elements.sampleContent?.checked ?? true }) });
            state.modules.push(payload.data);
            if (payload.assets) state.assets = payload.assets;
            const assetSummary = document.querySelector('.asset-summary strong');
            if (assetSummary) assetSummary.textContent = `${state.assets.length} ${state.assets.length === 1 ? 'image' : 'images'}`;
            elements.moduleDialog.close(); render(); window.showToast('Module added');
        }
        catch (error) { window.showToast(error.message); }
    }

    async function removeModule(module) {
        if (!confirm('Remove this module from the page?')) return;
        try { await window.apiFetch(`${state.routes.moduleBase}/${module.uuid}`, { method: 'DELETE' }); state.modules = state.modules.filter((item) => String(item.uuid) !== String(module.uuid)); state.modules.forEach((item, index) => item.position = index + 1); render(); window.showToast('Module removed'); }
        catch (error) { window.showToast(error.message); }
    }

    async function moveModule(module, direction) {
        const index = state.modules.indexOf(module); const target = direction === 'up' ? index - 1 : index + 1;
        if (target < 0 || target >= state.modules.length) return;
        [state.modules[index], state.modules[target]] = [state.modules[target], state.modules[index]]; state.modules.forEach((item, position) => item.position = position + 1); render();
        try { await window.apiFetch(state.routes.reorder, { method: 'POST', body: JSON.stringify({ modules: state.modules.map((item) => item.uuid) }) }); }
        catch (error) { window.showToast(error.message); }
    }

    function queueSave(module) {
        elements.save.className = 'save-state saving'; elements.save.innerHTML = '<span></span>Saving…'; clearTimeout(timers.get(module.uuid));
        timers.set(module.uuid, setTimeout(() => saveModule(module), 750));
    }

    async function saveModule(module) {
        try {
            const body = { content: module.content }; if (state.mode === 'project') body.version = module.version;
            const payload = await window.apiFetch(`${state.routes.moduleBase}/${module.uuid}`, { method: 'PATCH', body: JSON.stringify(body) });
            if (payload.data.version !== undefined) module.version = payload.data.version; elements.save.className = 'save-state'; elements.save.innerHTML = '<span></span>All changes saved'; renderPreview();
        } catch (error) { elements.save.className = 'save-state error'; elements.save.innerHTML = `<span></span>${esc(error.message)}`; }
    }

    function updateAssetSummary() {
        const summary = document.querySelector('.asset-summary strong');
        if (summary) summary.textContent = `${state.assets.length} ${state.assets.length === 1 ? 'image' : 'images'}`;
    }

    function openAsset(field = null, callback = null) {
        state.activeAsset = field ? { field, callback } : null;
        elements.assetSearch.value = '';
        elements.assetRequirement.textContent = field ? `Target: ${field.width} × ${field.height}px. Every selected image will be fitted exactly.` : 'Browse and update images already uploaded to this project.';
        document.getElementById('asset-ai-open').hidden = !field || !state.routes.aiImage;
        renderAssetLibrary();
        if (!elements.assetDialog.open) elements.assetDialog.showModal();
    }

    function renderAssetLibrary(filter = '') {
        const query = filter.trim().toLowerCase();
        const assets = state.assets.filter((asset) => `${asset.original_name || ''} ${asset.alt_text || ''}`.toLowerCase().includes(query));
        elements.assetLibrary.innerHTML = assets.length ? '' : '<div class="asset-library-empty"><strong>No images found</strong><span>Upload a JPG, PNG, or WebP image to get started.</span></div>';
        assets.forEach((asset) => {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'asset-card';
            button.innerHTML = `<span><img src="${esc(asset.thumbnail_url || asset.url)}" alt="${esc(asset.alt_text || '')}"></span><strong>${esc(asset.original_name || `Image ${asset.id}`)}</strong><small>${asset.width} × ${asset.height}</small>`;
            button.addEventListener('click', () => showAssetDetails(asset)); elements.assetLibrary.append(button);
        });
    }

    function showAssetDetails(asset) {
        state.selectedAsset = asset;
        if (elements.assetDialog.open) elements.assetDialog.close();
        elements.assetDetailPreview.innerHTML = `<img src="${esc(asset.preview_url || asset.url)}" alt="">`;
        elements.assetDetailName.textContent = asset.original_name || `Image ${asset.id || ''}`;
        elements.assetDetailDimensions.textContent = `${asset.width} × ${asset.height}px${asset.size_bytes ? ` · ${Math.max(1, Math.round(asset.size_bytes / 1024))} KB` : ''}`;
        elements.assetAlt.value = asset.alt_text || '';
        elements.assetDetailsUse.hidden = !state.activeAsset;
        elements.assetDetailsSave.textContent = asset.pending ? 'Save to library' : 'Save details';
        if (!elements.assetDetailsDialog.open) elements.assetDetailsDialog.showModal();
    }

    function openNewFile(file) {
        if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 10 * 1024 * 1024) { window.showToast('Choose a JPG, PNG, or WebP image up to 10 MB.'); return; }
        const previewUrl = URL.createObjectURL(file); const image = new Image();
        image.onload = () => showAssetDetails({ pending: true, file, preview_url: previewUrl, original_name: file.name, width: image.naturalWidth, height: image.naturalHeight, size_bytes: file.size, alt_text: '' });
        image.onerror = () => { URL.revokeObjectURL(previewUrl); window.showToast('That file could not be read as an image.'); };
        image.src = previewUrl;
    }

    async function uploadAsset(blob, name, altText) {
        const body = new FormData(); body.append('image', blob, name); body.append('alt_text', altText);
        const payload = await window.apiFetch(state.routes.assets, { method: 'POST', body });
        state.assets.push(payload.data); updateAssetSummary(); return payload.data;
    }

    function renderStockSearchResults() {
        const pageSize = Number(elements.stockPageSize.value);
        const pageCount = Math.max(1, Math.ceil(state.stockSearchResults.length / pageSize));
        state.stockSearchPage = Math.min(state.stockSearchPage, pageCount);
        const first = (state.stockSearchPage - 1) * pageSize;
        const images = state.stockSearchResults.slice(first, first + pageSize);
        elements.stockResults.innerHTML = images.length ? '' : '<div class="asset-library-empty"><strong>No matching images found</strong><span>Try another search or provider.</span></div>';
        images.forEach((result) => {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'stock-result-card';
            button.innerHTML = `<span><img src="${esc(result.thumbnail_url)}" alt="${esc(result.title)}" loading="lazy"></span><strong>${esc(result.title)}</strong><small>${esc(result.source || result.domain)} · ${result.width || '?'} × ${result.height || '?'}</small>`;
            button.addEventListener('click', async () => {
                button.disabled = true; const label = button.querySelector('strong'); const originalLabel = label.textContent; label.textContent = 'Downloading…';
                try {
                    const payload = await window.apiFetch(state.routes.assetImport, { method: 'POST', body: JSON.stringify({ token: result.token }) });
                    state.assets.push(payload.data); updateAssetSummary(); elements.stockDialog.close(); showAssetDetails(payload.data);
                    window.showToast('Image downloaded to this project');
                } catch (error) { label.textContent = originalLabel; button.disabled = false; window.showToast(error.message); }
            });
            elements.stockResults.append(button);
        });
        elements.stockPage.textContent = `Page ${state.stockSearchPage} of ${pageCount}`;
        elements.stockPrevious.disabled = state.stockSearchPage <= 1;
        elements.stockNext.disabled = state.stockSearchPage >= pageCount;
    }

    async function searchStockImages() {
        const query = elements.stockQuery.value.trim();
        if (!query) { elements.stockQuery.reportValidity(); return; }
        elements.stockSubmit.disabled = true; elements.stockSubmit.textContent = 'Searching…'; elements.stockSummary.textContent = 'Searching Serper…';
        try {
            const params = new URLSearchParams({ query, source: elements.stockProvider.value });
            const payload = await window.apiFetch(`${state.routes.assetSearch}?${params}`);
            state.stockSearchResults = payload.data.images || []; state.stockSearchPage = 1;
            elements.stockSummary.textContent = `${state.stockSearchResults.length} allowed results for “${payload.data.query}”`;
            renderStockSearchResults();
        } catch (error) {
            state.stockSearchResults = []; state.stockSearchPage = 1; renderStockSearchResults(); elements.stockSummary.textContent = error.message; window.showToast(error.message);
        } finally { elements.stockSubmit.disabled = false; elements.stockSubmit.textContent = 'Search'; }
    }

    async function saveAssetDetails() {
        const asset = state.selectedAsset; const altText = elements.assetAlt.value.trim();
        if (!altText) { elements.assetAlt.reportValidity(); return null; }
        if (asset.read_only) { asset.alt_text = altText; return asset; }
        if (asset.pending) {
            const uploaded = await uploadAsset(asset.file, asset.original_name, altText); state.selectedAsset = uploaded; return uploaded;
        }
        if (altText !== asset.alt_text) {
            const payload = await window.apiFetch(`${state.routes.assetBase}/${asset.id}`, { method: 'PATCH', body: JSON.stringify({ alt_text: altText }) });
            Object.assign(asset, payload.data);
        }
        return asset;
    }

    async function useSelectedAsset() {
        if (!elements.assetDetailsForm.reportValidity()) return;
        let asset = state.selectedAsset; asset.alt_text = elements.assetAlt.value.trim();
        const field = state.activeAsset.field;
        if (Number(asset.width) === Number(field.width) && Number(asset.height) === Number(field.height)) {
            asset = await saveAssetDetails(); if (asset) finishAsset(asset);
            return;
        }
        openCrop(asset);
    }

    function finishAsset(asset) {
        state.activeAsset.callback(assetReference(asset));
        [elements.cropDialog, elements.assetDetailsDialog, elements.assetDialog].forEach((dialog) => { if (dialog.open) dialog.close(); });
        render(); window.showToast('Image fitted and added');
    }

    function loadCropImage(source) {
        return new Promise((resolve, reject) => { const image = new Image(); image.onload = () => resolve(image); image.onerror = reject; image.src = source; });
    }

    async function openCrop(asset) {
        const field = state.activeAsset.field;
        try {
            const image = await loadCropImage(asset.preview_url || asset.url);
            state.crop = { asset, image, rotation: 0, zoom: 1, x: 0, y: 0 };
            elements.cropCanvas.width = field.width; elements.cropCanvas.height = field.height;
            elements.cropCanvas.style.aspectRatio = `${field.width} / ${field.height}`;
            elements.cropTargetLabel.textContent = `${field.width} × ${field.height}px`;
            elements.cropZoom.value = '1'; elements.assetDetailsDialog.close(); elements.cropDialog.showModal(); drawCrop();
        } catch { window.showToast('The selected image could not be opened for cropping.'); }
    }

    function cropMetrics() {
        const crop = state.crop; const canvas = elements.cropCanvas; const quarterTurn = Math.abs(crop.rotation % 180) === 90;
        const rotatedWidth = quarterTurn ? crop.image.naturalHeight : crop.image.naturalWidth;
        const rotatedHeight = quarterTurn ? crop.image.naturalWidth : crop.image.naturalHeight;
        const scale = Math.max(canvas.width / rotatedWidth, canvas.height / rotatedHeight) * crop.zoom;
        return { scale, renderedWidth: rotatedWidth * scale, renderedHeight: rotatedHeight * scale };
    }

    function clampCrop() {
        const metrics = cropMetrics(); const canvas = elements.cropCanvas;
        state.crop.x = Math.max((canvas.width - metrics.renderedWidth) / 2, Math.min((metrics.renderedWidth - canvas.width) / 2, state.crop.x));
        state.crop.y = Math.max((canvas.height - metrics.renderedHeight) / 2, Math.min((metrics.renderedHeight - canvas.height) / 2, state.crop.y));
    }

    function drawCrop() {
        if (!state.crop) return; clampCrop();
        const crop = state.crop; const canvas = elements.cropCanvas; const context = canvas.getContext('2d'); const { scale } = cropMetrics();
        context.clearRect(0, 0, canvas.width, canvas.height); context.save();
        context.translate(canvas.width / 2 + crop.x, canvas.height / 2 + crop.y); context.rotate(crop.rotation * Math.PI / 180);
        context.drawImage(crop.image, -crop.image.naturalWidth * scale / 2, -crop.image.naturalHeight * scale / 2, crop.image.naturalWidth * scale, crop.image.naturalHeight * scale); context.restore();
    }

    elements.assetFile.addEventListener('change', () => { const file = elements.assetFile.files?.[0]; elements.assetFile.value = ''; if (file) openNewFile(file); });
    elements.assetSearch.addEventListener('input', () => renderAssetLibrary(elements.assetSearch.value));
    elements.stockOpen.addEventListener('click', () => { elements.assetDialog.close(); elements.stockDialog.showModal(); setTimeout(() => elements.stockQuery.focus(), 0); });
    elements.stockForm.addEventListener('submit', (event) => { event.preventDefault(); searchStockImages(); });
    elements.stockPageSize.addEventListener('change', () => { state.stockSearchPage = 1; renderStockSearchResults(); });
    elements.stockPrevious.addEventListener('click', () => { state.stockSearchPage--; renderStockSearchResults(); elements.stockResults.scrollTop = 0; });
    elements.stockNext.addEventListener('click', () => { state.stockSearchPage++; renderStockSearchResults(); elements.stockResults.scrollTop = 0; });
    document.getElementById('asset-stock-search-back').addEventListener('click', () => { elements.stockDialog.close(); openAsset(state.activeAsset?.field, state.activeAsset?.callback); });
    document.getElementById('open-asset-manager')?.addEventListener('click', () => openAsset());
    document.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.closeDialog)?.close()));
    document.getElementById('asset-details-back').addEventListener('click', () => { elements.assetDetailsDialog.close(); openAsset(state.activeAsset?.field, state.activeAsset?.callback); });
    elements.assetDetailsForm.addEventListener('submit', async (event) => { event.preventDefault(); try { const asset = await saveAssetDetails(); if (asset) { showAssetDetails(asset); renderAssetLibrary(); window.showToast('Image details saved'); } } catch (error) { window.showToast(error.message); } });
    elements.assetDetailsUse.addEventListener('click', async () => { try { await useSelectedAsset(); } catch (error) { window.showToast(error.message); } });
    document.getElementById('crop-back').addEventListener('click', () => { elements.cropDialog.close(); showAssetDetails(state.selectedAsset); });
    elements.cropZoom.addEventListener('input', () => { state.crop.zoom = Number(elements.cropZoom.value); drawCrop(); });
    document.getElementById('crop-rotate-left').addEventListener('click', () => { state.crop.rotation = (state.crop.rotation - 90) % 360; state.crop.x = 0; state.crop.y = 0; drawCrop(); });
    document.getElementById('crop-rotate-right').addEventListener('click', () => { state.crop.rotation = (state.crop.rotation + 90) % 360; state.crop.x = 0; state.crop.y = 0; drawCrop(); });
    let cropPointer = null;
    elements.cropCanvas.addEventListener('pointerdown', (event) => { cropPointer = { id: event.pointerId, x: event.clientX, y: event.clientY }; elements.cropCanvas.setPointerCapture(event.pointerId); });
    elements.cropCanvas.addEventListener('pointermove', (event) => { if (!cropPointer || cropPointer.id !== event.pointerId) return; const rect = elements.cropCanvas.getBoundingClientRect(); state.crop.x += (event.clientX - cropPointer.x) * elements.cropCanvas.width / rect.width; state.crop.y += (event.clientY - cropPointer.y) * elements.cropCanvas.height / rect.height; cropPointer.x = event.clientX; cropPointer.y = event.clientY; drawCrop(); });
    elements.cropCanvas.addEventListener('pointerup', () => { cropPointer = null; });
    elements.cropApply.addEventListener('click', () => {
        elements.cropApply.disabled = true; elements.cropApply.textContent = 'Cropping…';
        elements.cropCanvas.toBlob(async (blob) => {
            try { if (!blob) throw new Error('The cropped image could not be created.'); const original = state.crop.asset.original_name || 'image'; const name = `${original.replace(/\.[^.]+$/, '')}-${elements.cropCanvas.width}x${elements.cropCanvas.height}.png`; const asset = await uploadAsset(blob, name, state.crop.asset.alt_text); finishAsset(asset); }
            catch (error) { window.showToast(error.message); }
            finally { elements.cropApply.disabled = false; elements.cropApply.textContent = 'Crop and use'; }
        }, 'image/png');
    });
    document.getElementById('asset-ai-open').addEventListener('click', () => { if (!state.routes.aiImage) return; elements.assetDialog.close(); elements.assetAiForm.reset(); elements.assetAiDialog.showModal(); });
    document.getElementById('asset-ai-back').addEventListener('click', () => { elements.assetAiDialog.close(); openAsset(state.activeAsset.field, state.activeAsset.callback); });
    elements.assetAiForm.addEventListener('submit', async (event) => {
        event.preventDefault(); const field = state.activeAsset.field; elements.assetAiGenerate.disabled = true; elements.assetAiGenerate.textContent = 'Generating…';
        try {
            const payload = await window.apiFetch(state.routes.aiImage, { method: 'POST', body: JSON.stringify({ prompt: elements.assetAiPrompt.value, alt_text: elements.assetAiAlt.value, width: field.width, height: field.height }) });
            state.assets.push(payload.data); updateAssetSummary(); elements.assetAiDialog.close(); finishAsset(payload.data);
        } catch (error) { window.showToast(error.message); }
        finally { elements.assetAiGenerate.disabled = false; elements.assetAiGenerate.textContent = 'Generate and use'; }
    });

    elements.aiForm.addEventListener('submit', async (event) => {
        event.preventDefault(); const submit = elements.aiForm.querySelector('[type="submit"]'); submit.disabled = true; submit.textContent = 'Writing…'; elements.aiResult.innerHTML = '';
        try {
            const payload = await window.apiFetch(state.routes.aiText, { method: 'POST', body: JSON.stringify({ prompt: elements.aiPrompt.value, module_type: state.aiModule.module_type }) }); const result = payload.data;
            elements.aiResult.innerHTML = `<div class="validation-list"><strong>Draft ready</strong><p>${esc(result.headline || '')}</p><p>${esc(result.body_html || '')}</p><small>Applying this draft replaces the current copy in this module.</small><button type="button" class="button button-secondary" id="apply-ai">Apply draft</button></div>`;
            document.getElementById('apply-ai').addEventListener('click', () => { applyAi(state.aiModule.content, result); queueSave(state.aiModule); elements.aiDialog.close(); render(); });
        } catch (error) { elements.aiResult.innerHTML = `<span class="field-error">${esc(error.message)}</span>`; }
        finally { submit.disabled = false; submit.textContent = 'Generate draft'; }
    });

    document.getElementById('gallery-details-form')?.addEventListener('submit', async (event) => {
        event.preventDefault(); const form = event.currentTarget; const submit = form.querySelector('[type="submit"]');
        submit.disabled = true; submit.textContent = 'Saving…';
        try {
            const fields = Object.fromEntries(new FormData(form)); fields.is_featured = form.elements.is_featured.checked;
            const creating = state.mode === 'template-create';
            const payload = await window.apiFetch(state.routes.entitySave, { method: creating ? 'POST' : 'PUT', body: JSON.stringify(fields) });
            if (creating && payload.redirect) { window.location.assign(payload.redirect); return; }
            state.project.name = payload.data.name; document.querySelector('.app-topbar h1').textContent = payload.data.name;
            window.showToast('Gallery details saved');
        } catch (error) { window.showToast(error.message); }
        finally { submit.disabled = false; submit.textContent = 'Save gallery details'; }
    });

    function applyAi(content, result) {
        if ('headline' in content && result.headline) content.headline = result.headline;
        if ('body_html' in content && result.body_html) content.body_html = result.body_html;
        if ('description_html' in content && result.body_html) content.description_html = result.body_html;
        Object.values(content).filter(Array.isArray).forEach((rows) => rows.forEach((row) => { if ('headline' in row && result.headline) row.headline = result.headline; if ('body_html' in row && result.body_html) row.body_html = result.body_html; }));
    }

    function renderPreview() {
        elements.preview.innerHTML = state.modules.length
            ? `<main class="amazon-preview">${state.modules.map(renderPreviewModule).join('')}</main>`
            : '<div class="builder-empty"><h2>Add modules to preview your page</h2></div>';
    }

    function previewImage(id, className = '') {
        const asset = assetById(id);
        return asset ? `<img class="${className}" src="${esc(asset.url)}" alt="${esc(asset.alt_text || '')}">` : '<span class="amazon-missing-image"></span>';
    }

    function previewRich(value) { return value ? `<div class="amazon-rich">${value}</div>` : ''; }
    function previewHeading(value, level = 2) { return value ? `<h${level}>${esc(value)}</h${level}>` : ''; }

    function renderPreviewModule(module) {
        const c = module.content || {};
        const items = c.items || [];
        const sections = c.sections || [];
        const bullets = c.bullets || [];

        switch (module.module_type) {
            case 'company_logo':
                return `<section class="amazon-module amazon-logo">${previewImage(c.image)}</section>`;
            case 'comparison_chart': {
                const products = c.products || [];
                const metrics = c.metrics || [];
                return `<section class="amazon-module amazon-comparison"><div class="amazon-compare-grid" style="--compare-count:${Math.max(products.length, 1)}"><span></span>${products.map((product) => `<article class="${product.highlighted ? 'highlighted' : ''}">${previewImage(product.image)}<strong>${esc(product.title || '')}</strong>${c.show_reviews ? '<span class="amazon-stars">★★★★★</span>' : ''}${c.show_prices ? '<small>Available on Amazon</small>' : ''}${c.show_add_to_cart ? '<button type="button">Shop now</button>' : ''}</article>`).join('')}${metrics.map((metric) => { const values = String(metric.values || '').split('|'); return `<strong class="metric-label">${esc(metric.label || '')}</strong>${products.map((_, index) => `<span class="metric-value">${esc(values[index] || '—')}</span>`).join('')}`; }).join('')}</div></section>`;
            }
            case 'four_image_text':
                return `<section class="amazon-module amazon-feature-columns">${previewHeading(c.headline)}<div class="columns four">${items.map((item) => `<article>${previewImage(item.image)}${previewHeading(item.headline, 3)}${previewRich(item.body_html)}</article>`).join('')}</div></section>`;
            case 'four_image_quadrant':
                return `<section class="amazon-module amazon-quadrants">${items.map((item) => `<article>${previewImage(item.image)}<div>${previewHeading(item.headline, 3)}${previewRich(item.body_html)}</div></article>`).join('')}</section>`;
            case 'dark_text_overlay':
            case 'light_text_overlay':
                return `<section class="amazon-module amazon-overlay ${module.module_type === 'dark_text_overlay' ? 'dark-copy' : 'light-copy'}">${previewImage(c.background)}<div class="overlay-copy">${previewHeading(c.headline)}${previewRich(c.body_html)}</div></section>`;
            case 'image_header_text':
                return `<section class="amazon-module amazon-header-image">${previewHeading(c.top_headline)}${previewImage(c.image)}<div>${previewHeading(c.headline)}${previewRich(c.body_html)}</div></section>`;
            case 'multiple_image_a':
                return `<section class="amazon-module amazon-multiple"><div class="multiple-main">${previewImage(items[0]?.image)}</div><div class="multiple-copy">${previewHeading(c.headline)}${previewRich(c.description_html)}<div class="multiple-thumbs">${items.map((item) => `<figure>${previewImage(item.image)}<figcaption>${esc(item.caption || '')}</figcaption></figure>`).join('')}</div></div></section>`;
            case 'product_description_text':
                return `<section class="amazon-module amazon-product-description">${previewRich(c.body_html)}</section>`;
            case 'single_image_highlights':
                return `<section class="amazon-module amazon-highlights">${previewImage(c.image)}<div class="highlight-copy">${sections.map((section) => `<article>${previewHeading(section.subheadline, 3)}${previewRich(section.body_html)}</article>`).join('')}</div><aside>${previewHeading(c.highlights_headline, 3)}<ul>${bullets.map((bullet) => `<li>${esc(bullet.text || '')}</li>`).join('')}</ul></aside></section>`;
            case 'single_image_sidebar':
                return `<section class="amazon-module amazon-sidebar"><figure>${previewImage(c.primary_image)}${c.image_caption ? `<figcaption>${esc(c.image_caption)}</figcaption>` : ''}</figure><div>${previewHeading(c.headline)}${previewHeading(c.subheadline, 3)}${previewRich(c.body_html)}${bullets.length ? `<ul>${bullets.map((bullet) => `<li>${esc(bullet.text || '')}</li>`).join('')}</ul>` : ''}</div><aside>${previewImage(c.sidebar_image)}${previewHeading(c.sidebar_headline, 3)}${previewRich(c.sidebar_body_html)}</aside></section>`;
            case 'single_image_specs_detail':
                return `<section class="amazon-module amazon-spec-detail">${previewHeading(c.headline)}<div class="spec-detail-grid">${previewImage(c.image)}${sections.map((section) => `<article>${previewHeading(section.headline, 3)}${previewHeading(section.subheadline, 4)}${previewRich(section.body_html)}</article>`).join('')}</div></section>`;
            case 'single_left_image':
                return `<section class="amazon-module amazon-single-image left">${previewImage(c.image)}<div>${previewHeading(c.headline)}${previewRich(c.body_html)}</div></section>`;
            case 'single_right_image':
                return `<section class="amazon-module amazon-single-image right"><div>${previewHeading(c.headline)}${previewRich(c.body_html)}</div>${previewImage(c.image)}</section>`;
            case 'technical_specifications':
                return `<section class="amazon-module amazon-tech-specs">${previewHeading(c.headline)}<div class="spec-rows columns-${esc(c.columns || '1')}">${(c.specifications || []).map((spec) => `<div><strong>${esc(spec.specification || '')}</strong><span>${esc(spec.definition || '')}</span></div>`).join('')}</div></section>`;
            case 'standard_text':
                return `<section class="amazon-module amazon-standard-text">${previewHeading(c.headline)}${previewRich(c.body_html)}</section>`;
            case 'three_images_text':
                return `<section class="amazon-module amazon-feature-columns">${previewHeading(c.headline)}<div class="columns three">${items.map((item) => `<article>${previewImage(item.image)}${previewHeading(item.headline, 3)}${previewRich(item.body_html)}</article>`).join('')}</div></section>`;
            default:
                return '';
        }
    }

    function validateProject() {
        const issues = [];
        state.modules.forEach((module, index) => {
            const definition = state.registry[module.module_type];
            (definition.fields || []).forEach((field) => validateField(field, module.content[field.key], `${index + 1}. ${definition.name}`, issues));
            (definition.repeaters || []).forEach((repeater) => (module.content[repeater.key] || []).forEach((row, rowIndex) => repeater.fields.forEach((field) => validateField(field, row[field.key], `${index + 1}. ${definition.name}, ${repeater.label} ${rowIndex + 1}`, issues))));
        });
        if (!state.modules.length) issues.push('Add at least one module.');
        alert(issues.length ? `Content check found ${issues.length} item(s):\n\n${issues.join('\n')}` : 'Everything required is filled in. Your page is ready to export.');
    }

    function validateField(field, value, context, issues) {
        if (field.required && !value) issues.push(`${context}: ${field.label} is required.`);
        if (field.type === 'image' && value) { const asset = assetById(value); if (asset && (asset.width !== field.width || asset.height !== field.height)) issues.push(`${context}: ${field.label} is ${asset.width}×${asset.height}; ${field.width}×${field.height} is recommended.`); }
    }

    elements.addModuleButtons.forEach((button) => button.addEventListener('click', () => {
        if (state.modules.length < boot.moduleLimit) elements.moduleDialog.showModal();
    }));
    elements.gallery.addEventListener('click', (event) => { const button = event.target.closest('[data-add-module]'); if (button) addModule(button.dataset.addModule); });
    elements.search.addEventListener('input', (event) => renderGallery(event.target.value));
    document.querySelectorAll('[data-builder-tab]').forEach((button) => button.addEventListener('click', () => { document.querySelectorAll('[data-builder-tab]').forEach((item) => item.classList.toggle('active', item === button)); document.querySelectorAll('[data-panel]').forEach((panel) => panel.hidden = panel.dataset.panel !== button.dataset.builderTab); if (button.dataset.builderTab === 'preview') renderPreview(); }));
    document.querySelectorAll('[data-preview-width]').forEach((button) => button.addEventListener('click', () => { document.querySelectorAll('[data-preview-width]').forEach((item) => item.classList.toggle('active', item === button)); document.getElementById('preview-frame').classList.toggle('mobile', button.dataset.previewWidth === 'mobile'); }));
    document.getElementById('validate-project').addEventListener('click', validateProject);
    renderGallery(); render();
}
