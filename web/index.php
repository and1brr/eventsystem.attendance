<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (current_user()) {
    redirect('/comprog/web/dashboard.php');
}

redirect('/comprog/web/login.php');
