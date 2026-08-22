const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
}[character]));
const previewImage = (source, className = '') => source
    ? `<img class="${escapeHtml(className)}" src="${escapeHtml(source)}" alt="">`
    : '<span class="amazon-missing-image"></span>';
const previewRich = (value) => value ? `<div class="amazon-rich">${value}</div>` : '';
const previewHeading = (value, level = 2) => value ? `<h${level}>${escapeHtml(value)}</h${level}>` : '';

export function renderTemplateModule(module) {
    const content = module.content || {};
    const items = content.items || [];
    const sections = content.sections || [];
    const bullets = content.bullets || [];

    switch (module.module_type) {
        case 'company_logo':
            return `<section class="amazon-module amazon-logo">${previewImage(content.image)}</section>`;
        case 'comparison_chart': {
            const products = content.products || [];
            const metrics = content.metrics || [];
            return `<section class="amazon-module amazon-comparison"><div class="amazon-compare-grid" style="--compare-count:${Math.max(products.length, 1)}"><span></span>${products.map((product) => `<article class="${product.highlighted ? 'highlighted' : ''}">${previewImage(product.image)}<strong>${escapeHtml(product.title || '')}</strong>${content.show_reviews ? '<span class="amazon-stars">★★★★★</span>' : ''}${content.show_prices ? '<small>Available on Amazon</small>' : ''}${content.show_add_to_cart ? '<button type="button">Shop now</button>' : ''}</article>`).join('')}${metrics.map((metric) => { const values = String(metric.values || '').split('|'); return `<strong class="metric-label">${escapeHtml(metric.label || '')}</strong>${products.map((_, index) => `<span class="metric-value">${escapeHtml(values[index] || '—')}</span>`).join('')}`; }).join('')}</div></section>`;
        }
        case 'four_image_text':
            return `<section class="amazon-module amazon-feature-columns">${previewHeading(content.headline)}<div class="columns four">${items.map((item) => `<article>${previewImage(item.image)}${previewHeading(item.headline, 3)}${previewRich(item.body_html)}</article>`).join('')}</div></section>`;
        case 'four_image_quadrant':
            return `<section class="amazon-module amazon-quadrants">${items.map((item) => `<article>${previewImage(item.image)}<div>${previewHeading(item.headline, 3)}${previewRich(item.body_html)}</div></article>`).join('')}</section>`;
        case 'dark_text_overlay':
        case 'light_text_overlay':
            return `<section class="amazon-module amazon-overlay ${module.module_type === 'dark_text_overlay' ? 'dark-copy' : 'light-copy'}">${previewImage(content.background)}<div class="overlay-copy">${previewHeading(content.headline)}${previewRich(content.body_html)}</div></section>`;
        case 'image_header_text':
            return `<section class="amazon-module amazon-header-image">${previewHeading(content.top_headline)}${previewImage(content.image)}<div>${previewHeading(content.headline)}${previewRich(content.body_html)}</div></section>`;
        case 'multiple_image_a':
            return `<section class="amazon-module amazon-multiple"><div class="multiple-main">${previewImage(items[0]?.image)}</div><div class="multiple-copy">${previewHeading(content.headline)}${previewRich(content.description_html)}<div class="multiple-thumbs">${items.map((item) => `<figure>${previewImage(item.image)}<figcaption>${escapeHtml(item.caption || '')}</figcaption></figure>`).join('')}</div></div></section>`;
        case 'product_description_text':
            return `<section class="amazon-module amazon-product-description">${previewRich(content.body_html)}</section>`;
        case 'single_image_highlights':
            return `<section class="amazon-module amazon-highlights">${previewImage(content.image)}<div class="highlight-copy">${sections.map((section) => `<article>${previewHeading(section.subheadline, 3)}${previewRich(section.body_html)}</article>`).join('')}</div><aside>${previewHeading(content.highlights_headline, 3)}<ul>${bullets.map((bullet) => `<li>${escapeHtml(bullet.text || '')}</li>`).join('')}</ul></aside></section>`;
        case 'single_image_sidebar':
            return `<section class="amazon-module amazon-sidebar"><figure>${previewImage(content.primary_image)}${content.image_caption ? `<figcaption>${escapeHtml(content.image_caption)}</figcaption>` : ''}</figure><div>${previewHeading(content.headline)}${previewHeading(content.subheadline, 3)}${previewRich(content.body_html)}${bullets.length ? `<ul>${bullets.map((bullet) => `<li>${escapeHtml(bullet.text || '')}</li>`).join('')}</ul>` : ''}</div><aside>${previewImage(content.sidebar_image)}${previewHeading(content.sidebar_headline, 3)}${previewRich(content.sidebar_body_html)}</aside></section>`;
        case 'single_image_specs_detail':
            return `<section class="amazon-module amazon-spec-detail">${previewHeading(content.headline)}<div class="spec-detail-grid">${previewImage(content.image)}${sections.map((section) => `<article>${previewHeading(section.headline, 3)}${previewHeading(section.subheadline, 4)}${previewRich(section.body_html)}</article>`).join('')}</div></section>`;
        case 'single_left_image':
            return `<section class="amazon-module amazon-single-image left">${previewImage(content.image)}<div>${previewHeading(content.headline)}${previewRich(content.body_html)}</div></section>`;
        case 'single_right_image':
            return `<section class="amazon-module amazon-single-image right"><div>${previewHeading(content.headline)}${previewRich(content.body_html)}</div>${previewImage(content.image)}</section>`;
        case 'technical_specifications':
            return `<section class="amazon-module amazon-tech-specs">${previewHeading(content.headline)}<div class="spec-rows columns-${escapeHtml(content.columns || '1')}">${(content.specifications || []).map((specification) => `<div><strong>${escapeHtml(specification.specification || '')}</strong><span>${escapeHtml(specification.definition || '')}</span></div>`).join('')}</div></section>`;
        case 'standard_text':
            return `<section class="amazon-module amazon-standard-text">${previewHeading(content.headline)}${previewRich(content.body_html)}</section>`;
        case 'three_images_text':
            return `<section class="amazon-module amazon-feature-columns">${previewHeading(content.headline)}<div class="columns three">${items.map((item) => `<article>${previewImage(item.image)}${previewHeading(item.headline, 3)}${previewRich(item.body_html)}</article>`).join('')}</div></section>`;
        default:
            return '';
    }
}

export function renderTemplateModules(modules) {
    return modules.length
        ? `<main class="amazon-preview">${modules.map(renderTemplateModule).join('')}</main>`
        : '<div class="builder-empty"><h2>Select at least one module to preview the template</h2></div>';
}

const dataElement = document.getElementById('template-preview-data');
const previewElement = document.getElementById('template-preview-content');
const frameElement = document.getElementById('template-preview-frame');

if (dataElement && previewElement && frameElement) {
    previewElement.innerHTML = renderTemplateModules(JSON.parse(dataElement.textContent || '[]'));
    document.querySelectorAll('[data-template-preview-width]').forEach((button) => {
        button.addEventListener('click', () => {
            const isMobile = button.dataset.templatePreviewWidth === 'mobile';
            document.querySelectorAll('[data-template-preview-width]').forEach((item) => {
                const isActive = item === button; item.classList.toggle('active', isActive); item.setAttribute('aria-pressed', String(isActive));
            });
            frameElement.classList.toggle('mobile', isMobile);
        });
    });
}
