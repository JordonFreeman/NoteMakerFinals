<?php
session_start();
require 'db_connection.php';

// Check authentication first, before processing any actions
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit();
}

// Set user_id from session
$user_id = $_SESSION["user_id"];

// Set response content type to JSON
header('Content-Type: application/json');

// Process locking a note
if (isset($_POST['action']) && $_POST['action'] == 'lock') {
    $note_id = $_POST['note_id'] ?? null;
    $password = $_POST['password'] ?? null;

    if (!$note_id || !$password) {
        echo json_encode(['success' => false, 'error' => 'Missing note ID or password!']);
        exit;
    }

    try {
        $password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE notes SET is_locked = 1, password = ? WHERE note_id = ? AND user_id = ?");
        $stmt->execute([$password, $note_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Note locked successfully!']);
    } catch (PDOException $e) {
        error_log("Lock note error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to lock note: ' . $e->getMessage()]);
    }
    exit;
}

// Process unlocking a note
if (isset($_POST['action']) && $_POST['action'] == 'unlock') {
    $note_id = $_POST['note_id'] ?? null;
    $password = $_POST['password'] ?? null;

    if (!$note_id || !$password) {
        echo json_encode(['success' => false, 'error' => 'Missing note ID or password!']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT is_locked, password FROM notes WHERE note_id = ? AND user_id = ?");
        $stmt->execute([$note_id, $user_id]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$note) {
            echo json_encode(['success' => false, 'error' => 'Note not found!']);
            exit;
        }

        if (!$note['is_locked']) {
            $_SESSION['unlocked_' . $note_id] = true;
            echo json_encode(['success' => true, 'message' => 'Note is already unlocked!']);
            exit;
        }

        if ($note['password'] === null || !password_verify($password, $note['password'])) {
            echo json_encode(['success' => false, 'error' => 'Incorrect password!']);
            exit;
        }

        $_SESSION['unlocked_' . $note_id] = true;
        echo json_encode(['success' => true, 'message' => 'Note unlocked successfully!']);
    } catch (PDOException $e) {
        error_log("Unlock note error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to unlock note: ' . $e->getMessage()]);
    }
    exit;
}

// Process re-locking a note (removing the session unlock)
if (isset($_POST['action']) && $_POST['action'] == 'relock') {
    $note_id = $_POST['note_id'] ?? null;

    if (!$note_id) {
        echo json_encode(['success' => false, 'error' => 'Missing note ID!']);
        exit;
    }

    try {
        // Just remove the session unlock
        unset($_SESSION['unlocked_' . $note_id]);
        
        echo json_encode(['success' => true, 'message' => 'Note re-locked successfully!']);
    } catch (PDOException $e) {
        error_log("Relock note error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to re-lock note: ' . $e->getMessage()]);
    }
    exit;
}

// Process changing note password
if (isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $note_id = $_POST['note_id'] ?? null;
    $current_password = $_POST['current_password'] ?? null;
    $new_password = $_POST['new_password'] ?? null;

    if (!$note_id || !$current_password || !$new_password) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields!']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT password FROM notes WHERE note_id = ? AND user_id = ?");
        $stmt->execute([$note_id, $user_id]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($note && password_verify($current_password, $note['password'])) {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE notes SET password = ? WHERE note_id = ? AND user_id = ?");
            $stmt->execute([$new_password_hash, $note_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Incorrect current password!']);
        }
    } catch (PDOException $e) {
        error_log("Change password error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to change password: ' . $e->getMessage()]);
    }
    exit;
}

// Process disabling password protection
if (isset($_POST['action']) && $_POST['action'] == 'disable_password') {
    $note_id = $_POST['note_id'] ?? null;
    $password = $_POST['password'] ?? null;

    if (!$note_id || !$password) {
        echo json_encode(['success' => false, 'error' => 'Missing note ID or password!']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT password FROM notes WHERE note_id = ? AND user_id = ?");
        $stmt->execute([$note_id, $user_id]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($note && password_verify($password, $note['password'])) {
            $stmt = $pdo->prepare("UPDATE notes SET is_locked = 0, password = NULL WHERE note_id = ? AND user_id = ?");
            $stmt->execute([$note_id, $user_id]);
            
            unset($_SESSION['unlocked_' . $note_id]);
            echo json_encode(['success' => true, 'message' => 'Password protection disabled successfully!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Incorrect password!']);
        }
    } catch (PDOException $e) {
        error_log("Disable password error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to disable password: ' . $e->getMessage()]);
    }
    exit;
}

// Process sharing a note
if (isset($_POST['action']) && $_POST['action'] == 'share') {
    $note_id = $_POST['note_id'] ?? null;
    $recipient_email = filter_var($_POST['recipient_email'] ?? '', FILTER_SANITIZE_EMAIL);
    $permission = $_POST['permission'] ?? null;

    if (!$note_id || !$recipient_email || !$permission) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields!']);
        exit;
    }

    try {
        // Check if recipient exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$recipient_email]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($recipient) {
            $recipient_id = $recipient['user_id'];
            
            // Check if already shared
            $stmt = $pdo->prepare("SELECT * FROM shared_notes WHERE note_id = ? AND owner_id = ? AND recipient_id = ?");
            $stmt->execute([$note_id, $user_id, $recipient_id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'error' => 'Note already shared with this recipient!']);
                exit;
            }
            
            // Create verification token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+7 days')); // 7 days expiry
            
            // FIXED: Parameters now match placeholders (6 params for 6 ?)
            $stmt = $pdo->prepare("INSERT INTO shared_notes (note_id, owner_id, recipient_id, permission, verification_token, expires, verified) 
                                  VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$note_id, $user_id, $recipient_id, $permission, $token, $expires]);
            
            echo json_encode(['success' => true, 'message' => 'Note shared successfully!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Recipient not found!']);
        }
    } catch (PDOException $e) {
        // Added detailed error logging for debugging
        error_log("Share note error: " . $e->getMessage() . " - Query parameters: note_id=$note_id, user_id=$user_id");
        echo json_encode(['success' => false, 'error' => 'Failed to share note: ' . $e->getMessage()]);
    }
    exit;
}

// Process revoking access to a shared note
if (isset($_POST['action']) && $_POST['action'] == 'revoke') {
    $note_id = $_POST['note_id'] ?? null;
    $recipient_id = $_POST['recipient_id'] ?? null;

    if (!$note_id || !$recipient_id) {
        echo json_encode(['success' => false, 'error' => 'Missing note ID or recipient ID!']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM shared_notes WHERE note_id = ? AND owner_id = ? AND recipient_id = ?");
        $stmt->execute([$note_id, $user_id, $recipient_id]);
        
        echo json_encode(['success' => true, 'message' => 'Access revoked successfully!']);
    } catch (PDOException $e) {
        error_log("Revoke access error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to revoke access: ' . $e->getMessage()]);
    }
    exit;
}

// If we got here, it's an invalid request
echo json_encode(['success' => false, 'error' => 'Invalid request!']);
?>