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
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash('success', 'User deleted successfully.');
        } catch (PDOException $e) {
            error_log('User Delete Error: ' . $e->getMessage());
            flash('error', 'Database error occurred. Please try again.');
        }
        redirect('/comprog/web/users.php');
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $roleId = (int) ($_POST['role_id'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $fullName === '' || $roleId <= 0) {
        flash('error', 'Username, full name, and role are required.');
        redirect('/comprog/web/users.php');
    }

    // Check for duplicate username
    $dupUsername = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id != :id');
    $dupUsername->execute(['username' => $username, 'id' => $id]);
    if ($dupUsername->fetchColumn()) {
        flash('error', 'Username already exists. Please choose a different username.');
        redirect('/comprog/web/users.php');
    }

    // Check for duplicate email (if provided)
    if ($email !== '') {
        $dupEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
        $dupEmail->execute(['email' => $email, 'id' => $id]);
        if ($dupEmail->fetchColumn()) {
            flash('error', 'Email already exists. Please use a different email.');
            redirect('/comprog/web/users.php');
        }
    }

    if ($id > 0) {
        try {
            $sql = 'UPDATE users SET username = :username, full_name = :full_name, email = :email, role_id = :role_id, is_active = :is_active';
            $params = [
                'username' => $username,
                'full_name' => $fullName,
                'email' => $email !== '' ? $email : null,
                'role_id' => $roleId,
                'is_active' => $isActive,
                'id' => $id,
            ];
            if ($password !== '') {
                $sql .= ', password_hash = :password_hash';
                $params['password_hash'] = 'sha256:' . base64_encode(hash('sha256', $password, true));
            }
            $sql .= ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            flash('success', 'User updated successfully.');
        } catch (PDOException $e) {
            error_log('User Update Error: ' . $e->getMessage());
            flash('error', 'Database error occurred. Please try again.');
        }
    } else {
        if ($password === '') {
            flash('error', 'Password is required for new users.');
            redirect('/comprog/web/users.php');
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, password_hash, full_name, email, role_id, is_active)
                 VALUES (:username, :password_hash, :full_name, :email, :role_id, :is_active)'
            );
            $stmt->execute([
                'username' => $username,
                'password_hash' => 'sha256:' . base64_encode(hash('sha256', $password, true)),
                'full_name' => $fullName,
                'email' => $email !== '' ? $email : null,
                'role_id' => $roleId,
                'is_active' => $isActive,
            ]);
            flash('success', 'User added successfully.');
        } catch (PDOException $e) {
            error_log('User Insert Error: ' . $e->getMessage());
            flash('error', 'Database error occurred. Please try again.');
        }
    }

    redirect('/comprog/web/users.php');
}

$editUser = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['edit']]);
    $editUser = $stmt->fetch() ?: null;
}

$roles = $pdo->query('SELECT * FROM roles ORDER BY name')->fetchAll();
$search = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT u.*, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE u.username LIKE :q OR u.full_name LIKE :q OR u.email LIKE :q';
    $params['q'] = '%' . $search . '%';
}
$sql .= ' ORDER BY u.full_name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$title = 'Users - ' . APP_NAME;
require __DIR__ . '/partials/header.php';
?>

<div class="row g-4">
    <div class="col-lg-4">
        <form method="post" class="card h-100">
            <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0"><?= $editUser ? 'Edit User' : 'Add User' ?></h5>
                    <div class="text-muted" style="font-size: 13px;">Manage admin and staff access.</div>
                </div>
                <?php if ($editUser): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="/comprog/web/users.php">Reset</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editUser['id'] ?? 0)) ?>">
                
                <div class="mb-3">
                    <label class="form-label fw-medium mb-1">Username</label>
                    <input name="username" value="<?= htmlspecialchars((string) ($editUser['username'] ?? '')) ?>" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-medium mb-1">Full Name</label>
                    <input name="full_name" value="<?= htmlspecialchars((string) ($editUser['full_name'] ?? '')) ?>" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-medium mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars((string) ($editUser['email'] ?? '')) ?>" class="form-control">
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-medium mb-1">Role</label>
                    <select name="role_id" class="form-select" required>
                        <option value="">Select Role...</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>" <?= (int) ($editUser['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-medium mb-1">Password <?= $editUser ? '<span class="text-muted fw-normal" style="font-size: 12px;">(leave blank to keep current)</span>' : '' ?></label>
                    <input type="password" name="password" class="form-control" <?= $editUser ? '' : 'required' ?>>
                </div>
                
                <div class="mb-4 form-check">
                    <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input" <?= (($editUser['is_active'] ?? 1) ? 'checked' : '') ?>>
                    <label class="form-check-label" for="is_active">User is Active</label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Save User</button>
            </div>
        </form>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header border-0 pb-0">
                <form class="d-flex gap-2" method="get">
                    <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search user accounts" class="form-control">
                    <button class="btn btn-outline-secondary px-4">Search</button>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium text-dark"><?= htmlspecialchars($user['full_name']) ?></div>
                                        <div class="text-muted" style="font-size: 13px;">@<?= htmlspecialchars($user['username']) ?></div>
                                        <?php if($user['email']): ?>
                                            <div class="text-muted" style="font-size: 12px;"><i class="fa fa-envelope me-1"></i><?= htmlspecialchars((string) $user['email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $user['role_name'] === 'Admin' ? 'bg-primary' : 'bg-secondary' ?>">
                                            <?= htmlspecialchars($user['role_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if((int) $user['is_active'] === 1): ?>
                                            <span class="text-success" style="font-size: 14px;"><i class="fa fa-check-circle me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="text-danger" style="font-size: 14px;"><i class="fa fa-times-circle me-1"></i>Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary" href="/comprog/web/users.php?edit=<?= (int) $user['id'] ?>">Edit</a>
                                        <?php if((int) $user['id'] !== (int) current_user()['id']): ?>
                                        <form method="post" class="d-inline m-0" onsubmit="return confirm('Delete this user?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No users found.</td>
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
