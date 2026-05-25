<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$stats = [
    'students' => (int) db()->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'scan_logs' => (int) db()->query('SELECT COUNT(*) FROM scan_logs')->fetchColumn(),
    'today_scans' => (int) db()->query("SELECT COUNT(*) FROM scan_logs WHERE DATE(scanned_at) = CURDATE()")->fetchColumn(),
    'events' => (int) db()->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()")->fetchColumn(),
];

$recentLogs = db()->query(
    'SELECT sl.id, sl.scan_code, sl.status, sl.scanned_at, s.student_id, CONCAT(s.fname, " ", s.lname) AS student_name, u.full_name AS scanned_by
     FROM scan_logs sl
     INNER JOIN students s ON s.id = sl.student_id
     INNER JOIN users u ON u.id = sl.scanned_by
     ORDER BY sl.scanned_at DESC
     LIMIT 8'
)->fetchAll();

$title = 'Dashboard - ' . APP_NAME;
require __DIR__ . '/partials/header.php';
?>

<div class="row g-4 mb-4">
    <?php foreach ([
        ['label' => 'Events', 'value' => $stats['events']],
        ['label' => 'Total Attendance', 'value' => $stats['scan_logs']],
        ['label' => 'Total Students', 'value' => $stats['students']],
        ['label' => 'Today\'s Scans', 'value' => $stats['today_scans']],
    ] as $card): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted" style="font-size: 14px;"><?= htmlspecialchars($card['label']) ?></div>
                    <div class="fs-2 fw-bold text-dark mt-2"><?= (int) $card['value'] ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">Quick Actions</h5>
                <span class="badge bg-secondary"><?= htmlspecialchars(current_user()['role'] ?? '') ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-4">
                        <a class="btn btn-primary w-100" href="/comprog/web/scan.php">Open Scanner</a>
                    </div>
                    <?php if(current_user()['role'] === 'Admin'): ?>
                    <div class="col-sm-4">
                        <a class="btn btn-outline-secondary w-100" href="/comprog/web/events.php">Manage Events</a>
                    </div>
                    <?php endif; ?>
                    <div class="col-sm-4">
                        <a class="btn btn-outline-secondary w-100" href="/comprog/web/students.php">Manage Students</a>
                    </div>
                    <div class="col-sm-4">
                        <a class="btn btn-outline-secondary w-100" href="/comprog/web/logs.php">Scan History</a>
                    </div>
                </div>
                <div class="mt-4 text-muted form-text" style="font-size: 13px;">
                    <p class="mb-1"><i class="fa fa-info-circle me-1"></i> QR codes must encode the student ID value from the students table.</p>
                    <p class="mb-0"><i class="fa fa-info-circle me-1"></i> Staff can scan and record attendance, while admins can manage records and users.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header border-0 pb-0">
                <h5 class="card-title mb-0">Recent Logs</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($recentLogs as $log): ?>
                        <div class="p-3 border rounded bg-light">
                            <div class="fw-medium text-dark mb-1">
                                <?= htmlspecialchars($log['student_name']) ?> 
                                <span class="text-muted" style="font-size: 13px;">(<?= htmlspecialchars($log['student_id']) ?>)</span>
                            </div>
                            <div class="text-muted" style="font-size: 12px; display: flex; align-items: center; justify-content: space-between;">
                                <span><?= htmlspecialchars($log['scanned_at']) ?></span>
                                <span><?= htmlspecialchars($log['scanned_by']) ?></span>
                                <?php if($log['status'] === 'success'): ?>
                                    <span class="text-success"><i class="fa fa-check me-1"></i>Success</span>
                                <?php elseif($log['status'] === 'duplicate'): ?>
                                    <span class="text-warning"><i class="fa fa-exclamation-triangle me-1"></i>Duplicate</span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="fa fa-times me-1"></i><?= htmlspecialchars($log['status']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
