  <!-- start Connexion -->
  <?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['email'])) {
    header('Location: connexion.html');
    exit();
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

$sql = "SELECT first_name, last_name, email, analyst_level, avatar FROM users WHERE email=$1";
$result = pg_query_params($conn, $sql, array($email));

if ($result && $row = pg_fetch_assoc($result)) {
    $first_name = $row['first_name'];
    $last_name = $row['last_name'];
    $email = $row['email'];
    $analyst_level = $row['analyst_level'];
    $avatar = $row['avatar'];
} else {
    die("Error retrieving user information.");
}

pg_free_result($result);
pg_close($conn);

$rulesPath = '/var/www/html/Downloaded/Sigma/rules';

function searchFiles($path, $query) {
    $results = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

    foreach ($rii as $file) {
        if ($file->isDir() || $file->getExtension() !== 'yml') {
            continue;
        }
        
        $content = file_get_contents($file->getPathname());
        if (stripos($content, $query) !== false) {
            $results[] = $file->getPathname();
        }
    }

    return $results;
}

function displayItems($path, $relativePath = '') {
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $fullPath = $path . '/' . $item;
        $linkPath = $relativePath . $item;
        if (is_dir($fullPath)) {
            echo "<div><a href='?folder=$linkPath/' class='sigma-link'>$item/</a></div>";
        } elseif (pathinfo($fullPath, PATHINFO_EXTENSION) === 'yml') {
            echo "<div><a href='?file=$linkPath' class='sigma-link'>$item</a></div>";
        }
    }
}



?>
<!-- End Connexion -->

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="MD_image/logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="css/main.css?v=<?= filemtime('css/main.css') ?>">
    <link rel="stylesheet" href="css/pages/sigma.css?v=<?= filemtime('css/pages/sigma.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="https://<?= $_SERVER['SERVER_ADDR'] ?>:5601" target="_blank"><i class="fas fa-crosshairs"></i> Hunting</a></li>
        <li><a href="mittre.php"><i class="fas fa-book"></i> Mitre Att&ck</a></li>
        <li><a href="custom_payloads.php"><i class="fas fa-code"></i> Custom Payloads</a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> Malware</a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> Sharing</a></li>
        <li><a href="sigma.php"><i class="fas fa-shield-alt"></i> Sigma Rules</a></li>
        <li><a href="rule_lifecycle.php" class="active"><i class="fas fa-cogs"></i> Rule Lifecycle</a></li>
        <li><a href="health.php"><i class="fas fa-heartbeat"></i> Health</a></li>
   <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@local.com'): ?>
        <li><a href="admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
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
            <button id="user-button" class="user-button">
            <span><?= $first_name ?> <?= $last_name ?></span>
                <div class="dropdown-content">
                <a href="usecase.php" id="settings-link">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </button>
        </div>
    </div>

<div class="content">
    <div class="sigma-container">
        <!-- Header Section -->
        <div class="sigma-header-section">
            <h1><i class="fas fa-shield-alt"></i> Sigma Rules Navigator</h1>
            <p class="subtitle">Browse detection rules by category or search for specific MITRE techniques</p>
            <button id="updateDatabaseBtn" class="update-btn" onclick="updateDatabaseSigma()">
                <i id="updateIcon" class="fas fa-sync"></i> Update Sigma Database
            </button>
        </div>

        <!-- Search Section -->
        <div class="sigma-search-section">
            <form action="sigma.php" method="get" class="search-form">
                <input type="text" name="search" placeholder="Enter MITRE technique or keyword..." class="search-input" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"/>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>

        <?php
        // Define categories with their descriptions and icons
        $categories = [
            'application' => ['icon' => 'fas fa-desktop', 'desc' => 'Application-specific detection rules'],
            'category' => ['icon' => 'fas fa-tags', 'desc' => 'Categorized detection patterns'],
            'cloud' => ['icon' => 'fas fa-cloud', 'desc' => 'Cloud platform security rules'],
            'compliance' => ['icon' => 'fas fa-check-circle', 'desc' => 'Compliance and audit rules'],
            'linux' => ['icon' => 'fab fa-linux', 'desc' => 'Linux system detection rules'],
            'macos' => ['icon' => 'fab fa-apple', 'desc' => 'macOS security detection rules'],
            'network' => ['icon' => 'fas fa-network-wired', 'desc' => 'Network traffic analysis rules'],
            'web' => ['icon' => 'fas fa-globe', 'desc' => 'Web application security rules'],
            'windows' => ['icon' => 'fab fa-windows', 'desc' => 'Windows system detection rules']
        ];

        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $searchTerm = $_GET['search'];
            $searchResults = searchFiles($rulesPath, $searchTerm);
            
            echo "<div class='sigma-content-section'>";
            echo "<div class='content-header'>";
            echo "<h2 class='content-title'>Search Results</h2>";
            echo "<div class='content-actions'>";
            echo "<a href='sigma.php' class='action-btn back-btn'><i class='fas fa-arrow-left'></i> Back</a>";
            echo "</div>";
            echo "</div>";
            
            if (count($searchResults) === 0) {
                echo "<div class='no-results'>";
                echo "<i class='fas fa-search'></i>";
                echo "<h3>No results found</h3>";
                echo "<p>No rules found for <strong>" . htmlspecialchars($searchTerm) . "</strong></p>";
                echo "</div>";
            } else {
                echo "<div class='results-header'>";
                echo "<i class='fas fa-check-circle'></i> Found " . count($searchResults) . " rules for \"" . htmlspecialchars($searchTerm) . "\"";
                echo "</div>";
                
                echo "<div class='sigma-items-grid'>";
                foreach ($searchResults as $result) {
                    $relativeFilePath = str_replace($rulesPath . '/', '', $result);
                    $filename = basename($result);
                    
                    echo "<a href='?file=" . urlencode($relativeFilePath) . "' class='sigma-item'>";
                    echo "<div class='item-icon file-icon'><i class='fas fa-file-code'></i></div>";
                    echo "<div class='item-details'>";
                    echo "<div class='item-name'>" . htmlspecialchars($filename) . "</div>";
                    echo "<div class='item-type'>Sigma Rule</div>";
                    echo "</div>";
                    echo "</a>";
                }
                echo "</div>";
            }
            echo "</div>";
        }
        elseif (isset($_GET['file']) && file_exists($rulesPath.'/'.$_GET['file'])) {
            $filePath = $rulesPath.'/'.$_GET['file'];
            $content = file_get_contents($filePath);
            $relativeFilePath = str_replace($rulesPath . '/', '', $filePath);
            
            echo "<div class='sigma-content-section'>";
            echo "<div class='content-header'>";
            echo "<h2 class='content-title'>" . htmlspecialchars($_GET['file']) . "</h2>";
            echo "<div class='content-actions'>";
            echo "<a href='sigma.php' class='action-btn back-btn'><i class='fas fa-arrow-left'></i> Back</a>";
            echo "</div>";
            echo "</div>";
            
            echo "<div class='file-content-container'>";
            echo "<div class='file-content-header'>";
            echo "<h3 class='file-content-title'>Rule Content</h3>";
            echo "<div class='file-content-actions'>";
            echo "<button class='copy-btn' onclick='copyFileContent()'><i class='fas fa-copy'></i> Copy</button>";
            echo "<button class='convert-btn' onclick='showConversionOptions()'><i class='fas fa-exchange-alt'></i> Convert</button>";
            echo "</div>";
            echo "</div>";
            echo "<div class='file-content-body'>";
            echo "<pre class='file-content-pre' id='fileContent'>" . htmlspecialchars($content) . "</pre>";
            echo "</div>";
            echo "</div>";
            
            echo "<div class='conversion-options' id='conversionOptions' style='display:none;'>";
            echo "<div class='conversion-header'><i class='fas fa-exchange-alt'></i> Convert to Platform</div>";
            echo "<p style='color: rgba(255, 255, 255, 0.7); margin-bottom: 15px;'>Choose the target platform for rule conversion:</p>";
            echo "<div class='conversion-buttons'>";
            echo "<button class='conversion-btn' onclick=\"convertRule('splunk', '" . htmlspecialchars($relativeFilePath) . "')\">Splunk</button>";
            echo "<button class='conversion-btn' onclick=\"convertRule('lucene', '" . htmlspecialchars($relativeFilePath) . "')\">Lucene</button>";
            echo "<button class='conversion-btn' onclick=\"convertRule('qradar', '" . htmlspecialchars($relativeFilePath) . "')\">QRadar</button>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }
        elseif (isset($_GET['folder']) && is_dir($rulesPath.'/'.$_GET['folder'])) {
            $folderPath = $rulesPath.'/'.$_GET['folder'];
            $folderName = $_GET['folder'];
            
            echo "<div class='sigma-content-section'>";
            echo "<div class='content-header'>";
            echo "<h2 class='content-title'>" . htmlspecialchars($folderName) . "</h2>";
            echo "<div class='content-actions'>";
            echo "<a href='sigma.php' class='action-btn back-btn'><i class='fas fa-arrow-left'></i> Back</a>";
            echo "</div>";
            echo "</div>";
            
            echo "<div class='sigma-items-grid'>";
            $items = scandir($folderPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                
                $fullPath = $folderPath . '/' . $item;
                $linkPath = $folderName . $item;
                
                if (is_dir($fullPath)) {
                    echo "<a href='?folder=$linkPath/' class='sigma-item'>";
                    echo "<div class='item-icon folder-icon'><i class='fas fa-folder'></i></div>";
                    echo "<div class='item-details'>";
                    echo "<div class='item-name'>" . htmlspecialchars($item) . "</div>";
                    echo "<div class='item-type'>Folder</div>";
                    echo "</div>";
                    echo "</a>";
                } elseif (pathinfo($fullPath, PATHINFO_EXTENSION) === 'yml') {
                    echo "<a href='?file=$linkPath' class='sigma-item'>";
                    echo "<div class='item-icon file-icon'><i class='fas fa-file-code'></i></div>";
                    echo "<div class='item-details'>";
                    echo "<div class='item-name'>" . htmlspecialchars($item) . "</div>";
                    echo "<div class='item-type'>Sigma Rule</div>";
                    echo "</div>";
                    echo "</a>";
                }
            }
            echo "</div>";
            echo "</div>";
        }
        else {
            // Show categories
            echo "<div class='sigma-categories-section'>";
            echo "<div class='categories-header'>";
            echo "<h2><i class='fas fa-layer-group'></i> Browse Categories</h2>";
            echo "</div>";
            echo "<div class='categories-grid'>";
            
            $items = scandir($rulesPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || !is_dir($rulesPath . '/' . $item)) continue;
                
                $categoryInfo = $categories[$item] ?? ['icon' => 'fas fa-folder', 'desc' => 'Detection rules collection'];
                
                echo "<a href='?folder=$item/' class='category-card'>";
                echo "<div class='category-icon'><i class='" . $categoryInfo['icon'] . "'></i></div>";
                echo "<div class='category-name'>" . htmlspecialchars($item) . "</div>";
                echo "<div class='category-description'>" . $categoryInfo['desc'] . "</div>";
                echo "</a>";
            }
            echo "</div>";
            echo "</div>";
        }
        ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Copy functionality for file content
    window.copyFileContent = function() {
        const content = document.getElementById('fileContent').textContent;
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(content).then(() => {
                showCopyFeedback('File content copied!', 'success');
            }).catch(err => {
                console.error('Failed to copy:', err);
                fallbackCopy(content);
            });
        } else {
            fallbackCopy(content);
        }
    }
    
    function fallbackCopy(text) {
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
                showCopyFeedback('File content copied!', 'success');
            } else {
                showCopyFeedback('Copy failed', 'error');
            }
        } catch (err) {
            console.error('Fallback copy failed:', err);
            showCopyFeedback('Copy failed', 'error');
        }
        
        document.body.removeChild(textarea);
    }
    
    function showCopyFeedback(message, type) {
        // Simple feedback - could be enhanced with toast notifications
        const btn = document.querySelector('.copy-btn');
        const originalContent = btn.innerHTML;
        
        if (type === 'success') {
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            btn.style.background = 'rgba(16, 185, 129, 0.3)';
        } else {
            btn.innerHTML = '<i class="fas fa-times"></i> Failed';
            btn.style.background = 'rgba(239, 68, 68, 0.3)';
        }
        
        setTimeout(() => {
            btn.innerHTML = originalContent;
            btn.style.background = '';
        }, 2000);
    }
    
    // Show conversion options
    window.showConversionOptions = function() {
        const options = document.getElementById('conversionOptions');
        if (options.style.display === 'none') {
            options.style.display = 'block';
            options.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            options.style.display = 'none';
        }
    }
});

function copyModalContent() {
    const content = document.getElementById("sigma-modal-text").textContent;
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(content).then(() => {
            const btn = document.getElementById("sigma-modal-copy");
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => {
                btn.innerHTML = originalContent;
            }, 2000);
        }).catch(err => {
            console.error('Could not copy text: ', err);
        });
    } else {
        const textArea = document.createElement("textarea");
        document.body.appendChild(textArea);
        textArea.value = content;
        textArea.select();
        try {
            const successful = document.execCommand('copy');
            const btn = document.getElementById("sigma-modal-copy");
            const originalContent = btn.innerHTML;
            btn.innerHTML = successful ? '<i class="fas fa-check"></i> Copied!' : '<i class="fas fa-times"></i> Failed';
            setTimeout(() => {
                btn.innerHTML = originalContent;
            }, 2000);
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }
        document.body.removeChild(textArea);
    }
}

function convertRule(plugin, rulePath) {
    fetch('http://' + window.location.hostname + ':5000/convert_sigma', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            rule_path: rulePath,
            plugin: plugin
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if(data.status === "success") {
            showModal(data.output, plugin);
        } else {
            console.error('Error in conversion:', data.error);
            showModal('Error in conversion: ' + data.error, 'Error');
        }
    })
    .catch((error) => {
        console.error('Fetch Error:', error);
        showModal('Fetch Error: ' + error.message, 'Error');
    });
}

function showModal(content, platform) {
    const modal = document.getElementById("sigma-modal");
    const modalTitle = document.getElementById("sigma-modal-title");
    const modalText = document.getElementById("sigma-modal-text");
    
    modalTitle.textContent = platform ? `Converted to ${platform}` : 'Conversion Result';
    modalText.textContent = content;
    modal.style.display = "flex";
    
    // Close modal functionality
    const closeBtn = modal.querySelector(".modal-close");
    closeBtn.onclick = () => modal.style.display = "none";
    
    modal.onclick = (event) => {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    };
}

function updateDatabaseSigma() {
    const updateBtn = document.getElementById("updateDatabaseBtn");
    const updateIcon = document.getElementById("updateIcon");
    
    updateIcon.classList.add("fa-spin");
    updateBtn.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/update_sigma_rules', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        alert(data.message);
    })
    .catch(error => {
        console.error('Error during the database update:', error);
        alert('Error during the database update: ' + error.message);
    })
    .finally(() => {
        updateIcon.classList.remove("fa-spin");
        updateBtn.disabled = false;
    });
}
</script>

<!-- Modal -->
<div id="sigma-modal" class="sigma-modal">
    <div class="sigma-modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="sigma-modal-title">Conversion Result</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <pre class="modal-content-text" id="sigma-modal-text"></pre>
        </div>
        <div class="modal-footer">
            <button id="sigma-modal-copy" class="modal-copy-btn" onclick="copyModalContent()">
                <i class="fas fa-copy"></i> Copy Result
            </button>
        </div>
    </div>
</div>
       
</body>
</html>
