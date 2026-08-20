const dataNode = document.getElementById('builder-data');

if (dataNode) {
    const boot = JSON.parse(dataNode.textContent);
    const state = { ...boot, modules: [...boot.modules].sort((a, b) => a.position - b.position), activeAsset: null, aiModule: null };
    const elements = {
        list: document.getElementById('module-list'), outline: document.getElementById('module-outline'), empty: document.getElementById('builder-empty'),
        gallery: document.getElementById('module-gallery'), moduleDialog: document.getElementById('module-dialog'), search: document.getElementById('module-search'),
        assetDialog: document.getElementById('asset-dialog'), assetForm: document.getElementById('asset-form'), assetFile: document.getElementById('asset-file'), assetPreview: document.getElementById('asset-preview'),
        assetAlt: document.getElementById('asset-alt'), assetRequirement: document.getElementById('asset-requirement'), assetAiPrompt: document.getElementById('asset-ai-prompt'), assetAiGenerate: document.getElementById('asset-ai-generate'), save: document.getElementById('save-state'),
        aiDialog: document.getElementById('ai-dialog'), aiForm: document.getElementById('ai-form'), aiPrompt: document.getElementById('ai-prompt'), aiResult: document.getElementById('ai-result'), preview: document.getElementById('preview-content'),
    };
    const timers = new Map();

    const esc = window.escapeHtml || ((value) => String(value));
    const assetById = (id) => state.assets.find((asset) => Number(asset.id) === Number(id));
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
        elements.empty.hidden = state.modules.length > 0;
        elements.list.innerHTML = '';
        elements.outline.innerHTML = '';
        state.modules.forEach((module, index) => {
            const definition = state.registry[module.module_type];
            const card = document.createElement('article'); card.className = 'module-editor'; card.dataset.uuid = module.uuid;
            card.innerHTML = `<header class="module-editor-head"><span>${String(index + 1).padStart(2, '0')}</span><h3>${esc(definition.name)}</h3>${definition.ai_ready ? '<span class="ai-badge">AI ready</span>' : ''}<div class="module-editor-actions">${definition.ai_ready ? '<button type="button" data-ai title="Draft copy with AI">✦ Generate</button>' : ''}<button type="button" data-move="up" title="Move up">↑</button><button type="button" data-move="down" title="Move down">↓</button><button type="button" data-delete title="Remove">×</button></div></header><div class="module-fields"></div>`;
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
            button.innerHTML = asset ? `<img src="${esc(asset.url)}" alt="${esc(asset.alt_text || '')}">` : `<span><span class="image-symbol">▧</span><strong>${field.width} × ${field.height}</strong><small>Click to add image</small></span>`;
            button.addEventListener('click', () => openAsset(field, (id) => onChange(id))); wrapper.append(button);
        } else if (field.type === 'asin') {
            const control = document.createElement('div'); control.className = 'asin-control';
            const input = document.createElement('input'); input.type = 'text'; input.maxLength = 10; input.value = fieldValue(value); input.placeholder = 'Enter 10-character ASIN';
            const button = document.createElement('button'); button.type = 'button'; button.className = 'button button-secondary'; button.textContent = 'Replace cover';
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
            const module = state.modules.find((item) => item.uuid === card.dataset.uuid);
            card.querySelector('[data-delete]')?.addEventListener('click', () => removeModule(module));
            card.querySelectorAll('[data-move]').forEach((button) => button.addEventListener('click', () => moveModule(module, button.dataset.move)));
            card.querySelector('[data-ai]')?.addEventListener('click', () => { state.aiModule = module; elements.aiResult.innerHTML = ''; elements.aiPrompt.value = ''; elements.aiDialog.showModal(); });
        });
    }

    async function addModule(type) {
        try { const payload = await window.apiFetch(state.routes.moduleStore, { method: 'POST', body: JSON.stringify({ module_type: type }) }); state.modules.push(payload.data); elements.moduleDialog.close(); render(); window.showToast('Module added'); }
        catch (error) { window.showToast(error.message); }
    }

    async function removeModule(module) {
        if (!confirm('Remove this module from the page?')) return;
        try { await window.apiFetch(`${state.routes.moduleBase}/${module.uuid}`, { method: 'DELETE' }); state.modules = state.modules.filter((item) => item.uuid !== module.uuid); state.modules.forEach((item, index) => item.position = index + 1); render(); window.showToast('Module removed'); }
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
            const payload = await window.apiFetch(`${state.routes.moduleBase}/${module.uuid}`, { method: 'PATCH', body: JSON.stringify({ version: module.version, content: module.content }) });
            module.version = payload.data.version; elements.save.className = 'save-state'; elements.save.innerHTML = '<span></span>All changes saved'; renderPreview();
        } catch (error) { elements.save.className = 'save-state error'; elements.save.innerHTML = `<span></span>${esc(error.message)}`; }
    }

    function openAsset(field, callback) {
        state.activeAsset = { field, callback }; elements.assetForm.reset(); elements.assetPreview.innerHTML = ''; elements.assetRequirement.textContent = `Recommended: ${field.width} × ${field.height}px. Alt text is required.`; elements.assetDialog.showModal();
    }

    elements.assetFile.addEventListener('change', () => { const file = elements.assetFile.files[0]; if (!file) return; const reader = new FileReader(); reader.onload = () => elements.assetPreview.innerHTML = `<img src="${reader.result}" alt="Selected image preview">`; reader.readAsDataURL(file); });
    elements.assetForm.addEventListener('submit', async (event) => {
        event.preventDefault(); const file = elements.assetFile.files[0]; if (!file) return;
        const body = new FormData(); body.append('image', file); body.append('alt_text', elements.assetAlt.value);
        try { const payload = await window.apiFetch(state.routes.assets, { method: 'POST', body }); state.assets.push(payload.data); state.activeAsset.callback(payload.data.id); elements.assetDialog.close(); render(); window.showToast('Image uploaded'); }
        catch (error) { window.showToast(error.message); }
    });

    elements.assetAiGenerate.addEventListener('click', async () => {
        if (!elements.assetAiPrompt.value.trim() || !elements.assetAlt.value.trim()) { window.showToast('Add image direction and alt text first.'); return; }
        const field = state.activeAsset.field; elements.assetAiGenerate.disabled = true; elements.assetAiGenerate.textContent = 'Generating…';
        try {
            const payload = await window.apiFetch(state.routes.aiImage, { method: 'POST', body: JSON.stringify({ prompt: elements.assetAiPrompt.value, alt_text: elements.assetAlt.value, width: field.width, height: field.height }) });
            state.assets.push(payload.data); state.activeAsset.callback(payload.data.id); elements.assetDialog.close(); render(); window.showToast('AI image generated and added');
        } catch (error) { window.showToast(error.message); }
        finally { elements.assetAiGenerate.disabled = false; elements.assetAiGenerate.textContent = '✦ Generate with AI'; }
    });

    elements.aiForm.addEventListener('submit', async (event) => {
        event.preventDefault(); const submit = elements.aiForm.querySelector('[type="submit"]'); submit.disabled = true; submit.textContent = 'Writing…'; elements.aiResult.innerHTML = '';
        try {
            const payload = await window.apiFetch(state.routes.aiText, { method: 'POST', body: JSON.stringify({ prompt: elements.aiPrompt.value, module_type: state.aiModule.module_type }) }); const result = payload.data;
            elements.aiResult.innerHTML = `<div class="validation-list"><strong>Draft ready</strong><p>${esc(result.headline || '')}</p><p>${esc(result.body_html || '')}</p><button type="button" class="button button-secondary" id="apply-ai">Apply to empty fields</button></div>`;
            document.getElementById('apply-ai').addEventListener('click', () => { applyAi(state.aiModule.content, result); queueSave(state.aiModule); elements.aiDialog.close(); render(); });
        } catch (error) { elements.aiResult.innerHTML = `<span class="field-error">${esc(error.message)}</span>`; }
        finally { submit.disabled = false; submit.textContent = 'Generate draft'; }
    });

    function applyAi(content, result) {
        if ('headline' in content && !content.headline && result.headline) content.headline = result.headline;
        if ('body_html' in content && !content.body_html && result.body_html) content.body_html = result.body_html;
        Object.values(content).filter(Array.isArray).forEach((rows) => rows.forEach((row) => { if ('headline' in row && !row.headline && result.headline) row.headline = result.headline; if ('body_html' in row && !row.body_html && result.body_html) row.body_html = result.body_html; }));
    }

    function renderPreview() {
        elements.preview.innerHTML = state.modules.length ? state.modules.map((module) => {
            const definition = state.registry[module.module_type]; const values = [];
            (definition.fields || []).forEach((field) => { const value = module.content[field.key]; if (value) values.push(previewField(field, value)); });
            (definition.repeaters || []).forEach((repeater) => { const rows = module.content[repeater.key] || []; if (rows.length) values.push(`<div class="preview-grid">${rows.map((row) => `<div class="preview-item">${repeater.fields.map((field) => row[field.key] ? previewField(field, row[field.key]) : '').join('')}</div>`).join('')}</div>`); });
            return `<section class="preview-module preview-${module.module_type}"><small>${esc(definition.name)}</small>${values.join('')}</section>`;
        }).join('') : '<div class="builder-empty"><h2>Add modules to preview your page</h2></div>';
    }

    function previewField(field, value) {
        if (field.type === 'image') { const asset = assetById(value); return asset ? `<img src="${esc(asset.url)}" alt="${esc(asset.alt_text || '')}">` : ''; }
        if (field.type === 'richtext') return `<div class="preview-rich">${value}</div>`;
        if (field.type === 'checkbox') return '';
        if (field.key.includes('headline') || field.key === 'title') return `<h2>${esc(value)}</h2>`;
        return `<p>${esc(value)}</p>`;
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

    document.querySelectorAll('[data-open-module-dialog]').forEach((button) => button.addEventListener('click', () => elements.moduleDialog.showModal()));
    elements.gallery.addEventListener('click', (event) => { const button = event.target.closest('[data-add-module]'); if (button) addModule(button.dataset.addModule); });
    elements.search.addEventListener('input', (event) => renderGallery(event.target.value));
    document.querySelectorAll('[data-builder-tab]').forEach((button) => button.addEventListener('click', () => { document.querySelectorAll('[data-builder-tab]').forEach((item) => item.classList.toggle('active', item === button)); document.querySelectorAll('[data-panel]').forEach((panel) => panel.hidden = panel.dataset.panel !== button.dataset.builderTab); if (button.dataset.builderTab === 'preview') renderPreview(); }));
    document.querySelectorAll('[data-preview-width]').forEach((button) => button.addEventListener('click', () => { document.querySelectorAll('[data-preview-width]').forEach((item) => item.classList.toggle('active', item === button)); document.getElementById('preview-frame').classList.toggle('mobile', button.dataset.previewWidth === 'mobile'); }));
    document.getElementById('validate-project').addEventListener('click', validateProject);
    renderGallery(); render();
}
