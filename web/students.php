<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_role(['Admin']);
if (!empty($_GET['download_template'])) {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        http_response_code(500);
        echo 'Missing dependencies. Run composer install.';
        exit;
    }

    require_once $autoloadPath;

    $headers = ['student_id', 'fname', 'lname', 'mname', 'course', 'year_level', 'section', 'status'];
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:H1')->getFont()->setBold(true);
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Send file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="students_import_template.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'import') {
        $upload = $_FILES['import_file'] ?? null;
        if (!$upload || !isset($upload['error'], $upload['tmp_name'], $upload['name'])) {
            flash('error', 'Please choose an Excel file to import.');
            redirect('/comprog/web/students.php');
        }

        if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Upload failed. Please try again.');
            redirect('/comprog/web/students.php');
        }

        $originalName = (string) $upload['name'];
        $tmpPath = (string) $upload['tmp_name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            flash('error', 'Unsupported file type. Please upload .xlsx, .xls, or .csv.');
            redirect('/comprog/web/students.php');
        }

        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoloadPath)) {
            flash('error', 'Excel import dependency is missing. Run composer install first.');
            redirect('/comprog/web/students.php');
        }

        require_once $autoloadPath;

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (empty($rows)) {
                flash('error', 'The uploaded file is empty.');
                redirect('/comprog/web/students.php');
            }

            $normalizeHeader = static function ($value): string {
                $s = strtolower(trim((string) $value));
                $s = preg_replace('/[^a-z0-9]+/', '', $s) ?? '';
                return $s;
            };

            $headerRow = array_shift($rows);
            $headerMap = [];
            foreach ($headerRow as $col => $label) {
                $k = $normalizeHeader($label);
                if ($k === '') {
                    continue;
                }

                $field = match ($k) {
                    'studentid', 'studentno', 'studentnumber', 'id', 'qrcode' => 'student_id',
                    'firstname', 'fname', 'givenname' => 'fname',
                    'lastname', 'lname', 'surname' => 'lname',
                    'middlename', 'mname' => 'mname',
                    'course', 'program' => 'course',
                    'yearlevel', 'year', 'level' => 'year_level',
                    'section' => 'section',
                    'status' => 'status',
                    default => null,
                };

                if ($field) {
                    $headerMap[$col] = $field;
                }
            }

            // If headers weren't recognized, treat the first row as data and use fixed order.
            $useFixedOrder = empty($headerMap);
            if ($useFixedOrder) {
                array_unshift($rows, $headerRow);
            }

            $fixedOrder = ['student_id', 'fname', 'lname', 'mname', 'course', 'year_level', 'section', 'status'];

            $selectByStudentId = $pdo->prepare('SELECT id FROM students WHERE student_id = :student_id LIMIT 1');
            $insertStudent = $pdo->prepare(
                'INSERT INTO students (student_id, fname, lname, mname, course, year_level, section, status)
                 VALUES (:student_id, :fname, :lname, :mname, :course, :year_level, :section, :status)'
            );
            $updateStudent = $pdo->prepare(
                'UPDATE students
                 SET fname = :fname,
                     lname = :lname,
                     mname = :mname,
                     course = :course,
                     year_level = :year_level,
                     section = :section,
                     status = :status
                 WHERE student_id = :student_id'
            );

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            $pdo->beginTransaction();
            foreach ($rows as $idx => $row) {
                // Excel row number for messages (1-based, plus header)
                $rowNumber = $useFixedOrder ? ($idx + 1) : ($idx + 2);

                $data = [
                    'student_id' => '',
                    'fname' => '',
                    'lname' => '',
                    'mname' => '',
                    'course' => '',
                    'year_level' => '',
                    'section' => '',
                    'status' => 'active',
                ];

                if ($useFixedOrder) {
                    $values = array_values($row);
                    foreach ($fixedOrder as $pos => $field) {
                        if (array_key_exists($pos, $values)) {
                            $data[$field] = trim((string) $values[$pos]);
                        }
                    }
                } else {
                    foreach ($headerMap as $col => $field) {
                        $data[$field] = trim((string) ($row[$col] ?? ''));
                    }
                }

                // Skip completely empty rows
                $nonEmpty = array_filter($data, static fn ($v) => trim((string) $v) !== '');
                if (empty($nonEmpty)) {
                    $skipped++;
                    continue;
                }

                $data['student_id'] = trim((string) $data['student_id']);
                $data['fname'] = trim((string) $data['fname']);
                $data['lname'] = trim((string) $data['lname']);

                if ($data['student_id'] === '' || $data['fname'] === '' || $data['lname'] === '') {
                    $skipped++;
                    if (count($errors) < 10) {
                        $errors[] = "Row {$rowNumber}: missing student_id/fname/lname";
                    }
                    continue;
                }

                $statusValue = strtolower(trim((string) $data['status']));
                $data['status'] = in_array($statusValue, ['active', 'inactive'], true) ? $statusValue : 'active';

                $payload = [
                    'student_id' => $data['student_id'],
                    'fname' => $data['fname'],
                    'lname' => $data['lname'],
                    'mname' => $data['mname'] !== '' ? $data['mname'] : null,
                    'course' => $data['course'] !== '' ? $data['course'] : null,
                    'year_level' => $data['year_level'] !== '' ? $data['year_level'] : null,
                    'section' => $data['section'] !== '' ? $data['section'] : null,
                    'status' => $data['status'],
                ];

                $selectByStudentId->execute(['student_id' => $payload['student_id']]);
                $existingId = $selectByStudentId->fetchColumn();
                if ($existingId) {
                    $updateStudent->execute($payload);
                    $updated++;
                } else {
                    $insertStudent->execute($payload);
                    $imported++;
                }
            }
            $pdo->commit();

            $msg = "Import complete: {$imported} added, {$updated} updated";
            if ($skipped > 0) {
                $msg .= ", {$skipped} skipped";
            }

            flash('success', $msg . '.');
            if (!empty($errors)) {
                flash('error', 'Some rows were skipped: ' . implode(' | ', $errors));
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Student Import Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            // Temporarily show sanitized exception message to aid debugging
            $msg = 'Import failed. '; 
            $msg .= 'Error: ' . htmlspecialchars($e->getMessage());
            flash('error', $msg);
        }

        redirect('/comprog/web/students.php');
    }

    $studentId = trim((string) ($_POST['student_id'] ?? ''));
    $fname = trim((string) ($_POST['fname'] ?? ''));
    $lname = trim((string) ($_POST['lname'] ?? ''));
    $mname = trim((string) ($_POST['mname'] ?? ''));
    $course = trim((string) ($_POST['course'] ?? ''));
    $yearLevel = trim((string) ($_POST['year_level'] ?? ''));
    $section = trim((string) ($_POST['section'] ?? ''));
    $status = in_array(($_POST['status'] ?? 'active'), ['active', 'inactive'], true) ? $_POST['status'] : 'active';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash('success', 'Student deleted successfully.');
        } catch (PDOException $e) {
            error_log('Student Delete Error: ' . $e->getMessage());
            flash('error', 'Database error occurred. Please try again.');
        }
        redirect('/comprog/web/students.php');
    }

    if ($studentId === '' || $fname === '' || $lname === '') {
        flash('error', 'Student ID, first name, and last name are required.');
        redirect('/comprog/web/students.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    
    // Check for duplicate Student ID
    $dupCheck = $pdo->prepare('SELECT id FROM students WHERE student_id = :student_id AND id != :id');
    $dupCheck->execute(['student_id' => $studentId, 'id' => $id]);
    if ($dupCheck->fetchColumn()) {
        flash('error', 'Student ID already exists. Please use a unique ID.');
        redirect('/comprog/web/students.php');
    }

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE students
                 SET student_id = :student_id, fname = :fname, lname = :lname, mname = :mname,
                     course = :course, year_level = :year_level, section = :section, status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                'student_id' => $studentId,
                'fname' => $fname,
                'lname' => $lname,
                'mname' => $mname !== '' ? $mname : null,
                'course' => $course !== '' ? $course : null,
                'year_level' => $yearLevel !== '' ? $yearLevel : null,
                'section' => $section !== '' ? $section : null,
                'status' => $status,
                'id' => $id,
            ]);
            flash('success', 'Student updated successfully.');
        } catch (PDOException $e) {
            error_log('Student Update Error: ' . $e->getMessage());
            flash('error', 'Database error occurred. Please try again.');
        }
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO students (student_id, fname, lname, mname, course, year_level, section, status)
                 VALUES (:student_id, :fname, :lname, :mname, :course, :year_level, :section, :status)'
            );
            $stmt->execute([
                'student_id' => $studentId,
                'fname' => $fname,
                'lname' => $lname,
                'mname' => $mname !== '' ? $mname : null,
                'course' => $course !== '' ? $course : null,
                'year_level' => $yearLevel !== '' ? $yearLevel : null,
                'section' => $section !== '' ? $section : null,
                'status' => $status,
            ]);
            flash('success', 'Student added successfully.');
        } catch (PDOException $e) {
            error_log('Student Insert Error: ' . $e->getMessage());
            flash('error', 'Database error occurred. Please try again.');
        }
    }

    redirect('/comprog/web/students.php');
}

$editStudent = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['edit']]);
    $editStudent = $stmt->fetch() ?: null;
}

$query = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT * FROM students';
$params = [];
if ($query !== '') {
    $sql .= ' WHERE student_id LIKE :q OR fname LIKE :q OR lname LIKE :q OR mname LIKE :q';
    $params['q'] = '%' . $query . '%';
}
$sql .= ' ORDER BY lname, fname';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$title = 'Students - ' . APP_NAME;
require __DIR__ . '/partials/header.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <form method="post" class="card h-100">
            <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0"><?= $editStudent ? 'Edit Student' : 'Add Student' ?></h5>
                    <div class="text-muted" style="font-size: 13px;">QR data is the Student ID.</div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/comprog/web/students.php?download_template=1">Download Template</a>
                    <button type="button" id="triggerImportBtn" class="btn btn-sm btn-outline-secondary">Import Excel</button>
                    <?php if ($editStudent): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="/comprog/web/students.php">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editStudent['id'] ?? 0)) ?>">
                
                <div class="mb-3">
                    <label class="form-label fw-medium mb-1">Student ID</label>
                    <input name="student_id" value="<?= htmlspecialchars((string) ($editStudent['student_id'] ?? '')) ?>" class="form-control" required>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium mb-1">First Name</label>
                        <input name="fname" value="<?= htmlspecialchars((string) ($editStudent['fname'] ?? '')) ?>" class="form-control" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium mb-1">Last Name</label>
                        <input name="lname" value="<?= htmlspecialchars((string) ($editStudent['lname'] ?? '')) ?>" class="form-control" required>
                    </div>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium mb-1">Middle Name</label>
                        <input name="mname" value="<?= htmlspecialchars((string) ($editStudent['mname'] ?? '')) ?>" class="form-control">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium mb-1">Course</label>
                        <input name="course" value="<?= htmlspecialchars((string) ($editStudent['course'] ?? '')) ?>" class="form-control">
                    </div>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium mb-1">Year Level</label>
                        <input name="year_level" value="<?= htmlspecialchars((string) ($editStudent['year_level'] ?? '')) ?>" class="form-control">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium mb-1">Section</label>
                        <input name="section" value="<?= htmlspecialchars((string) ($editStudent['section'] ?? '')) ?>" class="form-control">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-medium mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= (($editStudent['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($editStudent['status'] ?? 'active') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Save Student</button>

                
            </div>
        </form>

        <!-- Hidden import form triggered by the Import Excel button -->
        <form id="studentImportForm" method="post" enctype="multipart/form-data" class="d-none">
            <input type="hidden" name="action" value="import">
            <input type="file" name="import_file" id="studentImportFile" accept=".xlsx,.xls,.csv">
        </form>

        <script>
        (function(){
            var trigger = document.getElementById('triggerImportBtn');
            var fileInput = document.getElementById('studentImportFile');
            if(trigger && fileInput){
                trigger.addEventListener('click', function(){ fileInput.click(); });
                fileInput.addEventListener('change', function(){ if(this.files && this.files.length>0){ document.getElementById('studentImportForm').submit(); } });
            }
        })();
        </script>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header border-0 pb-0">
                <form class="d-flex gap-2" method="get">
                    <input name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Search student ID or name" class="form-control">
                    <button class="btn btn-outline-secondary px-4">Search</button>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>QR Code</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr class="align-middle">
                                    <td>
                                        <div class="fw-medium text-dark"><?= htmlspecialchars($student['lname'] . ', ' . $student['fname']) ?></div>
                                        <div class="text-muted" style="font-size: 13px;"><?= htmlspecialchars($student['student_id']) ?></div>
                                        <div class="text-muted" style="font-size: 12px;"><?= htmlspecialchars(trim(($student['course'] ?? '') . ' ' . ($student['year_level'] ?? '') . ' ' . ($student['section'] ?? ''))) ?></div>
                                    </td>
                                    <td>
                                        <img alt="QR code" class="rounded border p-1 bg-white" style="height: 60px; width: 60px;" src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= urlencode((string) $student['student_id']) ?>">
                                    </td>
                                    <td>
                                        <?php if($student['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary" href="/comprog/web/students.php?edit=<?= (int) $student['id'] ?>">Edit</a>
                                        <form method="post" class="d-inline m-0" onsubmit="return confirm('Delete this student?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No students found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
