<?php
/**
 * ============================================================================
 * API Endpoint Template - ToastNotification Examples
 * ============================================================================
 * Save this structure in: /web/api/example.php
 * 
 * This template shows how to use the json_helpers to return API responses
 * that automatically trigger toast notifications on the client side.
 * ============================================================================
 */

declare(strict_types=1);

// Include helpers
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/json_helpers.php';

// Verify user is authenticated
require_login();

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 'Use POST for this endpoint', 405);
}

// Get JSON payload
$payload = json_decode(file_get_contents('php://input'), true);

// ============================================================================
// EXAMPLES OF API RESPONSES
// ============================================================================

// Example 1: Success Response
// POST /api/students.php - Add a student
if (empty($payload['name']) || empty($payload['matric_no'])) {
    json_validation_error([
        'name' => $payload['name'] ? '' : 'Name is required',
        'matric_no' => $payload['matric_no'] ? '' : 'Matric number is required'
    ]);
}

// INSERT into database...
// if successful:
json_success([
    'student_id' => 123,
    'name' => $payload['name'],
    'matric_no' => $payload['matric_no']
], 'Student added successfully');

// end of successful response
//
// ============================================================================
// Example 2: Error Response
// DELETE /api/students.php?id=123 - Delete a student
// if (student has existing scans) {
//     json_error(
//         'Cannot delete student',
//         'This student has 5 existing attendance records. Delete records first.',
//         409
//     );
// }

// ============================================================================
// Example 3: Duplicate/Conflict Response
// POST /api/scan.php - Record attendance
// if (already_scanned_today) {
//     json_warning([
//         'student_id' => $student_id,
//         'scan_time' => $existing_scan_time
//     ], 'Student already scanned for this event today');
// }

// ============================================================================
// Example 4: Database Error (don't expose real error)
// try {
//     // database operation
// } catch (Exception $e) {
//     json_database_error($e->getMessage());
//     // User sees: "Database error - Please try again later"
//     // Error logged internally for debugging
// }

// ============================================================================
// Example 5: Validation Error
// json_validation_error([
//     'email' => 'Invalid email format',
//     'password' => 'Password must be at least 8 characters',
//     'matric_no' => 'Matric number already exists'
// ]);

// ============================================================================
// Example 6: Resource Not Found
// if (!$user) {
//     json_not_found('User');
// }

// ============================================================================
// Example 7: Unauthorized
// if (!user_has_role('Admin')) {
//     json_unauthorized();
// }

// ============================================================================
// Example 8: Unauthenticated (not logged in)
// if (!current_user()) {
//     json_unauthenticated();
// }

// ============================================================================
// RESPONSE FORMAT (what gets sent to browser)
// ============================================================================

/*
{
  "success": true,
  "data": {
    "student_id": 123,
    "name": "John Doe"
  },
  "toast": {
    "type": "success",
    "title": "Student added successfully",
    "details": "",
    "show": true
  }
}

// On client side, the AJAX handler automatically:
// 1. Shows a green success toast with the message
// 2. Calls the successCallback function
// 3. Removes the loading spinner
// 4. Allows the developer to handle the response data

// Usage in HTML/JavaScript:
// await fetchWithToast({
//     url: '/api/students.php',
//     method: 'POST',
//     body: { name: 'John', matric_no: '123456' },
//     successCallback: (data) => {
//         console.log('Student added:', data);
//         refreshStudentsList();
//     }
// });
*/

// ============================================================================
// COMMON API RESPONSE PATTERNS
// ============================================================================

/**
 * Pattern 1: Add/Create Resource
 * 
 * POST /api/students.php
 * 
 * if (validation_fails) {
 *     json_validation_error($errors);
 * }
 * 
 * try {
 *     $stmt = db()->prepare('INSERT INTO students ...');
 *     $stmt->execute($data);
 *     $student_id = db()->lastInsertId();
 *     
 *     json_success([
 *         'student_id' => $student_id,
 *         ...$data
 *     ], 'Student added successfully');
 * } catch (Exception $e) {
 *     json_database_error($e->getMessage());
 * }
 */

/**
 * Pattern 2: Update Resource
 * 
 * PUT /api/students.php?id=123
 * 
 * $student_id = (int)($_GET['id'] ?? 0);
 * if (!$student_id) {
 *     json_error('Invalid student ID', '', 400);
 * }
 * 
 * $stmt = db()->prepare('SELECT * FROM students WHERE id = ?');
 * $stmt->execute([$student_id]);
 * $student = $stmt->fetch();
 * 
 * if (!$student) {
 *     json_not_found('Student');
 * }
 * 
 * try {
 *     $stmt = db()->prepare('UPDATE students SET name = ? WHERE id = ?');
 *     $stmt->execute([$payload['name'], $student_id]);
 *     
 *     json_success(
 *         ['student_id' => $student_id, 'name' => $payload['name']],
 *         'Student updated successfully'
 *     );
 * } catch (Exception $e) {
 *     json_database_error($e->getMessage());
 * }
 */

/**
 * Pattern 3: Delete Resource
 * 
 * DELETE /api/students.php?id=123
 * 
 * $student_id = (int)($_GET['id'] ?? 0);
 * if (!$student_id) {
 *     json_error('Invalid student ID', '', 400);
 * }
 * 
 * // Check for related records
 * $scanCount = db()->query(
 *     'SELECT COUNT(*) FROM scan_logs WHERE student_id = ?',
 *     [$student_id]
 * )->fetchColumn();
 * 
 * if ($scanCount > 0) {
 *     json_error(
 *         'Cannot delete student',
 *         'This student has ' . $scanCount . ' attendance records',
 *         409
 *     );
 * }
 * 
 * try {
 *     $stmt = db()->prepare('DELETE FROM students WHERE id = ?');
 *     $stmt->execute([$student_id]);
 *     
 *     json_success([], 'Student deleted successfully');
 * } catch (Exception $e) {
 *     json_database_error($e->getMessage());
 * }
 */

/**
 * Pattern 4: List/Filter Resources (return as data)
 * 
 * GET /api/students.php?limit=10&offset=0
 * 
 * $limit = (int)($_GET['limit'] ?? 10);
 * $offset = (int)($_GET['offset'] ?? 0);
 * 
 * $stmt = db()->prepare('
 *     SELECT id, name, matric_no, created_at
 *     FROM students
 *     LIMIT ? OFFSET ?
 * ');
 * $stmt->execute([$limit, $offset]);
 * $students = $stmt->fetchAll();
 * 
 * json_success(['students' => $students], 'Students retrieved');
 */

/**
 * Pattern 5: Duplicate Record Check
 * 
 * POST /api/scan.php
 * 
 * $stmt = db()->prepare('
 *     SELECT * FROM scan_logs
 *     WHERE student_id = ? AND event_id = ?
 *     AND DATE(scanned_at) = CURDATE()
 * ');
 * $stmt->execute([$payload['student_id'], $payload['event_id']]);
 * $existing = $stmt->fetch();
 * 
 * if ($existing) {
 *     json_warning(
 *         ['scan_id' => $existing['id'], 'scan_time' => $existing['scanned_at']],
 *         'Duplicate scan detected - Student already recorded today'
 *     );
 * }
 */

// ============================================================================
// AUTHENTICATION EXAMPLE
// ============================================================================

/**
 * POST /api/login.php
 */

// Get credentials
$username = $payload['username'] ?? '';
$password = $payload['password'] ?? '';

// Validate
if (!$username || !$password) {
    json_validation_error([
        'username' => $username ? '' : 'Username required',
        'password' => $password ? '' : 'Password required'
    ]);
}

// Query user
$stmt = db()->prepare('
    SELECT u.id, u.username, u.password_hash, u.full_name, r.name as role
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE u.username = ?
');
$stmt->execute([$username]);
$user = $stmt->fetch();

// Verify password
if (!$user || !password_verify($password, $user['password_hash'])) {
    json_error('Invalid credentials', 'Username or password incorrect', 401);
}

// Login successful - set session and return user data
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

json_success([
    'user_id' => $user['id'],
    'username' => $user['username'],
    'full_name' => $user['full_name'],
    'role' => $user['role']
], 'Login successful');

// ============================================================================
?>
