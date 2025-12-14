// Delete Record Confirmation
function deleteRecord(recordId) {
    if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
        return;
    }
    
    fetch('../api/delete_record.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ record_id: recordId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('An error occurred. Please try again.');
    });
}

// Add Employee Form
document.getElementById('addEmployeeForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adding...';
    
    try {
        const response = await fetch('../api/add_employee.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            document.getElementById('addEmployeeForm').reset(); // ✅ clear fields
            location.reload(); // ✅ optional: keep this if you want the table to refresh
        } else {
            alert('Error: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Add Employee';
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Add Employee';
    }
});

// Deactivate Employee
function deactivateEmployee(employeeId) {
    if (!confirm('Are you sure you want to deactivate this employee?')) {
        return;
    }
    
    fetch('../api/deactivate_employee.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ employee_id: employeeId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('An error occurred. Please try again.');
    });
}

// Generate PIN
function generatePIN() {
    const pin = Math.floor(100000 + Math.random() * 900000).toString();
    document.getElementById('pin').value = pin;
}
