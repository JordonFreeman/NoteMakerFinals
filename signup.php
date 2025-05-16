<?php
//Test message Remove later
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
require 'db_connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    if (!$email) {
      $error[] = "Invalid email address.";
    }

    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    $count = $stmt->fetchColumn();
   
    if ($_POST['password'] !== $_POST['confirm_password']) {
      $error[] = "Passwords do not match.";
  }
 
    if ($count > 0) {
        $error[] = "The username or email already exists. Please choose a different one.";
    } else {
        if(empty($error)){
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user into database with temporary unverified status
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, is_verified) VALUES (?, ?, ?, 0)");
       
        if ($stmt->execute([$username, $email, $hashedPassword])) {
            $userId = $pdo->lastInsertId();

            // Generate and save a verification token
            $verifyToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $verifyLink = "http://localhost/Finals/verify.php?token=$verifyToken";

            // Save token in database
            $stmt = $pdo->prepare("INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $verifyToken, $expiresAt]);

            // Send verification email
            $mail = new PHPMailer(true);
            try {
                $mail->CharSet = 'UTF-8';
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'kietvdbd@gmail.com';       // Replace with your Gmail
                $mail->Password = 'yhip ysya stnp sbej';      // Use App Password if 2FA enabled
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('kietvdbd@gmail.com', 'Verify your email');
                $mail->addAddress($email, htmlspecialchars($username));
                $mail->isHTML(true);
                $mail->Subject = 'Verify your account';
                $mail->Body    = "Please click the following link to verify your account: <a href=\"$verifyLink\">Verify Account</a>";
                $mail->AltBody = "Please verify your account by visiting this link: $verifyLink";
                $mail->send();
                $success = "Registration successful! Please check your email to verify your account.";
            } catch (Exception $e) {
                $error[] = "Verification email could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error[] = "Registration failed. Please try again.";
        }
      }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Sign Up - NoteKeeper</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  
  <!-- jQuery and Bootstrap JS -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Poppins', sans-serif;
    }
    
    .signup-container {
      margin-top: 3rem;
      margin-bottom: 3rem;
    }
    
    .signup-card {
      border-radius: 15px;
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    
    .signup-header {
      background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
      color: white;
      padding: 2rem;
      text-align: center;
    }
    
    .signup-title {
      font-weight: 600;
      margin-bottom: 0;
    }
    
    .signup-form {
      padding: 2rem;
    }
    
    .form-control {
      border-radius: 8px;
      padding: 0.75rem 1rem;
      border: 1px solid #e1e5eb;
      font-size: 1rem;
    }
    
    .form-control:focus {
      box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.2);
      border-color: #6a11cb;
    }
    
    .btn-signup {
      background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
      border: none;
      border-radius: 8px;
      padding: 0.75rem 2rem;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-signup:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
    }
    
    .signup-links a {
      color: #6a11cb;
      text-decoration: none;
      transition: color 0.3s ease;
    }
    
    .signup-links a:hover {
      color: #2575fc;
      text-decoration: underline;
    }
    
    .input-group-text {
      background: transparent;
      border-right: none;
    }
    
    .input-group .form-control {
      border-left: none;
    }
    
    .input-icon {
      color: #6a11cb;
    }
    
    .password-requirements {
      margin-top: 8px;
      font-size: 0.8rem;
    }
    
    .requirement {
      color: #6c757d;
      margin-bottom: 3px;
    }
    
    .requirement.valid {
      color: #28a745;
    }
    
    .requirement.invalid {
      color: #dc3545;
    }
    
    .requirement i {
      margin-right: 5px;
      width: 16px;
    }
    
    .alert-success {
      background-color: rgba(40, 167, 69, 0.1);
      border-color: #28a745;
      color: #28a745;
    }
    
    .alert-danger {
      background-color: rgba(220, 53, 69, 0.1);
      border-color: #dc3545;
      color: #dc3545;
    }
  </style>
</head>
<body>

<div class="container signup-container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      
      <div class="card signup-card">
        <div class="signup-header">
          <h3 class="signup-title">NoteKeeper</h3>
          <p class="mb-0">Create your account</p>
        </div>
        
        <div class="signup-form">
          <!-- Error/Success Messages -->
          <?php if (!empty($error)) : ?>
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-circle mr-2"></i>
              <ul class="mb-0 pl-3">
                <?php foreach ($error as $e) : ?>
                  <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php elseif (!empty($success)) : ?>
            <div class="alert alert-success">
              <i class="fas fa-check-circle mr-2"></i>
              <?php echo $success; ?>
            </div>
          <?php endif; ?>
          
          <form method="POST" action="signup.php">
            <div class="form-group">
              <label for="username">Username</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fas fa-user input-icon"></i></span>
                </div>
                <input id="username" name="username" type="text" class="form-control" 
                       placeholder="Choose a username" required>
              </div>
            </div>
            
            <div class="form-group">
              <label for="email">Email address</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fas fa-envelope input-icon"></i></span>
                </div>
                <input id="email" name="email" type="email" class="form-control" 
                       placeholder="Enter your email" required>
              </div>
            </div>
            
            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fas fa-lock input-icon"></i></span>
                </div>
                <input id="password" name="password" type="password" class="form-control" 
                       placeholder="Create a password" required>
                <div class="input-group-append">
                  <span class="input-group-text cursor-pointer" onclick="togglePassword('password')">
                    <i class="fas fa-eye"></i>
                  </span>
                </div>
              </div>
              <div class="password-requirements">
                <div class="requirement" id="length">
                  <i class="fas fa-circle"></i> At least 6 characters long
                </div>
              </div>
            </div>
            
            <div class="form-group">
              <label for="confirm_password">Confirm Password</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fas fa-lock input-icon"></i></span>
                </div>
                <input id="confirm_password" name="confirm_password" type="password" class="form-control" 
                       placeholder="Confirm your password" required>
              </div>
            </div>
            
            <div class="form-group">
              <button type="submit" name="register" class="btn btn-signup btn-block">
                <i class="fas fa-user-plus mr-2"></i> Sign Up
              </button>
            </div>
            
            <div class="signup-links text-center">
              <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
            </div>
          </form>
        </div>
      </div>
      
    </div>
  </div>
</div>

<script>
  // Password toggle visibility
  function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.parentNode.querySelector('.fa-eye, .fa-eye-slash');
    
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }
  
  // Password validation
  const passwordInput = document.getElementById('password');
  const lengthRequirement = document.getElementById('length');
  const confirmPasswordInput = document.getElementById('confirm_password');
  
  passwordInput.addEventListener('input', function() {
    // Check length
    if (this.value.length >= 6) {
      lengthRequirement.classList.add('valid');
      lengthRequirement.classList.remove('invalid');
      lengthRequirement.querySelector('i').className = 'fas fa-check-circle';
    } else {
      lengthRequirement.classList.add('invalid');
      lengthRequirement.classList.remove('valid');
      lengthRequirement.querySelector('i').className = 'fas fa-circle';
    }
  });
  
  // Check password match
  confirmPasswordInput.addEventListener('input', function() {
    if (this.value === passwordInput.value) {
      this.style.borderColor = '#28a745';
    } else {
      this.style.borderColor = '';
    }
  });
</script>

</body>
</html>