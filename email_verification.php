<?php
session_start();
require 'vendor/autoload.php'; // For PHPMailer
require 'db_connection.php';  // For DB connection

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Ho_Chi_Minh');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Please provide your email address.";
    } else {
        // Check if email exists in the database
        $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM email_verification_tokens WHERE user_id = ? AND expires_at < NOW()");
        $stmt->execute([$user['user_id']]);


        if ($user) {
            // Check if there is an existing token for this user and expire it
            $stmt = $pdo->prepare("SELECT token, expires_at FROM email_verification_tokens WHERE user_id = ? AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$user['user_id']]);
            $existing_token = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("DELETE FROM email_verification_tokens WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);

            // Generate a new token and expiry time (1 hour)
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Insert the new token into the database
            $stmt = $pdo->prepare("INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['user_id'], $token, $expires_at]);

            // Send email with the reset link
            $reset_link = "http://localhost/Finals/reset_password.php?token=" . $token;

            $mail = new PHPMailer(true);
            try {
                $mail->CharSet = 'UTF-8';
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'kietvdbd@gmail.com';  // Replace with your Gmail
                $mail->Password = 'yhip ysya stnp sbej';  // Use App Password if 2FA enabled
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('kietvdbd@gmail.com', 'Password Reset Request');
                $mail->addAddress($email, htmlspecialchars($user['username']));
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "Please click the following link to reset your password: <a href=\"$reset_link\">Reset Password</a>";
                $mail->AltBody = "Please reset your password by visiting this link: $reset_link";

                $mail->send();
                $message = "Please check your email to reset your password.";
            } catch (Exception $e) {
                $error[] = "Password reset email could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "No account found with that email address.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - NoteKeeper</title>
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
            max-width: 500px;
            width: 100%;
            padding: 0;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: 1px solid #e0e0e0;
            margin: 1rem;
            animation: fadeIn 0.5s ease-in-out;
            overflow: hidden;
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

        .card-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-header i {
            margin-right: 0.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-floating .form-control {
            border-radius: 10px;
            border: 1px solid #ced4da;
            padding: 1rem 0.75rem;
            height: calc(3.5rem + 2px);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-floating .form-control:focus {
            border-color: #4c51bf;
            box-shadow: 0 0 0 0.25rem rgba(76, 81, 191, 0.25);
        }

        .form-floating label {
            padding: 1rem 0.75rem;
            color: #6c757d;
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

        .btn-link {
            color: #4c51bf;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .btn-link:hover {
            color: #a855f7;
            text-decoration: underline;
        }

        .alert {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            animation: fadeInAlert 0.5s ease-in-out;
        }

        @keyframes fadeInAlert {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
            box-shadow: 0 4px 15px rgba(0, 128, 0, 0.2);
        }

        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.2);
        }

        .email-icon {
            font-size: 3rem;
            color: #4c51bf;
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-20px);}
            60% {transform: translateY(-10px);}
        }

        .reset-info {
            text-align: center;
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 576px) {
            .container {
                padding: 1rem;
            }

            .card-header h2 {
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
                <h2><i class="fas fa-key"></i> Password Reset</h2>
            </div>
            <div class="card-body">
                <?php if (isset($message)): ?>
                    <div class="text-center mb-4">
                        <i class="fas fa-envelope-open-text email-icon"></i>
                        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                        <p class="reset-info">An email has been sent with instructions to reset your password. 
                        Please check your inbox and spam folder.</p>
                        <div class="mt-4">
                            <a href="login.php" class="btn btn-link">Back to Login</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?php if (is_array($error)): ?>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($error as $e): ?>
                                        <li><?php echo htmlspecialchars($e); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <?php echo htmlspecialchars($error); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-center mb-4">Enter your email address below and we'll send you a link to reset your password.</p>
                    
                    <form method="POST">
                        <div class="form-floating mb-4">
                            <input type="email" name="email" class="form-control" id="email" placeholder="name@example.com" required>
                            <label for="email">Email Address</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary mb-3">
                            <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                        </button>
                        
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-link">
                                <i class="fas fa-arrow-left me-1"></i> Back to Login
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>