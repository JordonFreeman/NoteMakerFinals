<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$success = $error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $avatar = $_FILES['avatar'] ?? null;

    if ($avatar && $avatar['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($avatar['name'], PATHINFO_EXTENSION);
        $target = 'uploads/avatars/' . uniqid('avatar_') . '.' . $ext;
        move_uploaded_file($avatar['tmp_name'], $target);
    } else {
        $target = $_POST['current_avatar']; // use existing
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, avatar = ? WHERE user_id = ?");
        $stmt->execute([$username, $email, $target, $user_id]);
        $_SESSION["username"] = $username;
        $_SESSION["avatar"] = $target; // Update session avatar
        $success = "Profile updated successfully!";
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT username, email, avatar FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - NoteKeeper</title>
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
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            background: linear-gradient(135deg, #4c51bf, #a855f7);
            color: #fff;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            text-align: center;
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

        .alert {
            border-radius: 10px;
            padding: 1rem;
            font-weight: 500;
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

        .avatar-preview {
            border: 3px solid #4c51bf;
            border-radius: 50%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1rem;
        }

        .avatar-preview:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(76, 81, 191, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, #4c51bf, #a855f7);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 81, 191, 0.4);
            background: linear-gradient(135deg, #3b41a0, #9333ea);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 10px;
            color: #fff;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
            background: linear-gradient(135deg, #5a6268, #343a40);
        }

        @media (max-width: 576px) {
            .card {
                margin: 1rem;
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
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-edit"></i> Edit Profile</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input name="email" type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>

                            <div class="mb-3 text-center">
                                <label class="form-label">Change Avatar</label><br>
                                <img id="avatar-preview" src="<?= htmlspecialchars($user['avatar'] ?? 'default_avatar.png') ?>" width="100" height="100" class="avatar-preview"><br>
                                <input type="file" name="avatar" class="form-control mt-2" accept="image/*" onchange="previewAvatar(event)">
                                <input type="hidden" name="current_avatar" value="<?= htmlspecialchars($user['avatar']) ?>">
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                                <a href="homepage.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function previewAvatar(event) {
            const input = event.target;
            const preview = document.getElementById('avatar-preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.transform = 'scale(1.1)';
                    preview.style.boxShadow = '0 4px 15px rgba(76, 81, 191, 0.3)';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>