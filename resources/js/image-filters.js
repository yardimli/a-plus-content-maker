import catalog from '../data/image-filters.json';

export { catalog };
const overlayDefinitions = new Map();
export function resolvedFilter(stack = []) {
    const filter = stack[0];
    const preset = catalog.presets.find(p => p.id === filter?.id);
    if (!preset) return null;
    const values = Object.fromEntries(catalog.sliders.map(slider => {
        const raw = Number(filter.values?.[slider.key] ?? preset.values[slider.key] ?? slider.default);
        return [slider.key, Number.isFinite(raw) ? Math.max(slider.min, Math.min(slider.max, raw)) : slider.default];
    }));
    return { values, overlay: { ...catalog.overlay.defaults, ...preset.overlay, ...filter.overlay } };
}

function overlayFilter(overlay) {
    if (overlay.type === 'none' || overlay.opacity === 0) return '';
    const key = JSON.stringify(overlay);
    if (overlayDefinitions.has(key)) return overlayDefinitions.get(key);
    const id = `image-overlay-${overlayDefinitions.size}`;
    const color = value => /^#[0-9a-f]{6}$/i.test(value) ? value : '#000000';
    const clamp = (n, max = 1) => Math.max(0, Math.min(max, Number(n) || 0));
    const alpha1 = clamp(overlay.alpha1) * clamp(overlay.opacity);
    const alpha2 = clamp(overlay.alpha2) * clamp(overlay.opacity);
    const stop1 = clamp(overlay.stop1, 100), stop2 = Math.max(stop1, clamp(overlay.stop2, 100));
    const coords = { 'to bottom':[0,0,0,1], 'to top':[0,1,0,0], 'to right':[0,0,1,0], 'to left':[1,0,0,0], 'to bottom right':[0,0,1,1], 'to bottom left':[1,0,0,1], 'to top right':[0,1,1,0], 'to top left':[1,1,0,0] }[overlay.direction] || [0,0,0,1];
    // SVG implementations interpolate alpha differently from CSS gradients.
    // Sample premultiplied sRGB stops so the CSS preview follows the PNG renderer.
    const rgb = hex => [1,3,5].map(i => parseInt(color(hex).slice(i,i+2),16));
    const first = rgb(overlay.color1), second = rgb(overlay.color2);
    const stops = Array.from({length:33}, (_, i) => {
        const t = i/32, alpha = (1-t)*alpha1+t*alpha2;
        const channels = first.map((v,j) => Math.round(alpha ? ((1-t)*alpha1*v+t*alpha2*second[j])/alpha : 0));
        return `<stop offset="${stop1+(stop2-stop1)*t}%" stop-color="rgb(${channels.join(',')})" stop-opacity="${alpha}"/>`;
    }).join('');
    const gradient = overlay.type === 'radial' ? `<radialGradient id="g" r="70.710678%">${stops}</radialGradient>` : `<linearGradient id="g" x1="${coords[0]}" y1="${coords[1]}" x2="${coords[2]}" y2="${coords[3]}">${stops}</linearGradient>`;
    const art = `<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><defs>${gradient}</defs><rect width="100" height="100" fill="${overlay.type === 'solid' ? color(overlay.color1) : 'url(#g)'}" fill-opacity="${overlay.type === 'solid' ? alpha1 : 1}"/></svg>`;
    const blend = Object.hasOwn(catalog.overlay.blendModes, overlay.blend) && !['initial','inherit','unset'].includes(overlay.blend) ? overlay.blend : 'normal';
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width', '0'); svg.setAttribute('height', '0'); svg.setAttribute('aria-hidden', 'true'); svg.style.position = 'absolute';
    svg.innerHTML = `<filter id="${id}" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB"><feImage href="data:image/svg+xml,${encodeURIComponent(art)}" preserveAspectRatio="none" result="overlay"/><feBlend in="overlay" in2="SourceGraphic" mode="${blend}"/></filter>`;
    document.body.append(svg);
    const result = `url(#${id})`;
    overlayDefinitions.set(key, result);
    return result;
}

export function filterCss(stack = []) {
    const resolved = resolvedFilter(stack);
    if (!resolved) return 'none';
    // Match cssFilters: blend the overlay first, then filter the combined image.
    return [overlayFilter(resolved.overlay), ...catalog.operationOrder.map(key => {
        const slider = catalog.sliders.find(s => s.key === key);
        return `${key}(${resolved.values[key]}${slider.unit})`;
    })].filter(Boolean).join(' ');
}

export function mountFilterEditor(root, { stack, assets, onChange, onSave }) {
    let filters = structuredClone((stack || []).slice(0, 1));
    const selected = new Map();
    const esc = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
    let draft = new Map();
    root.innerHTML = `<header class="filter-page-header"><p class="eyebrow">Image styling</p><h2>Template image filters</h2><p>Choose one preset and adjust its filters and overlay for every enabled image. Uncheck “Apply template filters” on any image to skip it.</p></header><div class="filter-workspace"><div class="filter-controls"><div class="filter-actions filter-add"><label><select data-preset aria-label="Filter preset">${catalog.presets.map(p => `<option value="${esc(p.id)}">${esc(p.name)} — ${esc(p.description)}</option>`).join('')}</select></label></div><div data-stack></div><div class="filter-actions"><button type="button" class="button button-ghost" data-clear>Remove filter</button><button type="button" class="button button-primary" data-save>Save filters</button><span data-status role="status"></span></div><p class="field-hint">Instagram-inspired presets. Grain and lens effects are approximated with color adjustments.</p></div><section class="filter-preview" aria-label="Filter preview images"><div class="filter-preview-heading"><div><h3>Image preview</h3><p>Test your settings on up to 3 images.</p></div><button type="button" class="button button-secondary" data-choose>Choose images</button></div><div data-samples class="filter-samples"></div></section></div><dialog class="studio-dialog filter-asset-dialog" aria-labelledby="filter-asset-title"><div class="dialog-shell"><header><div><p class="eyebrow">Preview assets</p><h2 id="filter-asset-title">Choose up to 3 images</h2></div><button type="button" class="icon-button" data-cancel aria-label="Close image chooser">×</button></header><div class="filter-picker-tools"><input type="search" data-search placeholder="Search images…" aria-label="Search preview images"><span data-count role="status"></span></div><div data-choices class="filter-asset-grid"></div><footer><button type="button" class="button button-ghost" data-cancel>Cancel</button><button type="button" class="button button-primary" data-use>Use selected images</button></footer></div></dialog>`;
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
    const display = (slider, value) => `${Number((value * slider.displayScale).toFixed(1))}${slider.displayUnit}`;
    const range = (slider, value, attribute) => `<label>${esc(slider.name)} <output>${display(slider, value)}</output><input type="range" aria-label="${esc(slider.name)}" ${attribute}="${slider.key}" min="${slider.min}" max="${slider.max}" step="${slider.step}" value="${value}"></label>`;
    const options = (items, current) => Object.entries(items).map(([value,label]) => `<option value="${value}" ${value === current ? 'selected' : ''}>${esc(label)}</option>`).join('');
    const renderStack = () => {
        const filter = filters[0];
        root.querySelector('[data-preset]').value = filter?.id || '';
        if (!filter) { root.querySelector('[data-stack]').innerHTML = '<p>No filter applied. Choose a preset to begin.</p>'; return; }
        const preset = catalog.presets.find(p => p.id === filter.id);
        const resolved = resolvedFilter(filters);
        const overlay = resolved.overlay;
        root.querySelector('[data-stack]').innerHTML = `<fieldset class="filter-card"><legend>${esc(preset.name)}</legend><p class="filter-description">${esc(preset.description)}</p><div class="filter-section-heading"><h3>Filters</h3><button type="button" data-clear-values>Clear All</button></div><div class="filter-sliders">${catalog.sliders.map(slider => range(slider, resolved.values[slider.key], 'data-slider')).join('')}</div><div class="filter-section-heading"><h3>Overlay</h3></div><div class="overlay-controls"><label>Overlay type<select data-overlay="type">${options(catalog.overlay.types, overlay.type)}</select></label><div data-overlay-fields ${overlay.type === 'none' ? 'hidden' : ''}><div class="overlay-color-row"><label>Color 1<input type="color" data-overlay="color1" value="${esc(overlay.color1)}"></label><label>Color 2<input type="color" data-overlay="color2" value="${esc(overlay.color2)}" ${overlay.type === 'solid' ? 'disabled' : ''}></label></div><div class="filter-sliders">${catalog.overlay.sliders.filter(s => s.key !== 'opacity').map(slider => range(slider, overlay[slider.key], 'data-overlay-slider')).join('')}</div><label>Gradient Direction<select data-overlay="direction" ${overlay.type !== 'linear' ? 'disabled' : ''}>${options(catalog.overlay.directions, overlay.direction)}</select></label><label>Mix Blend Mode<select data-overlay="blend">${options(catalog.overlay.blendModes, overlay.blend)}</select></label><div class="filter-sliders">${range(catalog.overlay.sliders.find(s => s.key === 'opacity'), overlay.opacity, 'data-overlay-slider')}</div></div></div><div class="filter-actions"><button type="button" data-reset>Reset preset</button></div></fieldset>`;
        root.querySelectorAll('[data-slider], [data-overlay-slider]').forEach(input => input.oninput = () => {
            const isOverlay = input.hasAttribute('data-overlay-slider');
            const key = isOverlay ? input.dataset.overlaySlider : input.dataset.slider;
            const slider = (isOverlay ? catalog.overlay.sliders : catalog.sliders).find(s => s.key === key);
            filter[isOverlay ? 'overlay' : 'values'] ||= {};
            filter[isOverlay ? 'overlay' : 'values'][key] = Number(input.value);
            input.previousElementSibling.value = display(slider, Number(input.value));
            changed();
        });
        root.querySelectorAll('[data-overlay]').forEach(input => input.addEventListener(input.type === 'color' ? 'input' : 'change', () => {
            filter.overlay ||= {}; filter.overlay[input.dataset.overlay] = input.value;
            if (input.dataset.overlay === 'type') renderStack();
            changed();
        }));
        root.querySelector('[data-clear-values]').onclick = () => { filter.values = Object.fromEntries(catalog.sliders.map(s => [s.key,s.default])); renderStack(); changed(); };
        root.querySelector('[data-reset]').onclick = () => { filters = [{id:filter.id, values:{}, overlay:{}}]; renderStack(); changed(); };
    };
    root.querySelector('[data-preset]').insertAdjacentHTML('afterbegin', '<option value="">No filter</option>');
    root.querySelector('[data-preset]').onchange = event => { filters = event.target.value ? [{id:event.target.value, values:{}, overlay:{}}] : []; renderStack(); changed(); };
    root.querySelector('[data-clear]').onclick = () => { filters = []; renderStack(); changed(); };
    root.querySelector('[data-save]').onclick = async event => {
        event.currentTarget.disabled = true;
        try { if (await onSave() !== false) status.textContent = ''; }
        finally { root.querySelector('[data-save]').disabled = false; }
    };
    renderStack();
    refreshSamples();
}
