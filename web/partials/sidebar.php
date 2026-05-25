<?php
/** Sidebar navigation partial */
$user_role = current_user()['role'] ?? 'Staff';
?>
<aside id="site-sidebar" class="d-none d-md-flex flex-column border-end" style="width: 250px; background: white; position: sticky; top: 0; max-height: 100vh; overflow-y: auto;">
  <div class="p-3">
    <!-- Logo -->
    <div class="d-flex align-items-center gap-3 mb-4">
      <div class="d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <i class="fa fa-qrcode"></i>
      </div>
      <div>
        <div class="mb-0 fw-semibold" style="font-size: 14px;"><?= htmlspecialchars(APP_NAME) ?></div>
        <small class="text-muted"><?= htmlspecialchars($user_role) ?> Panel</small>
      </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="nav flex-column gap-1" style="font-size: 14px;">
      <?php
      $nav = [
        ['uri' => '/comprog/web/dashboard.php', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'roles' => ['Admin', 'Staff']],
        ['uri' => '/comprog/web/students.php', 'icon' => 'fa-user-graduate', 'label' => 'Students', 'roles' => ['Admin']],
        ['uri' => '/comprog/web/scan.php', 'icon' => 'fa-qrcode', 'label' => 'QR Scanner', 'roles' => ['Admin', 'Staff']],
        ['uri' => '/comprog/web/events.php', 'icon' => 'fa-calendar', 'label' => 'Events', 'roles' => ['Admin']],
        ['uri' => '/comprog/web/attendance_logs.php', 'icon' => 'fa-chart-bar', 'label' => 'Attendance Logs', 'roles' => ['Admin', 'Staff']],
        ['uri' => '/comprog/web/logs.php', 'icon' => 'fa-history', 'label' => 'Logs', 'roles' => ['Admin', 'Staff']],
        ['uri' => '/comprog/web/users.php', 'icon' => 'fa-users-cog', 'label' => 'Users', 'roles' => ['Admin']],
      ];

      foreach ($nav as $item):
          if (!in_array($user_role, $item['roles'])) continue;
          $active = strpos($_SERVER['REQUEST_URI'], $item['uri']) !== false;
          $class = $active ? 'nav-link active' : 'nav-link';
      ?>
        <a href="<?= $item['uri'] ?>" class="<?= $class ?> d-flex align-items-center gap-3 px-3 py-2" style="color: #0f172a; text-decoration: none; border-radius: 8px; transition: all 0.15s ease; border-left: 4px solid <?= $active ? '#22c55e' : 'transparent' ?>;">
          <i class="fa <?= $item['icon'] ?>" style="width: 16px; color: #22c55e;"></i>
          <span><?= htmlspecialchars($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="border-top mt-3 pt-3">
      <a href="/comprog/web/logout.php" class="nav-link d-flex align-items-center gap-3 px-3 py-2" style="color: #ef4444; text-decoration: none; border-radius: 8px;">
        <i class="fa fa-sign-out-alt" style="width: 16px;"></i>
        <span>Logout</span>
      </a>
    </div>
  </div>
</aside>
