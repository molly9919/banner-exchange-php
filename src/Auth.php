<?php

declare(strict_types=1);

namespace App;

final class Auth
{
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function isAdmin(): bool
    {
        return isset($_SESSION['is_admin']) && (bool) $_SESSION['is_admin'];
    }

    public static function requireUser(): void
    {
        if (!self::userId()) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            header('Location: /login.php');
            exit;
        }
    }
}
