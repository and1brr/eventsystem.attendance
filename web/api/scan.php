<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_role(['Admin', 'Staff']);

header('Content-Type: application/json');

$payload = json_decode((string) file_get_contents('php://input'), true) ?? [];
$studentCode = trim((string) ($payload['student_id'] ?? $payload['code'] ?? ''));
$scanType = in_array(($payload['scan_type'] ?? 'entry'), ['entry', 'exit'], true)
    ? $payload['scan_type']
    : 'entry';
$eventId = !empty($payload['event_id']) ? (int) $payload['event_id'] : null;

$deviceLat = isset($payload['device_lat']) ? (float) $payload['device_lat'] : null;
$deviceLng = isset($payload['device_lng']) ? (float) $payload['device_lng'] : null;
$deviceAccuracy = isset($payload['device_accuracy_m']) ? (int) $payload['device_accuracy_m'] : (isset($payload['device_accuracy']) ? (int)$payload['device_accuracy'] : null);

try {
    if ($studentCode === '') {
        echo json_encode(['ok' => false, 'message' => 'Missing QR code payload']);
        exit;
    }

    // Enforce geofence (server-side) when event has location + radius
    if ($eventId !== null) {
        $eventStmt = db()->prepare('SELECT id, location_lat, location_lng, geofence_radius_m FROM events WHERE id = :id LIMIT 1');
        $eventStmt->execute(['id' => $eventId]);
        $event = $eventStmt->fetch();

        if (!$event) {
            echo json_encode(['ok' => false, 'message' => 'Event not found']);
            exit;
        }

        $geoLat = $event['location_lat'] !== null ? (float) $event['location_lat'] : null;
        $geoLng = $event['location_lng'] !== null ? (float) $event['location_lng'] : null;
        $geoRadius = $event['geofence_radius_m'] !== null ? (int) $event['geofence_radius_m'] : null;

        $geofenceEnabled = ($geoLat !== null && $geoLng !== null && $geoRadius !== null && $geoRadius > 0);
        if ($geofenceEnabled) {
            if ($deviceLat === null || $deviceLng === null) {
                echo json_encode(['ok' => false, 'message' => 'Location is required for this event']);
                exit;
            }

            if ($deviceLat < -90 || $deviceLat > 90 || $deviceLng < -180 || $deviceLng > 180) {
                echo json_encode(['ok' => false, 'message' => 'Invalid device location']);
                exit;
            }

            $distanceM = haversine_meters($deviceLat, $deviceLng, $geoLat, $geoLng);
            if ($distanceM > $geoRadius) {
                echo json_encode(['ok' => false, 'message' => 'Outside event geofence']);
                exit;
            }
        }
    }

    $stmt = db()->prepare('SELECT id, student_id, fname, lname FROM students WHERE student_id = :student_id AND status = "active" LIMIT 1');
    $stmt->execute(['student_id' => $studentCode]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode(['ok' => false, 'message' => 'Student not found or inactive']);
        exit;
    }

    // Check for duplicate scan within 2 minutes (120 seconds)
    $duplicate = db()->prepare(
        'SELECT id, scanned_at FROM scan_logs WHERE student_id = :student_id AND DATE(scanned_at) = CURDATE() AND TIME_TO_SEC(TIMEDIFF(NOW(), scanned_at)) < 120 LIMIT 1'
    );
    $duplicate->execute(['student_id' => (int) $student['id']]);
    $duplicateRecord = $duplicate->fetch();
    $isDuplicate = (bool) $duplicateRecord;

    $insert = db()->prepare(
        'INSERT INTO scan_logs (student_id, scanned_by, scan_code, scan_type, status, event_id, device_lat, device_lng, device_accuracy_m, scanned_at)
         VALUES (:student_id, :scanned_by, :scan_code, :scan_type, :status, :event_id, :device_lat, :device_lng, :device_accuracy_m, NOW())'
    );
    $insert->execute([
        'student_id' => (int) $student['id'],
        'scanned_by' => (int) current_user()['id'],
        'scan_code' => $studentCode,
        'scan_type' => $scanType,
        'status' => $isDuplicate ? 'duplicate' : 'success',
        'event_id' => $eventId,
        'device_lat' => $deviceLat,
        'device_lng' => $deviceLng,
        'device_accuracy_m' => $deviceAccuracy,
    ]);

    if ($isDuplicate) {
        echo json_encode([
            'ok' => true,
            'status' => 'duplicate',
            'message' => sprintf('%s %s already scanned', $student['fname'], $student['lname']),
            'previous_scan' => $duplicateRecord['scanned_at'] ?? null,
        ]);
    } else {
        echo json_encode([
            'ok' => true,
            'status' => 'success',
            'message' => sprintf('%s %s recorded', $student['fname'], $student['lname']),
        ]);
    }
} catch (PDOException $e) {
    error_log('Scan API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database error occurred. Please try again.',
    ]);
} catch (Exception $e) {
    error_log('Scan API Unexpected Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'An unexpected error occurred.',
    ]);
}
exit;

/**
 * Great-circle distance between two points.
 */
function haversine_meters(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $R = 6371000.0;
    $toRad = static fn (float $deg): float => $deg * M_PI / 180.0;

    $dLat = $toRad($lat2 - $lat1);
    $dLon = $toRad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLon / 2) ** 2;
    return 2.0 * $R * asin(min(1.0, sqrt($a)));
}
