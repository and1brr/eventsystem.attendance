<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_role(['Admin', 'Staff']);

$pdo = db();

$title = 'Attendance Logs - ' . APP_NAME;
require __DIR__ . '/partials/header.php';

// Fetch events for filter
try {
    $events = $pdo->query('SELECT id, event_name, event_date FROM events ORDER BY event_date DESC')->fetchAll();
} catch (PDOException $e) {
    error_log('Events Fetch Error: ' . $e->getMessage());
    $events = [];
}

$filter_event = (int)($_GET['event_id'] ?? 0);
$filter_date = trim((string)($_GET['date'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));

// If CSV export requested
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $filter_event) {
    try {
        // Build query for CSV
        $stmt = $pdo->prepare('SELECT s.student_id, CONCAT(s.fname, " ", s.lname) AS full_name, sl.scanned_at, sl.scanned_out, sl.status
            FROM scan_logs sl
            LEFT JOIN students s ON s.id = sl.student_id
            WHERE sl.event_id = :event_id'
        );
        $stmt->execute(['event_id' => $filter_event]);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=attendance_event_' . $filter_event . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Student ID', 'Full Name', 'Time In', 'Time Out', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['student_id'], $r['full_name'], $r['scanned_at'], $r['scanned_out'], $r['status']]);
        }
        exit;
    } catch (PDOException $e) {
        error_log('CSV Export Error: ' . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to export CSV. Please try again.']);
        exit;
    }
}

// Query summary stats for selected event
$summary = null;
$attendance_rows = [];
$totalRegistered = 0;
$totalAttended = 0;
$attendancePercent = 0;

if ($filter_event) {
    try {
        $summaryStmt = $pdo->prepare('SELECT e.id, e.event_name, e.event_date, COUNT(DISTINCT sl.student_id) AS attended
            FROM events e
            LEFT JOIN scan_logs sl ON sl.event_id = e.id AND sl.scan_type = "entry"
            WHERE e.id = :event_id
            GROUP BY e.id');
        $summaryStmt->execute(['event_id' => $filter_event]);
        $summary = $summaryStmt->fetch();

        // Total registered students -> assume all students (or could be event-specific if implemented)
        $totalRegistered = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
        $totalAttended = (int) ($summary['attended'] ?? 0);
        $attendancePercent = $totalRegistered ? round(($totalAttended / $totalRegistered) * 100, 1) : 0;
    } catch (PDOException $e) {
        error_log('Summary Stats Query Error: ' . $e->getMessage());
        $summary = null;
    }

    // Fetch grouped attendance rows
    $q = 'SELECT 
        s.student_id, 
        CONCAT(s.fname, " ", s.lname) AS full_name,
        MIN(CASE WHEN sl.scan_type IN ("entry", "Entry", "in") THEN sl.scanned_at END) AS entry_time,
        MAX(CASE WHEN sl.scan_type IN ("exit", "Exit", "out") THEN sl.scanned_at END) AS exit_time
        FROM scan_logs sl
        INNER JOIN students s ON s.id = sl.student_id
        WHERE sl.event_id = :event_id ';
        
    $params = ['event_id' => $filter_event];
    if ($filter_date) { 
        $q .= ' AND DATE(sl.scanned_at) = :date '; 
        $params['date'] = $filter_date; 
    }
    // We must put SEARCH logic in having or inside a subquery. 
    // It is easier to join and group, then apply HAVING for full_name / student_id search!
    $q .= ' GROUP BY s.id, s.student_id, s.fname, s.lname ';
    
    if ($search) {
        $q .= ' HAVING s.student_id LIKE :q OR full_name LIKE :q ';
        $params['q'] = "%$search%";
    }
    
    $q .= ' ORDER BY s.lname ASC';

    try {
        $stmt = $pdo->prepare($q);
        $stmt->execute($params);
        $attendance_rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Attendance Logs Query Error: ' . $e->getMessage());
        $attendance_rows = [];
    }
}

?>
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0">Attendance Logs</h1>
        </div>
        <div class="d-flex gap-2">
            <?php if ($filter_event): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-primary">Export CSV</a>
                <button onclick="window.print()" class="btn btn-info">Print / PDF</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row align-items-end g-3">
                <div class="col-md-5">
                    <label class="form-label" style="font-size: 13px;">Event Filter</label>
                    <select name="event_id" class="form-select form-select-sm">
                        <option value="">-- Select Event --</option>
                        <?php foreach ($events as $ev): ?>
                            <option value="<?= (int)$ev['id'] ?>" <?= $filter_event === (int)$ev['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ev['event_name'] . ' (' . $ev['event_date'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size: 13px;">Date</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label class="form-label" style="font-size: 13px;">Search Student</label>
                    <div class="d-flex gap-2">
                        <input name="q" value="<?= htmlspecialchars($search) ?>" class="form-control form-control-sm" placeholder="ID or Name">
                        <button type="submit" class="btn btn-primary btn-sm px-3">Filter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($filter_event && $summary): ?>
        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted" style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Event</div>
                        <div class="h5 mb-1"><?= htmlspecialchars($summary['event_name']) ?></div>
                        <div class="text-muted" style="font-size: 13px;"><?= htmlspecialchars($summary['event_date']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted" style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Total Registered</div>
                        <div class="h5 mb-0"><?= number_format($totalRegistered) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted" style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Attended</div>
                        <div class="h5 mb-0"><?= number_format($totalAttended) ?> <span class="text-muted" style="font-size: 13px;">(<?= $attendancePercent ?>%)</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">Attendance Table</h5>
                <span class="text-muted" style="font-size: 13px;">Showing <?= count($attendance_rows) ?> rows</span>
            </div>
            <div class="card-body">
            <?php if (empty($attendance_rows)): ?>
                <div class="text-center py-5 text-muted">
                    <div style="font-size: 14px;">No attendance records found for this event.</div>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Entry Time</th>
                            <th>Exit Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_rows as $row): 
                            $entry = $row['entry_time'];
                            $exit = $row['exit_time'];
                            $status = "Incomplete";
                            if ($entry && $exit) $status = "Complete";
                        ?>
                            <tr>
                                <td><span class="fw-medium text-dark"><?= htmlspecialchars($row['student_id']) ?></span></td>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td>
                                    <?php if($entry): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fa-solid fa-arrow-down mr-1"></i> <?= htmlspecialchars(date('h:i A', strtotime($entry))) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($exit): ?>
                                        <span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-arrow-up mr-1"></i> <?= htmlspecialchars(date('h:i A', strtotime($exit))) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($status === 'Complete'): ?>
                                        <span class="badge badge-info px-2 py-1"><?= $status ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-warning px-2 py-1"><?= $status ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                Select an event and click Filter to view attendance summaries.
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php';
exit;
?>
