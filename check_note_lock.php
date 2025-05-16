<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION["user_id"];
$note_id = $_GET['note_id'] ?? 0;

if (!$note_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Note ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT is_locked FROM notes WHERE note_id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$note) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Note not found or you do not have permission']);
        exit;
    }
    
    $is_locked = (bool)($note['is_locked'] ?? false);
    $is_unlocked = isset($_SESSION['unlocked_' . $note_id]) && $_SESSION['unlocked_' . $note_id] === true;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'is_locked' => $is_locked,
        'is_unlocked' => $is_unlocked
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}