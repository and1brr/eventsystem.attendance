/**
 * ============================================================================
 * Toast Implementation Guide for Existing Pages
 * ============================================================================
 * 
 * This guide shows how to integrate toast notifications into your existing
 * dashboard.php, events.php, student management, and scan.php pages.
 * 
 * ============================================================================
 * 1. DASHBOARD PAGE (dashboard.php)
 * ============================================================================
 * 
 * // Add these includes in <head>
 * <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
 * <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
 * <link rel="stylesheet" href="includes/toasts.css">
 * 
 * // Add before closing </head>
 * <script src="includes/toasts.js"></script>
 * <script src="includes/ajax_toasts.js"></script>
 * 
 * // For logout button, update the link:
 * <a href="logout.php" onclick="confirmLogout(event)">Logout</a>
 * 
 * <script>
 *     function confirmLogout(e) {
 *         e.preventDefault();
 *         showConfirm({
 *             title: 'Logout?',
 *             message: 'Are you sure you want to logout?',
 *             confirmText: 'Logout',
 *             cancelText: 'Cancel',
 *             type: 'warning',
 *             onConfirm: () => {
 *                 showSuccess('Goodbye!', 1500);
 *                 setTimeout(() => {
 *                     window.location.href = 'logout.php';
 *                 }, 1500);
 *             }
 *         });
 *     }
 * </script>
 * 
 * ============================================================================
 * 2. EVENTS PAGE (events.php)
 * ============================================================================
 * 
 * // For Add Event Form:
 * <form id="addEventForm" method="POST" class="space-y-6">
 *     <!-- form fields -->
 *     <button type="submit" class="btn btn-success">Add Event</button>
 * </form>
 * 
 * <script src="includes/toasts.js"></script>
 * <script src="includes/ajax_toasts.js"></script>
 * 
 * <script>
 *     document.getElementById('addEventForm').addEventListener('submit', async (e) => {
 *         e.preventDefault();
 *         
 *         const formData = new FormData(e.target);
 *         const body = Object.fromEntries(formData);
 *         
 *         const result = await fetchWithToast({
 *             url: '/comprog/web/api/events.php',
 *             method: 'POST',
 *             body: body,
 *             successCallback: () => {
 *                 e.target.reset();
 *                 // Refresh events list or reload page
 *                 setTimeout(() => location.reload(), 2000);
 *             }
 *         });
 *     });
 *     
 *     // Delete event
 *     function deleteEvent(eventId, eventName) {
 *         deleteWithConfirmation({
 *             url: `/comprog/web/api/events.php?id=${eventId}`,
 *             itemName: `Event: ${eventName}`,
 *             successCallback: () => {
 *                 setTimeout(() => location.reload(), 1500);
 *             }
 *         });
 *     }
 * </script>
 * 
 * ============================================================================
 * 3. STUDENT MANAGEMENT PAGE
 * ============================================================================
 * 
 * // For Add/Edit Student AJAX:
 * 
 * <script src="includes/toasts.js"></script>
 * <script src="includes/ajax_toasts.js"></script>
 * 
 * <script>
 *     // Add Student
 *     document.getElementById('addStudentBtn')?.addEventListener('click', async () => {
 *         const name = document.getElementById('studentName').value;
 *         const matricNo = document.getElementById('matricNo').value;
 *         
 *         if (!name || !matricNo) {
 *             showWarning('Please fill in all fields');
 *             return;
 *         }
 *         
 *         const result = await fetchWithToast({
 *             url: '/comprog/web/api/students.php',
 *             method: 'POST',
 *             body: { name, matric_no: matricNo },
 *             successCallback: (data) => {
 *                 document.getElementById('studentName').value = '';
 *                 document.getElementById('matricNo').value = '';
 *                 // Refresh student list
 *                 loadStudents();
 *             }
 *         });
 *     });
 *     
 *     // Delete Student
 *     function deleteStudent(studentId) {
 *         deleteWithConfirmation({
 *             url: `/comprog/web/api/students.php?id=${studentId}`,
 *             itemName: 'Student',
 *             successCallback: () => {
 *                 loadStudents(); // Refresh list
 *             }
 *         });
 *     }
 *     
 *     // Update Student
 *     document.getElementById('editStudentForm')?.addEventListener('submit', async (e) => {
 *         e.preventDefault();
 *         
 *         const studentId = document.getElementById('studentId').value;
 *         const formData = new FormData(e.target);
 *         const body = Object.fromEntries(formData);
 *         
 *         const result = await fetchWithToast({
 *             url: `/comprog/web/api/students.php?id=${studentId}`,
 *             method: 'PUT',
 *             body: body,
 *             successCallback: () => {
 *                 e.target.reset();
 *                 loadStudents();
 *             }
 *         });
 *     });
 * </script>
 * 
 * ============================================================================
 * 4. SCANNER PAGE (scan.php)
 * ============================================================================
 * 
 * // For QR Scanner with real-time feedback:
 * 
 * <script src="includes/toasts.js"></script>
 * <script src="includes/ajax_toasts.js"></script>
 * <script src="https://unpkg.com/html5-qrcode"></script>
 * 
 * <script>
 *     let lastScanTime = 0;
 *     const SCAN_COOLDOWN = 2000; // 2 seconds between scans
 *     
 *     function onScanSuccess(decodedText, decodedResult) {
 *         // Prevent duplicate rapid scans
 *         const now = Date.now();
 *         if (now - lastScanTime < SCAN_COOLDOWN) {
 *             showWarning('Scanning too fast, please wait...', 1000);
 *             return;
 *         }
 *         lastScanTime = now;
 *         
 *         // Process the scan
 *         processScan(decodedText);
 *     }
 *     
 *     function onScanFailure(error) {
 *         // Don't show error for every failed scan attempt
 *         // (too many false negatives during scanning)
 *         console.log('Scan failed:', error);
 *     }
 *     
 *     async function processScan(qrData) {
 *         const eventId = document.getElementById('eventId').value;
 *         const attendanceName = document.getElementById('attendanceName').value;
 *         
 *         if (!eventId) {
 *             showWarning('Please select an event first');
 *             return;
 *         }
 *         
 *         showLoading('Processing scan...');
 *         
 *         try {
 *             const response = await fetch('/comprog/web/api/scan.php', {
 *                 method: 'POST',
 *                 headers: {
 *                     'Content-Type': 'application/json'
 *                 },
 *                 body: JSON.stringify({
 *                     qr_data: qrData,
 *                     event_id: eventId,
 *                     attendance_name: attendanceName
 *                 })
 *             });
 *             
 *             const data = await response.json();
 *             closeLoading();
 *             
 *             if (data.success) {
 *                 // Show success with scanner feedback
 *                 showScannerSuccess(
 *                     `${data.data.student_name} - Attendance recorded`
 *                 );
 *                 
 *                 // Add to recent scans list
 *                 addToRecentScans(data.data);
 *             } else {
 *                 showError(data.toast.title, data.toast.details);
 *             }
 *         } catch (error) {
 *             closeLoading();
 *             showError('Scan failed', 'Network or server error');
 *         }
 *     }
 *     
 *     function addToRecentScans(scanData) {
 *         const scanList = document.getElementById('recentScans');
 *         if (!scanList) return;
 *         
 *         const item = document.createElement('div');
 *         item.className = 'scan-item recent-scan';
 *         item.innerHTML = `
 *             <div class="flex justify-between items-center">
 *                 <div>
 *                     <strong>${scanData.student_name}</strong>
 *                     <small>${new Date().toLocaleTimeString()}</small>
 *                 </div>
 *                 <span class="badge badge-success">Recorded</span>
 *             </div>
 *         `;
 *         
 *         scanList.insertBefore(item, scanList.firstChild);
 *         
 *         // Keep only recent 5 scans
 *         while (scanList.children.length > 5) {
 *             scanList.removeChild(scanList.lastChild);
 *         }
 *     }
 *     
 *     // Initialize scanner on page load
 *     document.addEventListener('DOMContentLoaded', () => {
 *         const qrCodeReader = new Html5QrcodeScanner(
 *             'reader',
 *             { fps: 10, qrbox: { width: 250, height: 250 } }
 *         );
 *         
 *         qrCodeReader.render(onScanSuccess, onScanFailure);
 *     });
 * </script>
 * 
 * ============================================================================
 * 5. API RESPONSE FORMAT (For all API endpoints)
 * ============================================================================
 * 
 * // Example using json_helpers.php:
 * 
 * <?php
 * require_once 'includes/json_helpers.php';
 * 
 * // Success response
 * json_success([
 *     'student_id' => 1,
 *     'student_name' => 'John Doe'
 * ], 'Attendance recorded successfully');
 * 
 * // Error response
 * json_error('Duplicate attendance', 
 *            'Student already scanned for this event today', 400);
 * 
 * // Validation error
 * json_validation_error([
 *     'event_id' => 'Event is required',
 *     'qr_data' => 'Invalid QR code format'
 * ]);
 * 
 * // Database error
 * json_database_error('Connection failed: ' . $e->getMessage());
 * ?>
 * 
 * ============================================================================
 * 6. TOAST TYPES REFERENCE
 * ============================================================================
 * 
 * Success (Green):
 *   showSuccess('Student added successfully');
 *   showSuccess('Attendance recorded');
 *   showSuccess('Event updated');
 * 
 * Error (Red):
 *   showError('Failed to add student', 'Database error');
 *   showError('Invalid QR code');
 *   showError('Authentication failed');
 * 
 * Warning (Yellow/Orange):
 *   showWarning('Duplicate attendance scan');
 *   showWarning('Session about to expire');
 *   showWarning('Please fill in all fields');
 * 
 * Info (Blue):
 *   showInfo('Processing your request...');
 *   showInfo('Scanner initialized');
 * 
 * Confirmation Dialog:
 *   showConfirm({
 *       title: 'Delete Student?',
 *       message: 'This cannot be undone',
 *       confirmText: 'Delete',
 *       type: 'delete',
 *       onConfirm: () => { /* delete logic */ }
 *   });
 * 
 * Loading:
 *   showLoading('Processing...');
 *   closeLoading();
 * 
 * ============================================================================
 * 7. COMMON PATTERNS
 * ============================================================================
 * 
 * // Pattern 1: Form Submission
 * form.addEventListener('submit', async (e) => {
 *     e.preventDefault();
 *     showLoading('Submitting...');
 *     
 *     const result = await fetchWithToast({
 *         url: '/api/endpoint',
 *         method: 'POST',
 *         body: new FormData(form),
 *         successCallback: () => form.reset()
 *     });
 * });
 * 
 * // Pattern 2: Delete with Confirmation
 * deleteBtn.addEventListener('click', () => {
 *     deleteWithConfirmation({
 *         url: '/api/item/' + itemId,
 *         itemName: 'Item',
 *         successCallback: () => refreshList()
 *     });
 * });
 * 
 * // Pattern 3: Real-time Updates (Scanner)
 * async function scanItem(data) {
 *     const result = await fetchWithToast({
 *         url: '/api/scan',
 *         method: 'POST',
 *         body: { code: data },
 *         successCallback: (data) => {
 *             playSuccessSound();
 *             highlightSuccess(scannerBox);
 *         }
 *     });
 * }
 * 
 * ============================================================================
 * 8. STYLING CUSTOMIZATION
 * ============================================================================
 * 
 * // Change toast duration
 * showSuccess('Message', 5000); // 5 seconds
 * 
 * // Show loading with disabled outside click
 * showLoading('Processing...');
 * 
 * // Prevent auto-dismiss
 * showToast('Important message', 'info', '', 0);
 * 
 * // Add custom HTML in toast
 * showToast('Student Added', '', '<strong>' + name + '</strong>', 3000);
 * 
 * ============================================================================
 */
