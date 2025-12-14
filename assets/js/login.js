// Employee Login Form
document.getElementById('employeeLoginForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const errorDiv = document.getElementById('employee-error');
    const submitBtn = e.target.querySelector('button[type="submit"]');
    
    // Get form data
    const formData = {
        employee_id: document.getElementById('employee_id').value,
        pin: document.getElementById('pin').value
    };
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Logging in...';
    
    try {
        const response = await fetch('../api/employee_login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData),
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            errorDiv.classList.add('d-none');
            window.location.assign(data.redirect);
        } else {
            errorDiv.textContent = data.message;
            errorDiv.classList.remove('d-none');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Login';
        }
    } catch (error) {
        errorDiv.textContent = 'An error occurred. Please try again.';
        errorDiv.classList.remove('d-none');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Login';
    }
});

// (Admin login now uses normal POST, no JS handler)

// Clear error messages when switching tabs
document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function() {
        document.getElementById('employee-error')?.classList.add('d-none');
        document.getElementById('admin-error')?.classList.add('d-none');
    });
});

// Toggle admin password visibility
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleAdminPassword');
    const passwordInput = document.getElementById('password');
    if (!toggleBtn || !passwordInput) return;

    const eyeSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">\n' +
                   '  <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/>\n' +
                   '  <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z" fill="#fff"/>\n' +
                   '</svg>';

    const eyeSlashSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">\n' +
                        '  <path d="M13.359 11.238C14.214 10.45 15 9.33 15 8c0-.275-.03-.543-.087-.803l.987-.987-.708-.708-1.03 1.03A7.478 7.478 0 0 0 8 2.5 7.478 7.478 0 0 0 2.838 4.435L1.646 3.243l-.708.708.987.987C1.03 5.457 1 5.725 1 6c0 1.33.786 2.45 1.641 3.238A7.478 7.478 0 0 0 8 13.5c1.774 0 3.414-.57 4.859-1.562l.5.5.708-.708-.708-.5z"/>\n' +
                        '  <path d="M4.646 4.646 11.354 11.354 10.646 12.06 3.938 5.352 4.646 4.646z"/>\n' +
                        '</svg>';

    // Initialize with eye icon
    toggleBtn.innerHTML = eyeSvg;

    toggleBtn.addEventListener('click', function() {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.innerHTML = eyeSlashSvg;
            toggleBtn.setAttribute('aria-label', 'Hide password');
        } else {
            passwordInput.type = 'password';
            toggleBtn.innerHTML = eyeSvg;
            toggleBtn.setAttribute('aria-label', 'Show password');
        }
    });
});