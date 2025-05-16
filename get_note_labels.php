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
    echo json_encode(['success' => false, 'error' => 'Note ID required']);
    exit;
}

try {
    // Check if note belongs to user
    $stmt = $pdo->prepare("SELECT note_id FROM notes WHERE note_id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    if ($stmt->rowCount() === 0) {
        // Note may be shared with user, check shared notes
        $stmt = $pdo->prepare("SELECT note_id FROM shared_notes WHERE note_id = ? AND recipient_id = ? AND verified = 1");
        $stmt->execute([$note_id, $user_id]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Note not found or you do not have permission']);
            exit;
        }
    }
    
    // Get labels for note
    $stmt = $pdo->prepare("
        SELECT l.label_id, l.name
        FROM labels l
        JOIN note_labels nl ON l.label_id = nl.label_id
        WHERE nl.note_id = ?
    ");
    $stmt->execute([$note_id]);
    $labels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'labels' => $labels]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}