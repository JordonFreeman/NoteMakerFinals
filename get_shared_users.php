<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit();
}

header('Content-Type: application/json');

$user_id = $_SESSION["user_id"];
$note_id = $_GET['note_id'] ?? null;

if (!$note_id) {
    echo json_encode(['success' => false, 'error' => 'Missing note ID']);
    exit;
}

try {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT note_id FROM notes WHERE note_id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$note) {
        echo json_encode(['success' => false, 'error' => 'You do not own this note']);
        exit;
    }
    
    // Get shared users
    $stmt = $pdo->prepare("
        SELECT s.recipient_id as user_id, u.email, s.permission, s.verified
        FROM shared_notes s
        JOIN users u ON s.recipient_id = u.user_id
        WHERE s.note_id = ? AND s.owner_id = ?
    ");
    $stmt->execute([$note_id, $user_id]);
    $shared_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $shared_users
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
