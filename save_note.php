<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION["user_id"];

// Get note details
$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';
$labels = isset($_POST['labels']) && !empty($_POST['labels']) ? explode(',', $_POST['labels']) : [];

try {
    $pdo->beginTransaction();
    
    // Insert the note
    $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute([$user_id, $title, $content]);
    $note_id = $pdo->lastInsertId();
    
    // Process labels - only use existing labels, don't create new ones
    if (!empty($labels)) {
        // Prepare statement for multiple inserts
        $stmt = $pdo->prepare("INSERT INTO note_labels (note_id, label_id) VALUES (?, ?)");
        
        foreach ($labels as $label_id) {
            // Check if label belongs to user before inserting
            $checkStmt = $pdo->prepare("SELECT label_id FROM labels WHERE label_id = ? AND user_id = ?");
            $checkStmt->execute([$label_id, $user_id]);
            
            if ($checkStmt->rowCount() > 0) {
                $stmt->execute([$note_id, $label_id]);
            }
        }
    }
    
    // Process images if any
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $uploaded_images = [];
        $upload_dir = 'uploads/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            $file_name = $_FILES['images']['name'][$key];
            $file_size = $_FILES['images']['size'][$key];
            $file_tmp = $_FILES['images']['tmp_name'][$key];
            $file_type = $_FILES['images']['type'][$key];
            
            // Generate a unique filename
            $new_file_name = uniqid() . '_' . $file_name;
            $upload_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $uploaded_images[] = $upload_path;
            }
        }
        
        if (!empty($uploaded_images)) {
            $images_str = implode(',', $uploaded_images);
            $stmt = $pdo->prepare("UPDATE notes SET images = ? WHERE note_id = ?");
            $stmt->execute([$images_str, $note_id]);
        }
    }
    
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'note_id' => $note_id, 'message' => 'Note created successfully']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}