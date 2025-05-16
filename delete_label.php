<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION["user_id"];
$label_id = $_POST['label_id'] ?? null;

if (!$label_id) {
    echo json_encode(['success' => false, 'error' => 'Missing label ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    // First verify the label belongs to the current user
    $stmt = $pdo->prepare("SELECT label_id FROM labels WHERE label_id = ? AND user_id = ?");
    $stmt->execute([$label_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Label not found or you do not have permission']);
        exit;
    }

    // First, remove all note-label associations
    $stmt = $pdo->prepare("DELETE FROM note_labels WHERE label_id = ?");
    $stmt->execute([$label_id]);

    // Then delete the label itself
    $stmt = $pdo->prepare("DELETE FROM labels WHERE label_id = ? AND user_id = ?");
    $stmt->execute([$label_id, $user_id]);

    $stmt = $pdo->prepare("SELECT label_id FROM labels WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $labels = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // For each label, delete note_label associations
    foreach ($labels as $label_id) {
        $stmt = $pdo->prepare("DELETE FROM note_labels WHERE label_id = ?");
        $stmt->execute([$label_id]);
    }

    // Now delete all labels for this user
    $stmt = $pdo->prepare("DELETE FROM labels WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Then you can delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Label deleted successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
