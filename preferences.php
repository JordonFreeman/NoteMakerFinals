<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $prefs = json_encode([
        "theme" => $_POST['theme'],
        "note_layout" => $_POST['note_layout'],
        "font_size" => $_POST['font_size'],
        "note_color" => $_POST['note_color']
    ]);

    $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE user_id = ?");
    $stmt->execute([$prefs, $user_id]);
    $success = "Preferences saved successfully!";
}

$stmt = $pdo->prepare("SELECT preferences FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$prefs = json_decode($stmt->fetchColumn(), true) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Preferences - NoteKeeper</title>
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
        
        .form-control-color {
            height: 50px;
            padding: 0.5rem;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .form-control-color:hover {
            transform: scale(1.05);
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
        
        .option-preview {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        .theme-option {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .theme-preview {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .theme-light {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        
        .theme-dark {
            background: #212529;
            border: 1px solid #495057;
        }
        
        .font-preview {
            display: inline-block;
            margin-left: 10px;
        }
        
        .font-small {
            font-size: 0.85rem;
        }
        
        .font-medium {
            font-size: 1rem;
        }
        
        .font-large {
            font-size: 1.2rem;
        }
        
        .layout-preview {
            height: 24px;
            width: 36px;
            display: inline-block;
            margin-left: 10px;
        }
        
        .layout-grid {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='36' height='24' viewBox='0 0 36 24'%3E%3Crect x='2' y='2' width='7' height='7' rx='1' fill='%234c51bf' /%3E%3Crect x='14' y='2' width='7' height='7' rx='1' fill='%234c51bf' /%3E%3Crect x='26' y='2' width='7' height='7' rx='1' fill='%234c51bf' /%3E%3Crect x='2' y='14' width='7' height='7' rx='1' fill='%234c51bf' /%3E%3Crect x='14' y='14' width='7' height='7' rx='1' fill='%234c51bf' /%3E%3Crect x='26' y='14' width='7' height='7' rx='1' fill='%234c51bf' /%3E%3C/svg%3E") no-repeat center center;
        }
        
        .layout-list {
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='36' height='24' viewBox='0 0 36 24'%3E%3Crect x='2' y='2' width='32' height='4' rx='1' fill='%234c51bf' /%3E%3Crect x='2' y='10' width='32' height='4' rx='1' fill='%234c51bf' /%3E%3Crect x='2' y='18' width='32' height='4' rx='1' fill='%234c51bf' /%3E%3C/svg%3E") no-repeat center center;
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
                        <h2><i class="fas fa-sliders-h"></i> User Preferences</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Theme</label>
                                <select name="theme" class="form-control" id="theme-select">
                                    <option value="light" <?= ($prefs['theme'] ?? '') === 'light' ? 'selected' : '' ?>>
                                        <span class="theme-preview theme-light"></span> Light Theme
                                    </option>
                                    <option value="dark" <?= ($prefs['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>
                                        <span class="theme-preview theme-dark"></span> Dark Theme
                                    </option>
                                </select>
                                <div class="mt-2 d-flex align-items-center">
                                    <span class="me-2">Preview:</span>
                                    <span class="theme-preview theme-light" id="theme-preview"></span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes Layout</label>
                                <select name="note_layout" class="form-control" id="layout-select">
                                    <option value="grid" <?= ($prefs['note_layout'] ?? '') === 'grid' ? 'selected' : '' ?>>Grid Layout</option>
                                    <option value="list" <?= ($prefs['note_layout'] ?? '') === 'list' ? 'selected' : '' ?>>List Layout</option>
                                </select>
                                <div class="mt-2 d-flex align-items-center">
                                    <span class="me-2">Preview:</span>
                                    <span class="layout-preview layout-grid" id="layout-preview"></span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Font Size</label>
                                <select name="font_size" class="form-control" id="font-select">
                                    <option value="small" <?= ($prefs['font_size'] ?? '') === 'small' ? 'selected' : '' ?>>Small</option>
                                    <option value="medium" <?= ($prefs['font_size'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="large" <?= ($prefs['font_size'] ?? '') === 'large' ? 'selected' : '' ?>>Large</option>
                                </select>
                                <div class="mt-2 d-flex align-items-center">
                                    <span class="me-2">Preview:</span>
                                    <span class="font-preview font-medium" id="font-preview">Sample Text</span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Note Color</label>
                                <input type="color" name="note_color" class="form-control form-control-color w-100"
                                    value="<?= htmlspecialchars($prefs['note_color'] ?? '#ffffff') ?>" id="color-picker">
                                <div class="mt-2 d-flex align-items-center">
                                    <span class="me-2">Preview:</span>
                                    <div id="color-preview" style="width: 50px; height: 30px; border-radius: 5px; border: 1px solid #ddd; background-color: <?= htmlspecialchars($prefs['note_color'] ?? '#ffffff') ?>"></div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Preferences
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
        // Theme preview
        const themeSelect = document.getElementById('theme-select');
        const themePreview = document.getElementById('theme-preview');
        
        themeSelect.addEventListener('change', function() {
            themePreview.className = 'theme-preview';
            themePreview.classList.add('theme-' + this.value);
        });
        
        // Set initial theme preview
        themePreview.classList.add('theme-' + themeSelect.value);
        
        // Layout preview
        const layoutSelect = document.getElementById('layout-select');
        const layoutPreview = document.getElementById('layout-preview');
        
        layoutSelect.addEventListener('change', function() {
            layoutPreview.className = 'layout-preview';
            layoutPreview.classList.add('layout-' + this.value);
        });
        
        // Set initial layout preview
        layoutPreview.classList.add('layout-' + layoutSelect.value);
        
        // Font size preview
        const fontSelect = document.getElementById('font-select');
        const fontPreview = document.getElementById('font-preview');
        
        fontSelect.addEventListener('change', function() {
            fontPreview.className = 'font-preview';
            fontPreview.classList.add('font-' + this.value);
        });
        
        // Set initial font preview
        fontPreview.classList.add('font-' + fontSelect.value);
        
        // Color preview
        const colorPicker = document.getElementById('color-picker');
        const colorPreview = document.getElementById('color-preview');
        
        colorPicker.addEventListener('input', function() {
            colorPreview.style.backgroundColor = this.value;
        });
        
        // Add subtle animation for form controls
        const formControls = document.querySelectorAll('.form-control');
        formControls.forEach(control => {
            control.addEventListener('focus', function() {
                this.style.transform = 'scale(1.02)';
            });
            
            control.addEventListener('blur', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>