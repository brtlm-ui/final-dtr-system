-- SQL Views for KPI and Report Optimizations
-- Run this script in your MySQL client to create views for fast aggregations

-- View: Daily hours per employee
CREATE VIEW IF NOT EXISTS v_employee_daily_hours AS
SELECT 
    employee_id,
    DATE(work_date) AS work_date,
    SUM(COALESCE(TIMESTAMPDIFF(MINUTE, clock_in_am, clock_out_am), 0) +
        COALESCE(TIMESTAMPDIFF(MINUTE, clock_in_pm, clock_out_pm), 0)) / 60 AS total_hours,
    SUM(CASE WHEN am_in_status = 'LATE' THEN 1 ELSE 0 END) +
    SUM(CASE WHEN pm_in_status = 'LATE' THEN 1 ELSE 0 END) AS late_incidents,
    SUM(CASE WHEN am_out_status = 'OVERTIME' OR pm_out_status = 'OVERTIME' THEN 1 ELSE 0 END) AS overtime_incidents,
    SUM(CASE WHEN am_in_status = 'EARLY LEAVE' OR pm_out_status = 'EARLY LEAVE' THEN 1 ELSE 0 END) AS undertime_incidents
FROM time_record
GROUP BY employee_id, DATE(work_date);

-- View: Monthly summary per employee
CREATE VIEW IF NOT EXISTS v_employee_monthly_summary AS
SELECT 
    employee_id,
    YEAR(work_date) AS year,
    MONTH(work_date) AS month,
    COUNT(*) AS total_days,
    SUM(COALESCE(TIMESTAMPDIFF(MINUTE, clock_in_am, clock_out_am), 0) +
        COALESCE(TIMESTAMPDIFF(MINUTE, clock_in_pm, clock_out_pm), 0)) / 60 AS total_hours,
    SUM(CASE WHEN am_in_status = 'LATE' OR pm_in_status = 'LATE' THEN 1 ELSE 0 END) AS total_late_incidents,
    SUM(COALESCE(am_out_diff_minutes, 0) + COALESCE(pm_out_diff_minutes, 0)) / 60 AS total_overtime_hours,
    SUM(CASE WHEN am_in_reason_status = 'approved' OR am_out_reason_status = 'approved' 
             OR pm_in_reason_status = 'approved' OR pm_out_reason_status = 'approved' THEN 1 ELSE 0 END) AS approved_reasons,
    SUM(CASE WHEN am_in_reason_status = 'pending' OR am_out_reason_status = 'pending' 
             OR pm_in_reason_status = 'pending' OR pm_out_reason_status = 'pending' THEN 1 ELSE 0 END) AS pending_reasons
FROM time_record
GROUP BY employee_id, YEAR(work_date), MONTH(work_date);

-- View: Daily on-time rate across all employees
CREATE VIEW IF NOT EXISTS v_daily_on_time_rate AS
SELECT 
    DATE(work_date) AS work_date,
    SUM(CASE WHEN am_in_status = 'ON TIME' THEN 1 ELSE 0 END) +
    SUM(CASE WHEN pm_in_status = 'ON TIME' THEN 1 ELSE 0 END) AS on_time_count,
    SUM(CASE WHEN clock_in_am IS NOT NULL THEN 1 ELSE 0 END) +
    SUM(CASE WHEN clock_in_pm IS NOT NULL THEN 1 ELSE 0 END) AS total_entries,
    ROUND(
        (SUM(CASE WHEN am_in_status = 'ON TIME' THEN 1 ELSE 0 END) +
         SUM(CASE WHEN pm_in_status = 'ON TIME' THEN 1 ELSE 0 END)) /
        (SUM(CASE WHEN clock_in_am IS NOT NULL THEN 1 ELSE 0 END) +
         SUM(CASE WHEN clock_in_pm IS NOT NULL THEN 1 ELSE 0 END)) * 100, 2
    ) AS on_time_percentage
FROM time_record
GROUP BY DATE(work_date);

-- View: Reasons audit trail (all submitted reasons with details)
CREATE VIEW IF NOT EXISTS v_reasons_audit AS
SELECT 
    tr.record_id,
    tr.employee_id,
    e.first_name,
    e.last_name,
    DATE(tr.work_date) AS work_date,
    CASE 
        WHEN tr.am_in_reason IS NOT NULL THEN 'AM In'
        WHEN tr.am_out_reason IS NOT NULL THEN 'AM Out'
        WHEN tr.pm_in_reason IS NOT NULL THEN 'PM In'
        WHEN tr.pm_out_reason IS NOT NULL THEN 'PM Out'
    END AS entry_type,
    COALESCE(tr.am_in_reason, tr.am_out_reason, tr.pm_in_reason, tr.pm_out_reason) AS reason_text,
    COALESCE(tr.am_in_reason_timestamp, tr.am_out_reason_timestamp, tr.pm_in_reason_timestamp, tr.pm_out_reason_timestamp) AS submitted_at,
    COALESCE(tr.am_in_reason_status, tr.am_out_reason_status, tr.pm_in_reason_status, tr.pm_out_reason_status) AS approval_status
FROM time_record tr
JOIN employee e ON tr.employee_id = e.employee_id
WHERE tr.am_in_reason IS NOT NULL OR tr.am_out_reason IS NOT NULL 
   OR tr.pm_in_reason IS NOT NULL OR tr.pm_out_reason IS NOT NULL;

-- Index recommendations for performance
ALTER TABLE time_record ADD INDEX idx_work_date (work_date);
ALTER TABLE time_record ADD INDEX idx_employee_date (employee_id, work_date);
ALTER TABLE time_record ADD INDEX idx_am_in_status (am_in_status);
ALTER TABLE time_record ADD INDEX idx_pm_in_status (pm_in_status);
