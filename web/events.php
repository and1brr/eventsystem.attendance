<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_role(['Admin']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        try {
            $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash('success', 'Event deleted successfully.');
        } catch (PDOException $e) {
            error_log('Event Delete Error: ' . $e->getMessage());
            flash('error', 'Database error occurred. Please try again.');
        }
        redirect('/comprog/web/events.php');
    }

    $eventName = trim((string) ($_POST['event_name'] ?? ''));
    $eventDescription = trim((string) ($_POST['event_description'] ?? ''));
    $eventDate = trim((string) ($_POST['event_date'] ?? ''));
    $eventTime = trim((string) ($_POST['event_time'] ?? ''));

    $locationLatRaw = trim((string) ($_POST['location_lat'] ?? ''));
    $locationLngRaw = trim((string) ($_POST['location_lng'] ?? ''));
    $radiusRaw = trim((string) ($_POST['geofence_radius_m'] ?? ''));

    $locationLat = $locationLatRaw === '' ? null : (float) $locationLatRaw;
    $locationLng = $locationLngRaw === '' ? null : (float) $locationLngRaw;
    $geofenceRadiusM = $radiusRaw === '' ? 0 : (int) $radiusRaw;

    if ($eventName === '' || $eventDate === '') {
        flash('error', 'Event name and date are required.');
        redirect('/comprog/web/events.php');
    }

    // Geofence validation: geofence is enforced only when radius > 0
    $coordsProvided = ($locationLatRaw !== '' || $locationLngRaw !== '');
    $radiusProvided = ($radiusRaw !== '');
    $radiusPositive = $geofenceRadiusM > 0;

    if ($geofenceRadiusM < 0) {
        flash('error', 'Geofence radius must be 0 or greater.');
        redirect('/comprog/web/events.php' . ($id > 0 ? '?edit=' . $id : ''));
    }

    if ($radiusPositive && ($locationLat === null || $locationLng === null)) {
        flash('error', 'Please set the event location on the map to enable geofencing.');
        redirect('/comprog/web/events.php' . ($id > 0 ? '?edit=' . $id : ''));
    }

    if ($coordsProvided) {
        if ($locationLat === null || $locationLng === null) {
            flash('error', 'Please set the event location on the map.');
            redirect('/comprog/web/events.php' . ($id > 0 ? '?edit=' . $id : ''));
        }
        if ($locationLat < -90 || $locationLat > 90 || $locationLng < -180 || $locationLng > 180) {
            flash('error', 'Invalid event location coordinates.');
            redirect('/comprog/web/events.php' . ($id > 0 ? '?edit=' . $id : ''));
        }
    } else {
        // No coordinates set; store radius as 0 unless explicitly provided otherwise
        if (!$radiusProvided || !$radiusPositive) {
            $geofenceRadiusM = 0;
        }
    }

    // Check for duplicate event name on the same date
    $dupCheck = $pdo->prepare('SELECT id FROM events WHERE event_name = :event_name AND DATE(event_date) = DATE(:event_date) AND id != :id');
    $dupCheck->execute(['event_name' => $eventName, 'event_date' => $eventDate, 'id' => $id]);
    if ($dupCheck->fetchColumn()) {
        flash('error', 'An event with this name already exists on this date.');
        redirect('/comprog/web/events.php');
    }

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE events
                 SET event_name = :event_name,
                     event_description = :event_description,
                     event_date = :event_date,
                     event_time = :event_time,
                     location_lat = :location_lat,
                     location_lng = :location_lng,
                     geofence_radius_m = :geofence_radius_m
                 WHERE id = :id'
            );
            $stmt->execute([
                'event_name' => $eventName,
                'event_description' => $eventDescription !== '' ? $eventDescription : null,
                'event_date' => $eventDate,
                'event_time' => $eventTime !== '' ? $eventTime : null,
                'location_lat' => $locationLat,
                'location_lng' => $locationLng,
                'geofence_radius_m' => $geofenceRadiusM,
                'id' => $id,
            ]);
            flash('success', 'Event updated successfully.');
        } catch (PDOException $e) {
            error_log('Event Update Error: ' . $e->getMessage());
            flash('error', 'Database error occurred. Please try again.');
        }
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO events (event_name, event_description, event_date, event_time, location_lat, location_lng, geofence_radius_m, created_by)
                 VALUES (:event_name, :event_description, :event_date, :event_time, :location_lat, :location_lng, :geofence_radius_m, :created_by)'
            );
            $stmt->execute([
                'event_name' => $eventName,
                'event_description' => $eventDescription !== '' ? $eventDescription : null,
                'event_date' => $eventDate,
                'event_time' => $eventTime !== '' ? $eventTime : null,
                'location_lat' => $locationLat,
                'location_lng' => $locationLng,
                'geofence_radius_m' => $geofenceRadiusM,
                'created_by' => (int) current_user()['id'],
            ]);
            flash('success', 'Event created successfully.');
        } catch (PDOException $e) {
            error_log('Event Insert Error: ' . $e->getMessage());
            flash('error', 'Database error occurred. Please try again.');
        }
    }

    redirect('/comprog/web/events.php');
}

$editEvent = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['edit']]);
    $editEvent = $stmt->fetch() ?: null;
}

$events = $pdo->query(
    'SELECT e.*, u.full_name AS created_by_name, COUNT(sl.id) AS scan_count FROM events e
     LEFT JOIN users u ON u.id = e.created_by
     LEFT JOIN scan_logs sl ON sl.event_id = e.id
     GROUP BY e.id
     ORDER BY e.event_date DESC'
)->fetchAll();

$title = 'Events - ' . APP_NAME;
require __DIR__ . '/partials/header.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <form method="post" class="card h-100">
            <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0"><?= $editEvent ? 'Edit Event' : 'Create Event' ?></h5>
                    <div class="text-muted" style="font-size: 13px;">Manage attendance events.</div>
                </div>
                <?php if ($editEvent): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="/comprog/web/events.php">Reset</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editEvent['id'] ?? 0)) ?>">
                
                <div class="mb-3">
                    <label class="form-label fw-medium mb-1">Event Name</label>
                    <input name="event_name" value="<?= htmlspecialchars((string) ($editEvent['event_name'] ?? '')) ?>" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-medium mb-1">Description</label>
                    <textarea name="event_description" rows="3" class="form-control"><?= htmlspecialchars((string) ($editEvent['event_description'] ?? '')) ?></textarea>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium mb-1">Date</label>
                        <input name="event_date" type="date" value="<?= htmlspecialchars((string) ($editEvent['event_date'] ?? '')) ?>" class="form-control" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium mb-1">Time</label>
                        <input name="event_time" type="time" value="<?= htmlspecialchars((string) ($editEvent['event_time'] ?? '')) ?>" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium mb-1">Event Location (OpenStreetMap)</label>
                    <div class="text-muted" style="font-size: 13px;">Click the map to set the location, then set a radius in meters. Set radius to 0 to disable geofencing.</div>
                    <div id="eventLocationMap" class="border rounded mt-2" style="height: 260px; overflow: hidden;"></div>
                    <input type="hidden" name="location_lat" id="location_lat" value="<?= htmlspecialchars((string) ($editEvent['location_lat'] ?? '')) ?>">
                    <input type="hidden" name="location_lng" id="location_lng" value="<?= htmlspecialchars((string) ($editEvent['location_lng'] ?? '')) ?>">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium mb-1">Geofence Radius (meters)</label>
                    <input name="geofence_radius_m" id="geofence_radius_m" type="number" min="0" step="1" value="<?= htmlspecialchars((string) ($editEvent['geofence_radius_m'] ?? '0')) ?>" class="form-control">
                </div>
                
                <button class="btn btn-primary w-100 py-2 fw-semibold">Save Event</button>
            </div>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header border-0 pb-0">
                <h5 class="card-title mb-0">Events Schedule</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3 overflow-y-auto" style="max-height: 28rem;">
                    <?php if(empty($events)): ?>
                        <div class="text-center py-4 text-muted">No events created yet.</div>
                    <?php endif; ?>
                    <?php foreach ($events as $event): ?>
                        <div class="p-3 border rounded bg-light border-start border-4 <?= current_user()['role'] === 'Admin' ? 'border-primary' : 'border-secondary' ?>">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($event['event_name']) ?></div>
                                    <div class="text-muted" style="font-size: 13px;">
                                        <i class="fa fa-calendar-alt me-1"></i> <?= htmlspecialchars($event['event_date']) ?> 
                                        <?= $event['event_time'] ? ' <i class="fa fa-clock ms-2 me-1"></i> ' . htmlspecialchars($event['event_time']) : '' ?>
                                    </div>
                                    <?php if ($event['event_description']): ?>
                                        <div class="mt-2 text-secondary" style="font-size: 13.5px;"><?= htmlspecialchars($event['event_description']) ?></div>
                                    <?php endif; ?>

                                    <?php
                                        $hasGeo = ($event['location_lat'] !== null && $event['location_lng'] !== null);
                                        $radius = $event['geofence_radius_m'] !== null ? (int) $event['geofence_radius_m'] : 0;
                                        $geofenceEnabled = ($hasGeo && $radius > 0);
                                    ?>
                                    <div class="mt-2 text-muted" style="font-size: 12px;">
                                        <?php if ($geofenceEnabled): ?>
                                            <span class="badge bg-info-subtle text-info-emphasis me-2">Geofence: <?= $radius ?>m</span>
                                            <span class="js-event-address" data-lat="<?= htmlspecialchars((string) $event['location_lat']) ?>" data-lng="<?= htmlspecialchars((string) $event['location_lng']) ?>">Resolving location…</span>
                                        <?php elseif ($hasGeo): ?>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis me-2">Location set</span>
                                            <span class="js-event-address" data-lat="<?= htmlspecialchars((string) $event['location_lat']) ?>" data-lng="<?= htmlspecialchars((string) $event['location_lng']) ?>">Resolving location…</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis">No location</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-3 text-muted" style="font-size: 12px;">
                                        <span class="badge bg-secondary me-2"><?= (int) $event['scan_count'] ?> scans</span>
                                        Created by <?= htmlspecialchars($event['created_by_name'] ?? 'Unknown') ?>
                                    </div>
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    <a class="btn btn-sm btn-outline-secondary" href="/comprog/web/events.php?edit=<?= (int) $event['id'] ?>">Edit</a>
                                    <form method="post" class="m-0" onsubmit="return confirm('Delete this event?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger w-100">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet (OpenStreetMap) for location picking -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
(function () {
    const mapEl = document.getElementById('eventLocationMap');
    if (!mapEl) return;

    const latInput = document.getElementById('location_lat');
    const lngInput = document.getElementById('location_lng');
    const radiusInput = document.getElementById('geofence_radius_m');

    const parseNum = (v) => {
        if (v === null || v === undefined) return null;
        if (typeof v === 'string' && v.trim() === '') return null;
        const n = Number(v);
        return Number.isFinite(n) ? n : null;
    };

    const initialLat = parseNum(latInput.value);
    const initialLng = parseNum(lngInput.value);

    const map = L.map('eventLocationMap', {
        zoomControl: true,
        attributionControl: true,
    });

    const infoColor = (getComputedStyle(document.documentElement).getPropertyValue('--info') || '').trim() || '#3b82f6';

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;
    let circle = null;

    function getRadius() {
        const r = parseNum(radiusInput.value);
        if (r === null) return 0;
        return Math.max(0, Math.floor(r));
    }

    function setLatLng(lat, lng) {
        latInput.value = String(lat);
        lngInput.value = String(lng);
    }

    function updateCircle(latlng) {
        const r = getRadius();
        if (r <= 0) {
            if (circle) {
                circle.remove();
                circle = null;
            }
            return;
        }

        if (!circle) {
            circle = L.circle(latlng, {
                radius: r,
                color: infoColor,
                fillColor: infoColor,
                fillOpacity: 0.12,
                weight: 2,
            }).addTo(map);
        } else {
            circle.setLatLng(latlng);
            circle.setRadius(r);
        }
    }

    function ensureMarker(latlng) {
        if (!marker) {
            marker = L.marker(latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function () {
                const p = marker.getLatLng();
                setLatLng(p.lat, p.lng);
                updateCircle(p);
            });
        } else {
            marker.setLatLng(latlng);
        }
        updateCircle(latlng);
    }

    map.on('click', function (e) {
        const latlng = e.latlng;
        setLatLng(latlng.lat, latlng.lng);
        ensureMarker(latlng);
    });

    radiusInput.addEventListener('input', function () {
        if (!marker) return;
        updateCircle(marker.getLatLng());
    });

    if (initialLat !== null && initialLng !== null) {
        const latlng = L.latLng(initialLat, initialLng);
        map.setView(latlng, 16);
        ensureMarker(latlng);
    } else {
        // Default pin: Gordon College, Olongapo City (via OSM Nominatim search)
        const fallbackLatLng = L.latLng(14.8386, 120.2842); // Olongapo City fallback
        map.setView(fallbackLatLng, 13);

        const defaultQuery = 'Gordon College, Olongapo City';
        const cacheKey = 'event_default_place_v1:' + defaultQuery;

        const applyDefault = (lat, lng, zoom = 16) => {
            const latlng = L.latLng(lat, lng);
            map.setView(latlng, zoom);
            setLatLng(latlng.lat, latlng.lng);
            ensureMarker(latlng);
        };

        try {
            const cached = localStorage.getItem(cacheKey);
            if (cached) {
                const parsed = JSON.parse(cached);
                if (parsed && Number.isFinite(parsed.lat) && Number.isFinite(parsed.lng)) {
                    applyDefault(parsed.lat, parsed.lng, parsed.zoom || 16);
                    return;
                }
            }
        } catch {
            // ignore cache errors
        }

        const url = new URL('https://nominatim.openstreetmap.org/search');
        url.searchParams.set('format', 'jsonv2');
        url.searchParams.set('q', defaultQuery);
        url.searchParams.set('limit', '1');

        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then((res) => res.ok ? res.json() : Promise.reject(new Error('Geocode failed')))
            .then((rows) => {
                const row = Array.isArray(rows) && rows.length ? rows[0] : null;
                const lat = row && row.lat ? Number(row.lat) : null;
                const lng = row && row.lon ? Number(row.lon) : null;
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) throw new Error('No result');
                applyDefault(lat, lng, 16);
                try {
                    localStorage.setItem(cacheKey, JSON.stringify({ lat, lng, zoom: 16 }));
                } catch {}
            })
            .catch(() => {
                // If geocoding fails (offline/rate-limit), fall back to Olongapo City
                applyDefault(fallbackLatLng.lat, fallbackLatLng.lng, 13);
            });
    }
})();
</script>

<script>
(function () {
    const els = Array.from(document.querySelectorAll('.js-event-address'));
    if (els.length === 0) return;

    const cachePrefix = 'event_address_v1:';

    const cacheKey = (lat, lng) => {
        const latKey = Number(lat).toFixed(5);
        const lngKey = Number(lng).toFixed(5);
        return `${cachePrefix}${latKey},${lngKey}`;
    };

    const readCache = (key) => {
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return null;
            const data = JSON.parse(raw);
            if (!data || typeof data.value !== 'string') return null;
            // Cache for 30 days
            if (typeof data.ts === 'number' && (Date.now() - data.ts) > 30 * 24 * 60 * 60 * 1000) return null;
            return data.value;
        } catch {
            return null;
        }
    };

    const writeCache = (key, value) => {
        try {
            localStorage.setItem(key, JSON.stringify({ value, ts: Date.now() }));
        } catch {
            // ignore storage errors
        }
    };

    async function reverseGeocode(lat, lng) {
        const url = new URL('https://nominatim.openstreetmap.org/reverse');
        url.searchParams.set('format', 'jsonv2');
        url.searchParams.set('lat', String(lat));
        url.searchParams.set('lon', String(lng));
        url.searchParams.set('zoom', '18');
        url.searchParams.set('addressdetails', '0');

        const res = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json'
            }
        });
        if (!res.ok) throw new Error('Geocode failed');
        const data = await res.json();
        return (data && typeof data.display_name === 'string' && data.display_name.trim() !== '')
            ? data.display_name.trim()
            : null;
    }

    // De-dupe requests for same lat/lng
    const inFlight = new Map();

    els.forEach((el) => {
        const latRaw = el.dataset.lat;
        const lngRaw = el.dataset.lng;
        if (!latRaw || !lngRaw) {
            el.textContent = 'Location set';
            return;
        }

        const lat = Number(latRaw);
        const lng = Number(lngRaw);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            el.textContent = 'Location set';
            return;
        }

        const key = cacheKey(lat, lng);
        const cached = readCache(key);
        if (cached) {
            el.textContent = cached;
            return;
        }

        if (!inFlight.has(key)) {
            inFlight.set(key, reverseGeocode(lat, lng)
                .then((name) => {
                    const value = name || 'Location set';
                    writeCache(key, value);
                    return value;
                })
                .catch(() => 'Location set')
            );
        }

        inFlight.get(key).then((value) => {
            el.textContent = value;
        });
    });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
