<?php
session_start();

define('DB_FILE', __DIR__ . '/ads.db');

// Create database and tables if they don't exist
function initDB() {
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create users table
        $db->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Create ads table
        $db->exec('CREATE TABLE IF NOT EXISTS ads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            video_link TEXT NOT NULL,
            gender TEXT NOT NULL,
            age_groups TEXT NOT NULL,
            is_default INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Add is_default column if it doesn't exist (for existing databases)
        try {
            $db->exec('ALTER TABLE ads ADD COLUMN is_default INTEGER DEFAULT 0');
        } catch (PDOException $e) {
            // Column already exists, ignore error
        }
        
        // Create default admin user if not exists
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM users WHERE username = :username');
        $stmt->execute([':username' => 'admin']);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (:username, :password)');
            $stmt->execute([
                ':username' => 'admin',
                ':password' => $hashedPassword
            ]);
        }
        
        return $db;
    } catch (PDOException $e) {
        die('Database connection failed: ' . $e->getMessage());
    }
}

// Get database connection
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_FILE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $db;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}
?>
