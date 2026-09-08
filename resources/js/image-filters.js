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
    let draft = new Map();
    root.innerHTML = `<header class="filter-page-header"><p class="eyebrow">Image styling</p><h2>Template image filters</h2><p>Apply up to three filters in order to every enabled image. Uncheck “Apply template filters” on any image to skip it.</p></header><div class="filter-workspace"><div class="filter-controls"><div class="filter-actions filter-add"><select data-preset aria-label="Filter preset">${catalog.presets.map(p => `<option value="${esc(p.id)}">${esc(p.name)} — ${esc(p.description)}</option>`).join('')}</select><button type="button" class="button button-secondary" data-add>Add filter</button></div><div data-stack></div><div class="filter-actions"><button type="button" class="button button-ghost" data-clear>Remove all</button><button type="button" class="button button-primary" data-save>Save filters</button><span data-status role="status"></span></div><p class="field-hint">Instagram-inspired color presets. Grain, blur, lens and other special effects are color approximations.</p></div><section class="filter-preview" aria-label="Filter preview images"><div class="filter-preview-heading"><div><h3>Image preview</h3><p>See the full filter stack on up to 3 images.</p></div><button type="button" class="button button-secondary" data-choose>Choose images</button></div><div data-samples class="filter-samples"></div></section></div><dialog class="studio-dialog filter-asset-dialog" aria-labelledby="filter-asset-title"><div class="dialog-shell"><header><div><p class="eyebrow">Preview assets</p><h2 id="filter-asset-title">Choose up to 3 images</h2></div><button type="button" class="icon-button" data-cancel aria-label="Close image chooser">×</button></header><div class="filter-picker-tools"><input type="search" data-search placeholder="Search images…" aria-label="Search preview images"><span data-count role="status"></span></div><div data-choices class="filter-asset-grid"></div><footer><button type="button" class="button button-ghost" data-cancel>Cancel</button><button type="button" class="button button-primary" data-use>Use selected images</button></footer></div></dialog>`;
    const status = root.querySelector('[data-status]');
    const dialog = root.querySelector('dialog');
    const refreshSamples = () => {
        root.querySelector('[data-samples]').innerHTML = [...selected].map(([id, asset]) => `<figure><div class="filter-sample-art"><img src="${esc(asset.url)}" style="filter:${filterCss(filters)}" alt="${esc(asset.alt_text || asset.original_name || 'Filter sample')}"></div><figcaption><span>${esc(asset.original_name || 'Sample')}</span><button type="button" data-remove-sample="${esc(id)}" aria-label="Remove ${esc(asset.original_name || 'sample')} from preview">Remove</button></figcaption></figure>`).join('') || '<div class="filter-preview-empty"><span aria-hidden="true">▧</span><h4>Try your filters on real images</h4><p>Choose up to 3 images from your assets to compare them side by side.</p><button type="button" class="button button-secondary" data-choose-empty>Choose images</button></div>';
        root.querySelector('[data-samples]').style.setProperty('--sample-count', Math.max(selected.size, 1));
        root.querySelector('[data-choose-empty]')?.addEventListener('click', openChooser);
        root.querySelectorAll('[data-remove-sample]').forEach(button => button.onclick = () => {
            selected.delete(button.dataset.removeSample); refreshSamples();
        });
    };
    const refreshChoices = () => {
        const query = root.querySelector('[data-search]').value.toLowerCase().trim();
        const matches = assets().filter(asset => `${asset.original_name || ''} ${asset.alt_text || ''}`.toLowerCase().includes(query));
        root.querySelector('[data-count]').textContent = `${draft.size} / 3 selected`;
        root.querySelector('[data-choices]').innerHTML = matches.map(asset => {
            const checked = draft.has(String(asset.id));
            return `<label class="filter-asset-choice ${checked ? 'selected' : ''}"><input type="checkbox" data-sample="${esc(asset.id)}" ${checked ? 'checked' : ''} ${!checked && draft.size >= 3 ? 'disabled' : ''}><img src="${esc(asset.thumbnail_url || asset.url)}" alt="" loading="lazy"><span>${esc(asset.original_name || asset.alt_text || 'Image')}</span></label>`;
        }).join('') || '<p class="filter-picker-empty">' + (assets().length ? 'No matching images. Try another search.' : 'No assets yet. Add images in the Asset manager, then return here.') + '</p>';
        root.querySelectorAll('[data-sample]').forEach(input => input.onchange = () => {
            if (input.checked && draft.size < 3) draft.set(input.dataset.sample, assets().find(a => String(a.id) === input.dataset.sample));
            else draft.delete(input.dataset.sample);
            const focusedId = input.dataset.sample;
            refreshChoices();
            [...root.querySelectorAll('[data-sample]')].find(choice => choice.dataset.sample === focusedId)?.focus();
        });
    };
    function openChooser() {
        draft = new Map(selected); root.querySelector('[data-search]').value = ''; refreshChoices(); dialog.showModal();
    }
    root.querySelector('[data-choose]').onclick = openChooser;
    root.querySelectorAll('[data-cancel]').forEach(button => button.onclick = () => dialog.close());
    root.querySelector('[data-search]').oninput = refreshChoices;
    root.querySelector('[data-use]').onclick = () => {
        selected.clear(); draft.forEach((asset, id) => selected.set(id, asset)); refreshSamples(); dialog.close();
    };
    const changed = () => { status.textContent = 'Unsaved filter changes'; onChange(structuredClone(filters)); refreshSamples(); };
    const renderStack = () => {
        root.querySelector('[data-stack]').innerHTML = filters.map((filter, index) => {
            const preset = catalog.presets.find(p => p.id === filter.id);
            return `<fieldset class="filter-card"><legend>${index + 1}. ${esc(preset.name)}</legend><p class="filter-description">${esc(preset.description)}</p><div class="filter-sliders">${catalog.sliders.map(slider => {
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
    renderStack();
    refreshSamples();
}
