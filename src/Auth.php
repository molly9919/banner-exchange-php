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

    public static function moderatorRights(): array
    {
        return $_SESSION['moderator_rights'] ?? [];
    }

    public static function hasRight(string $right): bool
    {
        if (self::isAdmin()) {
            return true;
        }

        return in_array($right, self::moderatorRights(), true);
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

    public static function requireRight(string $right): void
    {
        if (!self::hasRight($right)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }
}
