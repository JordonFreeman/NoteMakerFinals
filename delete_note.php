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

if (!$note_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Note ID is required']);
    exit;
}

// Start transaction to ensure all related operations are performed together
try {
    $pdo->beginTransaction();
    
    // First verify the note belongs to the current user
    $stmt = $pdo->prepare("SELECT note_id FROM notes WHERE note_id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Note not found or you do not have permission']);
        exit;
    }
    
    // Delete associated note labels
    $stmt = $pdo->prepare("DELETE FROM note_labels WHERE note_id = ?");
    $stmt->execute([$note_id]);
    
    // Delete any shared access to this note
    if ($pdo->prepare("SHOW TABLES LIKE 'shared_notes'")->execute() && $pdo->prepare("SHOW TABLES LIKE 'shared_notes'")->rowCount() > 0) {
        $stmt = $pdo->prepare("DELETE FROM shared_notes WHERE note_id = ?");
        $stmt->execute([$note_id]);
    }
    
    // Delete the note itself
    $stmt = $pdo->prepare("DELETE FROM notes WHERE note_id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    
    // Clean up any session variables related to this note
    unset($_SESSION['unlocked_' . $note_id]);
    
    // Commit the transaction
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Note deleted successfully']);
    
} catch (PDOException $e) {
    // Roll back the transaction if any query fails
    $pdo->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}