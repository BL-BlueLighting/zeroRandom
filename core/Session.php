<?php
/**
 * OIManka - Session Management
 *
 * Simple session wrapper with flash messages and auth helpers.
 */

class Session {

    /**
     * Start the session if not already started.
     */
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'httponly' => true,
            ]);
            session_start();
        }
    }

    /**
     * Check if a user is logged in.
     */
    public static function isLoggedIn(): bool {
        self::start();
        return isset($_SESSION['user_id']);
    }

    /**
     * Get the current logged-in user's ID.
     */
    public static function userId(): ?int {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get the current logged-in user's data.
     */
    public static function user(): ?array {
        self::start();
        if (!isset($_SESSION['user_id'])) return null;

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Log a user in by setting session data.
     */
    public static function login(int $userId, string $username): void {
        self::start();
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        session_regenerate_id(true);
    }

    /**
     * Log the current user out.
     */
    public static function logout(): void {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Set a flash message (one-time message shown on next page load).
     */
    public static function flash(string $key, string $message): void {
        self::start();
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Get and clear flash messages.
     */
    public static function getFlash(string $key): ?string {
        self::start();
        if (isset($_SESSION['flash'][$key])) {
            $msg = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $msg;
        }
        return null;
    }

    /**
     * Get all flash messages and clear them.
     */
    public static function getAllFlashes(): array {
        self::start();
        $msgs = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $msgs;
    }

    /**
     * Require authentication - redirect to login if not logged in.
     */
    public static function requireAuth(): void {
        if (!self::isLoggedIn()) {
            self::flash('error', '请先登录后再访问此页面。');
            header('Location: ' . APP_URL . '/login');
            exit;
        }
    }
}
