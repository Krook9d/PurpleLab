<?php
session_start();

if (!isset($_SESSION['email'])) {
    header('Location: connexion.html');
    exit;
}

$conn_string = sprintf(
    "host=%s port=5432 dbname=%s user=%s password=%s",
    getenv('DB_HOST'),
    getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASS')
);

$conn = pg_connect($conn_string);

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

$email = $_SESSION['email'];

// Get user information
$sql = "SELECT id, first_name, last_name, email, analyst_level, avatar FROM users WHERE email=$1";
$result = pg_query_params($conn, $sql, array($email));

if ($result && $row = pg_fetch_assoc($result)) {
    $user_id = $row['id'];
    $first_name = $row['first_name'];
    $last_name = $row['last_name'];
    $email = $row['email'];
    $analyst_level = $row['analyst_level'];
    $avatar = $row['avatar'];
} else {
    die("Error retrieving user information.");
}

// Handle content submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = $_POST['content'];

    $sql = "INSERT INTO contents (author_id, content) VALUES ($1, $2)";
    $result = pg_query_params($conn, $sql, array($user_id, $content));
    
    if ($result) {
        header('Location: sharing.php');
        exit;
    } else {
        echo "Error during content insertion: " . pg_last_error($conn);
    }
}

// Handle content deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['id'])) {
    $id = $_POST['id'];
    
    $canDelete = true;
    
    if ($canDelete) {
        $sql = "DELETE FROM contents WHERE id = $1";
        $result = pg_query_params($conn, $sql, array($id));
        
        if ($result) {
            header('Location: sharing.php?deleted=success');
            exit;
        } else {
            echo "Error when deleting content: " . pg_last_error($conn);
        }
    }
}

// Get all contents
$sql = "SELECT contents.id, contents.content, users.first_name, users.last_name, contents.author_id FROM contents JOIN users ON contents.author_id = users.id";
$result = pg_query($conn, $sql);
$contents = [];

if (pg_num_rows($result) > 0) {
    while ($row = pg_fetch_assoc($result)) {
        $contents[] = $row;
    }
}

pg_close($conn);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="MD_image/logowhite.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="css/main.css?v=<?= filemtime('css/main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab management
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        let currentTab = 'text';

        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.dataset.tab;
                switchTab(tab);
            });
        });

        function switchTab(tab) {
            currentTab = tab;
            
            // Update tab buttons
            tabBtns.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });
            
            // Update tab content
            tabContents.forEach(content => {
                content.classList.toggle('active', content.id === tab + '-tab');
            });
        }

        // Editor management
        const editors = document.querySelectorAll('.content-editor');
        const charCount = document.querySelector('.char-count');
        const wordCount = document.querySelector('.word-count');
        const submitBtn = document.getElementById('submit-btn');
        const errorMessage = document.querySelector('.error-message');

        editors.forEach(editor => {
            editor.addEventListener('input', function() {
                updateStats(this);
                validateContent(this);
                syncEditors(this);
            });

            editor.addEventListener('keydown', function(e) {
                // Tab key handling for code editor
                if (e.key === 'Tab' && this.classList.contains('code-editor')) {
                    e.preventDefault();
                    const start = this.selectionStart;
                    const end = this.selectionEnd;
                    this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 4;
                }
            });
        });

        function updateStats(editor) {
            const content = editor.value;
            const chars = content.length;
            const words = content.trim() ? content.trim().split(/\s+/).length : 0;
            
            charCount.textContent = chars + '/1000';
            wordCount.textContent = words + ' words';
            
            // Update char count color
            charCount.className = 'char-count';
            if (chars > 800) charCount.classList.add('warning');
            if (chars > 950) charCount.classList.add('danger');
        }

        function validateContent(editor) {
            const content = editor.value;
            const isValid = content.length <= 1000 && content.trim().length > 0;
            
            submitBtn.disabled = !isValid;
            
            if (content.length > 1000) {
                errorMessage.textContent = 'Content exceeds 1000 characters limit.';
                errorMessage.style.display = 'block';
            } else {
                errorMessage.style.display = 'none';
            }
        }

        function syncEditors(activeEditor) {
            // Sync content between editors
            const content = activeEditor.value;
            editors.forEach(editor => {
                if (editor !== activeEditor) {
                    editor.value = content;
                }
            });
        }

        // Language selector for code editor
        const languageSelector = document.querySelector('.language-selector');
        if (languageSelector) {
            languageSelector.addEventListener('change', function() {
                const codeEditor = document.querySelector('.code-editor');
                codeEditor.dataset.language = this.value;
                
                // Update placeholder based on language
                const placeholders = {
                    'bash': '#!/bin/bash\necho "Purple Team Script"\n# Add your bash commands here',
                    'python': '#!/usr/bin/env python3\nprint("Purple Team Tool")\n# Add your Python code here',
                    'powershell': '# PowerShell Script\nWrite-Host "Purple Team Tool"\n# Add your PowerShell commands here',
                    'javascript': '// JavaScript Code\nconsole.log("Purple Team Tool");\n// Add your JavaScript code here',
                    'yaml': '# YAML Configuration\nname: purple-team-config\nversion: 1.0\n# Add your YAML here',
                    'json': '{\n  "name": "purple-team-config",\n  "version": "1.0"\n}',
                    'sql': '-- SQL Query\nSELECT * FROM security_logs\nWHERE event_type = \'suspicious\';\n-- Add your SQL here',
                    'regex': '# Regex Pattern\n(?i)(password|passwd|pwd)\\s*[=:]\\s*["\']?([^\\s"\']+)\n# Add your regex here'
                };
                
                codeEditor.placeholder = placeholders[this.value] || 'Enter your code here...';
            });
        }

        // Copy code functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.copy-code')) {
                e.preventDefault();
                e.stopPropagation();
                
                const copyBtn = e.target.closest('.copy-code');
                const codeBlock = copyBtn.closest('.code-block');
                const codeElement = codeBlock.querySelector('code');
                
                if (codeElement) {
                    // Get the text content, preserving line breaks
                    const codeText = codeElement.textContent || codeElement.innerText;
                    
                    // Try to use the modern clipboard API first
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(codeText).then(() => {
                            showCopySuccess(copyBtn);
                        }).catch(err => {
                            console.error('Failed to copy with clipboard API:', err);
                            fallbackCopy(codeText, copyBtn);
                        });
                    } else {
                        // Fallback for older browsers or non-HTTPS
                        fallbackCopy(codeText, copyBtn);
                    }
                }
            }
        });
        
        function fallbackCopy(text, btn) {
            // Create a temporary textarea
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-999999px';
            textarea.style.top = '-999999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess(btn);
                } else {
                    showCopyError(btn);
                }
            } catch (err) {
                console.error('Fallback copy failed:', err);
                showCopyError(btn);
            }
            
            document.body.removeChild(textarea);
        }
        
        function showCopySuccess(btn) {
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check" style="color: #50fa7b;"></i>';
            btn.style.background = 'rgba(80, 250, 123, 0.2)';
            
            setTimeout(() => {
                btn.innerHTML = originalContent;
                btn.style.background = '';
            }, 2000);
        }
        
        function showCopyError(btn) {
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-times" style="color: #ff5555;"></i>';
            btn.style.background = 'rgba(255, 85, 85, 0.2)';
            
            setTimeout(() => {
                btn.innerHTML = originalContent;
                btn.style.background = '';
            }, 2000);
        }

        // Filter functionality
        const filterBtns = document.querySelectorAll('.filter-btn');
        const posts = document.querySelectorAll('.post-card');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const filter = this.dataset.filter;
                
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                posts.forEach(post => {
                    if (filter === 'all' || post.dataset.type === filter) {
                        post.style.display = 'block';
                        post.style.animation = 'fadeInUp 0.3s ease-out';
                    } else {
                        post.style.display = 'none';
                    }
                });
            });
        });

        // Preview functionality
        const previewBtn = document.getElementById('preview-btn');
        let isPreviewMode = false;

        previewBtn.addEventListener('click', function() {
            const activeEditor = document.querySelector('.tab-content.active .content-editor');
            
            if (!isPreviewMode) {
                showPreview(activeEditor);
            } else {
                hidePreview();
            }
        });

        function showPreview(editor) {
            const content = editor.value;
            if (!content.trim()) return;
            
            const previewDiv = document.createElement('div');
            previewDiv.className = 'preview-content';
            previewDiv.innerHTML = processContentForPreview(content);
            
            editor.style.display = 'none';
            editor.parentNode.appendChild(previewDiv);
            
            previewBtn.innerHTML = '<i class="fas fa-edit"></i> Edit';
            isPreviewMode = true;
        }

        function hidePreview() {
            const preview = document.querySelector('.preview-content');
            const activeEditor = document.querySelector('.tab-content.active .content-editor');
            
            if (preview) {
                preview.remove();
                activeEditor.style.display = 'block';
            }
            
            previewBtn.innerHTML = '<i class="fas fa-eye"></i> Preview';
            isPreviewMode = false;
        }

        function processContentForPreview(content) {
            // Simple markdown-like processing for preview
            let processed = content;
            
            // Code blocks
            processed = processed.replace(/```(\w+)?\n([\s\S]*?)\n```/g, 
                '<div class="code-block"><div class="code-header"><span class="language">$1</span></div><pre><code>$2</code></pre></div>');
            
            // Inline code
            processed = processed.replace(/`([^`]+)`/g, '<code class="inline-code">$1</code>');
            
            // Headers
            processed = processed.replace(/^## (.+)$/gm, '<h3>$1</h3>');
            processed = processed.replace(/^# (.+)$/gm, '<h2>$1</h2>');
            
            // Line breaks
            processed = processed.replace(/\n/g, '<br>');
            
            return processed;
        }

        // Initialize
        if (editors.length > 0) {
            updateStats(editors[0]);
            validateContent(editors[0]);
        }

        // Initialize Prism syntax highlighting
        function initializePrism() {
            if (typeof Prism !== 'undefined') {
                Prism.highlightAll();
            }
        }
        
        // Call Prism on page load
        initializePrism();
        
        // Re-highlight after any dynamic content changes
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Check if new code blocks were added
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1 && node.querySelector && node.querySelector('code[class*="language-"]')) {
                            setTimeout(initializePrism, 100);
                        }
                    });
                }
            });
        });
        
        // Observe changes in the posts container
        const postsContainer = document.querySelector('.posts-container');
        if (postsContainer) {
            observer.observe(postsContainer, { childList: true, subtree: true });
        }

       
        let currentPostId = null;
        const deleteModal = document.getElementById('deleteConfirmModal');
        
        // Ouvrir la modale de confirmation
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-post-btn')) {
                e.preventDefault();
                const btn = e.target.closest('.delete-post-btn');
                currentPostId = btn.getAttribute('data-post-id');
                deleteModal.classList.add('modal-show');
                document.body.style.overflow = 'hidden';
            }
        });
        
    
        document.getElementById('cancelDelete').addEventListener('click', function() {
            closeDeleteModal();
        });
        
        
        document.getElementById('confirmDelete').addEventListener('click', function() {
            if (currentPostId) {
                deletePost(currentPostId);
            }
        });
        
      
        deleteModal.addEventListener('click', function(e) {
            if (e.target === deleteModal) {
                closeDeleteModal();
            }
        });
        
      
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && deleteModal.classList.contains('modal-show')) {
                closeDeleteModal();
            }
        });
        
        function closeDeleteModal() {
            deleteModal.classList.remove('modal-show');
            document.body.style.overflow = '';
            currentPostId = null;
        }
        
        function deletePost(postId) {
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'sharing.php';
            form.style.display = 'none';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = postId;
            
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete';
            deleteInput.value = '1';
            
            form.appendChild(idInput);
            form.appendChild(deleteInput);
            document.body.appendChild(form);
            
         
            closeDeleteModal();
            
   
            form.submit();
        }

    });
    </script>
</head>
<body>

<div class="nav-bar">
    <!-- Add logo to top of nav-bar -->
    <div class="nav-logo">
        <img src="MD_image/logowhiteV3.png" alt="Logo" /> 
    </div>

    <!-- Display software version -->
    <?php include $_SERVER['DOCUMENT_ROOT'].'/scripts/php/version.php'; ?>
    <div class="software-version">
        <?php echo SOFTWARE_VERSION; ?>
    </div>

    <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
        <li><a href="https://<?= $_SERVER['SERVER_ADDR'] ?>:5601" target="_blank"><i class="fas fa-crosshairs"></i> <span>Hunting</span></a></li>
        <li><a href="mittre.php"><i class="fas fa-book"></i> <span>Mitre Att&ck</span></a></li>
        <li><a href="custom_payloads.php"><i class="fas fa-code"></i> <span>Custom Payloads</span></a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> <span>Malware</span></a></li>
        <li><a href="sharing.php" class="active"><i class="fas fa-pencil-alt"></i> <span>Sharing</span></a></li>
        <li><a href="sigma.php"><i class="fas fa-shield-alt"></i> <span>Sigma Rules</span></a></li>
        <li><a href="rule_lifecycle.php"><i class="fas fa-cogs"></i> <span>Rule Lifecycle</span></a></li>
        <li><a href="health.php"><i class="fas fa-heartbeat"></i> <span>Health</span></a></li>
        <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@local.com'): ?>
            <li><a href="admin.php"><i class="fas fa-user-shield"></i> <span>Admin</span></a></li>
        <?php endif; ?>
    </ul>

    <!-- Container for credits at the bottom of the nav-bar -->
    <div class="nav-footer">
        <a href="https://github.com/Krook9d" target="_blank">
            <img src="https://pngimg.com/uploads/github/github_PNG20.png" alt="GitHub Icon" class="github-icon"/> 
            Made by Krook9d
        </a>
    </div>
</div>

<div class="user-info-bar">
    <div class="avatar-info">
        <img src="<?= $avatar ?>" alt="Avatar">
        <button class="user-button">
            <span><?= $first_name ?> <?= $last_name ?></span>
            <div class="dropdown-content">
                <a href="sharing.php" id="settings-link"><i class="fas fa-cog"></i>Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </button>
    </div>
</div>

<div class="content">
    <div class="sharing-container">
        <!-- New Post Section -->
        <div class="new-post-section">
            <div class="post-editor">
                <div class="editor-header">
                    <h3><i class="fas fa-edit"></i> Create New Post</h3>
                    <div class="editor-tabs">
                        <button class="tab-btn active" data-tab="text">
                            <i class="fas fa-align-left"></i> Text
                        </button>
                        <button class="tab-btn" data-tab="code">
                            <i class="fas fa-code"></i> Code
                        </button>
                        <button class="tab-btn" data-tab="mixed">
                            <i class="fas fa-layer-group"></i> Mixed
                        </button>
                    </div>
                </div>

                <form class="post-form" method="POST" action="sharing.php">
                    <div class="editor-content">
                        <!-- Text Tab -->
                        <div class="tab-content active" id="text-tab">
                            <textarea 
                                name="content" 
                                class="content-editor text-editor" 
                                placeholder="Share your knowledge, insights, or purple team techniques..."
                                rows="8"></textarea>
                        </div>

                        <!-- Code Tab -->
                        <div class="tab-content" id="code-tab">
                            <div class="code-editor-wrapper">
                                <input 
                                    type="text" 
                                    name="code_title" 
                                    class="code-title-input" 
                                    placeholder="Script title (e.g., 'File Monitor Script', 'Log Parser', etc.)"
                                    maxlength="100">
                                <select class="language-selector">
                                    <option value="bash">Bash/Shell</option>
                                    <option value="python">Python</option>
                                    <option value="powershell">PowerShell</option>
                                    <option value="javascript">JavaScript</option>
                                    <option value="yaml">YAML</option>
                                    <option value="json">JSON</option>
                                    <option value="sql">SQL</option>
                                    <option value="regex">Regex</option>
                                </select>
                                <textarea 
                                    name="content" 
                                    class="content-editor code-editor" 
                                    placeholder="# Enter your code here&#10;echo 'Hello Purple Team!'"
                                    rows="12"></textarea>
                                <textarea 
                                    name="code_description" 
                                    class="code-description-input" 
                                    placeholder="Describe what this code does, how to use it, or any important notes..."
                                    rows="3"></textarea>
                            </div>
                        </div>

                        <!-- Mixed Tab -->
                        <div class="tab-content" id="mixed-tab">
                            <textarea 
                                name="content" 
                                class="content-editor mixed-editor" 
                                placeholder="You can mix text and code using markdown syntax:&#10;&#10;## Purple Team Technique&#10;&#10;Here's a useful command:&#10;&#10;```bash&#10;grep -r 'suspicious_pattern' /var/log/&#10;```&#10;&#10;This helps with threat hunting..."
                                rows="12"></textarea>
                            <div class="markdown-help">
                                <small><i class="fas fa-info-circle"></i> Use ```language to create code blocks</small>
                            </div>
                        </div>
                    </div>

                    <div class="editor-footer">
                        <div class="editor-stats">
                            <span class="char-count">0/1000</span>
                            <span class="word-count">0 words</span>
                        </div>
                        <div class="editor-actions">
                            <button type="button" class="btn btn-secondary" id="preview-btn">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <button type="submit" class="btn btn-primary" id="submit-btn">
                                <i class="fas fa-paper-plane"></i> Share Knowledge
                            </button>
                        </div>
                    </div>

                    <div class="error-message" style="display: none;"></div>
                </form>
            </div>
        </div>

        <!-- Posts Feed -->
        <div class="posts-feed">
            <div class="feed-header">
                <h3><i class="fas fa-stream"></i> Community Knowledge</h3>
                <div class="feed-filters">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="text">Text</button>
                    <button class="filter-btn" data-filter="code">Code</button>
                </div>
            </div>

            <div class="posts-container">
    <?php
    if (!empty($contents)) {
        foreach ($contents as $content) {
                        $contentText = htmlspecialchars($content['content']);
                        
                        $codePatterns = [
                            '/```/',                                    // Markdown code blocks
                            '/(?:echo|printf|cat|grep|awk|sed)\s/',    // Shell commands
                            '/(?:#!/|#\s*!\/)/m',                      // Shebangs
                            '/(?:const|let|var|function)\s+\w+/m',     // JavaScript
                            '/(?:def|class|import|from)\s+\w+/m',      // Python
                            '/(?:SELECT|INSERT|UPDATE|DELETE)\s+/i',   // SQL
                            '/\$\w+\s*[=:]/m',                         // Variables
                            '/(?:\w+\(.*?\)|console\.log|fs\.|require\()/m', // Function calls
                            '/{[\s\S]*}/m'                             // JSON-like structures
                        ];
                        
                        $hasCode = false;
                        foreach ($codePatterns as $pattern) {
                            if (preg_match($pattern, $contentText)) {
                                $hasCode = true;
                                break;
                            }
                        }
                        
                        $postType = $hasCode ? 'code' : 'text';
                        
                        echo '<article class="post-card" data-type="' . $postType . '">';
                        echo '<div class="post-header">';
                        echo '<div class="post-author">';
                        echo '<div class="author-avatar">';
                        echo '<i class="fas fa-user-secret"></i>';
                        echo '</div>';
                        echo '<div class="author-info">';
                        echo '<span class="author-name">' . htmlspecialchars($content['first_name']) . ' ' . htmlspecialchars($content['last_name']) . '</span>';
                        echo '<span class="post-type-badge ' . $postType . '">';
                        echo '<i class="fas fa-' . ($postType === 'code' ? 'code' : 'align-left') . '"></i>';
                        echo ucfirst($postType);
                        echo '</span>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div class="post-content">';
                        if ($hasCode) {
                            // Process content with code blocks
                            $processedContent = processCodeBlocks($contentText);
                            echo $processedContent;
                        } else {
                            echo '<p>' . nl2br($contentText) . '</p>';
            }
            echo '</div>';
                        
                        echo '<div class="post-footer">';
                        echo '<div class="post-meta">';
                        echo '<time>Just now</time>';
                        echo '</div>';
        
            if (isset($content['author_id']) && $user_id == $content['author_id']) {
                            echo '<div class="post-actions">';
                            echo '<form method="POST" action="sharing.php" style="display: inline;" class="delete-form">';
                echo '<input type="hidden" name="id" value="' . $content['id'] . '">';
                            echo '<button type="button" name="delete" class="action-btn delete-btn delete-post-btn" data-post-id="' . $content['id'] . '">';
                            echo '<i class="fas fa-trash"></i>';
                            echo '</button>';
                echo '</form>';
                            echo '</div>';
            }
                        
            echo '</div>';
                        echo '</article>';
        }
    } else {
                    echo '<div class="empty-state">';
                    echo '<div class="empty-icon">';
                    echo '<i class="fas fa-comments"></i>';
                    echo '</div>';
                    echo '<h3>No knowledge shared yet</h3>';
                    echo '<p>Be the first to share your purple team insights and techniques with the community!</p>';
                    echo '</div>';
                }

                function processCodeBlocks($content) {
                    
                    function detectLanguage($code) {
                        $code = trim($code);
                        
                        // JavaScript
                        if (preg_match('/(?:const|let|var|function)\s+\w+|require\(|console\.log|\.forEach|=>|\{\s*[\w\s:,]*\}/', $code)) {
                            return 'javascript';
                        }
                        
                        // Python
                        if (preg_match('/(?:def|class|import|from)\s+\w+|print\(|if\s+\w+.*:|for\s+\w+\s+in\s+|elif\s+/', $code)) {
                            return 'python';
                        }
                        
                        // Bash/Shell
                        if (preg_match('/^#!/|(?:echo|grep|cat|awk|sed|chmod|mkdir|cd)\s+|if\s*\[|\$\{|\$\w+/', $code)) {
                            return 'bash';
                        }
                        
                        // PowerShell
                        if (preg_match('/Write-Host|Get-|Set-|New-|\$\w+\s*=|\|\s*Where-Object/', $code)) {
                            return 'powershell';
                        }
                        
                        // SQL
                        if (preg_match('/(?:SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\s+/i', $code)) {
                            return 'sql';
                        }
                        
                        // JSON
                        if (preg_match('/^\s*\{[\s\S]*\}\s*$|^\s*\[[\s\S]*\]\s*$/', $code)) {
                            return 'json';
                        }
                        
                        // YAML
                        if (preg_match('/^\s*\w+:\s*$|^\s*-\s+\w+/m', $code)) {
                            return 'yaml';
                        }
                        
                        // Regex
                        if (preg_match('/^\s*\/.*\/[gimuy]*\s*$|^\s*\(.*\)\s*$/', $code)) {
                            return 'regex';
                        }
                        
                        return 'text';
                    }
                    
                    
                    $patterns = [
                        '/```(\w+)?\n(.*?)\n```/s' => function($matches) {
                            $lang = !empty($matches[1]) ? $matches[1] : detectLanguage($matches[2]);
                            return '<div class="code-block"><div class="code-header"><span class="language">' . strtoupper($lang) . '</span><button class="copy-code"><i class="fas fa-copy"></i> Copy</button></div><pre><code class="language-' . $lang . '">' . htmlspecialchars($matches[2]) . '</code></pre></div>';
                        },
                        '/`([^`]+)`/' => '<code class="inline-code">$1</code>'
                    ];
                    
                    foreach ($patterns as $pattern => $replacement) {
                        if (is_callable($replacement)) {
                            $content = preg_replace_callback($pattern, $replacement, $content);
                        } else {
                            $content = preg_replace($pattern, $replacement, $content);
                        }
                    }
                    
                   
                    if (strpos($content, '<div class="code-block">') === false && strpos($content, '<code class="inline-code">') === false) {
                        $detectedLang = detectLanguage($content);
                        $content = '<div class="code-block"><div class="code-header"><span class="language">' . strtoupper($detectedLang) . '</span><button class="copy-code"><i class="fas fa-copy"></i> Copy</button></div><pre><code class="language-' . $detectedLang . '">' . $content . '</code></pre></div>';
                    }
                    
                    return '<div class="processed-content">' . $content . '</div>';
    }
    ?>
</div>
        </div>
    </div>
</div>


<div id="deleteConfirmModal" class="delete-modal">
    <div class="delete-modal-content">
        <div class="delete-modal-header">
            <i class="fas fa-exclamation-triangle delete-warning-icon"></i>
            <h3>Confirm Deletion</h3>
        </div>
        <div class="delete-modal-body">
            <p>Are you sure you want to delete this post?</p>
            <p class="delete-warning-text">This action cannot be undone.</p>
        </div>
        <div class="delete-modal-actions">
            <button type="button" class="btn btn-cancel" id="cancelDelete">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-danger" id="confirmDelete">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

</body>
</html>
