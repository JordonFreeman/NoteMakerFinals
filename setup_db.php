<?php
// Include your updated database connection file
require 'db_connection.php';

echo "Starting database setup...<br>";

try {
    // Read the SQL file
    $sql = file_get_contents('midterm.sql');
    
    // Remove comments and multi-line queries
    $sql = preg_replace('/\/\*.*\*\//s', '', $sql);
    $sql = preg_replace('/--.*\n/', '', $sql);
    
    // Split the SQL file at the semicolons to get individual queries
    $queries = explode(';', $sql);
    
    $count = 0;
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
            $count++;
            echo "Executed query #$count<br>";
        }
    }
    
    echo "Database setup completed successfully! Executed $count queries.";
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "<br>In query: " . $query ?? "unknown");
}
?>