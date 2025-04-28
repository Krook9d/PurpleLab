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
<html lang="fr">
<head>
    <link rel="icon" href="MD_image/logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="css/main.css?v=<?= filemtime('css/main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</head>
<body>

<div class="nav-bar">
        <!-- Add logo to top of nav-bar -->
        <div class="nav-logo">
        <img src="MD_image/logowhite.png" alt="Logo" /> 
    </div>

    <!-- Display software version -->
    <?php include $_SERVER['DOCUMENT_ROOT'].'/scripts/php/version.php'; ?>
        <div class="software-version">
        <?php echo SOFTWARE_VERSION; ?>
    </div>

    <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="http://<?= $_SERVER['SERVER_ADDR'] ?>:5601" target="_blank"><i class="fas fa-crosshairs"></i> Hunting</a></li>
        <li><a href="mittre.php"><i class="fas fa-book"></i> Mitre Att&ck</a></li>
        <li><a href="custom_payloads.php"><i class="fas fa-code"></i> Custom Payloads</a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> Malware</a></li>
        <li><a href="simulation.php"><i class="fas fa-project-diagram"></i> Log Simulation</a></li>
        <li><a href="usecase.php"><i class="fas fa-lightbulb"></i> UseCase</a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> Sharing</a></li>
        <li><a href="sigma.php"><i class="fas fa-shield-alt"></i> Sigma Rules</a></li>
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

<h1 class="title">Sigma Rules Navigator</h1>
<br>



<button id="updateDatabaseBtn" class="update-btn" onclick="updateDatabaseSigma()">
            <i id="updateIcon" class="fas fa-sync"></i> Sigma rules update database
        </button> 


<div class="sigma-search-container">
<br> 
<form action="sigma.php" method="get">
        <input type="text" name="search" placeholder="Search..." class="sigma-search-box"/>
        <button type="submit" class="sigma-search-btn">Submit</button>
    </form>
</div>

<?php

if (isset($_GET['search']) && $_GET['search'] !== '') {
    $searchTerm = $_GET['search'];
    $searchResults = searchFiles($rulesPath, $searchTerm);
    
    if (count($searchResults) === 0) {
        echo "<p>No results found for <strong>" . htmlspecialchars($searchTerm) . "</strong>.</p>";
    } else {
        echo "<p>Results found for <strong>" . htmlspecialchars($searchTerm) . "</strong>:</p>";
    foreach ($searchResults as $result) {
    
        $relativeFilePath = str_replace($rulesPath . '/', '', $result);
        $filename = basename($result);
        echo "<div><a href='?file=" . urlencode($relativeFilePath) . "' class='sigma-link'>$filename</a></div>";
    }


    }
}

if (isset($_GET['file']) && file_exists($rulesPath.'/'.$_GET['file'])) {
    $filePath = $rulesPath.'/'.$_GET['file'];
    $content = file_get_contents($filePath);

    $relativeFilePath = str_replace($rulesPath . '/', '', $filePath);

    echo "<div class='sigma-file-content'><h2>Content of file: " . htmlspecialchars($_GET['file']) . " <span class='sigma-copy-icon' <i class='fas fa-copy'></i></span>";


    echo "<span class='sigma-convert-icon' onclick='showOptions()'><i class='fas fa-exchange-alt'></i></span></h2><pre id='fileContent'>" . htmlspecialchars($content) . "</pre>";

    echo "<div id='conversionOptions' style='display:none;'>
            <p>Which language would you like to convert the Sigma rule to?</p>
            <button class='sigma-convert-btn' onclick=\"convertRule('splunk', '" . htmlspecialchars($relativeFilePath) . "')\">Splunk</button>
            <button class='sigma-convert-btn' onclick=\"convertRule('lucene', '" . htmlspecialchars($relativeFilePath) . "')\">Lucene</button>
            <button class='sigma-convert-btn' onclick=\"convertRule('qradar', '" . htmlspecialchars($relativeFilePath) . "')\">QRadar</button>
        </div>";
    echo "</div>"; 
}




elseif (isset($_GET['folder']) && is_dir($rulesPath.'/'.$_GET['folder'])) {
    $folderPath = $rulesPath.'/'.$_GET['folder'];
    echo "<div class='sigma-folder-content'><h2>Folder content: " . htmlspecialchars($_GET['folder']) . "</h2>";
    displayItems($folderPath, $_GET['folder']);
    echo "</div>";
}

else {
    displayItems($rulesPath);
}


echo "<div class='sigma-back-container'><a href='sigma.php' class='sigma-back-link'>Back to Root</a></div>";
?>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalCopyBtn = document.getElementById("sigma-modal-copy");
    if (modalCopyBtn) {
        modalCopyBtn.addEventListener('click', copyModalContent);
    }

    const closeBtn = document.getElementsByClassName("sigma-close")[0];
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            document.getElementById("sigma-modal").style.display = "none";
        });
    }

    window.addEventListener('click', (event) => {
        const modal = document.getElementById("sigma-modal");
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });
});



function copyModalContent() {
    var content = document.getElementById("sigma-modal-text").innerText || document.getElementById("sigma-modal-text").textContent;

  
    if (navigator.clipboard) {
        navigator.clipboard.writeText(content).then(function() {
            console.log('Text copied to clipboard');
         
            document.getElementById("sigma-modal-copy").innerText = 'Copied!';
            setTimeout(function() {
                document.getElementById("sigma-modal-copy").innerText = 'Copy';
            }, 2000);
        }, function(err) {
            console.error('Could not copy text: ', err);
        });
    } else {
        
        var textArea = document.createElement("textarea");
        document.body.appendChild(textArea);
        textArea.value = content;
        textArea.select();
        try {
            var successful = document.execCommand('copy');
            var msg = successful ? 'Copied !' : 'Failed to copy';
            console.log(msg);
            document.getElementById("sigma-modal-copy").innerText = msg;
            setTimeout(function() {
                document.getElementById("sigma-modal-copy").innerText = 'Copy';
            }, 2000);
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }
        document.body.removeChild(textArea);
    }
}


function showOptions() {
    document.getElementById('conversionOptions').style.display = 'block';
}

function convertRule(plugin, rulePath) {
    console.log("Plugin:", plugin);
    console.log("Rule Path:", rulePath);
    
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
            showModal(data.output);
        } else {
            console.error('Error in conversion:', data.error);
            showModal('Error in conversion: ' + data.error);
        }
    })
    .catch((error) => {
        console.error('Fetch Error:', error);
        showModal('Fetch Error: ' + error.message);
    });
}

function showModal(content) {
    const modal = document.getElementById("sigma-modal");
    const modalText = document.getElementById("sigma-modal-text");
    modalText.innerHTML = content.replace(/\n/g, "<br>");
    modal.style.display = "block";

    const closeBtn = document.getElementsByClassName("sigma-close")[0];
    closeBtn.onclick = function() {
        modal.style.display = "none";
    };

    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    };
}


function updateDatabaseSigma() {
    const updateBtn = document.getElementById("updateDatabaseBtn");
    const updateIcon = document.getElementById("updateIcon");
    updateIcon.classList.add("fa-spin");

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
    });
}




</script>



<!-- Modal Start -->
<div id="sigma-modal" class="sigma-modal">
  <div class="sigma-modal-content">
    <span class="sigma-close">&times;</span>
    <pre id="sigma-modal-text"></pre>
    <button id="sigma-modal-copy" class="sigma-copy-btn">Copy</button>
  </div>
</div>

<!-- Modal End -->
       
</body>
</html>
