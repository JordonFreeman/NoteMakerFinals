<?php
require 'db_connection.php';

echo "Starting database setup...<br>";

try {
    // Read the SQL file
    $sql = file_get_contents('midterm.sql');
    
    // Break it into individual queries
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
    die("Database setup failed: " . $e->getMessage() . "<br>In query: " . ($query ?? "unknown"));
}
?>