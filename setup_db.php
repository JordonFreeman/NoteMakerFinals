<?php
require 'db_connection.php';

// Read and execute the SQL from your midterm.sql file
$sql = file_get_contents('midterm.sql');
try {
    $pdo->exec($sql);
    echo "Database setup completed successfully!";
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
?>