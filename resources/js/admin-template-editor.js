import { renderTemplateModules } from './template-preview';

const adminTemplateForm = document.querySelector('.admin-template-layout');

if (adminTemplateForm) {
    const previewDialog = document.getElementById('admin-template-preview-dialog');
    const previewFrame = document.getElementById('admin-template-preview-frame');
    const previewContent = document.getElementById('admin-template-preview-content');

    const sanitizeRichHtml = (html) => {
        const template = document.createElement('template'); template.innerHTML = html || '';
        const allowed = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI']);
        [...template.content.querySelectorAll('*')].reverse().forEach((element) => {
            if (!allowed.has(element.tagName)) element.replaceWith(...element.childNodes);
            else [...element.attributes].forEach((attribute) => element.removeAttribute(attribute.name));
        });
        return template.innerHTML;
    };

    adminTemplateForm.addEventListener('input', (event) => {
        const content = event.target.closest('[data-admin-rich-content]');
        if (content) content.closest('[data-admin-rich-editor]').querySelector('[data-admin-rich-source]').value = content.innerHTML;
    });
    adminTemplateForm.addEventListener('mousedown', (event) => {
        if (event.target.closest('[data-admin-rich-command]')) event.preventDefault();
    });
    adminTemplateForm.addEventListener('click', (event) => {
        const command = event.target.closest('[data-admin-rich-command]');
        if (!command) return;
        const editor = command.closest('[data-admin-rich-editor]'); const content = editor.querySelector('[data-admin-rich-content]');
        content.focus(); document.execCommand(command.dataset.adminRichCommand);
        editor.querySelector('[data-admin-rich-source]').value = content.innerHTML;
    });

    const assignValue = (root, tokens, value) => {
        let cursor = root;
        tokens.forEach((token, index) => {
            const last = index === tokens.length - 1;
            if (last) { cursor[token] = token.endsWith('_html') ? sanitizeRichHtml(value) : value; return; }
            const nextIsIndex = /^\d+$/.test(tokens[index + 1]);
            if (cursor[token] === undefined) cursor[token] = nextIsIndex ? [] : {};
            cursor = cursor[token];
        });
    };
    const compactArrays = (value) => {
        if (Array.isArray(value)) return value.filter((item) => item !== undefined).map(compactArrays);
        if (value && typeof value === 'object') Object.keys(value).forEach((key) => { value[key] = compactArrays(value[key]); });
        return value;
    };
    const modulesFromForm = () => {
        const contentByType = {}; const checkboxNames = new Set([...adminTemplateForm.querySelectorAll('input[type="checkbox"]')].map((input) => input.name));
        for (const [name, rawValue] of new FormData(adminTemplateForm).entries()) {
            if (!name.startsWith('module_content[')) continue;
            const tokens = [...name.matchAll(/\[([^\]]+)\]/g)].map((match) => match[1]);
            assignValue(contentByType, tokens, checkboxNames.has(name) ? rawValue === '1' : rawValue);
        }
        compactArrays(contentByType);
        return [...adminTemplateForm.querySelectorAll('[data-template-module-toggle]:checked')].map((input, index) => ({ module_type: input.value, position: index + 1, content: contentByType[input.value] || {}, settings: {} }));
    };

    document.getElementById('admin-template-preview-open').addEventListener('click', () => {
        previewContent.innerHTML = renderTemplateModules(modulesFromForm()); previewFrame.classList.remove('mobile');
        document.querySelectorAll('[data-admin-preview-width]').forEach((button) => { const active = button.dataset.adminPreviewWidth === 'desktop'; button.classList.toggle('active', active); button.setAttribute('aria-pressed', String(active)); });
        previewDialog.showModal();
    });
    document.querySelectorAll('[data-admin-preview-close]').forEach((button) => button.addEventListener('click', () => previewDialog.close()));
    document.querySelectorAll('[data-admin-preview-width]').forEach((button) => button.addEventListener('click', () => {
        document.querySelectorAll('[data-admin-preview-width]').forEach((item) => { const active = item === button; item.classList.toggle('active', active); item.setAttribute('aria-pressed', String(active)); });
        previewFrame.classList.toggle('mobile', button.dataset.adminPreviewWidth === 'mobile');
    }));
}
