<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION["user_id"];
$note_id = $_POST['note_id'] ?? null;
$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';

if (!$note_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing note ID']);
    exit;
}

try {
    // Check if user has edit permission
    $stmt = $pdo->prepare("
        SELECT * FROM shared_notes 
        WHERE note_id = ? AND recipient_id = ? AND permission = 'edit' AND verified = 1
    ");
    $stmt->execute([$note_id, $user_id]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$share) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'You do not have permission to edit this note']);
        exit;
    }
    
    // Update the note
    $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, updated_at = NOW() WHERE note_id = ?");
    $stmt->execute([$title, $content, $note_id]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}