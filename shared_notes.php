<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

try {
    // Get user data
    $stmt = $pdo->prepare("SELECT username, email, avatar, preferences FROM users WHERE user_id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Parse preferences
    $prefs = json_decode($user['preferences'] ?? '{}', true);
    $theme = $prefs['theme'] ?? 'light';
    $font_size = $prefs['font_size'] ?? 'medium';
    $note_color = $prefs['note_color'] ?? '#ffffff';
    
    // Font size mapping
    $fontSizeCss = match($font_size) {
        'small' => '0.9rem',
        'large' => '1.3rem',
        default => '1rem'
    };
    
    // Get notes shared with user
    $stmt = $pdo->prepare("
        SELECT n.*, s.permission, u.username as owner_name 
        FROM shared_notes s
        JOIN notes n ON s.note_id = n.note_id
        JOIN users u ON s.owner_id = u.user_id
        WHERE s.recipient_id = ? AND s.verified = 1
        ORDER BY n.updated_at DESC
    ");
    $stmt->execute([$user_id]);
    $shared_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Log the full error message with details
    error_log('Database error in note sharing: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to share note. Error: ' . $e->getMessage()]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Notes - NoteKeeper</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        :root {
            --primary-color: #4c51bf;
            --secondary-color: #a855f7;
            --accent-color: #6b48ff;
            --light-bg: #f8f9fa;
            --dark-bg: #1e1e1e;
            --light-text: #f5f5f5;
            --dark-text: #333333;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            --transition-speed: 0.3s;
        }
        
        body {
            background: linear-gradient(135deg, #6b48ff, #a3c9ff);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            padding: 2rem 0;
            color: <?= $theme === 'dark' ? 'var(--light-text)' : 'var(--dark-text)' ?>;
        }
        
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .page-header {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .page-title i {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-right: 0.5rem;
            font-size: 2rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            border-radius: 10px;
            transition: transform var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 81, 191, 0.4);
            background: linear-gradient(135deg, #3b41a0, #9333ea);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 10px;
            transition: all var(--transition-speed) ease;
            font-weight: 500;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76, 81, 191, 0.3);
        }
        
        .btn-outline-danger {
            border-radius: 10px;
            transition: all var(--transition-speed) ease;
            font-weight: 500;
        }
        
        .btn-outline-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn i {
            margin-right: 0.5rem;
        }
        
        .note-card {
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            transition: all var(--transition-speed) ease;
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease-in-out;
            animation-fill-mode: both;
            background-color: <?= $theme === 'dark' ? 'rgba(40, 40, 40, 0.95)' : 'rgba(255, 255, 255, 0.95)' ?>;
            border: none;
            overflow: hidden;
        }
        
        .note-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        
        .card-header {
            font-weight: 600;
            padding: 1rem 1.25rem;
            background: <?= $theme === 'dark' ? 'rgba(30, 30, 30, 0.9)' : 'rgba(245, 245, 250, 0.9)' ?>;
            border-bottom: 1px solid <?= $theme === 'dark' ? 'rgba(100, 100, 100, 0.3)' : 'rgba(0, 0, 0, 0.1)' ?>;
            color: <?= $theme === 'dark' ? 'var(--light-text)' : 'var(--dark-text)' ?>;
        }
        
        .card-body {
            padding: 1.5rem;
            font-size: <?= $fontSizeCss ?>;
            color: <?= $theme === 'dark' ? 'var(--light-text)' : 'var(--dark-text)' ?>;
        }
        
        .note-card textarea, .note-card input {
            resize: none;
            border: none;
            background: transparent;
            outline: none !important;
            width: 100%;
            font-size: inherit;
            color: inherit;
            padding: 0;
        }
        
        .note-card textarea:focus, .note-card input:focus {
            box-shadow: none;
            border-color: transparent;
        }
        
        .note-card img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform var(--transition-speed) ease;
            margin-bottom: 0.5rem;
        }
        
        .note-card img:hover {
            transform: scale(1.02);
        }
        
        .card-footer {
            background-color: <?= $theme === 'dark' ? 'rgba(30, 30, 30, 0.9)' : 'rgba(245, 245, 250, 0.9)' ?>;
            color: <?= $theme === 'dark' ? 'rgba(255, 255, 255, 0.6)' : 'rgba(0, 0, 0, 0.5)' ?>;
            border-top: 1px solid <?= $theme === 'dark' ? 'rgba(100, 100, 100, 0.3)' : 'rgba(0, 0, 0, 0.1)' ?>;
            font-size: 0.875rem;
            padding: 0.75rem 1.25rem;
        }
        
        .shared-by {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            z-index: 10;
        }
        
        .shared-by i {
            margin-right: 0.3rem;
        }
        
        .permission-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            font-size: 0.75rem;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            z-index: 10;
        }
        
        .permission-read {
            background-color: #cfe2ff;
            color: #084298;
        }
        
        .permission-edit {
            background-color: #fff3cd;
            color: #664d03;
        }
        
        .permission-badge i {
            margin-right: 0.3rem;
        }
        
        .alert {
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
            animation: fadeIn 0.5s ease-in-out;
            text-align: center;
            box-shadow: var(--card-shadow);
        }
        
        .alert i {
            font-size: 1.5rem;
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        
        .note-content {
            min-height: 120px;
        }
        
        .image-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .image-gallery img {
            flex: 0 0 calc(50% - 0.5rem);
            object-fit: cover;
            max-height: 150px;
        }
        
        /* Animation delays for cards */
        .row > div:nth-child(1) .note-card {
            animation-delay: 0.1s;
        }
        
        .row > div:nth-child(2) .note-card {
            animation-delay: 0.2s;
        }
        
        .row > div:nth-child(3) .note-card {
            animation-delay: 0.3s;
        }
        
        .row > div:nth-child(4) .note-card {
            animation-delay: 0.4s;
        }
        
        .row > div:nth-child(5) .note-card {
            animation-delay: 0.5s;
        }
        
        .row > div:nth-child(6) .note-card {
            animation-delay: 0.6s;
        }
        
        /* Toast notification for auto-save */
        .save-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(25, 135, 84, 0.9);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            font-weight: 500;
            z-index: 1050;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }
        
        .save-toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .save-toast i {
            margin-right: 0.5rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-header .btn-group {
                margin-top: 1rem;
                width: 100%;
                justify-content: space-between;
            }
            
            .note-card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header d-flex justify-content-between align-items-center">
            <h2 class="page-title"><i class="fas fa-share-alt"></i> Shared Notes</h2>
            <div>
                <a href="homepage.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <?php if (empty($shared_notes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                No notes have been shared with you yet. When someone shares a note, it will appear here.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($shared_notes as $note): ?>
                    <div class="col-md-4 col-sm-6 mb-4">
                        <div class="card note-card h-100" id="shared-note-<?= $note['note_id'] ?>">
                            <div class="position-relative">
                                <span class="permission-badge <?= $note['permission'] === 'read' ? 'permission-read' : 'permission-edit' ?>">
                                    <i class="fas <?= $note['permission'] === 'read' ? 'fa-eye' : 'fa-edit' ?>"></i>
                                    <?= $note['permission'] === 'read' ? 'Read Only' : 'Can Edit' ?>
                                </span>
                                <span class="shared-by">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($note['owner_name']) ?>
                                </span>
                            </div>
                            
                            <div class="card-header">
                                <?php if ($note['permission'] === 'edit'): ?>
                                    <input type="text" 
                                        class="form-control-plaintext shared-title"
                                        data-id="<?= $note['note_id'] ?>"
                                        value="<?= htmlspecialchars($note['title']) ?>">
                                <?php else: ?>
                                    <h5 class="card-title m-0"><?= htmlspecialchars($note['title']) ?></h5>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body">
                                <?php if ($note['permission'] === 'edit'): ?>
                                    <textarea 
                                        class="form-control-plaintext shared-content note-content"
                                        data-id="<?= $note['note_id'] ?>"
                                        rows="5"><?= htmlspecialchars($note['content']) ?></textarea>
                                <?php else: ?>
                                    <div class="note-content">
                                        <?= nl2br(htmlspecialchars($note['content'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($note['images'])): ?>
                                    <div class="image-gallery">
                                        <?php foreach (explode(',', $note['images']) as $img): ?>
                                            <img src="<?= htmlspecialchars($img) ?>"
                                                alt="Attached image" />
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-footer">
                                <small>
                                    <i class="fas fa-clock me-1"></i>
                                    Updated: <?= date('M j, Y g:i A', strtotime($note['updated_at'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Auto-save toast notification -->
    <div class="save-toast" id="save-toast">
        <i class="fas fa-check-circle"></i> Changes saved
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Autosave for editable shared notes
    let autoSaveTimer;
    const saveToast = document.getElementById('save-toast');
    
    $('.shared-title, .shared-content').on('input', function() {
        const noteId = $(this).data('id');
        const isTitle = $(this).hasClass('shared-title');
        const value = $(this).val();
        
        clearTimeout(autoSaveTimer);
        
        // Visual feedback - add subtle glow to indicate unsaved changes
        const card = $(this).closest('.note-card');
        card.css('box-shadow', '0 0 0 2px rgba(76, 81, 191, 0.3), 0 10px 30px rgba(0, 0, 0, 0.15)');
        
        autoSaveTimer = setTimeout(() => {
            const title = $(`.shared-title[data-id="${noteId}"]`).val();
            const content = $(`.shared-content[data-id="${noteId}"]`).val();
            
            $.ajax({
                url: 'update_shared_note.php',
                type: 'POST',
                data: {
                    note_id: noteId,
                    title: title,
                    content: content
                },
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.success) {
                            // Remove the unsaved indicator
                            card.css('box-shadow', '0 10px 30px rgba(0, 0, 0, 0.15)');
                            
                            // Show the toast notification
                            saveToast.classList.add('show');
                            setTimeout(() => {
                                saveToast.classList.remove('show');
                            }, 2000);
                        } else {
                            console.error('Failed to update shared note:', data.error);
                        }
                    } catch (e) {
                        console.error('Invalid response:', e);
                    }
                },
                error: function() {
                    console.error('Failed to connect to server');
                }
            });
        }, 1000);
    });
    
    // Auto-resize textareas as content grows
    $('.shared-content').each(function() {
        this.setAttribute('style', 'height:' + (this.scrollHeight) + 'px;overflow-y:hidden;');
    }).on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Add a hover effect to cards
    $('.note-card').hover(
        function() {
            $(this).find('.card-header').css('background', 
                '<?= $theme === "dark" ? "rgba(40, 40, 40, 0.9)" : "rgba(235, 235, 250, 0.9)" ?>');
        },
        function() {
            $(this).find('.card-header').css('background', 
                '<?= $theme === "dark" ? "rgba(30, 30, 30, 0.9)" : "rgba(245, 245, 250, 0.9)" ?>');
        }
    );
    
    // Add animation when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.note-card');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100 * index);
        });
    });
    </script>
</body>
</html>