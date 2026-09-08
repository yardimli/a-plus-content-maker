import catalog from '../data/image-filters.json';

export { catalog };
export function filterCss(stack = []) {
    return stack.slice(0, catalog.maxFilters).flatMap(filter => {
        const preset = catalog.presets.find(p => p.id === filter.id);
        if (!preset) return [];
        return catalog.sliders.map(slider => {
            const raw = Number(filter.values?.[slider.key] ?? preset.values[slider.key] ?? slider.default);
            const value = Number.isFinite(raw) ? Math.max(slider.min, Math.min(slider.max, raw)) : slider.default;
            return `${slider.key}(${value}${slider.unit})`;
        });
    }).join(' ') || 'none';
}

export function mountFilterEditor(root, { stack, assets, onChange, onSave }) {
    let filters = structuredClone(stack || []);
    const selected = new Map();
    const esc = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
    root.innerHTML = `<h2>Template image filters</h2><p>Apply up to three filters in order to every enabled image. Uncheck “Apply template filters” on any image to skip it.</p><p class="field-hint">Instagram-inspired color presets. Grain, blur, lens and other special effects are color approximations.</p><div data-stack></div><div class="filter-actions"><label>Add preset <select data-preset aria-label="Filter preset">${catalog.presets.map(p => `<option value="${esc(p.id)}">${esc(p.name)}</option>`).join('')}</select></label><button type="button" class="button button-secondary" data-add>Add filter</button><button type="button" class="button button-ghost" data-clear>Remove all</button><button type="button" class="button button-primary" data-save>Save filters</button><span data-status role="status"></span></div><details data-comparison><summary>Compare up to 3 images side by side</summary><p>Choose library images or local files to test this filter stack.</p><div data-choices class="filter-choices"></div><label>Choose local images <input data-files type="file" accept="image/png,image/jpeg,image/webp" multiple></label><div data-samples class="filter-samples"></div></details>`;
    const status = root.querySelector('[data-status]');
    const refreshSamples = () => {
        root.querySelector('[data-samples]').innerHTML = [...selected].map(([id, asset]) => `<figure><img src="${esc(asset.url)}" style="filter:${filterCss(filters)}" alt="${esc(asset.original_name || 'Filter sample')}"><figcaption>${esc(asset.original_name || 'Sample')} <button type="button" data-remove-sample="${esc(id)}">Remove</button></figcaption></figure>`).join('');
        root.querySelectorAll('[data-remove-sample]').forEach(button => button.onclick = () => {
            const asset = selected.get(button.dataset.removeSample); if (asset.local) URL.revokeObjectURL(asset.url);
            selected.delete(button.dataset.removeSample); refreshSamples(); refreshChoices();
        });
    };
    const refreshChoices = () => {
        root.querySelector('[data-choices]').innerHTML = assets().map(asset => `<label><input type="checkbox" data-sample="${esc(asset.id)}" ${selected.has(String(asset.id)) ? 'checked' : ''}> ${esc(asset.original_name || asset.alt_text || 'Image')}</label>`).join('');
        root.querySelectorAll('[data-sample]').forEach(input => input.onchange = () => {
            if (input.checked && selected.size >= 3) { input.checked = false; window.showToast('Choose up to 3 comparison images.'); return; }
            if (input.checked) selected.set(input.dataset.sample, assets().find(a => String(a.id) === input.dataset.sample));
            else selected.delete(input.dataset.sample);
            refreshSamples();
        });
    };
    const changed = () => { status.textContent = 'Unsaved filter changes'; onChange(structuredClone(filters)); refreshSamples(); };
    const renderStack = () => {
        root.querySelector('[data-stack]').innerHTML = filters.map((filter, index) => {
            const preset = catalog.presets.find(p => p.id === filter.id);
            return `<fieldset class="filter-card"><legend>${index + 1}. ${esc(preset.name)}</legend><div class="filter-sliders">${catalog.sliders.map(slider => {
                const value = filter.values?.[slider.key] ?? preset.values[slider.key] ?? slider.default;
                return `<label>${esc(slider.name)} <output>${value}${slider.unit}</output><input type="range" aria-label="${esc(preset.name)} ${esc(slider.name)}" data-index="${index}" data-slider="${slider.key}" min="${slider.min}" max="${slider.max}" step="${slider.step}" value="${value}"></label>`;
            }).join('')}</div><div class="filter-actions"><button type="button" data-up="${index}" ${index === 0 ? 'disabled' : ''}>Move earlier</button><button type="button" data-down="${index}" ${index === filters.length - 1 ? 'disabled' : ''}>Move later</button><button type="button" data-reset="${index}">Reset sliders</button><button type="button" data-remove="${index}">Remove filter</button></div></fieldset>`;
        }).join('') || '<p>No filters applied. Original colors are preserved.</p>';
        root.querySelector('[data-add]').disabled = filters.length >= catalog.maxFilters;
        root.querySelectorAll('[data-slider]').forEach(input => input.oninput = () => {
            const filter = filters[Number(input.dataset.index)]; filter.values ||= {};
            filter.values[input.dataset.slider] = Number(input.value);
            input.previousElementSibling.value = input.value + catalog.sliders.find(s => s.key === input.dataset.slider).unit;
            changed();
        });
        for (const action of ['up', 'down', 'reset', 'remove']) root.querySelectorAll(`[data-${action}]`).forEach(button => button.onclick = () => {
            const i = Number(button.dataset[action]);
            if (action === 'remove') filters.splice(i, 1);
            else if (action === 'reset') filters[i].values = {};
            else { const j = i + (action === 'up' ? -1 : 1); [filters[i], filters[j]] = [filters[j], filters[i]]; }
            renderStack(); changed();
        });
    };
    root.querySelector('[data-add]').onclick = () => { if (filters.length < catalog.maxFilters) { filters.push({ id: root.querySelector('[data-preset]').value, values: {} }); renderStack(); changed(); } };
    root.querySelector('[data-clear]').onclick = () => { filters = []; renderStack(); changed(); };
    root.querySelector('[data-save]').onclick = async event => {
        event.currentTarget.disabled = true;
        try { if (await onSave() !== false) status.textContent = ''; }
        finally { root.querySelector('[data-save]').disabled = false; }
    };
    root.querySelector('[data-comparison]').ontoggle = refreshChoices;
    root.querySelector('[data-files]').onchange = event => {
        for (const file of event.target.files) {
            if (selected.size >= 3) { window.showToast('Choose up to 3 comparison images.'); break; }
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) continue;
            if (file.size > 10 * 1024 * 1024) { window.showToast('Choose images under 10 MB.'); continue; }
            const url = URL.createObjectURL(file); selected.set(url, { url, original_name: file.name, local: true });
        }
        event.target.value = ''; refreshSamples();
    };
    renderStack();
}
