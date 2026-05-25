<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_role(['Admin']);

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = db()->prepare('DELETE FROM scan_logs WHERE id = :id');
    $stmt->execute(['id' => $id]);
    flash('success', 'Scan log deleted successfully.');
}

redirect('/comprog/web/logs.php');
