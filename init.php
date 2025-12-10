<?php
require_once 'config.php';

// This will create the database and tables if they don't exist
$db = initDB();

echo "Database initialization complete!\n";
echo "Admin user created with username: admin, password: admin123\n";
?>
