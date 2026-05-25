<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_role(['Admin']);

$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare(
        'UPDATE scan_logs
         SET student_id = :student_id, scan_code = :scan_code, scan_type = :scan_type, status = :status, scanned_at = :scanned_at
         WHERE id = :id'
    );
    $stmt->execute([
        'student_id' => (int) $_POST['student_id'],
        'scan_code' => trim((string) $_POST['scan_code']),
        'scan_type' => in_array($_POST['scan_type'], ['attendance', 'entry', 'exit'], true) ? $_POST['scan_type'] : 'attendance',
        'status' => in_array($_POST['status'], ['success', 'duplicate', 'invalid'], true) ? $_POST['status'] : 'success',
        'scanned_at' => $_POST['scanned_at'],
        'id' => $id,
    ]);
    flash('success', 'Scan log updated successfully.');
    redirect('/comprog/web/logs.php');
}

$stmt = $pdo->prepare(
    'SELECT sl.*, s.student_id AS student_code, CONCAT(s.fname, " ", s.lname) AS student_name
     FROM scan_logs sl INNER JOIN students s ON s.id = sl.student_id WHERE sl.id = :id'
);
$stmt->execute(['id' => $id]);
$log = $stmt->fetch();

if (!$log) {
    flash('error', 'Scan log not found.');
    redirect('/comprog/web/logs.php');
}

$students = $pdo->query('SELECT id, student_id, CONCAT(fname, " ", lname) AS student_name FROM students ORDER BY lname, fname')->fetchAll();

$title = 'Edit Scan Log - ' . APP_NAME;
require __DIR__ . '/partials/header.php';
?>

<form method="post" class="max-w-3xl rounded-3xl bg-white/90 shadow border border-white p-6 space-y-4">
    <input type="hidden" name="id" value="<?= (int) $log['id'] ?>">
    <label class="block">
        <span class="text-sm font-medium">Student</span>
        <select name="student_id" class="mt-1 w-full rounded-xl border-slate-300">
            <?php foreach ($students as $student): ?>
                <option value="<?= (int) $student['id'] ?>" <?= (int) $student['id'] === (int) $log['student_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($student['student_id'] . ' - ' . $student['student_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="grid md:grid-cols-2 gap-3">
        <label class="block"><span class="text-sm font-medium">Scan Code</span><input name="scan_code" value="<?= htmlspecialchars($log['scan_code']) ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
        <label class="block"><span class="text-sm font-medium">Scan Type</span><select name="scan_type" class="mt-1 w-full rounded-xl border-slate-300"><option value="attendance" <?= $log['scan_type'] === 'attendance' ? 'selected' : '' ?>>attendance</option><option value="entry" <?= $log['scan_type'] === 'entry' ? 'selected' : '' ?>>entry</option><option value="exit" <?= $log['scan_type'] === 'exit' ? 'selected' : '' ?>>exit</option></select></label>
    </div>
    <div class="grid md:grid-cols-2 gap-3">
        <label class="block"><span class="text-sm font-medium">Status</span><select name="status" class="mt-1 w-full rounded-xl border-slate-300"><option value="success" <?= $log['status'] === 'success' ? 'selected' : '' ?>>success</option><option value="duplicate" <?= $log['status'] === 'duplicate' ? 'selected' : '' ?>>duplicate</option><option value="invalid" <?= $log['status'] === 'invalid' ? 'selected' : '' ?>>invalid</option></select></label>
        <label class="block"><span class="text-sm font-medium">Scanned At</span><input type="datetime-local" name="scanned_at" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime((string) $log['scanned_at']))) ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
    </div>
    <button class="rounded-xl bg-slate-900 text-white px-4 py-3 font-semibold">Update Log</button>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
