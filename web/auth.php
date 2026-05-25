<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function current_user(): ?array
{
    if (empty($_SESSION['user'])) {
        return null;
    }

    return $_SESSION['user'];
}

function login_user(array $user): void
{
    $_SESSION['user'] = $user;
}

function logout_user(): void
{
    unset($_SESSION['user']);
    session_regenerate_id(true);
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /comprog/web/login.php');
        exit;
    }
}

function require_role(array $allowedRoles): void
{
    require_login();
    $user = current_user();

    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}
