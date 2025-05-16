<?php 
session_start(); 
require 'db_connection.php';

// Get token from URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if the token exists and is not expired
    $stmt = $pdo->prepare("
        SELECT evt.user_id, evt.token, evt.expires_at, u.is_verified, u.username, u.email
        FROM email_verification_tokens evt
        JOIN users u ON evt.user_id = u.user_id
        WHERE evt.token = ?
    ");
    $stmt->execute([$token]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        if ($record['is_verified']) {
            $message = "This account is already verified.";
            $alertClass = "alert-info";
            $isVerified = true;
        } elseif (strtotime($record['expires_at']) < time()) {
            $message = "The verification token has expired.";
            $alertClass = "alert-danger";
            $isVerified = false;
        } else {
            // Mark the user as verified
            $updateStmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ?");
            $updateStmt->execute([$record['user_id']]);
            
            // Delete the token after successful verification
            $deleteStmt = $pdo->prepare("DELETE FROM email_verification_tokens WHERE token = ?");
            $deleteStmt->execute([$token]);
            
            $message = "Welcome, " . htmlspecialchars($record['username']) . "! Your account has been successfully verified.";
            $alertClass = "alert-success";
            $isVerified = true;
            $username = $record['username'];
            $email = $record['email'];
        }
    } else {
        $message = "Invalid verification token or it has already been used.";
        $alertClass = "alert-danger";
        $isVerified = false;
    }
} else {
    $message = "No verification token was provided.";
    $alertClass = "alert-danger";
    $isVerified = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification - NoteKeeper</title>
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
            overflow: hidden;
        }

        .container {
            max-width: 600px;
            width: 100%;
            padding: 0;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid #e0e0e0;
            margin: 1rem;
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
            border-radius: 15px 15px 0 0;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: pulse 6s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0); }
            100% { transform: scale(1); opacity: 0; }
        }

        .card-header h3 {
            margin: 0;
            font-weight: 700;
            font-size: 1.75rem;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-header i {
            margin-right: 0.5rem;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .card-body {
            padding: 2rem;
            text-align: center;
        }

        .alert {
            border-radius: 10px;
            padding: 1.5rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            border: none;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            box-shadow: 0 4px 15px rgba(0, 128, 0, 0.2);
            animation: slideIn 0.5s ease-out;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.2);
            animation: slideIn 0.5s ease-out;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert a {
            color: #155724;
            text-decoration: underline;
            transition: color 0.3s ease;
        }

        .alert a:hover {
            color: #0f4019;
        }

        .welcome-section {
            padding: 2rem;
            background: linear-gradient(135deg, #e6e6fa, #d8bfd8);
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .welcome-section h4 {
            color: #4c51bf;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .welcome-section p {
            color: #333;
            margin-bottom: 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4c51bf, #a855f7);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 81, 191, 0.4);
            background: linear-gradient(135deg, #3b41a0, #9333ea);
        }

        @media (max-width: 576px) {
            .card {
                margin: 1rem;
            }

            .card-header h3 {
                font-size: 1.5rem;
            }

            .card-body, .welcome-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-check-circle"></i> Account Verification</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!$isVerified || $alertClass === "alert-danger" || $alertClass === "alert-info"): ?>
                            <div class="alert <?php echo $alertClass; ?>"><?php echo $message; ?></div>
                        <?php else: ?>
                            <div class="welcome-section">
                                <h4><i class="fas fa-user-check"></i> Welcome, <?php echo htmlspecialchars($username); ?>!</h4>
                                <p>Your email <strong><?php echo htmlspecialchars($email); ?></strong> has been successfully verified.</p>
                                <p>You can now start using NoteKeeper. Click the button below to log in.</p>
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i> Go to Login
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>