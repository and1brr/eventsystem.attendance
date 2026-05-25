<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_role(['Admin', 'Staff']);

$pdo = db();
$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

$sql = 'SELECT sl.id, sl.scan_code, sl.scan_type, sl.status, sl.scanned_at, sl.device_lat, sl.device_lng, sl.device_accuracy_m, s.student_id, CONCAT(s.fname, " ", s.lname) AS student_name, u.full_name AS scanned_by,
    e.id AS event_id, e.event_name, e.event_date, e.location_lat, e.location_lng
    FROM scan_logs sl
    INNER JOIN students s ON s.id = sl.student_id
    INNER JOIN users u ON u.id = sl.scanned_by
    LEFT JOIN events e ON e.id = sl.event_id
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

$sql .= ' ORDER BY sl.scanned_at DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$title = 'Scan Logs - ' . APP_NAME;
require __DIR__ . '/partials/header.php';
?>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 items-end" method="get">
            <div class="col-md-3">
                <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search student or code" class="form-control">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach (['success', 'duplicate', 'invalid'] as $value): ?>
                        <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= ucfirst($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <div class="dropdown w-100">
                    <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/comprog/web/export.php?format=csv&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"><i class="fa fa-file-csv me-2"></i>Export CSV</a></li>
                        <li><a class="dropdown-item" href="/comprog/web/export.php?format=pdf&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"><i class="fa fa-file-pdf me-2"></i>Export PDF</a></li>
                    </ul>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Event</th>
                    <th>Where</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Scanned At</th>
                    <th>By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <div class="fw-medium text-dark"><?= htmlspecialchars($log['student_name']) ?></div>
                            <div class="text-muted" style="font-size: 13px;"><?= htmlspecialchars($log['student_id']) ?></div>
                        </td>
                        <td>
                            <?php if (!empty($log['event_name'])): ?>
                                <div class="fw-medium"><?= htmlspecialchars($log['event_name']) ?></div>
                                <div class="text-muted" style="font-size: 12px;"><?= htmlspecialchars($log['event_date']) ?></div>
                            <?php else: ?>
                                <div class="text-muted">-</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['device_lat'] !== null && $log['device_lng'] !== null): ?>
                                <div class="fw-medium">Device</div>
                                <div class="text-muted js-reverse" data-lat="<?= htmlspecialchars($log['device_lat']) ?>" data-lng="<?= htmlspecialchars($log['device_lng']) ?>">Resolving address…</div>
                            <?php elseif ($log['location_lat'] !== null && $log['location_lng'] !== null): ?>
                                <div class="fw-medium">Event</div>
                                <div class="text-muted js-reverse" data-lat="<?= htmlspecialchars($log['location_lat']) ?>" data-lng="<?= htmlspecialchars($log['location_lng']) ?>">Resolving address…</div>
                            <?php else: ?>
                                <div class="text-muted">-</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($log['scan_type'] === 'entry'): ?>
                                <span class="badge badge-success">Entry</span>
                            <?php elseif($log['scan_type'] === 'exit'): ?>
                                <span class="badge badge-danger">Exit</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($log['scan_type'] ?? 'N/A')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($log['status'] === 'success'): ?>
                                <span class="badge badge-success"><i class="fa fa-check me-1"></i>Success</span>
                            <?php elseif($log['status'] === 'duplicate'): ?>
                                <span class="badge badge-warning"><i class="fa fa-exclamation-triangle me-1"></i>Duplicate</span>
                            <?php else: ?>
                                <span class="badge badge-danger"><i class="fa fa-times me-1"></i><?= htmlspecialchars($log['status'] ?? 'Absent') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($log['scanned_at']) ?></td>
                        <td><?= htmlspecialchars($log['scanned_by']) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="/comprog/web/log_edit.php?id=<?= (int) $log['id'] ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-danger" href="/comprog/web/log_delete.php?id=<?= (int) $log['id'] ?>" onclick="return confirm('Delete this scan log?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No scan logs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
// Reverse geocode coordinates using Nominatim (OpenStreetMap)
async function reverseGeocode(lat, lng) {
    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&addressdetails=1`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return null;
        const data = await res.json();

        // Prefer building a short address from address components, excluding city/state/postcode/country
        const a = data.address || {};
        const parts = [];
        // street / road with house number
        const house = a.house_number || a.house || null;
        const road = a.road || a.pedestrian || a.cycleway || a.footway || a.path || a.residential || a.street || null;
        if (house && road) {
            parts.push(`${house} ${road}`);
        } else if (road) {
            parts.push(road);
        } else if (a.building) {
            parts.push(a.building);
        }

        // prefer neighbourhood/suburb/village/hamlet/city_district as the locality part
        const localityKeys = ['neighbourhood','suburb','village','hamlet','city_district','quarter','locality','town'];
        for (const k of localityKeys) {
            if (a[k]) { parts.push(a[k]); break; }
        }

        if (parts.length > 0) return parts.join(', ');

        // fallback: use display_name but strip trailing city/state/postcode/country parts
        if (data.display_name) {
            const segs = data.display_name.split(',').map(s => s.trim()).filter(Boolean);
            // drop up to last 3 segments (country, state, postcode/city)
            if (segs.length > 3) segs.splice(-3); else if (segs.length > 1) segs.splice(-1);
            return segs.join(', ');
        }
        return null;
    } catch (e) {
        return null;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const els = Array.from(document.querySelectorAll('.js-reverse'));
    els.forEach(async (el) => {
        const lat = el.getAttribute('data-lat');
        const lng = el.getAttribute('data-lng');
        if (!lat || !lng) return;
        const addr = await reverseGeocode(lat, lng);
        if (addr) {
            el.textContent = addr;
        } else {
            // show fallback with truncated coords
            el.textContent = `Lat ${parseFloat(lat).toFixed(5)}, Lng ${parseFloat(lng).toFixed(5)}`;
        }
    });
});
</script>
