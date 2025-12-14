# Priority Printables Implementation

## Overview
Implemented two high-priority printable reports for the DTR system:

### 1. Employee Personal Monthly Timesheet
**File:** `print/employee_timesheet.php`
**Purpose:** Allow employees to print their own monthly attendance records

**Features:**
- Calendar format showing all days of selected month
- AM/PM clock times for each day
- Total daily hours calculation
- Monthly totals (total hours, days worked, late incidents)
- Status remarks for each entry
- Employee details (ID, name, department, position)
- Signature boxes for employee and supervisor
- Print-friendly layout with CSS

**Access Points:**
- Employee Dashboard: "Print My Monthly Timesheet" button (current month)
- Employee View Records: "Print Monthly Timesheet" button (filtered month)

**Security:** Employee-only access, can only print their own records

---

### 2. Admin Late Incidents Report
**File:** `print/late_incidents.php`
**Purpose:** Generate disciplinary documentation for late clock-ins

**Features:**
- Lists all late AM/PM clock-ins for selected date range
- Shows employee name, department, date, entry type (AM/PM)
- Displays actual clock time vs official time
- Calculates lateness duration
- Includes submitted reasons
- Summary statistics (total incidents, by employee)
- Filtered by date range and optional employee
- Print-friendly layout with professional formatting

**Access Points:**
- Admin Dashboard: "Late Incidents Report" quick button (current month)
- Admin Reports Page: "Late Incidents Report" button with custom filters

**Security:** Admin-only access

---

## Technical Details

### Employee Timesheet
```php
// URL Parameters
?year=2024&month=03  // Year and month to display

// Data Structure
- Loops through all days of month
- Maps time records by day number
- Calculates daily totals using getTotalHours()
- Uses getRecordMetrics() for status badges
- Shows late/overtime remarks inline
```

### Late Incidents Report
```php
// URL Parameters
?start_date=2024-03-01&end_date=2024-03-31&employee_id=123  // Optional employee filter

// Data Structure
- Queries time_record with employee joins
- Filters for LATE status entries only (AM and PM)
- Groups incidents by employee and date
- Shows rowspan for employees with multiple incidents
- Calculates summary by employee (sorted by incident count)
```

---

## UI Integration

### Employee Side
1. **Dashboard** (employee/dashboard.php)
   - Quick print button in Official Schedule card
   - Links to current month timesheet

2. **View Records** (employee/view_records.php)
   - Print button next to filter controls
   - Uses filtered month from date range

### Admin Side
1. **Dashboard** (admin/dashboard.php)
   - Quick Reports card with two buttons
   - Late Incidents: current month default
   - Full Reports Dashboard link

2. **Reports Page** (admin/reports.php)
   - Priority Reports card (red header)
   - Late Incidents button with dynamic date filters
   - Full Time Records Report link

---

## Print CSS
Uses `assets/css/print.css` for:
- Page breaks
- Print-friendly layouts
- Professional headers/footers
- Signature boxes
- Table formatting

---

## Benefits

### For Employees
✅ Self-service access to personal records
✅ Professional format for HR submissions
✅ Useful for loan applications, visa processing, personal records
✅ Shows attendance patterns and compliance

### For Admins/HR
✅ Quick disciplinary documentation
✅ Identifies chronic late patterns
✅ Professional format for HR files
✅ Filters by date range and employee
✅ Summary statistics for management reporting
✅ Supports progressive discipline process

---

## Future Enhancements (Optional)
- PDF export via wkhtmltopdf or similar
- Email printable to employee/HR
- Scheduled monthly reports
- Perfect attendance awards
- Custom date range for employee timesheet
