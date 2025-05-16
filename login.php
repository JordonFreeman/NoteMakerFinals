<?php
session_start();
require 'vendor/autoload.php'; // Load Composer libraries (PHPMailer, etc.)
require 'db_connection.php';   // Database connection ($pdo)

// If already logged in, redirect to homepage
if (isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true) {
    header("Location: homepage.php");
    exit();
}

define("ADMIN_USERNAME", "admin");
define("ADMIN_PASSWORD", "password123");

$error = "";
$saved_username = $_COOKIE["remember_username"] ?? "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? "");
    $password = $_POST['password'] ?? "";

    if ($username === "" || $password === "") {
        $error = "Please fill in both username and password.";
    } else {
        // Admin login fallback
        if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
            $_SESSION["logged_in"] = true;
            $_SESSION["username"] = $username;
            $_SESSION["user_id"] = 0; // Admin không có user_id trong DB
            $_SESSION["avatar"] = "uploads/avatars/default_avatar.png"; // Avatar mặc định cho admin
            $_SESSION["just_logged_in"] = true; // Đánh dấu vừa đăng nhập
            handleRememberMe($username);
            header("Location: homepage.php");
            exit();
        }

        // Regular user login
        $stmt = $pdo->prepare("SELECT user_id, username, password, avatar, is_verified, two_factor_enabled FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ((int)$user['is_verified'] !== 1) {
                $error = "Your account has not been verified yet. Please check your email for the verification link.";
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION["temp_user_id"] = $user['user_id'];
                $_SESSION["temp_username"] = $username;
                $_SESSION["remember_me"] = isset($_POST["remember"]);
                $_SESSION["temp_avatar"] = $user['avatar'] ?? "uploads/avatars/default_avatar.png";

                // Check if two-factor authentication is enabled
                if ($user['two_factor_enabled'] === 1) {
                    $stmt = $pdo->prepare("SELECT totp_secret FROM users WHERE user_id = ?");
                    $stmt->execute([$user['user_id']]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!empty($row['totp_secret'])) {
                        $_SESSION["totp_secret"] = $row['totp_secret'];
                        $_SESSION["2fa_totp_user"] = $user['user_id'];
                        header("Location: verify_totp.php");
                        exit();
                    }
                }

                // If 2FA is not enabled, log in directly
                $_SESSION["logged_in"] = true;
                $_SESSION["username"] = $username;
$_SESSION["user_id"] = $user['user_id'];
                $_SESSION["avatar"] = $user['avatar'] ?? "uploads/avatars/default_avatar.png";
                $_SESSION["just_logged_in"] = true; // Đánh dấu vừa đăng nhập
                handleRememberMe($username);
                header("Location: homepage.php");
                exit();
            } else {
                $error = "Incorrect username or password.";
            }
        } else {
            $error = "Incorrect username or password.";
        }
    }
}

// Handle the "Remember Me" functionality
function handleRememberMe($username) {
    if (isset($_POST["remember"])) {
        setcookie("remember_username", $username, time() + (86400 * 30), "/"); // 30 days
    } else {
        setcookie("remember_username", "", time() - 3600, "/"); // Expire cookie
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Login - NoteKeeper</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Login - NoteKeeper</title>
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
    
    .login-container {
      margin-top: 5rem;
    }
    
    .login-card {
      border-radius: 15px;
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    
    .login-header {
      background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
      color: white;
      padding: 2rem;
      text-align: center;
    }
    
    .login-title {
      font-weight: 600;
      margin-bottom: 0;
    }
    
    .login-form {
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
    
    .custom-control-input:checked ~ .custom-control-label::before {
      background-color: #6a11cb;
      border-color: #6a11cb;
    }
    
    .btn-login {
      background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
      border: none;
      border-radius: 8px;
      padding: 0.75rem 2rem;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    
    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
    }
    
    .login-links a {
      color: #6a11cb;
      text-decoration: none;
      transition: color 0.3s ease;
    }
    
    .login-links a:hover {
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
    
    /* Animation for error message */
    .alert-danger {
      animation: shake 0.5s ease-in-out;
    }
    
    @keyframes shake {
      0%, 100% {transform: translateX(0);}
      10%, 30%, 50%, 70%, 90% {transform: translateX(-5px);}
      20%, 40%, 60%, 80% {transform: translateX(5px);}
    }
  </style>
</head>
<body>

<div class="container login-container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      
      <div class="card login-card">
        <div class="login-header">
          <h3 class="login-title">NoteKeeper</h3>
          <p class="mb-0">Sign in to access your notes</p>
        </div>
        
        <div class="login-form">
          <!-- Error message block (only shown if $error is not empty) -->
          <?php if (!empty($error)) : ?>
            <div class="alert alert-danger d-flex align-items-center">
              <i class="fas fa-exclamation-circle mr-2"></i>
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>
          
          <form method="POST" action="">
            <div class="form-group">
              <label for="username">Username</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fas fa-user input-icon"></i></span>
                </div>
                <input id="username" name="username" type="text" class="form-control" 
                       placeholder="Enter username" value="<?= htmlspecialchars($saved_username) ?>" required>
              </div>
            </div>
            
            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fas fa-lock input-icon"></i></span>
                </div>
                <input id="password" name="password" type="password" class="form-control" 
                       placeholder="Enter password" required>
              </div>
            </div>
            
            <div class="form-group custom-control custom-checkbox">
              <input type="checkbox" name="remember" class="custom-control-input" id="remember" 
                     <?= $saved_username ? 'checked' : '' ?>>
              <label class="custom-control-label" for="remember">Remember me</label>
            </div>
            
            <div class="form-group">
              <button type="submit" name="login" class="btn btn-login btn-block">
                <i class="fas fa-sign-in-alt mr-2"></i> Login
              </button>
            </div>
            
            <div class="login-links text-center">
              <p class="mb-2">Forgot password? <a href="email_verification.php">Reset Password</a></p>
              <p class="mb-0">Don't have an account? <a href="signup.php">Sign up here</a></p>
            </div>
          </form>
        </div>
      </div>
      
    </div>
  </div>
</div>

</body>
</html>