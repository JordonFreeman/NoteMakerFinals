<?php
// user_functions.php

/**
 * Delete a user and all associated data
 *
 * @param int $user_id The ID of the user to delete
 * @param PDO $pdo The PDO database connection
 * @return bool True on success, false on failure
 */
function deleteUser($user_id, $pdo) {
    try {
        $pdo->beginTransaction();
        
        // 1. Delete shared_notes where user is owner or recipient
        $stmt = $pdo->prepare("DELETE FROM shared_notes WHERE owner_id = ? OR recipient_id = ?");
        $stmt->execute([$user_id, $user_id]);
        
        // 2. Get all notes for this user
        $stmt = $pdo->prepare("SELECT note_id FROM notes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $notes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 3. For each note, delete note_label associations
        foreach ($notes as $note_id) {
            $stmt = $pdo->prepare("DELETE FROM note_labels WHERE note_id = ?");
            $stmt->execute([$note_id]);
        }
        
        // 4. Delete all notes for this user
        $stmt = $pdo->prepare("DELETE FROM notes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 5. Get all labels for this user
        $stmt = $pdo->prepare("SELECT label_id FROM labels WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $labels = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 6. For each label, delete any remaining note_label associations
        foreach ($labels as $label_id) {
            $stmt = $pdo->prepare("DELETE FROM note_labels WHERE label_id = ?");
            $stmt->execute([$label_id]);
        }
        
        // 7. Delete all labels for this user
        $stmt = $pdo->prepare("DELETE FROM labels WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 8. Delete any email verification tokens
        $stmt = $pdo->prepare("DELETE FROM email_verification_tokens WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 9. Finally, delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Error deleting user: ' . $e->getMessage());
        return false;
    }
}