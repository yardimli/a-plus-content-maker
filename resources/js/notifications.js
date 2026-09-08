const region = document.getElementById('toast-region');

function enableDismissal(toast) {
    let timer;
    const dismiss = () => { clearTimeout(timer); toast.remove(); };
    const pause = () => clearTimeout(timer);
    const resume = () => {
        pause();
        if (!toast.matches(':hover') && !toast.contains(document.activeElement)) {
            timer = setTimeout(dismiss, 6000);
        }
    };
    toast.querySelector('.toast-close').addEventListener('click', dismiss);
    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', resume);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', resume);
    resume();
}

window.showToast = (message, type = 'info') => {
    if (!region) return;
    const variant = ['success', 'error'].includes(type) ? type : 'info';
    const toast = document.createElement('div');
    toast.className = `toast toast-${variant}`;
    toast.setAttribute('role', variant === 'error' ? 'alert' : 'status');
    const copy = document.createElement('div');
    copy.className = 'toast-copy';
    const title = document.createElement('strong');
    title.textContent = { success: 'Success', error: 'Something went wrong', info: 'Notification' }[variant];
    const text = document.createElement('span');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast-close';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.textContent = '×';
    copy.append(title, text);
    toast.append(copy, close);
    region.append(toast);
    enableDismissal(toast);
};

region?.querySelectorAll('[data-toast]').forEach(enableDismissal);
