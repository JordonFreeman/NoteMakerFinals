<?php
session_start();
require 'db_connection.php';

if (!isset($_GET['token'])) {
    die("Invalid request.");
}

$token = $_GET['token'];
$success = '';
$error = '';

// Check if token exists and is not expired
$stmt = $pdo->prepare("SELECT user_id FROM email_verification_tokens WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tokenData) {
    die("This reset link is invalid or has expired.");
}

// If form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Hash the new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Update password in the users table
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$hashedPassword, $tokenData['user_id']]);

        // Delete the token after reset
        $stmt = $pdo->prepare("DELETE FROM email_verification_tokens WHERE token = ?");
        $stmt->execute([$token]);

        $success = "Your password has been reset. You can now <a href='login.php'>log in</a>.";
    }
    session_unset();
    session_destroy();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - NoteKeeper</title>
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
            background: rgba(255, 255, 255, 0.95);
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            border: 1px solid #e0e0e0;
        }

        h3 {
            text-align: center;
            color: #4c51bf;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        h3 i {
            margin-right: 0.5rem;
        }

        .alert {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .alert a {
            color: #155724;
            text-decoration: underline;
        }

        .alert a:hover {
            color: #0f4019;
        }

        .form-label {
            font-weight: 600;
            color: #4c51bf;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 10px;
            border: 1px solid #ced4da;
            padding: 0.75rem 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-control:focus {
            border-color: #4c51bf;
            box-shadow: 0 0 0 4px rgba(76, 81, 191, 0.2);
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4c51bf, #a855f7);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 81, 191, 0.4);
            background: linear-gradient(135deg, #3b41a0, #9333ea);
        }

        @media (max-width: 576px) {
            .container {
                padding: 1.5rem;
                margin: 1rem;
            }

            h3 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h3><i class="fas fa-key"></i> Reset Your Password</h3>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-lock me-2"></i> Reset Password
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>