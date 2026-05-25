<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_role(['Admin', 'Staff']);

$format = strtolower((string) ($_GET['format'] ?? 'csv'));
$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

$sql = 'SELECT sl.id, s.student_id, CONCAT(s.fname, " ", s.lname) AS student_name, sl.scan_type, sl.status, sl.scanned_at, u.full_name AS scanned_by
        FROM scan_logs sl
        INNER JOIN students s ON s.id = sl.student_id
        INNER JOIN users u ON u.id = sl.scanned_by
        WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (s.student_id LIKE :search OR s.fname LIKE :search OR s.lname LIKE :search OR sl.scan_code LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($status !== '' && in_array($status, ['success', 'duplicate', 'invalid'], true)) {
    $sql .= ' AND sl.status = :status';
    $params['status'] = $status;
}
if ($from !== '') {
    $sql .= ' AND DATE(sl.scanned_at) >= :fromDate';
    $params['fromDate'] = $from;
}
if ($to !== '') {
    $sql .= ' AND DATE(sl.scanned_at) <= :toDate';
    $params['toDate'] = $to;
}
$sql .= ' ORDER BY sl.scanned_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($format === 'pdf') {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        http_response_code(500);
        echo 'PDF export requires Composer dependencies. Run composer install first.';
        exit;
    }

    require_once $autoload;
    $html = '<h1>Scan Logs</h1><table border="1" cellpadding="6" cellspacing="0"><thead><tr><th>Student</th><th>Type</th><th>Status</th><th>Scanned At</th><th>By</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . htmlspecialchars($row['student_name'] . ' (' . $row['student_id'] . ')') . '</td><td>' . htmlspecialchars($row['scan_type']) . '</td><td>' . htmlspecialchars($row['status']) . '</td><td>' . htmlspecialchars($row['scanned_at']) . '</td><td>' . htmlspecialchars($row['scanned_by']) . '</td></tr>';
    }
    $html .= '</tbody></table>';

    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('scan_logs.pdf', ['Attachment' => true]);
    exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="scan_logs.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Student ID', 'Student Name', 'Type', 'Status', 'Scanned At', 'Scanned By']);
foreach ($rows as $row) {
    fputcsv($out, [$row['student_id'], $row['student_name'], $row['scan_type'], $row['status'], $row['scanned_at'], $row['scanned_by']]);
}
fclose($out);
