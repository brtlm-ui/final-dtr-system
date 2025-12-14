<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Ensure timezone is set for date() defaults
require_once '../config/timezone.php';

requireAdminLogin();

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Normalize date inputs: accept YYYY-MM-DD or DD/MM/YYYY from legacy UI and convert to YYYY-MM-DD
function normalizeDateInput($d) {
    $d = trim((string)$d);
    if (!$d) return '';
    // If already YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
    // If DD/MM/YYYY
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $d, $m)) {
        $dd = intval($m[1]); $mm = intval($m[2]); $yy = intval($m[3]);
        if (checkdate($mm, $dd, $yy)) return sprintf('%04d-%02d-%02d', $yy, $mm, $dd);
    }
    // Try strtotime fallback
    $ts = strtotime($d);
    if ($ts !== false) return date('Y-m-d', $ts);
    return '';
}

$normStart = normalizeDateInput($startDate);
$normEnd = normalizeDateInput($endDate);
if ($normStart) $startDate = $normStart;
if ($normEnd) $endDate = $normEnd;
$employeeId = $_GET['employee_id'] ?? '';

$empStmt = $conn->query("SELECT employee_id, CONCAT(first_name,' ',last_name) as name FROM employee WHERE is_active = 1 ORDER BY first_name");
$employees = $empStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reports - Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<?php require_once '../includes/header.php'; ?>

<?php require_once '../includes/sidebar.php'; ?>
<div class="container-fluid mt-4">
    <div class="main-content">
    <div class="d-flex justify-content-between mb-3">
        <h3>Reports</h3>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form id="reportFilters" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="report_start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" id="report_end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Employee</label>
                    <select name="employee_id" id="report_employee_id" class="form-select">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>" <?php echo $employeeId == $emp['employee_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="previewBtn" class="btn btn-primary">Preview</button>
                    <button type="button" id="exportBtn" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">Export</button>
                </div>
            </form>
        </div>
    </div>

    <div id="previewArea">
        <div class="alert alert-info">Click Preview to load a sample of the report (first 200 rows).</div>
    </div>
</div>

<script>
document.getElementById('previewBtn').addEventListener('click', function() {
    var form = document.getElementById('reportFilters');
    // read and normalize date inputs client-side to ensure YYYY-MM-DD
    var startInput = form.querySelector('input[name="start_date"]');
    var endInput = form.querySelector('input[name="end_date"]');
    function toISO(d) {
        if (!d) return '';
        // If already in YYYY-MM-DD
        if (/^\d{4}-\d{2}-\d{2}$/.test(d)) return d;
        // If DD/MM/YYYY
        var m = d.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (m) return m[3] + '-' + m[2] + '-' + m[1];
        // try Date parse
        var dt = new Date(d);
        if (!isNaN(dt)) {
            var yy = dt.getFullYear();
            var mm = String(dt.getMonth()+1).padStart(2,'0');
            var dd = String(dt.getDate()).padStart(2,'0');
            return yy + '-' + mm + '-' + dd;
        }
        return '';
    }

    var params = new URLSearchParams();
    var startVal = toISO(startInput.value || '');
    var endVal = toISO(endInput.value || '');
    if (!startVal) startVal = new Date().toISOString().slice(0,10);
    if (!endVal) endVal = new Date().toISOString().slice(0,10);
    params.append('start_date', startVal);
    params.append('end_date', endVal);
    params.append('employee_id', form.querySelector('select[name="employee_id"]').value || '');
    params.append('action','preview_daily');

    fetch('../api/reports.php?' + params.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function(r) {
            if (r.status === 401) throw new Error('Unauthorized (not logged in as admin)');
            return r.json();
        })
        .then(json => {
            console.log('Preview response', json);
            var area = document.getElementById('previewArea');
            if (!json.success) {
                area.innerHTML = '<div class="alert alert-danger">' + (json.message || 'Error') + '</div>';
                return;
            }
            var rows = json.rows;
            if (!rows || rows.length === 0) {
                area.innerHTML = '<div class="alert alert-info">No records found for the selected filters.</div>';
                return;
            }

            var html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr>' +
                '<th>Date</th><th>Employee</th>' +
                '<th>AM In</th><th>AM Out</th><th>PM In</th><th>PM Out</th>' +
                '<th>Actions</th></tr></thead><tbody>';
            rows.forEach(function(r){
                html += '<tr>' +
                        '<td>' + (r.record_date||'') + '</td>' +
                        '<td>' + (r.first_name+' '+r.last_name) + '</td>' +
                        '<td>' + (r.am_in||'') + '</td>' +
                        '<td>' + (r.am_out||'') + '</td>' +
                        '<td>' + (r.pm_in||'') + '</td>' +
                        '<td>' + (r.pm_out||'') + '</td>' +
                        '<td><a class="btn btn-sm btn-outline-secondary" href="../print/record.php?id=' + r.record_id + '" target="_blank">Print</a></td>' +
                    '</tr>';
            });
            html += '</tbody></table></div>';
            area.innerHTML = html;
        })
        .catch(err => {
            document.getElementById('previewArea').innerHTML = '<div class="alert alert-danger">'+err+'</div>';
        });
});
</script>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Choose export format</label>
                    <div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exportFormat" id="exportCsv" value="csv" checked>
                            <label class="form-check-label" for="exportCsv">
                                <strong>CSV Export</strong> - Excel compatible time records
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exportFormat" id="exportPdf" value="pdf">
                            <label class="form-check-label" for="exportPdf">
                                <strong>Full Time Records</strong> - Print preview of all records
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exportFormat" id="exportLateIncidents" value="late_incidents">
                            <label class="form-check-label" for="exportLateIncidents">
                                <strong class="text-danger">Late Incidents Report</strong> - Disciplinary documentation
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-text text-muted">All options will use the date range and employee filter selected above.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmExport" class="btn btn-primary">Export</button>
            </div>
        </div>
    </div>
</div>

<script>
// Handle modal export confirm
document.getElementById('confirmExport').addEventListener('click', function(){
        var format = document.querySelector('input[name="exportFormat"]:checked').value;
        var form = document.getElementById('reportFilters');
        var data = new FormData(form);
        var params = new URLSearchParams();
        for (const pair of data.entries()) params.append(pair[0], pair[1]);

        if (format === 'csv') {
                params.append('action','export_daily');
                var url = '../api/reports.php?' + params.toString();
                window.open(url, '_blank');
        } else if (format === 'pdf') {
                // open print-friendly page that will call window.print()
                var url = '../print/reports_print.php?' + params.toString();
                window.open(url, '_blank');
        } else if (format === 'late_incidents') {
                // open late incidents report
                var url = '../print/late_incidents.php?' + params.toString();
                window.open(url, '_blank');
        }

        // close modal
        var modalEl = document.getElementById('exportModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
});
</script>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>