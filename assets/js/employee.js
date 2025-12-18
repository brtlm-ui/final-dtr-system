// Confirm and Clock In
// NOTE: employee/confirm.php implements its own submit handler (with the mandatory reason modal).
// Avoid double-submitting clock-in requests by only attaching this handler when the modal is absent.
const __confirmForm = document.getElementById('confirmForm');
if (__confirmForm && !document.getElementById('clockReasonModal')) __confirmForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const errorDiv = document.getElementById('confirm-error');
    const successDiv = document.getElementById('confirm-success');
    const confirmBtn = document.getElementById('confirmBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    
    // Disable button and show spinner
    confirmBtn.disabled = true;
    btnText.classList.add('d-none');
    btnSpinner.classList.remove('d-none');
    
    try {
        const response = await fetch('../api/clock_in.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            errorDiv.classList.add('d-none');
            successDiv.textContent = data.message;
            successDiv.classList.remove('d-none');
            
            // Redirect to dashboard after 1 second
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            errorDiv.textContent = data.message;
            errorDiv.classList.remove('d-none');
            successDiv.classList.add('d-none');
            
            // Re-enable button
            confirmBtn.disabled = false;
            btnText.classList.remove('d-none');
            btnSpinner.classList.add('d-none');
        }
    } catch (error) {
        errorDiv.textContent = 'An error occurred. Please try again.';
        errorDiv.classList.remove('d-none');
        successDiv.classList.add('d-none');
        
        // Re-enable button
        confirmBtn.disabled = false;
        btnText.classList.remove('d-none');
        btnSpinner.classList.add('d-none');
    }
});