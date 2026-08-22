import './builder';
import './template-preview';
import './admin-template-assets';
import './admin-template-editor';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

window.apiFetch = async (url, options = {}) => {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) };
    if (!(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    const response = await fetch(url, { ...options, headers });
    const payload = response.headers.get('content-type')?.includes('json') ? await response.json() : null;
    if (!response.ok) {
        const firstError = payload?.errors ? Object.values(payload.errors).flat()[0] : null;
        const error = new Error(firstError || payload?.message || `Request failed (${response.status})`);
        error.status = response.status;
        error.payload = payload;
        throw error;
    }
    return payload;
};

window.showToast = (message) => {
    const region = document.getElementById('toast-region');
    if (!region) return;
    const toast = document.createElement('div'); toast.className = 'toast'; toast.textContent = message; region.append(toast);
    setTimeout(() => toast.remove(), 3800);
};

document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => document.getElementById('app-sidebar')?.classList.toggle('open'));
document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => { if (!confirm(form.dataset.confirm)) event.preventDefault(); }));

const lookupButton = document.getElementById('asin-lookup');
if (lookupButton && window.projectCreate) {
    lookupButton.addEventListener('click', async () => {
        const asin = document.getElementById('asin').value.trim().toUpperCase();
        const result = document.getElementById('asin-result');
        lookupButton.disabled = true; lookupButton.textContent = 'Looking up…'; result.innerHTML = '';
        try {
            const payload = await window.apiFetch(window.projectCreate.lookupUrl, { method: 'POST', body: JSON.stringify({ asin }) });
            const book = payload.data;
            document.getElementById('asin').value = book.asin;
            document.getElementById('product_snapshot').value = JSON.stringify(book);
            const name = document.querySelector('input[name="name"]'); if (name && !name.value) name.value = `${book.title} A+ page`;
            result.innerHTML = `<div class="asin-result-card">${book.image_url ? `<img src="${escapeHtml(book.image_url)}" alt="">` : ''}<div><strong>${escapeHtml(book.title)}</strong><small>${escapeHtml(book.asin)}${book.rating ? ` · ★ ${book.rating}` : ''}</small></div></div>`;
        } catch (error) { result.innerHTML = `<span class="field-error">${escapeHtml(error.message)}</span>`; }
        finally { lookupButton.disabled = false; lookupButton.textContent = 'Find book'; }
    });
}

const templateModuleToggles = document.querySelectorAll('[data-template-module-toggle]');
const syncTemplateModuleEditors = () => {
    const selected = [...templateModuleToggles].filter((input) => input.checked);
    const countLabel = document.getElementById('module-count');
    if (countLabel) countLabel.textContent = `${selected.length} selected`;
    templateModuleToggles.forEach((input) => {
        const editor = document.querySelector(`[data-template-module-content="${input.dataset.templateModuleToggle}"]`);
        if (!editor) return;
        editor.hidden = !input.checked;
        editor.querySelectorAll('input, textarea, select').forEach((control) => { control.disabled = !input.checked; });
    });
    selected.forEach((input, index) => {
        const number = document.querySelector(`[data-template-module-content="${input.dataset.templateModuleToggle}"] > header > div > span`);
        if (number) number.textContent = String(index + 1).padStart(2, '0');
    });
};
templateModuleToggles.forEach((input) => input.addEventListener('change', syncTemplateModuleEditors));

document.querySelectorAll('[data-template-repeater]').forEach((repeater) => {
    const rows = repeater.querySelector('[data-template-rows]');
    const template = repeater.querySelector('[data-template-row-template]');
    const add = repeater.querySelector('[data-template-add-row]');
    let nextIndex = rows.children.length;
    const refresh = () => {
        const rowElements = [...rows.querySelectorAll(':scope > [data-template-row]')];
        rowElements.forEach((row, index) => {
            const number = row.querySelector('.admin-template-row-number'); if (number) number.textContent = index + 1;
            const remove = row.querySelector('[data-template-remove-row]'); if (remove) remove.disabled = rowElements.length <= Number(repeater.dataset.min);
        });
        add.disabled = rowElements.length >= Number(repeater.dataset.max);
    };
    add.addEventListener('click', () => {
        if (rows.children.length >= Number(repeater.dataset.max)) return;
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        refresh();
    });
    rows.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-template-remove-row]');
        if (!remove || rows.children.length <= Number(repeater.dataset.min)) return;
        remove.closest('[data-template-row]').remove(); refresh();
    });
    refresh();
});

if (templateModuleToggles.length) syncTemplateModuleEditors();

document.getElementById('ai-model-filter')?.addEventListener('input', (event) => {
    const query = event.target.value.trim().toLowerCase();
    document.querySelectorAll('#ai-model-table tbody tr').forEach((row) => {
        row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
    });
});

function escapeHtml(value = '') {
    return String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
}
window.escapeHtml = escapeHtml;
