<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT username, email, avatar, preferences FROM users WHERE user_id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user && $user_id !== 0) { // Cho phép admin (user_id = 0)
        die("User not found.");
    }

    $username = $_SESSION["username"]; // Sử dụng từ session để hỗ trợ admin
    $email = $user['email'] ?? 'admin@example.com'; // Email mặc định cho admin
    $avatar = $_SESSION["avatar"] ?? 'uploads/avatars/default_avatar.png'; // Sử dụng từ session

    $prefs = json_decode($user['preferences'] ?? '{}', true);
    $theme = $prefs['theme'] ?? 'light';
    $font_size = $prefs['font_size'] ?? 'medium';
    $note_color = $prefs['note_color'] ?? '#ffffff';
    $note_layout = $prefs['note_layout'] ?? 'grid';

    if ($user_id !== 0) { // Chỉ lấy notes cho người dùng thông thường
        $stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = :id ORDER BY pinned_at DESC, updated_at DESC");
        $stmt->execute(['id' => $user_id]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $notes = []; // Admin không có notes
    }
} catch (PDOException $e) {
    die("Error fetching user data: " . $e->getMessage());
}

// Kiểm tra trạng thái vừa đăng nhập
$show_avatar_prompt = isset($_SESSION["just_logged_in"]) && $_SESSION["just_logged_in"];
if ($show_avatar_prompt) {
    unset($_SESSION["just_logged_in"]); // Xóa để không hiển thị lại
}

// Font size mapping và layout class (giữ nguyên)
$fontSizeCss = match ($font_size) {
    'small' => '0.9rem',
    'large' => '1.3rem',
    default => '1rem'
};
$layoutClass = $note_layout === 'grid' ? 'row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3' : 'd-flex flex-column gap-3';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NoteKeeper - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body {
            background-color: <?= $theme === 'dark' ? '#1e1e1e' : '#f8f9fa' ?>;
            color: <?= $theme === 'dark' ? '#f5f5f5' : '#333333' ?>;
            min-height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            margin: 0;
        }

        .container {
            max-width: 1200px;
            padding: 0 15px;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            font-weight: 500;
            background-color: <?= $theme === 'dark' ? '#333' : '#f1f3f5' ?>;
            color: <?= $theme === 'dark' ? '#fff' : '#333' ?>;
        }

        .btn {
            border-radius: 5px;
            transition: all 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background-color: #5b6af8;
            border-color: #5b6af8;
        }

        .btn-outline-primary {
            color: #5b6af8;
            border-color: #5b6af8;
        }

        .btn-outline-primary:hover {
            background-color: #5b6af8;
        }

        .note-card {
            height: 100%;
            position: relative;
        }

        .note-card .card-title {
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
            font-size: <?= $fontSizeCss ?>;
        }

        .note-card .note-labels {
            text-align: center;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            color: #666;
        }

        .note-card textarea {
            resize: none;
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
            font-size: 0.85rem;
        }

        .note-card img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
        }

        .locked-content {
            filter: blur(8px);
            pointer-events: none;
            user-select: none;
            -webkit-user-select: none;
            position: relative;
        }

        .locked-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.1);
            z-index: 1;
        }

        .locked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 10px;
            pointer-events: all;
        }

        .unlock-button {
            transform: translateY(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .unlock-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        /* Make card title still readable when note is locked */
        .locked-content.editable-title {
            filter: blur(0);
            pointer-events: auto;
        }

        /* Additional hover effect for locked notes */
        .note-card:hover .locked-overlay {
            background: rgba(0, 0, 0, 0.15);
        }

        .border-warning {
            border: 2px solid #ffc107 !important;
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.1);
        }

        .form-control:focus {
            border-color: #5b6af8;
            box-shadow: 0 0 0 0.25rem rgba(91, 106, 248, 0.25);
        }

        #searchBox {
            border-radius: 20px;
            padding-left: 15px;
        }

        #autosave-status {
            opacity: 0;
            transition: opacity 0.3s;
        }

        #autosave-status.visible {
            opacity: 1;
        }

        .modal {
            z-index: 1050;
        }

        .modal-backdrop {
            z-index: 1040;
        }

        #notes-container {
            overflow-y: visible;
            padding-bottom: 20px;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .row-cols-md-3 {
                grid-template-columns: repeat(2, 1fr);
            }

            .note-card .card-body {
                padding: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .row-cols-sm-2 {
                grid-template-columns: 1fr;
            }

            .card-header {
                padding: 0.75rem;
            }

            .card-body {
                padding: 1rem;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 1rem;
            }

            #searchBox {
                width: 100%;
            }

            .btn-group {
                width: 100%;
                justify-content: space-between;
            }

            .note-card {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 10px;
            }

            h2 {
                font-size: 1.5rem;
            }

            .card {
                margin-bottom: 15px;
            }

            .note-card .card-title {
                font-size: 1rem;
            }

            .note-card .note-labels {
                font-size: 0.8rem;
            }

            .note-card textarea {
                font-size: 0.75rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }

            .form-control {
                font-size: 0.9rem;
            }

            .card-header h5 {
                font-size: 1.25rem;
            }

            .modal-dialog {
                margin: 0.5rem;
            }
        }

        .note-card img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-top: 0.5rem;
            max-height: 200px;
            object-fit: contain;
        }

        /* Search box */
        #searchBox {
            border-radius: 20px;
            padding-left: 15px;
            max-width: 300px;
        }

        /* Auto-save status styling */
        #autosave-status {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #autosave-status.visible {
            opacity: 1;
        }

        /* Dropdown */
        .dropdown-menu {
            background: <?= $theme === 'dark' ? '#2c2f3d' : '#ffffff' ?>;
            border-radius: 8px;
            border: 1px solid <?= $theme === 'dark' ? '#444' : '#e5e7eb' ?>;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);

        }

        .dropdown-item {
            color: <?= $theme === 'dark' ? '#e0e0e0' : '#333' ?>;
            padding: 0.5rem 1rem;
        }

        .dropdown-item:hover {
            background: <?= $theme === 'dark' ? '#3d4060' : '#f3f4f6' ?>;
        }

        /* Modal */
        .modal-content {
            border-radius: 12px;
            background: <?= $theme === 'dark' ? '#2c2f3d' : '#ffffff' ?>;
        }

        .modal-header {
            background: <?= $theme === 'dark' ? '#3d4060' : '#f8f9fa' ?>;
            color: <?= $theme === 'dark' ? '#fff' : '#333' ?>;
            border-bottom: 1px solid <?= $theme === 'dark' ? '#444' : '#dee2e6' ?>;
        }

        .modal-footer {
            border-top: 1px solid <?= $theme === 'dark' ? '#444' : '#dee2e6' ?>;
        }

        .avatar-preview {
            max-width: 120px;
            border: 4px solid #4c51bf;
            margin-bottom: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .row-cols-md-3 {
                grid-template-columns: repeat(2, 1fr);
            }

            .note-card .card-body {
                padding: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .row-cols-sm-2 {
                grid-template-columns: repeat(1, 1fr);
            }

            .card-header {
                padding: 0.75rem;
            }

            .card-body {
                padding: 1rem;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 1rem;
            }

            #searchBox {
                width: 100%;
            }

            .btn-group {
                width: 100%;
                justify-content: space-between;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 10px;
            }

            h2 {
                font-size: 1.5rem;
            }

            .card {
                margin-bottom: 15px;
            }

            .note-card .card-title {
                font-size: 1rem;
            }

            .note-card .note-labels {
                font-size: 0.8rem;
            }

            .note-card textarea {
                font-size: 0.75rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }

            .form-control {
                font-size: 0.9rem;
            }

            .card-header h5 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header-section">
            <h2><i class="fas fa-sticky-note me-2"></i>Welcome, <?= htmlspecialchars($username) ?>!</h2>
            <div>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>

        <!-- Profile Card -->
        <div class="card profile-card mb-4">
            <div class="card-header">Your Profile</div>
            <div class="card-body d-flex align-items-center">
                <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="rounded-circle me-3" width="100" height="100">
                <div>
                    <p><i class="fas fa-user me-2"></i><strong>Username:</strong> <?= htmlspecialchars($username) ?></p>
                    <p><i class="fas fa-envelope me-2"></i><strong>Email:</strong> <?= htmlspecialchars($email) ?></p>
                    <a href="edit_profile.php" class="btn btn-outline-primary">
                        <i class="fas fa-user-edit me-1"></i> Edit Profile
                    </a>
                    <?php if ($user_id !== 0): ?>
                        <a href="preferences.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-cog me-1"></i> User Preferences
                        </a>
                        <a href="shared_notes.php" class="btn btn-outline-info ms-2">
                            <i class="fas fa-share-alt me-1"></i> Shared Notes
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card mb-4">
            <div class="card-header">Change Password</div>
            <div class="card-body">
                <p>To change your password, please verify your identity.</p>
                <a href="email_verification.php" class="btn btn-primary">
                    <i class="fas fa-lock me-2"></i> Verify Email to Change Password
                </a>
            </div>
        </div>

        <!-- New Note -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Note</h5>
                <div class="d-flex align-items-center">
                    <i class="fas fa-search me-2"></i>
                    <input type="text" id="searchBox" class="form-control" placeholder="Search notes..." aria-label="Search notes">
                </div>
            </div>
            <div class="card-body">
                <form id="note-form" onsubmit="event.preventDefault(); addNote();">
                    <div class="mb-3">
                        <label for="note-title" class="form-label">Title</label>
                        <input type="text" id="note-title" class="form-control" placeholder="Enter title" required>
                    </div>
                    <div class="mb-3">
                        <label for="note-labels" class="form-label">Labels</label>
                        <select id="note-labels" class="form-select" multiple aria-label="Select labels">
                            <!-- Options populated dynamically -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="note-content" class="form-label">Content</label>
                        <textarea id="note-content" class="form-control" rows="4" placeholder="Write your note..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="note-images" class="form-label">Attach Images</label>
                        <input type="file" id="note-images" class="form-control" multiple accept="image/*">
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div id="autosave-status" class="form-text text-muted mb-0" aria-live="polite"></div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Add Note
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Label Management Section -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#labelModal">
                <i class="fas fa-tags me-2"></i>Manage Labels
            </button>

            <div class="d-flex align-items-center">
                <label class="me-2">Filter by Label:</label>
                <select id="labelFilter" class="form-select form-select-sm w-auto">
                    <option value="">-- All --</option>
                    <!-- Options populated dynamically -->
                </select>
            </div>
        </div>

        <!-- Notes Container -->
        <div class="<?= $layoutClass ?>" id="notes-container">
            <?php foreach ($notes as $note): ?>
                <div class="col mb-4">
                    <div class="card position-relative note-card <?= $note['pinned_at'] ? 'border-warning' : '' ?>"
                        id="note-<?= $note['note_id'] ?>"
                        style="background-color: <?= htmlspecialchars($note_color) ?>; font-size: <?= $fontSizeCss ?>;">

                        <?php if (isset($note['is_locked']) && $note['is_locked']): ?>
                            <div class="position-absolute top-0 end-0 p-2">
                                <i class="fas fa-lock text-warning" title="This note is password protected"></i>
                            </div>

                            <?php if (isset($_SESSION['unlocked_' . $note['note_id']]) && $_SESSION['unlocked_' . $note['note_id']]): ?>
                                <!-- Note is temporarily unlocked, show content normally -->
                                <div class="position-absolute top-0 end-0 p-2">
                                    <i class="fas fa-unlock text-success" title="This note is temporarily unlocked"></i>
                                </div>
                            <?php else: ?>
                                <!-- Note is locked, add locked-note class -->
                                <div class="locked-overlay">
                                    <button class="btn btn-primary unlock-button" onclick="unlockNote(<?= $note['note_id'] ?>)">
                                        <i class="fas fa-unlock me-2"></i>Unlock Note
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="card-header d-flex justify-content-between align-items-center">
                            <input type="text"
                                id="title-<?= $note['note_id'] ?>"
                                class="form-control-plaintext editable-title card-title"
                                data-id="<?= $note['note_id'] ?>"
                                value="<?= htmlspecialchars($note['title']) ?>"
                                placeholder="Note title"
                                <?= (isset($note['is_locked']) && $note['is_locked'] && (!isset($_SESSION['unlocked_' . $note['note_id']]) || !$_SESSION['unlocked_' . $note['note_id']])) ? 'readonly' : '' ?>>

                            <button class="btn btn-sm <?= $note['pinned_at'] ? 'btn-warning' : 'btn-outline-warning' ?>" onclick="togglePin(<?= $note['note_id'] ?>)">
                                <i class="fas fa-thumbtack"></i>
                            </button>
                        </div>

                        <div class="card-body <?= (isset($note['is_locked']) && $note['is_locked'] && (!isset($_SESSION['unlocked_' . $note['note_id']]) || !$_SESSION['unlocked_' . $note['note_id']])) ? 'locked-content' : '' ?>">
                            <textarea id="content-<?= $note['note_id'] ?>"
                                class="form-control-plaintext editable-content"
                                data-id="<?= $note['note_id'] ?>"
                                rows="3"
                                placeholder="Write your note..."
                                <?= (isset($note['is_locked']) && $note['is_locked'] && (!isset($_SESSION['unlocked_' . $note['note_id']]) || !$_SESSION['unlocked_' . $note['note_id']])) ? 'readonly' : '' ?>><?= htmlspecialchars($note['content']) ?></textarea>

                            <?php if (!empty($note['images'])): ?>
                                <div class="mb-3 <?= (isset($note['is_locked']) && $note['is_locked'] && (!isset($_SESSION['unlocked_' . $note['note_id']]) || !$_SESSION['unlocked_' . $note['note_id']])) ? 'locked-content' : '' ?>">
                                    <?php foreach (explode(',', $note['images']) as $img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>"
                                            class="img-fluid rounded border my-1"
                                            alt="Attached image for note <?= $note['note_id'] ?>">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <select class="form-select form-select-sm note-label-select mb-2 me-2 <?= (isset($note['is_locked']) && $note['is_locked'] && (!isset($_SESSION['unlocked_' . $note['note_id']]) || !$_SESSION['unlocked_' . $note['note_id']])) ? 'locked-content' : '' ?>"
                                    style="max-width: 150px;"
                                    data-note-id="<?= $note['note_id'] ?>"
                                    multiple
                                    <?= (isset($note['is_locked']) && $note['is_locked'] && (!isset($_SESSION['unlocked_' . $note['note_id']]) || !$_SESSION['unlocked_' . $note['note_id']])) ? 'disabled' : '' ?>>
                                    <!-- Options populated dynamically -->
                                </select>

                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-danger" onclick="deleteNote(<?= $note['note_id'] ?>)">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?= $note['note_id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?= $note['note_id'] ?>">
                                            <?php if (isset($note['is_locked']) && $note['is_locked']): ?>
                                                <?php if (isset($_SESSION['unlocked_' . $note['note_id']]) && $_SESSION['unlocked_' . $note['note_id']]): ?>
                                                    <li><a class="dropdown-item" href="#" onclick="relockNote(<?= $note['note_id'] ?>); return false;">
                                                            <i class="fas fa-lock me-2"></i>Re-lock Note
                                                        </a></li>
                                                <?php else: ?>
                                                    <li><a class="dropdown-item" href="#" onclick="unlockNote(<?= $note['note_id'] ?>); return false;">
                                                            <i class="fas fa-unlock me-2"></i>Unlock Note
                                                        </a></li>
                                                <?php endif; ?>
                                                <li><a class="dropdown-item" href="#" onclick="changeNotePassword(<?= $note['note_id'] ?>); return false;">
                                                        <i class="fas fa-key me-2"></i>Change Password
                                                    </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="disableNotePassword(<?= $note['note_id'] ?>); return false;">
                                                        <i class="fas fa-unlock-alt me-2"></i>Remove Password
                                                    </a></li>
                                            <?php else: ?>
                                                <li><a class="dropdown-item" href="#" onclick="lockNote(<?= $note['note_id'] ?>); return false;">
                                                        <i class="fas fa-lock me-2"></i>Password Protect
                                                    </a></li>
                                            <?php endif; ?>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item" href="#" onclick="shareNote(<?= $note['note_id'] ?>); return false;">
                                                    <i class="fas fa-share-alt me-2"></i>Share Note
                                                </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="viewSharedUsers(<?= $note['note_id'] ?>); return false;">
                                                    <i class="fas fa-users me-2"></i>Manage Sharing
                                                </a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Modals (security and labels) -->
        <!-- Label Management Modal -->
        <div class="modal fade" id="labelModal" tabindex="-1" aria-labelledby="labelModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-tags me-2"></i>Manage Labels</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="list-group" id="labelList"></ul>
                        <div class="input-group mt-3">
                            <input type="text" id="newLabelName" class="form-control" placeholder="New label name">
                            <button class="btn btn-primary" id="addLabelBtn">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Avatar Prompt Modal -->
        <div class="modal fade" id="avatarPromptModal" tabindex="-1" aria-labelledby="avatarPromptModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-circle me-2"></i>Welcome, <?= htmlspecialchars($username) ?>!</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="Current Avatar" class="avatar-preview rounded-circle">
                        <p>Would you like to change your avatar?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Skip</button>
                        <a href="edit_profile.php" class="btn btn-primary">Change Avatar</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lock Note Modal -->
        <div class="modal fade" id="lockNoteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-lock me-2"></i>Lock Note</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Set a password to protect this note. You'll need this password to view or edit the note.</p>
                        <input type="hidden" id="lock_note_id">
                        <div class="mb-3">
                            <label for="lock_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="lock_password" placeholder="Enter password">
                        </div>
                        <div class="mb-3">
                            <label for="lock_confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="lock_confirm_password" placeholder="Confirm password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="lockNoteBtn"><i class="fas fa-lock me-2"></i>Lock Note</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unlock Note Modal -->
        <div class="modal fade" id="unlockNoteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-unlock me-2"></i>Unlock Note</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Enter the password to unlock this note.</p>
                        <input type="hidden" id="unlock_note_id">
                        <div class="mb-3">
                            <label for="unlock_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="unlock_password" placeholder="Enter password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="unlockNoteBtn"><i class="fas fa-unlock me-2"></i>Unlock Note</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Password Modal -->
        <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-key me-2"></i>Change Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="change_note_id">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" placeholder="Enter current password">
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" placeholder="Enter new password">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_new_password" placeholder="Confirm new password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="changePasswordBtn"><i class="fas fa-save me-2"></i>Change Password</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disable Password Modal -->
        <div class="modal fade" id="disablePasswordModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-unlock-alt me-2"></i>Remove Password Protection</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Enter your current password to remove password protection from this note.</p>
                        <input type="hidden" id="disable_note_id">
                        <div class="mb-3">
                            <label for="disable_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="disable_password" placeholder="Enter current password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="disablePasswordBtn"><i class="fas fa-unlock me-2"></i>Remove Protection</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Share Note Modal -->
        <div class="modal fade" id="shareNoteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-share-alt me-2"></i>Share Note</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="share_note_id">
                        <div class="mb-3">
                            <label for="recipient_email" class="form-label">Recipient Email</label>
                            <input type="email" class="form-control" id="recipient_email" placeholder="Enter recipient's email">
                        </div>
                        <div class="mb-3">
                            <label for="share_permission" class="form-label">Permission</label>
                            <select class="form-select" id="share_permission">
                                <option value="read">Read only</option>
                                <option value="edit">Can edit</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="shareNoteBtn"><i class="fas fa-share me-2"></i>Share Note</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shared With Modal -->
        <div class="modal fade" id="sharedWithModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-users me-2"></i>Shared With</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="shared_with_note_id">
                        <div id="shared_users_list">
                            <p class="text-center">Loading shared users...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- JavaScript for functionality -->
        <script>
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.toggle('collapsed');
                const content = document.querySelector('.content-area');
                content.style.marginRight = sidebar.classList.contains('collapsed') ? '0' : '300px';
            }
            // Function to handle note deletion
            function deleteNote(noteId) {
                if (confirm('Are you sure you want to delete this note?')) {
                    // First check if the note is locked
                    $.ajax({
                        url: 'check_note_lock.php',
                        type: 'GET',
                        data: {
                            note_id: noteId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.is_locked && !response.is_unlocked) {
                                // If locked and not temporarily unlocked in session, prompt for password
                                let password = prompt('This note is password protected. Enter the password to delete:');

                                if (password !== null) { // User clicked OK
                                    // First try to unlock with the password
                                    $.ajax({
                                        url: 'advanced_notes.php',
                                        type: 'POST',
                                        data: {
                                            action: 'unlock',
                                            note_id: noteId,
                                            password: password
                                        },
                                        dataType: 'json',
                                        success: function(unlockResponse) {
                                            if (unlockResponse.success) {
                                                // Password was correct, proceed with deletion
                                                performDeleteNote(noteId);
                                            } else {
                                                alert('Incorrect password. Cannot delete the note.');
                                            }
                                        },
                                        error: function() {
                                            alert('Failed to verify password. Could not connect to server.');
                                        }
                                    });
                                }
                            } else {
                                // Not locked or already unlocked in session, proceed with deletion
                                performDeleteNote(noteId);
                            }
                        },
                        error: function() {
                            alert('Failed to check note lock status. Could not connect to server.');
                        }
                    });
                }
            }

            function performDeleteNote(noteId) {
                $.ajax({
                    url: 'delete_note.php',
                    type: 'POST',
                    data: {
                        note_id: noteId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Remove the note from the DOM
                            $('#note-' + noteId).parent().remove();
                            alert(response.message || 'Note deleted successfully');
                        } else {
                            alert(response.error || 'Failed to delete note');
                        }
                    },
                    error: function() {
                        alert('Failed to delete note. Could not connect to server.');
                    }
                });
            }

            function togglePin(noteId) {
                $.post('toggle_pin.php', {
                    note_id: noteId
                }, function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        location.reload();
                    } else {
                        alert("Failed to toggle pin.");
                    }
                });
            }

            let searchTimeout;

            $('#searchBox').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    const query = $('#searchBox').val().trim();
                    $.post('search_notes.php', {
                        query: query
                    }, function(response) {
                        let data;
                        try {
                            data = typeof response === "object" ? response : JSON.parse(response);
                        } catch (e) {
                            alert("Invalid response from server.");
                            return;
                        }

                        if (data.success) {
                            const notesHtml = data.notes.map(note => {
                                const noteColor = note.note_color || '<?= $note_color ?>';
                                const fontSize = note.font_size || '<?= $fontSizeCss ?>';
                                const pinned = note.pinned_at ? 'Unpin' : 'Pin';
                                const borderClass = note.pinned_at ? 'border-warning' : '';
                                const pinnedBtnClass = note.pinned_at ? 'btn-warning' : 'btn-outline-warning';

                                const imagesHtml = note.images ?
                                    note.images.split(',').map(img => `<img src="${img}" class="img-fluid rounded my-2" />`).join('') :
                                    '';

                                return `
                        <div class="col">
                            <div class="card note-card ${borderClass}" id="note-${note.note_id}" style="background-color: ${noteColor}; font-size: ${fontSize};">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <input type="text" class="form-control-plaintext editable-title" data-id="${note.note_id}" value="${note.title}">
                                    <div>
                                        <button class="btn btn-sm ${pinnedBtnClass}" onclick="togglePin(${note.note_id})">
                                            <i class="fas fa-thumbtack"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <textarea class="form-control-plaintext editable-content" data-id="${note.note_id}" rows="3">${note.content}</textarea>
                                    ${imagesHtml}
                                </div>
                                <div class="card-footer bg-transparent">
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteNote(${note.note_id})">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                            }).join('');

                            $('#notes-container').html(`<div class="row">${notesHtml}</div>`);
                            // Re-attach edit handlers after dynamic content load
                            attachEditHandlers();
                        } else {
                            alert("Search failed: " + (data.error || "Unknown error"));
                        }
                    });
                }, 300);
            });

            function addNote() {
                const title = $('#note-title').val().trim();
                const content = $('#note-content').val().trim();
                const labels = $('#note-labels').val() || [];
                const images = $('#note-images')[0].files;

                if (!title && !content && images.length === 0) {
                    alert("Note is empty.");
                    return;
                }

                const formData = new FormData();
                formData.append('title', title);
                formData.append('content', content);

                // Only send labels if there are valid values (non-empty array)
                if (labels.length > 0) {
                    // Filter labels to ensure only positive integers are sent
                    const validLabels = labels.filter(label => !isNaN(label) && parseInt(label) > 0);
                    if (validLabels.length > 0) {
                        formData.append('labels', validLabels.join(','));
                    }
                }

                // Add images
                for (let i = 0; i < images.length; i++) {
                    formData.append('images[]', images[i]);
                }

                $.ajax({
                    url: 'save_note.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            $('#note-title').val('');
                            $('#note-content').val('');
                            $('#note-labels').val(null).trigger('change'); // Clear label selection
                            $('#note-images').val('');
                            alert(res.message || 'Note added successfully');
                            location.reload();
                        } else {
                            alert(res.error || 'Failed to add note');
                        }
                    },
                    error: function() {
                        alert('Failed to connect to server');
                    }
                });
            }

            function loadLabels() {
                $.get('get_labels.php', function(res) {
                    const data = typeof res === "object" ? res : JSON.parse(res);
                    if (data.success) {
                        const select = $('#labelFilter');
                        select.empty().append('<option value="">-- All --</option>');
                        data.labels.forEach(label => {
                            select.append(`<option value="${label.label_id}">${label.name}</option>`);
                        });
                    }
                });
            }

            $('#labelFilter').on('change', function() {
                const labelId = $(this).val();
                if (!labelId) {
                    location.reload(); // Show all
                    return;
                }

                $.post('filter_notes_by_label.php', {
                    label_id: labelId
                }, function(res) {
                    const data = typeof res === "object" ? res : JSON.parse(res);
                    if (data.success) {
                        const notesHtml = data.notes.map(note => {
                            const noteColor = note.note_color || '<?= $note_color ?>';
                            const fontSize = note.font_size || '<?= $fontSizeCss ?>';
                            const pinned = note.pinned_at ? 'Unpin' : 'Pin';
                            const borderClass = note.pinned_at ? 'border-warning' : '';
                            const pinnedBtnClass = note.pinned_at ? 'btn-warning' : 'btn-outline-warning';

                            const imagesHtml = note.images ?
                                note.images.split(',').map(img => `<img src="${img}" class="img-fluid rounded my-2" />`).join('') :
                                '';

                            return `
                    <div class="col mb-4">
                        <div class="card note-card ${borderClass}" style="background-color: ${noteColor}; font-size: ${fontSize};">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <input type="text" class="form-control-plaintext editable-title" data-id="${note.note_id}" value="${note.title}">
                                <div>
                                    <button class="btn btn-sm ${pinnedBtnClass}" onclick="togglePin(${note.note_id})">
                                        <i class="fas fa-thumbtack"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control-plaintext editable-content" data-id="${note.note_id}" rows="3">${note.content}</textarea>
                                ${imagesHtml}
                            </div>
                            <div class="card-footer bg-transparent">
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteNote(${note.note_id})">
                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                        }).join('');

                        $('#notes-container').html(`<div class="<?= $layoutClass ?>">${notesHtml}</div>`);
                        // Re-attach edit handlers after dynamic content load
                        attachEditHandlers();
                    }
                });
            });

            // Initial call to populate the dropdown
            loadLabels();

            function refreshLabelList() {
                $.get('get_labels.php', function(res) {
                    const data = typeof res === "object" ? res : JSON.parse(res);
                    const list = $('#labelList');
                    list.empty();
                    if (data.success && data.labels) {
                        data.labels.forEach(label => {
                            list.append(`
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <input type="text" class="form-control form-control-sm me-2 flex-grow-1" value="${label.name}" data-id="${label.label_id}" onchange="renameLabel(${label.label_id}, this.value)">
                        <button class="btn btn-sm btn-danger" onclick="deleteLabel(${label.label_id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </li>
                `);
                        });
                    }
                    loadLabels(); // Refresh dropdown
                });
            }

            function renameLabel(labelId, newName) {
                $.post('rename_label.php', {
                    label_id: labelId,
                    name: newName
                }, function(res) {
                    const data = typeof res === "object" ? res : JSON.parse(res);
                    if (!data.success) alert(data.error || 'Failed to rename label');
                    loadLabels();
                });
            }

            function deleteLabel(labelId) {
                if (!confirm('Are you sure you want to delete this label?')) return;
                $.post('delete_label.php', {
                    label_id: labelId
                }, function(res) {
                    const data = typeof res === "object" ? res : JSON.parse(res);
                    if (!data.success) alert(data.error || 'Failed to delete label');
                    refreshLabelList();
                    loadLabels();
                });
            }

            $('#addLabelBtn').on('click', function() {
                const labelName = $('#newLabelName').val().trim();
                if (!labelName) return alert('Label name required');
                $.post('add_label.php', {
                    name: labelName
                }, function(res) {
                    const data = typeof res === "object" ? res : JSON.parse(res);
                    if (!data.success) alert(data.error || 'Failed to add label');
                    $('#newLabelName').val('');
                    refreshLabelList();
                    loadLabels();
                });
            });

            $('#labelModal').on('shown.bs.modal', refreshLabelList);

            function populateNoteLabelSelector() {
                $.get('get_labels.php', function(res) {
                    const data = typeof res === "object" ? res : JSON.parse(res);
                    const select = $('#note-labels');
                    select.empty();
                    if (data.success && data.labels) {
                        data.labels.forEach(label => {
                            select.append(`<option value="${label.label_id}">${label.name}</option>`);
                        });
                    }

                    // Populate labels for existing notes
                    $('.note-label-select').each(function() {
                        const noteId = $(this).data('note-id');
                        const selectElement = $(this);
                        selectElement.empty();

                        if (data.success && data.labels) {
                            data.labels.forEach(label => {
                                selectElement.append(`<option value="${label.label_id}">${label.name}</option>`);
                            });

                            // Get selected labels for this note
                            $.get(`get_note_labels.php?note_id=${noteId}`, function(labelRes) {
                                try {
                                    const labelData = typeof labelRes === "object" ? labelRes : JSON.parse(labelRes);
                                    if (labelData.success && labelData.labels) {
                                        const selectedLabelIds = labelData.labels.map(l => l.label_id);
                                        selectElement.val(selectedLabelIds);
                                    }
                                } catch (e) {
                                    console.error("Error parsing label data:", e);
                                }
                            });
                        }
                    });
                });
            }

            // Function to attach event handlers to label selects
            function attachLabelHandlers() {
                $('.note-label-select').off('change').on('change', function() {
                    const noteId = $(this).data('note-id');
                    const selectedLabels = $(this).val() || [];

                    $.ajax({
                        type: 'POST',
                        url: 'update_note_labels.php',
                        data: {
                            note_id: noteId,
                            labels: JSON.stringify(selectedLabels)
                        },
                        success: function(response) {
                            console.log('Labels updated:', response);
                        },
                        error: function() {
                            alert('Failed to update labels');
                        }
                    });
                });
            }

            // Function to attach edit handlers
            function attachEditHandlers() {
                // Detach any existing handlers to prevent duplicates
                $('.editable-title, .editable-content').off('input');

                // Attach new handlers
                $('.editable-title, .editable-content').on('input', function() {
                    const noteId = $(this).data('id');
                    const value = $(this).val();
                    autoSaveNote(noteId, $(this).hasClass('editable-title') ? 'title' : 'content', value);
                });

                // Also reattach label handlers
                attachLabelHandlers();
            }

            let autoSaveTimer;

            function autoSaveNote(noteId, fieldType, value) {
                clearTimeout(autoSaveTimer);

                // Show saving status
                const status = $('#autosave-status');
                status.text("Saving...").addClass('visible text-muted');

                autoSaveTimer = setTimeout(() => {
                    const title = $(`.editable-title[data-id="${noteId}"]`).val();
                    const content = $(`.editable-content[data-id="${noteId}"]`).val();

                    $.post('autosave_note.php', {
                        note_id: noteId,
                        title: title,
                        content: content
                    }, function(response) {
                        const data = typeof response === "object" ? response : JSON.parse(response);
                        if (data.success) {
                            status.text("Saved at " + new Date().toLocaleTimeString()).removeClass('text-muted').addClass('text-success');

                            // Hide after 2 seconds
                            setTimeout(() => {
                                status.removeClass('visible');
                            }, 2000);
                        } else {
                            console.error(data.error || "Auto-save failed");
                            status.text("Failed to save").removeClass('text-muted').addClass('text-danger');
                        }
                    });
                }, 1000);
            }

            // Note security features
            // Lock Note
            function lockNote(noteId) {
                $('#lock_note_id').val(noteId);
                $('#lockNoteModal').modal('show');
            }

            $('#lockNoteBtn').on('click', function() {
                const noteId = $('#lock_note_id').val();
                const password = $('#lock_password').val();
                const confirmPassword = $('#lock_confirm_password').val();

                if (!password) {
                    alert('Please enter a password');
                    return;
                }

                if (password !== confirmPassword) {
                    alert('Passwords do not match');
                    return;
                }

                $.ajax({
                    url: 'advanced_notes.php',
                    type: 'POST',
                    data: {
                        action: 'lock',
                        note_id: noteId,
                        password: password
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#lockNoteModal').modal('hide');
                            location.reload();
                        } else {
                            alert(response.error || 'Failed to lock note');
                        }
                    },
                    error: function() {
                        alert('Failed to connect to server');
                    }
                });
            });

            // Unlock Note
            function unlockNote(noteId) {
                $('#unlock_note_id').val(noteId);
                $('#unlockNoteModal').modal('show');
            }

            $('#unlockNoteBtn').on('click', function() {
                const noteId = $('#unlock_note_id').val();
                const password = $('#unlock_password').val();

                if (!password) {
                    alert('Please enter the password');
                    return;
                }

                $.ajax({
                    url: 'advanced_notes.php',
                    type: 'POST',
                    data: {
                        action: 'unlock',
                        note_id: noteId,
                        password: password
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#unlockNoteModal').modal('hide');
                            location.reload();
                        } else {
                            alert(response.error || 'Failed to unlock note');
                        }
                    },
                    error: function() {
                        alert('Failed to connect to server');
                    }
                });
            });

            // Change Note Password
            function changeNotePassword(noteId) {
                $('#change_note_id').val(noteId);
                $('#changePasswordModal').modal('show');
            }

            $('#changePasswordBtn').on('click', function() {
                const noteId = $('#change_note_id').val();
                const currentPassword = $('#current_password').val();
                const newPassword = $('#new_password').val();
                const confirmNewPassword = $('#confirm_new_password').val();

                if (!currentPassword || !newPassword) {
                    alert('Please fill in all fields');
                    return;
                }

                if (newPassword !== confirmNewPassword) {
                    alert('New passwords do not match');
                    return;
                }

                $.ajax({
                    url: 'advanced_notes.php',
                    type: 'POST',
                    data: {
                        action: 'change_password',
                        note_id: noteId,
                        current_password: currentPassword,
                        new_password: newPassword
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#changePasswordModal').modal('hide');
                        } else {
                            alert(response.error || 'Failed to change password');
                        }
                    },
                    error: function() {
                        alert('Failed to connect to server');
                    }
                });
            });

            // Disable Password Protection
            function disableNotePassword(noteId) {
                $('#disable_note_id').val(noteId);
                $('#disablePasswordModal').modal('show');
            }

            $('#disablePasswordBtn').on('click', function() {
                const noteId = $('#disable_note_id').val();
                const password = $('#disable_password').val();

                if (!password) {
                    alert('Please enter the current password');
                    return;
                }

                $.ajax({
                    url: 'advanced_notes.php',
                    type: 'POST',
                    data: {
                        action: 'disable_password',
                        note_id: noteId,
                        password: password
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#disablePasswordModal').modal('hide');
                            location.reload();
                        } else {
                            alert(response.error || 'Failed to disable password protection');
                        }
                    },
                    error: function() {
                        alert('Failed to connect to server');
                    }
                });
            });

            // Share Note
            function shareNote(noteId) {
                $('#share_note_id').val(noteId);
                $('#shareNoteModal').modal('show');
            }

            $('#shareNoteBtn').on('click', function() {
                const noteId = $('#share_note_id').val();
                const recipientEmail = $('#recipient_email').val();
                const permission = $('#share_permission').val();

                if (!recipientEmail) {
                    alert('Please enter a recipient email');
                    return;
                }

                $.ajax({
                    url: 'advanced_notes.php',
                    type: 'POST',
                    data: {
                        action: 'share',
                        note_id: noteId,
                        recipient_email: recipientEmail,
                        permission: permission
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#shareNoteModal').modal('hide');
                        } else {
                            alert(response.error || 'Failed to share note');
                        }
                    },
                    error: function() {
                        alert('Failed to connect to server');
                    }
                });
            });

            // View and manage shared users
            function viewSharedUsers(noteId) {
                $('#shared_with_note_id').val(noteId);

                // Load shared users
                $.ajax({
                    url: 'get_shared_users.php',
                    type: 'GET',
                    data: {
                        note_id: noteId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let html = '';
                            if (response.users && response.users.length > 0) {
                                html = '<ul class="list-group">';
                                response.users.forEach(user => {
                                    html += `
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <strong>${user.email}</strong>
                  <span class="badge ${user.permission === 'read' ? 'bg-info' : 'bg-warning'} ms-2">${user.permission === 'read' ? 'Read Only' : 'Can Edit'}</span>
                </div>
                <button type="button" class="btn btn-sm btn-danger" onclick="revokeAccess(${noteId}, ${user.user_id})">
                  <i class="fas fa-times me-1"></i> Revoke
                </button>
              </li>
            `;
                                });
                                html += '</ul>';
                            } else {
                                html = '<p class="text-center">This note is not shared with anyone.</p>';
                            }
                            $('#shared_users_list').html(html);
                        } else {
                            $('#shared_users_list').html('<p class="text-center text-danger">Failed to load shared users.</p>');
                        }
                    },
                    error: function() {
                        $('#shared_users_list').html('<p class="text-center text-danger">Failed to connect to server.</p>');
                    }
                });

                $('#sharedWithModal').modal('show');
            }

            // Revoke access
            function revokeAccess(noteId, recipientId) {
                if (confirm('Are you sure you want to revoke access for this user?')) {
                    $.ajax({
                        url: 'advanced_notes.php',
                        type: 'POST',
                        data: {
                            action: 'revoke',
                            note_id: noteId,
                            recipient_id: recipientId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert(response.message);
                                viewSharedUsers(noteId); // Refresh the list
                            } else {
                                alert(response.error || 'Failed to revoke access');
                            }
                        },
                        error: function() {
                            alert('Failed to connect to server');
                        }
                    });
                }
            }

            // Check if note is locked before viewing/editing
            function checkNoteLock(noteId, callback) {
                $.ajax({
                    url: 'check_note_lock.php',
                    type: 'GET',
                    data: {
                        note_id: noteId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.is_locked && !response.is_unlocked) {
                            unlockNote(noteId);
                        } else {
                            if (callback) callback();
                        }
                    },
                    error: function() {
                        alert('Failed to check note lock status');
                    }
                });
            }

            function relockNote(noteId) {
                $.ajax({
                    url: 'advanced_notes.php',
                    type: 'POST',
                    data: {
                        action: 'relock',
                        note_id: noteId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.error || 'Failed to re-lock note');
                        }
                    },
                    error: function() {
                        alert('Failed to connect to server');
                    }
                });
            }

            // Initialize when document is ready
            $(document).ready(function() {
                // Call your existing functions
                attachEditHandlers();
                populateNoteLabelSelector();

                <?php if ($show_avatar_prompt): ?>
                    $('#avatarPromptModal').modal('show');
                <?php endif; ?>
            });

            document.addEventListener('DOMContentLoaded', function() {
                // For existing elements
                document.querySelectorAll('.dropdown').forEach(dropdown => {
                    dropdown.classList.add('dropup');
                });

                // Handle dynamically created notes and dropdowns
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) { // Only process element nodes
                                    const dropdowns = node.querySelectorAll ? node.querySelectorAll('.dropdown') : [];
                                    dropdowns.forEach(dropdown => {
                                        dropdown.classList.add('dropup');
                                    });
                                }
                            });
                        }
                    });
                });

                // Start observing the document with the configured parameters
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });

                // For handling click events (alternative approach)
                document.addEventListener('click', function(event) {
                    const dropdownToggle = event.target.closest('.dropdown-toggle');
                    if (dropdownToggle) {
                        const dropdownContainer = dropdownToggle.closest('.dropdown');
                        if (dropdownContainer) {
                            dropdownContainer.classList.add('dropup');

                            // Ensure the dropdown menu is visible with proper z-index
                            setTimeout(() => {
                                if (dropdownContainer.querySelector('.dropdown-menu.show')) {
                                    dropdownContainer.style.zIndex = '1030';
                                }
                            }, 10);
                        }
                    }
                });

                // Clean up z-index when dropdown is closed
                document.addEventListener('hidden.bs.dropdown', function(event) {
                    setTimeout(() => {
                        const dropdown = event.target;
                        dropdown.style.zIndex = '';
                    }, 150);
                });
            });
        </script>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </div>
</body>

</html>