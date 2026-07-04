#!/usr/bin/env php
<?php
/**
 * zero Random - Set Admin CLI
 *
 * Usage: php setadmin.php <username>
 * Makes the given user a site administrator.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

// Check arguments
if ($argc < 2) {
    echo "Usage: php setadmin.php <username>\n";
    echo "  Makes the specified user a site administrator.\n";
    echo "  The first registered user is automatically admin.\n\n";
    exit(1);
}

$username = trim($argv[1]);

if (empty($username)) {
    echo "Error: Username cannot be empty.\n";
    exit(1);
}

// Ensure DB is initialized
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    echo "Error: Database not initialized. Please run install.php first.\n";
    echo "  http://localhost:8000/install.php\n";
    exit(1);
}

// Check if user exists
$stmt = $db->prepare("SELECT id, username, is_admin FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    echo "Error: User '{$username}' not found.\n";
    exit(1);
}

if ($user['is_admin']) {
    echo "User '{$username}' is already an administrator.\n";
    exit(0);
}

// Set admin
$stmt = $db->prepare("UPDATE users SET is_admin = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->execute([$user['id']]);

echo "✅ User '{$username}' is now a site administrator.\n";