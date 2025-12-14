// Simple toast helper using Bootstrap to show short messages
function showToast(message, type = 'info', timeout = 4000) {
    // create container if not exists
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.position = 'fixed';
        container.style.top = '1rem';
        container.style.right = '1rem';
        container.style.zIndex = 1080;
        document.body.appendChild(container);
    }

    const colors = {
        'info': 'bg-primary text-white',
        'success': 'bg-success text-white',
        'error': 'bg-danger text-white',
        'warning': 'bg-warning text-dark'
    };

    const toast = document.createElement('div');
    toast.className = 'toast align-items-center ' + (colors[type] || colors['info']);
    toast.role = 'alert';
    toast.ariaLive = 'assertive';
    toast.ariaAtomic = 'true';
    toast.style.minWidth = '200px';
    toast.style.marginBottom = '0.5rem';

    const toastBody = document.createElement('div');
    toastBody.className = 'd-flex';
    toastBody.innerHTML = `<div class="toast-body">${message}</div>`;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-close btn-close-white me-2 m-auto';
    btn.setAttribute('data-bs-dismiss', 'toast');
    btn.ariaLabel = 'Close';
    btn.addEventListener('click', () => { bsToast.hide(); });

    toastBody.appendChild(btn);
    toast.appendChild(toastBody);
    container.appendChild(toast);

    const bsToast = new bootstrap.Toast(toast, { delay: timeout });
    bsToast.show();

    // remove after hidden
    toast.addEventListener('hidden.bs.toast', () => { toast.remove(); });
}
