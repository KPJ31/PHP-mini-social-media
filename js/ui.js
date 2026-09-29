document.documentElement.classList.add('js');
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('site-navigation');
    const toggle = document.querySelector('.menu-toggle');
    const backdrop = document.querySelector('.nav-backdrop');
    const mobile = window.matchMedia('(max-width: 991px)');
    function closeMenu(restore = true) {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        document.body.classList.remove('menu-open');
        toggle?.setAttribute('aria-expanded', 'false');
        if (backdrop) backdrop.hidden = true;
        if (restore && mobile.matches) toggle?.focus();
    }
    toggle?.addEventListener('click', () => {
        sidebar.classList.add('open');
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        document.body.classList.add('menu-open');
        toggle.setAttribute('aria-expanded', 'true');
        backdrop.hidden = false;
        sidebar.querySelector('[data-close-menu]').focus();
    });
    document.querySelectorAll('[data-close-menu]').forEach(button => button.addEventListener('click', () => closeMenu()));
    document.addEventListener('keydown', event => {
        if (!sidebar?.classList.contains('open')) return;
        if (event.key === 'Escape') closeMenu();
        if (event.key === 'Tab') {
            const items = [...sidebar.querySelectorAll('a[href], button')].filter(item => item.getClientRects().length);
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    mobile.addEventListener('change', () => closeMenu(false));
    document.querySelectorAll('[data-password-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const field = document.getElementById(button.dataset.passwordToggle);
            const show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.innerHTML = '<i class="bx ' + (show ? 'bx-hide' : 'bx-show') + '" aria-hidden="true"></i>';
        });
    });
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        let objectUrl;
        input.addEventListener('change', () => {
            const preview = document.getElementById(input.dataset.preview);
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            preview.hidden = true;
            const file = input.files[0];
            if (file && ['image/jpeg', 'image/png', 'image/gif'].includes(file.type) && file.size <= 2 * 1024 * 1024) {
                objectUrl = URL.createObjectURL(file);
                preview.src = objectUrl;
                preview.hidden = false;
            }
        });
    });
    document.querySelectorAll('form[data-loading-form]').forEach(form => {
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            const button = form.querySelector('button[type="submit"]');
            if (!button) return;
            button.disabled = true;
            button.textContent = 'Please wait?';
        });
    });
});
