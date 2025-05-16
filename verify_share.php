<?php
session_start();
require 'db_connection.php';

$token = $_GET['token'] ?? null;
$note_id = $_GET['note_id'] ?? null;

if (!$token || !$note_id) {
    $error = "Missing verification token or note ID";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, n.title 
            FROM shared_notes s 
            JOIN notes n ON s.note_id = n.note_id
            WHERE s.verification_token = ? AND s.note_id = ? AND s.expires > NOW()
        ");
        $stmt->execute([$token, $note_id]);
        $share = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($share) {
            // Mark as verified
            $stmt = $pdo->prepare("UPDATE shared_notes SET verified = 1 WHERE verification_token = ?");
            $stmt->execute([$token]);
            
            $success = "You now have " . ($share['permission'] == 'read' ? 'read-only' : 'edit') . 
                       " access to the note: " . htmlspecialchars($share['title']);
                       
            // If user is logged in, redirect to homepage
            if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
                $_SESSION['share_success'] = $success;
                header("Location: homepage.php");
                exit;
            }
        } else {
            $error = "Invalid or expired verification link";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Shared Note Access - NoteKeeper</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #6b48ff, #a3c9ff);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            color: #333;
        }

        .container {
            max-width: 600px;
            padding: 2rem;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
            overflow: hidden;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            background: linear-gradient(135deg, #4c51bf, #a855f7);
            color: #fff;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
            text-align: center;
            border-bottom: none;
        }

        .card-header h3 {
            margin: 0;
            font-weight: 700;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-header i {
            margin-right: 0.5rem;
            font-size: 1.75rem;
        }

        .card-body {
            padding: 2rem;
            text-align: center;
        }

        .alert {
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            animation: bounceIn 0.5s ease;
        }

        @keyframes bounceIn {
            0%, 20%, 40%, 60%, 80%, 100% {transform: translateY(0);}
            50% {transform: translateY(-10px);}
        }

        .alert-success {
            background: rgba(212, 237, 218, 0.9);
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: rgba(248, 215, 218, 0.9);
            border-color: #f5c6cb;
            color: #721c24;
        }

        .alert i {
            font-size: 1.5rem;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4c51bf, #a855f7);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(76, 81, 191, 0.4);
            background: linear-gradient(135deg, #3b41a0, #9333ea);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }

        .note-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #4c51bf;
            background: linear-gradient(135deg, #4c51bf, #a855f7);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .message-text {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            color: #555;
        }

        .buttons-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        @media (max-width: 576px) {
            .container {
                padding: 1rem;
            }

            .card-header h3 {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-share-alt"></i> Note Access Verification</h3>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <i class="fas fa-file-excel note-icon text-danger"></i>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                    <p class="message-text">The verification link appears to be invalid or has expired. Please ask the note owner to share the note with you again.</p>
                <?php elseif (isset($success)): ?>
                    <i class="fas fa-file-alt note-icon"></i>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $success ?>
                    </div>
                    <p class="message-text">You can now access this shared note in your NoteKeeper account.</p>
                <?php endif; ?>
                
                <div class="buttons-container">
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                        <a href="homepage.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i> Go to Homepage
                        </a>
                        <a href="shared_notes.php" class="btn btn-secondary">
                            <i class="fas fa-share-alt me-2"></i> View Shared Notes
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i> Login to Access
                        </a>
                        <a href="register.php" class="btn btn-secondary">
                            <i class="fas fa-user-plus me-2"></i> Create Account
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>