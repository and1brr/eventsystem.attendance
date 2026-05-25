<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_role(['Admin', 'Staff']);

$pdo = db();
$events = $pdo->query('SELECT id, event_name, event_date, location_lat, location_lng, geofence_radius_m FROM events WHERE event_date >= CURDATE() ORDER BY event_date DESC')->fetchAll();

$eventGeofences = [];
foreach ($events as $event) {
    $eventGeofences[(int) $event['id']] = [
        'location_lat' => $event['location_lat'] !== null ? (float) $event['location_lat'] : null,
        'location_lng' => $event['location_lng'] !== null ? (float) $event['location_lng'] : null,
        'geofence_radius_m' => $event['geofence_radius_m'] !== null ? (int) $event['geofence_radius_m'] : null,
    ];
}

$title = 'Scan QR - ' . APP_NAME;
require __DIR__ . '/partials/header.php';
?>

<div class="container-fluid py-3">
    <div class="row g-3">
        <!-- Scanner Section -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header border-0 pb-0">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <h4 class="card-title mb-1">QR Scanner</h4>
                            <p class="text-muted mb-0" style="font-size: 13px;">Scan student QR codes to record attendance</p>
                        </div>
                        <div class="text-end" style="font-size: 13px;">
                            <div id="currentTime" class="fw-semibold"></div>
                            <div id="currentDate" class="text-muted"></div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Event & Scan Type Selection -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Event</label>
                            <select id="eventSelect" class="form-select">
                                <option value="">No Event</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?= (int) $event['id'] ?>"><?= htmlspecialchars($event['event_name'] . ' (' . $event['event_date'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Scan Type</label>
                            <select id="scanType" class="form-select">
                                <option value="entry">Entry</option>
                                <option value="exit">Exit</option>
                            </select>
                        </div>
                    </div>

                    <!-- Camera Selection & Controls -->
                    <div class="mb-4 d-flex gap-2">
                        <select id="cameraSelect" class="form-select flex-grow-1">
                            <option>Select Camera...</option>
                        </select>
                        <button id="startScan" class="btn btn-primary px-3">Start Scanning</button>
                        <button id="stopScan" class="btn btn-secondary px-3 d-none">Stop</button>
                    </div>

                    <!-- Scanner Container - FIXED SIZE -->
                    <div class="d-flex justify-content-center mb-4">
                        <div id="reader" class="scanner-container position-relative">
                            <div class="position-absolute top-50 start-50 translate-middle text-muted" style="font-size: 13px;">Camera preview</div>
                            <!-- Corner markers -->
                            <div class="corner-marker corner-tl"></div>
                            <div class="corner-marker corner-tr"></div>
                            <div class="corner-marker corner-bl"></div>
                            <div class="corner-marker corner-br"></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <p id="scanMessage" class="text-muted" style="font-size: 13px;">Select event and start scanner. Recent scans appear on the right.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Scans Panel -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header border-0 pb-0">
                    <h5 class="card-title mb-0">Recent Scans</h5>
                </div>
                <div class="card-body">
                    <div id="recentScans" style="max-height: 400px; overflow-y: auto;">
                        <div class="text-muted text-center py-5" style="font-size: 13px;">No scans yet</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const EVENT_GEOFENCES = <?= json_encode($eventGeofences, JSON_UNESCAPED_SLASHES) ?>;

    // ========== UI Elements ==========
    const message = document.getElementById('scanMessage');
    const scanType = document.getElementById('scanType');
    const cameraSelect = document.getElementById('cameraSelect');
    const startBtn = document.getElementById('startScan');
    const stopBtn = document.getElementById('stopScan');
    const eventSelect = document.getElementById('eventSelect');
    const recentScansContainer = document.getElementById('recentScans');

    let html5QrCode = null;
    const cooldownMap = new Map();
    const COOLDOWN_SECONDS = 30;
    const recentScans = [];
    const MAX_RECENT = 5;

    let lastPosition = null;
    const POSITION_CACHE_MS = 10_000;

    // ========== Clock & Date Display ==========
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();

    // ========== Message Display ==========
    function setMessage(text, status = 'info') {
        message.textContent = text;
        const colorClass = status === 'success' ? 'text-success' : status === 'error' ? 'text-danger' : 'text-muted';
        message.className = colorClass;
        message.style.fontSize = '13px';
    }

    // ========== Recent Scans Panel ==========
    function addRecentScan(code, name, status, pos = null) {
        const timestamp = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        recentScans.unshift({ code, name, status, timestamp, pos });
        if (recentScans.length > MAX_RECENT) recentScans.pop();
        updateRecentScansDisplay();
    }

    function updateRecentScansDisplay() {
        recentScansContainer.innerHTML = recentScans.map(scan => {
            const bgColor = scan.status === 'success' ? 'rgba(34,197,94,0.1)' : scan.status === 'error' ? 'rgba(239,68,68,0.1)' : 'rgba(249,115,22,0.1)';
            const borderColor = scan.status === 'success' ? '4px solid #22c55e' : scan.status === 'error' ? '4px solid #ef4444' : '4px solid #f97316';
            const textColor = scan.status === 'success' ? '#166534' : scan.status === 'error' ? '#7f1d1d' : '#92400e';
            const hasPos = scan.pos && typeof scan.pos.lat === 'number' && typeof scan.pos.lng === 'number';
            // Always make the item clickable; if no scan pos, show user's current device location when clicked
            const latArg = hasPos ? scan.pos.lat : 'null';
            const lngArg = hasPos ? scan.pos.lng : 'null';
            const accArg = hasPos ? (scan.pos.accuracy || 0) : 0;
            const clickAttr = `onclick="showScanLocation(${latArg}, ${lngArg}, ${accArg})" style="cursor: pointer;"`;
            const hint = '<small style="opacity:0.8; margin-left:6px;">(show map)</small>';
            return `
                <div ${clickAttr} style="background: ${bgColor}; border-left: ${borderColor}; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 8px; color: ${textColor};">
                    <div style="display:flex; align-items:center; justify-content:space-between"><div style="font-weight: 600;">${scan.code}</div>${hint}</div>
                    <div style="font-size: 11px; opacity: 0.8;">${scan.name || 'Manual Entry'}</div>
                    <div style="font-size: 11px; margin-top: 4px;">${scan.timestamp}</div>
                </div>
            `;
        }).join('') || '<div style="font-size: 13px; color: #9ca3af; text-align: center; padding: 20px 0;">No scans yet</div>';
    }

    // ========== Submit Code with Event & Attendance Name ==========
    async function submitCode(code) {
        if (!code) {
            showWarning('Please enter a student ID');
            return;
        }
        try {
            showLoading('Processing scan...');

            const selectedEventId = eventSelect.value ? parseInt(eventSelect.value) : null;
            const geo = selectedEventId ? EVENT_GEOFENCES[selectedEventId] : null;
            let devicePos = null;

            if (geo && geo.location_lat !== null && geo.location_lng !== null && geo.geofence_radius_m !== null && geo.geofence_radius_m > 0) {
                devicePos = await getDevicePosition();
                if (!devicePos) {
                    closeLoading();
                    showError('Location required', 'Enable location permission to scan for this event.');
                    setMessage('Location permission required for this event', 'error');
                    // include event center if available for context
                    const eventCenter = (geo && geo.location_lat !== null && geo.location_lng !== null) ? { lat: geo.location_lat, lng: geo.location_lng, accuracy: 0 } : null;
                    addRecentScan(code, 'Geofence', 'error', eventCenter);
                    return;
                }

                const distM = haversineMeters(devicePos.lat, devicePos.lng, geo.location_lat, geo.location_lng);
                if (distM > geo.geofence_radius_m) {
                    closeLoading();
                    showError('Outside event area', `You are about ${Math.round(distM)}m away (limit: ${geo.geofence_radius_m}m).`);
                    setMessage('Outside event geofence', 'error');
                    addRecentScan(code, 'Geofence', 'error', devicePos || (geo && geo.location_lat !== null ? { lat: geo.location_lat, lng: geo.location_lng, accuracy: 0 } : null));
                    return;
                }
            }

            const payload = {
                student_id: code,
                scan_type: scanType.value,
                event_id: selectedEventId
            };

            if (devicePos) {
                payload.device_lat = devicePos.lat;
                payload.device_lng = devicePos.lng;
                payload.device_accuracy_m = devicePos.accuracy;
            }
            const response = await fetch('/comprog/web/api/scan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            closeLoading();
            
                if (result.ok) {
                showScannerSuccess(result.message || 'Scan recorded successfully');
                setMessage(result.message || 'Scan recorded', 'success');
                // include device position in recent scans when available
                addRecentScan(code, result.status === 'duplicate' ? 'Duplicate' : scanType.value, 'success', devicePos || (geo && geo.location_lat !== null ? { lat: geo.location_lat, lng: geo.location_lng, accuracy: 0 } : null));
                // Highlight scanner box briefly
                highlightSuccess(document.getElementById('reader'), 800);
            } else {
                showError(result.message || 'Scan failed', '', 3000);
                setMessage(result.message || 'Scan failed', 'error');
                addRecentScan(code, 'Error', 'error');
            }
        } catch (e) {
            closeLoading();
            showError('Network error', e.message);
            setMessage('Network error: ' + e.message, 'error');
            addRecentScan(code, 'Error', 'error');
        }
    }

    function haversineMeters(lat1, lon1, lat2, lon2) {
        const R = 6371000; // meters
        const toRad = (d) => d * Math.PI / 180;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(a)));
    }

    async function getDevicePosition() {
        const now = Date.now();
        if (lastPosition && (now - lastPosition.timestamp) < POSITION_CACHE_MS) {
            return lastPosition;
        }

        if (!navigator.geolocation) {
            return null;
        }

        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    lastPosition = {
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy: pos.coords.accuracy,
                        timestamp: Date.now(),
                    };
                    resolve(lastPosition);
                },
                () => resolve(null),
                { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 }
            );
        });
    }

    // Manual entry removed for cleaner UI

    // ========== Camera Enumeration ==========
    async function populateCameras() {
        try {
            const cams = await Html5Qrcode.getCameras();
            cameraSelect.innerHTML = '<option>Select Camera...</option>';
            cams.forEach(cam => {
                const opt = document.createElement('option');
                opt.value = cam.id;
                opt.textContent = cam.label || 'Camera ' + cam.id;
                cameraSelect.appendChild(opt);
            });
            if (cams.length > 0) {
                const back = cams.find(c => /back|rear|environment/i.test(c.label));
                cameraSelect.value = back ? back.id : cams[0].id;
                showInfo('Camera ready', 1500);
            } else {
                cameraSelect.disabled = true;
                setMessage('No cameras found', 'error');
                showWarning('No cameras found on this device');
            }
        } catch (e) {
            cameraSelect.disabled = true;
            setMessage('Camera access denied', 'error');
            showError('Camera access denied', 'Please grant camera permissions to scan QR codes');
        }
    }

    // ========== Scanner Control ==========
    async function startScanner() {
        if (html5QrCode) {
            try { await html5QrCode.stop(); } catch (e) { }
            html5QrCode.clear();
            html5QrCode = null;
        }

        const selectedCamera = cameraSelect.value;
        if (!selectedCamera || selectedCamera === 'Select Camera...') {
            setMessage('Please select a camera', 'error');
            showWarning('Please select a camera first');
            return;
        }

        try {
            showLoading('Starting scanner...');
            await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: selectedCamera } } });
        } catch (err) {
            setMessage('Camera permission denied', 'error');
            showError('Camera permission denied', 'Please grant camera access in your browser settings');
            closeLoading();
            return;
        }

        const reader = document.getElementById('reader');
        html5QrCode = new Html5Qrcode('reader');
        const config = { fps: 20, qrbox: { width: 250, height: 250 }, disableFlip: false };

        html5QrCode.start(
            { deviceId: { exact: selectedCamera } },
            config,
            (decodedText, result) => {
                const now = Date.now();
                const last = cooldownMap.get(decodedText) || 0;
                if (now - last < COOLDOWN_SECONDS * 1000) {
                    return; // Ignore duplicate rapid scans
                }
                cooldownMap.set(decodedText, now);
                submitCode(decodedText);
            },
            (errorMessage) => {
                // Per-frame decode errors are ignored
            }
        ).then(() => {
            setMessage('Scanning active...', 'info');
            startBtn.classList.add('d-none');
            stopBtn.classList.remove('d-none');
            closeLoading();
            showInfo('Scanner initialized - Ready to scan QR codes', 2000);
        }).catch(err => {
            setMessage('Unable to start scanner', 'error');
            showError('Scanner initialization failed', 'Please try again');
            closeLoading();
        });
    }

    async function stopScanner() {
        if (html5QrCode) {
            try {
                await html5QrCode.stop();
                html5QrCode.clear();
            } catch (e) { }
            html5QrCode = null;
        }
        const reader = document.getElementById('reader');
        reader.innerHTML = `
            <div class="position-absolute top-50 start-50 translate-middle text-muted" style="font-size: 13px;">Camera preview</div>
            <div class="corner-marker corner-tl"></div>
            <div class="corner-marker corner-tr"></div>
            <div class="corner-marker corner-bl"></div>
            <div class="corner-marker corner-br"></div>
        `;
        setMessage('Scanning stopped', 'info');
        startBtn.classList.remove('d-none');
        stopBtn.classList.add('d-none');
    }

    startBtn.addEventListener('click', startScanner);
    stopBtn.addEventListener('click', stopScanner);

    // ========== Initialize ==========
    document.addEventListener('DOMContentLoaded', populateCameras);
    populateCameras();
</script>

<!-- Leaflet (OpenStreetMap) for showing scan locations -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<!-- Map modal for scan locations -->
<div class="modal fade" id="scanLocationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Scan Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="scanLocationMap" style="height:400px; width:100%;"></div>
            </div>
        </div>
    </div>
</div>

<script>
// Leaflet map instance for modal
let _scanLocationMap = null;
let _scanLocationMarker = null;
let _scanEventMarker = null;
let _scanEventCircle = null;
let _scanDeviceMarker = null;
function showScanLocation(lat, lng, accuracy) {
    if (typeof L === 'undefined') return;
    const modalEl = document.getElementById('scanLocationModal');
    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();

    const renderAt = (latNum, lngNum, accNum) => {
        setTimeout(() => {
            if (!_scanLocationMap) {
                _scanLocationMap = L.map('scanLocationMap', { zoomControl: true, attributionControl: true });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(_scanLocationMap);
            }
            const latlng = L.latLng(latNum, lngNum);
            _scanLocationMap.setView(latlng, 16);
            if (!_scanLocationMarker) {
                _scanLocationMarker = L.marker(latlng).addTo(_scanLocationMap);
            } else {
                _scanLocationMarker.setLatLng(latlng);
            }
            // show accuracy circle if provided
            if (typeof accNum === 'number' && accNum > 0) {
                if (!_scanLocationMap._accuracyCircle) {
                    _scanLocationMap._accuracyCircle = L.circle(latlng, { radius: accNum, color: '#3b82f6', fillColor: '#3b82f6', fillOpacity: 0.12 }).addTo(_scanLocationMap);
                } else {
                    _scanLocationMap._accuracyCircle.setLatLng(latlng);
                    _scanLocationMap._accuracyCircle.setRadius(accNum);
                }
            }

            // Also show event center and geofence if event selected
            try {
                const selectedEventId = eventSelect && eventSelect.value ? parseInt(eventSelect.value) : null;
                const geo = selectedEventId ? EVENT_GEOFENCES[selectedEventId] : null;
                if (geo && geo.location_lat !== null && geo.location_lng !== null && geo.geofence_radius_m !== null && geo.geofence_radius_m > 0) {
                    const evLatLng = L.latLng(geo.location_lat, geo.location_lng);
                    // event marker
                    if (!_scanEventMarker) {
                        _scanEventMarker = L.marker(evLatLng).addTo(_scanLocationMap);
                    } else {
                        _scanEventMarker.setLatLng(evLatLng);
                    }
                    // event radius circle
                    if (!_scanEventCircle) {
                        _scanEventCircle = L.circle(evLatLng, { radius: geo.geofence_radius_m, color: '#16a34a', fillColor: '#16a34a', fillOpacity: 0.06 }).addTo(_scanLocationMap);
                    } else {
                        _scanEventCircle.setLatLng(evLatLng);
                        _scanEventCircle.setRadius(geo.geofence_radius_m);
                    }

                    // show device marker as red if outside radius, green if inside
                    const dist = typeof latNum === 'number' && typeof lngNum === 'number' ? haversineMeters(latNum, lngNum, geo.location_lat, geo.location_lng) : null;
                    const inside = dist !== null ? (dist <= geo.geofence_radius_m) : null;
                    const deviceLatLng = L.latLng(latNum, lngNum);
                    if (!_scanDeviceMarker) {
                        _scanDeviceMarker = L.circleMarker(deviceLatLng, { radius: 8, color: inside ? '#16a34a' : '#ef4444', fillColor: inside ? '#16a34a' : '#ef4444', fillOpacity: 0.9 }).addTo(_scanLocationMap);
                    } else {
                        _scanDeviceMarker.setLatLng(deviceLatLng);
                        _scanDeviceMarker.setStyle({ color: inside ? '#16a34a' : '#ef4444', fillColor: inside ? '#16a34a' : '#ef4444' });
                    }
                    // zoom to show both markers
                    const group = L.featureGroup([_scanEventMarker, _scanDeviceMarker]);
                    _scanLocationMap.fitBounds(group.getBounds().pad(0.5));
                    return;
                }
            } catch (err) {
                // ignore and continue
            }
            _scanLocationMap.invalidateSize();
        }, 250);
    };

    // If lat/lng passed are null, attempt to get current device position
    if (lat === null || lng === null) {
        if (!navigator.geolocation) {
            alert('Geolocation not supported by your browser');
            return;
        }
        // show temporary UI feedback
        setMessage('Fetching device location...', 'info');
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setMessage('Showing device location', 'success');
                renderAt(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy || 0);
            },
            (err) => {
                setMessage('Unable to get device location', 'error');
                alert('Unable to obtain device location. Please allow location access.');
            },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    } else {
        renderAt(lat, lng, accuracy || 0);
    }
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
