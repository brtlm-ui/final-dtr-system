<?php
function validateEmployeeForm($post, $conn) {
    $fieldErrors = [];
    $hasErrors = false;

    // Validate Employee ID
    if (empty($post['employee_id'])) {
        $fieldErrors['employee_id'] = 'Employee ID is required';
        $hasErrors = true;
    } elseif (!preg_match('/^\d{4}$/', $post['employee_id'])) {
        $fieldErrors['employee_id'] = 'Employee ID must be exactly 4 digits';
        $hasErrors = true;
    } else {
        // Check if employee ID exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM employee WHERE employee_id = ?");
        $stmt->execute([$post['employee_id']]);
        if ($stmt->fetchColumn() > 0) {
            $fieldErrors['employee_id'] = 'This Employee ID is already in use';
            $hasErrors = true;
        }
    }

    // Validate PIN
    if (empty($post['pin'])) {
        $fieldErrors['pin'] = 'PIN is required';
        $hasErrors = true;
    } elseif (!preg_match('/^\d{6}$/', $post['pin'])) {
        $fieldErrors['pin'] = 'PIN must be exactly 6 digits';
        $hasErrors = true;
    }

    // Validate First Name
    if (empty($post['first_name'])) {
        $fieldErrors['first_name'] = 'First Name is required';
        $hasErrors = true;
    } elseif (!preg_match('/^[a-zA-Z\s]{2,50}$/', $post['first_name'])) {
        $fieldErrors['first_name'] = 'First Name must contain only letters (2-50 characters)';
        $hasErrors = true;
    }

    // Validate Last Name
    if (empty($post['last_name'])) {
        $fieldErrors['last_name'] = 'Last Name is required';
        $hasErrors = true;
    } elseif (!preg_match('/^[a-zA-Z\s]{2,50}$/', $post['last_name'])) {
        $fieldErrors['last_name'] = 'Last Name must contain only letters (2-50 characters)';
        $hasErrors = true;
    }

    // Validate Email
    if (empty($post['email'])) {
        $fieldErrors['email'] = 'Email is required';
        $hasErrors = true;
    } elseif (!filter_var($post['email'], FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Please enter a valid email address';
        $hasErrors = true;
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM employee WHERE email = ?");
        $stmt->execute([$post['email']]);
        if ($stmt->fetchColumn() > 0) {
            $fieldErrors['email'] = 'This email is already registered';
            $hasErrors = true;
        }
    }

    // Validate Position
    if (empty($post['position'])) {
        $fieldErrors['position'] = 'Please select a position';
        $hasErrors = true;
    }

    // Validate Department
    if (empty($post['department'])) {
        $fieldErrors['department'] = 'Department is required';
        $hasErrors = true;
    }

    return ['hasErrors' => $hasErrors, 'fieldErrors' => $fieldErrors];
}
?>