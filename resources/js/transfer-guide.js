document.querySelector('.transfer-guide')?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;
    const content = button.closest('.transfer-copy-block').querySelector('[data-copy-content]');
    const text = content.innerText;
    try {
        if (content.hasAttribute('data-copy-rich') && navigator.clipboard?.write && window.ClipboardItem) {
            try {
                await navigator.clipboard.write([new ClipboardItem({
                    'text/html': new Blob([content.innerHTML], { type: 'text/html' }),
                    'text/plain': new Blob([text], { type: 'text/plain' }),
                })]);
            } catch { await navigator.clipboard.writeText(text); }
        } else if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const input = document.createElement('textarea');
            input.value = text;
            input.style.cssText = 'position:fixed;opacity:0';
            document.body.append(input);
            input.select();
            const copied = document.execCommand('copy');
            input.remove();
            button.focus();
            if (!copied) throw new Error('Clipboard unavailable');
        }
        window.showToast('Copied. Paste into the matching field in KDP.', 'success');
    } catch {
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(content);
        selection.removeAllRanges();
        selection.addRange(range);
        window.showToast('Text selected. Press Ctrl+C (or ⌘C) to copy, then paste into KDP.');
    }
});
