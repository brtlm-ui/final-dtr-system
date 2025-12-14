<?php
/**
 * Calculate attendance status (LATE, ON TIME, OVERTIME, etc.)
 */
function calculateStatus($clockTime, $officialTime, $gracePeriodMinutes = 15) {
    if (!$clockTime || !$officialTime) {
        return 'ABSENT';
    }
    
    $clock = new DateTime($clockTime);
    $official = new DateTime($officialTime);
    $graceTime = clone $official;
    $graceTime->modify("+{$gracePeriodMinutes} minutes");
    
    // For clock in - check if late
    if ($clock > $graceTime) {
        return 'LATE';
    }
    
    return 'ON TIME';
}

/**
 * Calculate overtime status
 */
function calculateOvertimeStatus($clockOutTime, $officialTimeOut) {
    if (!$clockOutTime || !$officialTimeOut) {
        return 'ON TIME';
    }
    
    $clockOut = new DateTime($clockOutTime);
    $officialOut = new DateTime($officialTimeOut);
    
    // If clocked out after official time, it's overtime
    if ($clockOut > $officialOut) {
        return 'OVERTIME';
    }
    
    return 'ON TIME';
}

/**
 * Mask name for privacy (e.g., "Edward" becomes "Edw***")
 */
function maskName($name) {
    if (strlen($name) <= 3) {
        return $name;
    }
    
    $visible = substr($name, 0, 3);
    $masked = str_repeat('*', strlen($name) - 3);
    
    return $visible . $masked;
}

/**
 * Format datetime to readable time
 */
function formatTime($datetime, $format = 'h:i A') {
    if (!$datetime) {
        return '--';
    }
    
    $date = new DateTime($datetime);
    // Don't convert timezone - times are already stored in app timezone (Asia/Manila)
    return $date->format($format);
}

/**
 * Format datetime to readable date
 */
function formatDate($datetime, $format = 'F d, Y') {
    if (!$datetime) {
        return '--';
    }
    
    $date = new DateTime($datetime);
    return $date->format($format);
}

/**
 * Calculate total hours between two times
 */
function getTotalHours($clockIn, $clockOut) {
    if (!$clockIn || !$clockOut) {
        return 0;
    }
    
    $start = new DateTime($clockIn);
    $end = new DateTime($clockOut);
    $interval = $start->diff($end);
    
    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;
    
    return round($hours + ($minutes / 60), 2);
}

/**
 * Get day of week from date
 */
function getDayOfWeek($date) {
    $datetime = new DateTime($date);
    return $datetime->format('l'); // Returns full day name (e.g., "Monday")
}

/**
 * Check if employee has already clocked in today
 */
function hasClockInToday($conn, $employeeId) {
    $today = date('Y-m-d');
    
    $stmt = $conn->prepare("
        SELECT record_id 
        FROM time_record 
        WHERE employee_id = ? 
        AND DATE(record_date) = ?
    ");
    
    $stmt->execute([$employeeId, $today]);
    return $stmt->fetch() !== false;
}

/**
 * Get today's time record for employee
 */
function getTodayRecord($conn, $employeeId) {
    $today = date('Y-m-d');
    
        $stmt = $conn->prepare("SELECT * FROM time_record WHERE employee_id = ? AND DATE(record_date) = ? LIMIT 1");
    
    $stmt->execute([$employeeId, $today]);
    return $stmt->fetch();
}

/**
 * Get official time for employee on specific day
 */
function getOfficialTime($conn, $employeeId, $dayOfWeek) {
    $stmt = $conn->prepare("
        SELECT * 
        FROM official_time 
        WHERE employee_id = ? 
        AND day_of_week = ? 
        AND is_active = 1
    ");
    
    $stmt->execute([$employeeId, $dayOfWeek]);
    return $stmt->fetch();
}

/**
 * Sanitize input
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate alert message HTML
 */
function alertMessage($message, $type = 'info') {
    $alertClass = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $class = $alertClass[$type] ?? 'alert-info';
    
    return "<div class='alert {$class} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

/**
 * Calculate clock OUT status (for early leave, overtime, on time)
 */
function calculateClockOutStatus($clockTime, $officialTime, $gracePeriodMinutes = 15) {
    if (!$clockTime || !$officialTime) {
        return 'ON TIME';
    }
    
    $clock = new DateTime($clockTime);
    $official = new DateTime($officialTime);
    $graceTime = clone $official;
    $graceTime->modify("+{$gracePeriodMinutes} minutes");
    
    // If clocked out before official time = EARLY LEAVE
    if ($clock < $official) {
        return 'EARLY LEAVE';
    }
    
    // If clocked out after grace time = OVERTIME
    if ($clock > $graceTime) {
        return 'OVERTIME';
    }
    
    // Otherwise ON TIME
    return 'ON TIME';
}

/**
 * Get difference in minutes between two datetimes (timeA - timeB)
 * Returns integer minutes (positive or negative)
 */
function getMinutesDifference($timeA, $timeB) {
    if (!$timeA || !$timeB) return null;
    $a = new DateTime($timeA);
    $b = new DateTime($timeB);
    $diff = $a->getTimestamp() - $b->getTimestamp();
    return (int) round($diff / 60);
}

/**
 * Compute per-record metrics (statuses, diff minutes, reasons) in PHP.
 * Returns an associative array keyed by entry type: am_in, am_out, pm_in, pm_out
 */
function getRecordMetrics($conn, $record) {
    $types = ['am_in','am_out','pm_in','pm_out'];

    // use only new normalized columns
    $vals = [];
    $vals['am_in'] = $record['am_in'] ?? null;
    $vals['am_out'] = $record['am_out'] ?? null;
    $vals['pm_in'] = $record['pm_in'] ?? null;
    $vals['pm_out'] = $record['pm_out'] ?? null;

    $recordDate = $record['record_date'] ?? $record['work_date'] ?? null;
    if (!$recordDate) {
        // fallback to created_at or derive from first non-empty clock value
        $recordDate = $record['created_at'] ?? null;
        if (!$recordDate) {
            foreach ($vals as $v) { if ($v) { $recordDate = date('Y-m-d', strtotime($v)); break; } }
        } else {
            $recordDate = date('Y-m-d', strtotime($recordDate));
        }
    } else {
        $recordDate = date('Y-m-d', strtotime($recordDate));
    }

    $employeeId = $record['employee_id'] ?? $record['emp_id'] ?? null;
    $dayOfWeek = $recordDate ? date('l', strtotime($recordDate)) : date('l');
    $official = $employeeId ? getOfficialTime($conn, $employeeId, $dayOfWeek) : null;

    $metrics = [];

    foreach ($types as $t) {
        $metrics[$t] = [
            'value' => $vals[$t] ?? null,
            'value_datetime' => $vals[$t] ? ($recordDate . ' ' . $vals[$t]) : null, // Full datetime for calculations
            'status' => null,
            'diff_minutes' => null,
            'official' => null,
            'reason' => null,
            'reason_status' => null,
            'reason_id' => null
        ];

        if ($official) {
            // build official datetime for comparison
            $officialField = null;
            if ($t === 'am_in') $officialField = $official['am_time_in'] ?? null;
            if ($t === 'am_out') $officialField = $official['am_time_out'] ?? null;
            if ($t === 'pm_in') $officialField = $official['pm_time_in'] ?? null;
            if ($t === 'pm_out') $officialField = $official['pm_time_out'] ?? null;

            if ($officialField) {
                $officialDatetime = $recordDate . ' ' . $officialField;
                $metrics[$t]['official'] = $officialDatetime;

                // compute status and diff
                if (!empty($metrics[$t]['value'])) {
                    // Prepend record date to the time value for accurate comparison
                    $actualDatetime = $recordDate . ' ' . $metrics[$t]['value'];
                    
                    if ($t === 'am_in' || $t === 'pm_in') {
                        $metrics[$t]['status'] = calculateStatus($actualDatetime, $officialDatetime, $official['grace_period_minutes'] ?? 15);
                        $metrics[$t]['diff_minutes'] = getMinutesDifference($actualDatetime, $officialDatetime);
                    } else {
                        // out entries use clock out status logic
                        $metrics[$t]['status'] = calculateClockOutStatus($actualDatetime, $officialDatetime, $official['grace_period_minutes'] ?? 15);
                        $metrics[$t]['diff_minutes'] = getMinutesDifference($actualDatetime, $officialDatetime);
                    }
                }
            }
        }

        // load latest reason (if any) from time_reason
        try {
            if (!empty($record['record_id'])) {
                $rstmt = $conn->prepare("SELECT reason_id, reason_text, submitted_at FROM time_reason WHERE record_id = ? AND reason_type = ? ORDER BY submitted_at DESC LIMIT 1");
                $rstmt->execute([$record['record_id'], $t]);
                $rrow = $rstmt->fetch();
                if ($rrow) {
                    $metrics[$t]['reason'] = $rrow['reason_text'];
                    $metrics[$t]['reason_id'] = $rrow['reason_id'];
                    // get latest approval status
                    $astmt = $conn->prepare("SELECT approval_status FROM time_approval WHERE reason_id = ? ORDER BY approved_at DESC LIMIT 1");
                    $astmt->execute([$rrow['reason_id']]);
                    $ap = $astmt->fetchColumn();
                    $metrics[$t]['reason_status'] = $ap ?: 'pending';
                }
            }
        } catch (PDOException $e) {
            // ignore DB errors here to avoid breaking display
        }
    }

    return $metrics;
}

/**
 * Human readable difference text for a clock entry
 * type: 'in' or 'out' (for semantics)
 */
function differenceText($clockTime, $officialTime, $status) {
    if (!$clockTime || !$officialTime || !$status) return '';
    $minutes = abs(getMinutesDifference($clockTime, $officialTime));
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    $hm = ($hours > 0) ? ($hours . ' hour' . ($hours > 1 ? 's' : '')) : '';
    $hm .= ($hours > 0 && $mins > 0) ? ' ' : '';
    $hm .= ($mins > 0) ? ($mins . ' minute' . ($mins > 1 ? 's' : '')) : '';

    if ($status === 'LATE') {
        return $hm ? "by $hm" : '';
    }
    if ($status === 'EARLY LEAVE') {
        return $hm ? "$hm undertime" : '';
    }
    if ($status === 'OVERTIME') {
        return $hm ? "$hm overtime" : '';
    }
    return '';
}

/**
 * Return inline CSS style for badge background based on status.
 * Uses the color palette requested by the user.
 */
function getBadgeStyle($status) {
    $map = [
        'LATE' => '#DC2626',        // red
        'EARLY LEAVE' => '#F97316', // orange
        'ON TIME' => '#10B981',     // green
        'OVERTIME' => '#3B82F6'     // blue
    ];

    $color = $map[$status] ?? '#6B7280'; // gray fallback
    return "background-color: {$color}; color: #ffffff;";
}

/**
 * KPI helper functions
 * Each function accepts a PDO connection plus a date range and optional employee filter.
 */

/**
 * On-time rate (percentage) across clock-in entries in range
 */
function kpi_on_time_rate($conn, $startDate, $endDate, $employeeId = null) {
    $params = [$startDate, $endDate];
    $employeeFilter = '';
    if ($employeeId) { $employeeFilter = ' AND tr.employee_id = ?'; $params[] = $employeeId; }

    // Dynamically calculate on-time status: clock-in within grace period of official time
    $sql = "SELECT 
        SUM(CASE WHEN tr.am_in IS NOT NULL 
            AND ot.am_time_in IS NOT NULL
            AND TIMESTAMPDIFF(MINUTE, ot.am_time_in, TIME(tr.am_in)) <= ot.grace_period_minutes
            THEN 1 ELSE 0 END) AS am_on_time,
        SUM(CASE WHEN tr.pm_in IS NOT NULL 
            AND ot.pm_time_in IS NOT NULL
            AND TIMESTAMPDIFF(MINUTE, ot.pm_time_in, TIME(tr.pm_in)) <= ot.grace_period_minutes
            THEN 1 ELSE 0 END) AS pm_on_time,
        SUM(CASE WHEN tr.am_in IS NOT NULL THEN 1 ELSE 0 END) AS am_total,
        SUM(CASE WHEN tr.pm_in IS NOT NULL THEN 1 ELSE 0 END) AS pm_total
        FROM time_record tr
        LEFT JOIN official_time ot ON ot.employee_id = tr.employee_id 
            AND ot.day_of_week = DAYNAME(tr.record_date) 
            AND ot.is_active = 1
        WHERE DATE(tr.record_date) BETWEEN ? AND ?" . $employeeFilter;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    $onTime = ($row['am_on_time'] ?? 0) + ($row['pm_on_time'] ?? 0);
    $total = ($row['am_total'] ?? 0) + ($row['pm_total'] ?? 0);
    $pct = $total > 0 ? round(($onTime / $total) * 100, 2) : null;

    return ['numerator' => (int)$onTime, 'denominator' => (int)$total, 'pct' => $pct];
}

/**
 * Average daily hours per record (across records in range)
 */
function kpi_average_daily_hours($conn, $startDate, $endDate, $employeeId = null) {
    $params = [$startDate, $endDate];
    $employeeFilter = '';
    if ($employeeId) { $employeeFilter = ' AND tr.employee_id = ?'; $params[] = $employeeId; }

    $sql = "SELECT AVG((
        COALESCE(TIMESTAMPDIFF(MINUTE, tr.am_in, tr.am_out),0) +
        COALESCE(TIMESTAMPDIFF(MINUTE, tr.pm_in, tr.pm_out),0)
    )/60) AS avg_hours
    FROM time_record tr
    WHERE DATE(tr.record_date) BETWEEN ? AND ?" . $employeeFilter;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return ['avg_hours' => $row && $row['avg_hours'] !== null ? round((float)$row['avg_hours'], 2) : null];
}

/**
 * Total overtime hours (sum of positive overtime diffs stored in pm_out_diff_minutes and am_out_diff_minutes)
 */
function kpi_total_overtime_hours($conn, $startDate, $endDate, $employeeId = null) {
    $params = [$startDate, $endDate];
    $employeeFilter = '';
    if ($employeeId) { $employeeFilter = ' AND tr.employee_id = ?'; $params[] = $employeeId; }

    // Compute overtime by comparing recorded out times to official schedule for that day.
    // Uses DAYNAME(record_date) to join the correct official_time row.
    $sql = "SELECT 
        SUM(
            CASE WHEN tr.pm_out IS NOT NULL AND ot.pm_time_out IS NOT NULL 
                 THEN GREATEST(TIMESTAMPDIFF(MINUTE, CONCAT(DATE(tr.record_date),' ', ot.pm_time_out), tr.pm_out), 0)
                 ELSE 0 END
        ) AS pm_ot,
        SUM(
            CASE WHEN tr.am_out IS NOT NULL AND ot.am_time_out IS NOT NULL 
                 THEN GREATEST(TIMESTAMPDIFF(MINUTE, CONCAT(DATE(tr.record_date),' ', ot.am_time_out), tr.am_out), 0)
                 ELSE 0 END
        ) AS am_ot
        FROM time_record tr
        LEFT JOIN official_time ot ON ot.employee_id = tr.employee_id AND ot.day_of_week = DAYNAME(tr.record_date) AND ot.is_active = 1
        WHERE DATE(tr.record_date) BETWEEN ? AND ?" . $employeeFilter;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    $minutes = ($row['pm_ot'] ?? 0) + ($row['am_ot'] ?? 0);
    return ['overtime_hours' => round($minutes / 60, 2), 'overtime_minutes' => (int)$minutes];
}

/**
 * Average lateness in minutes for LATE entries (Optional metric - simplified)
 */
function kpi_average_lateness($conn, $startDate, $endDate, $employeeId = null) {
    // Simplified: returns null (removed from primary KPIs)
    // Can be enhanced later if needed
    return ['avg_late_minutes' => null, 'avg_am_late' => null, 'avg_pm_late' => null];
}

/**
 * Count late incidents (clock-ins beyond grace period)
 */
function kpi_late_incidents_count($conn, $startDate, $endDate, $employeeId = null) {
    $params = [$startDate, $endDate];
    $employeeFilter = '';
    if ($employeeId) { $employeeFilter = ' AND tr.employee_id = ?'; $params[] = $employeeId; }

    // Dynamically count late entries: clock-ins beyond grace period
    $sql = "SELECT 
        SUM(CASE WHEN tr.am_in IS NOT NULL 
            AND ot.am_time_in IS NOT NULL
            AND TIMESTAMPDIFF(MINUTE, ot.am_time_in, TIME(tr.am_in)) > ot.grace_period_minutes
            THEN 1 ELSE 0 END) AS am_late,
        SUM(CASE WHEN tr.pm_in IS NOT NULL 
            AND ot.pm_time_in IS NOT NULL
            AND TIMESTAMPDIFF(MINUTE, ot.pm_time_in, TIME(tr.pm_in)) > ot.grace_period_minutes
            THEN 1 ELSE 0 END) AS pm_late
        FROM time_record tr
        LEFT JOIN official_time ot ON ot.employee_id = tr.employee_id 
            AND ot.day_of_week = DAYNAME(tr.record_date) 
            AND ot.is_active = 1
        WHERE DATE(tr.record_date) BETWEEN ? AND ?" . $employeeFilter;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    $count = ($row['am_late'] ?? 0) + ($row['pm_late'] ?? 0);
    return ['late_incidents' => (int)$count];
}

/**
 * Pending reasons count
 */
function kpi_pending_reasons_count($conn, $startDate, $endDate, $employeeId = null) {
    $params = [$startDate, $endDate];
    $employeeFilter = '';
    if ($employeeId) { $employeeFilter = ' AND tr.employee_id = ?'; $params[] = $employeeId; }

    // Actual pending reasons: reasons whose latest approval status is NULL or 'pending'
    // Correlated subquery gets latest approval status per reason.
    $sql = "SELECT COUNT(*) AS cnt
            FROM time_reason r
            JOIN time_record tr ON r.record_id = tr.record_id
            WHERE DATE(tr.record_date) BETWEEN ? AND ?" . $employeeFilter . "
              AND COALESCE(
                  (SELECT ta.approval_status
                   FROM time_approval ta
                   WHERE ta.reason_id = r.reason_id
                   ORDER BY ta.approved_at DESC
                   LIMIT 1), 'pending') = 'pending'";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return ['pending_reasons' => (int)($row['cnt'] ?? 0)];
}

?>