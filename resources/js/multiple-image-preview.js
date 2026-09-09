const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[character]));

export function renderMultipleImagePreview(items, copy, image) {
    const slides = items.filter(item => item.image);
    return `<section class="amazon-module amazon-multiple"><div class="multiple-main" aria-live="polite">${slides.map((item, index) => `<figure data-multiple-slide ${index ? 'hidden' : ''}>${image(item)}<figcaption>${escapeHtml(item.caption)}</figcaption></figure>`).join('') || '<span class="amazon-missing-image"></span>'}</div><div class="multiple-copy">${copy}</div><div class="multiple-thumbs" aria-label="Choose an image">${slides.map((item, index) => `<button type="button" data-multiple-thumbnail="${index}" aria-label="${escapeHtml(item.caption || `Show image ${index + 1}`)}" aria-pressed="${index === 0}">${image(item)}<span>${escapeHtml(item.caption)}</span></button>`).join('')}</div></section>`;
}

// Delegation also covers previews rebuilt after autosave and public template previews.
document.addEventListener('click', event => {
    const button = event.target.closest('[data-multiple-thumbnail]');
    if (!button) return;
    const module = button.closest('.amazon-multiple');
    module.querySelectorAll('[data-multiple-slide]').forEach((slide, index) => { slide.hidden = index !== Number(button.dataset.multipleThumbnail); });
    module.querySelectorAll('[data-multiple-thumbnail]').forEach(thumb => thumb.setAttribute('aria-pressed', String(thumb === button)));
});
