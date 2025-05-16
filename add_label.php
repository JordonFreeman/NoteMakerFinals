<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"] ?? 0;
$label_name = trim($_POST['name'] ?? '');

if (!$label_name) {
    echo json_encode(['success' => false, 'error' => 'Label name is required']);
    exit;
}

// Kiểm tra xem nhãn đã tồn tại chưa
$stmt = $pdo->prepare("SELECT COUNT(*) FROM labels WHERE user_id = ? AND name = ?");
$stmt->execute([$user_id, $label_name]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'error' => 'Label already exists']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO labels (user_id, name) VALUES (?, ?)");
    $success = $stmt->execute([$user_id, $label_name]);
    echo json_encode(['success' => $success, 'message' => 'Label added successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to add label: ' . $e->getMessage()]);
}
?>