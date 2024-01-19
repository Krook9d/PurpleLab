  <!-- start Connexion -->
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['email'])) {
    header('Location: connexion.html');
    exit();
}

$conn = new mysqli('localhost', 'toor', 'root', 'myDatabase');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = $_SESSION['email'];

$sql = "SELECT first_name, last_name, email, analyst_level, avatar FROM users WHERE email=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($first_name, $last_name, $email, $analyst_level, $avatar);

if (!$stmt->fetch()) {
    die("Erreur lors de la récupération des informations de l'utilisateur.");
}

$stmt->close();
$conn->close();
?>

<?php

// Fonctions pour vérifier l'état des services
function isServiceRunning($serviceName) {
    $result = shell_exec("systemctl is-active " . escapeshellarg($serviceName));
    return (strpos($result, "active") !== false);
}

function getServerMemoryUsage() {
    $free = shell_exec('free -m'); // -m pour obtenir la mémoire en Mo
    $free = (string)trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem);
    $mem = array_merge($mem);
    $memory_usage = $mem[2]/$mem[1]*100;
    $memory_total = round($mem[1]/1024, 2); // Convertir en Go
    $memory_used = round($mem[2]/1024, 2); // Convertir en Go

    return [
        'percent' => $memory_usage,
        'total' => $memory_total,
        'used' => $memory_used
    ];
}

function getServerDiskUsage() {
    $disktotal = disk_total_space ('/');
    $diskfree = disk_free_space ('/');
    $diskused = $disktotal - $diskfree;
    $diskusepercent = round (100 - (($diskfree / $disktotal) * 100));
    
    return [
        'percent' => $diskusepercent,
        'total' => round($disktotal/1024/1024/1024, 2), // Convertir en Go
        'used' => round($diskused/1024/1024/1024, 2) // Convertir en Go
    ];
}

// Utilisez ces fonctions pour obtenir les pourcentages et les valeurs absolues
$memory = getServerMemoryUsage();
$disk = getServerDiskUsage();


// Récupération des informations
$kibanaRunning = isServiceRunning("kibana");
$logstashRunning = isServiceRunning("logstash");
$elasticRunning = isServiceRunning("elasticsearch");
$virtualboxRunning = isServiceRunning("virtualbox");


// Utilisez ces fonctions pour obtenir les pourcentages
$memoryUsagePercent = getServerMemoryUsage();
$diskUsagePercent = getServerDiskUsage();


// Fonction pour vérifier si Flask est en cours d'exécution
function isFlaskRunning() {
    $url = 'http://127.0.0.1:5000/'; // Assurez-vous que cette route existe dans votre application Flask
    $headers = @get_headers($url);
    if ($headers !== false && is_array($headers)) {
        return strpos($headers[0], '200') !== false;
    } else {
        return false;
    }
}


$flaskStatus = isFlaskRunning() ? 'running' : 'stopped';


// URL de votre endpoint Flask pour obtenir l'état de la VM
$flask_vm_state_url = 'http://127.0.0.1:5000/vm_state';

// Utilisez cURL pour faire une requête GET
$curl = curl_init($flask_vm_state_url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

// Exécutez la requête cURL
$response = curl_exec($curl);
$vmInfo = "Error retrieving VM information";

if (!curl_errno($curl)) {
    $response_data = json_decode($response, true);
    if ($response_data) {
        // Supposons que la réponse est une chaîne contenant les informations de la VM
        $vmInfo = $response_data;
    }
}

curl_close($curl);


// URL de votre endpoint Flask pour obtenir l'IP de la VM
$flask_vm_ip_url = 'http://127.0.0.1:5000/vm_ip';

// Utilisez cURL pour faire une requête GET pour obtenir l'IP de la VM
$curl_ip = curl_init($flask_vm_ip_url);
curl_setopt($curl_ip, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_ip, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

// Exécutez la requête cURL
$response_ip = curl_exec($curl_ip);
$vmIP = "Error retrieving VM IP";

if (!curl_errno($curl_ip)) {
    $response_ip_data = json_decode($response_ip, true);
    if ($response_ip_data) {
        // Supposons que la réponse contient l'IP de la VM
        $vmIP = $response_ip_data['ip'];
    }
}

curl_close($curl_ip);

// Ajoutez l'IP à votre tableau $vmInfo
if (!is_array($vmInfo)) {
    $vmInfo = [];
}
$vmInfo['IP'] = $vmIP;



?>



  <!-- End Connexion -->

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogHunter</title>
    <link rel="stylesheet" href="styles.css?v=5.2" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
</head>
<body>

<div class="nav-bar">

        <!-- Ajout du logo en haut de la nav-bar -->
        <div class="nav-logo">
        <img src="logo.png" alt="Logo" /> <!-- Assurez-vous de mettre à jour le chemin vers votre logo -->
    </div>

    <!-- Affichage de la version du logiciel -->
    <div class="software-version">
        v1.0.0
    </div>

    <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="http://<?= $_SERVER['SERVER_ADDR'] ?>:5601" target="_blank"><i class="fas fa-crosshairs"></i> Hunting</a></li>
        <li><a href="mittre.php"><i class="fas fa-book"></i> Mitre Att&ck</a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> Malware</a></li>
        <li><a href="simulation.php"><i class="fas fa-project-diagram"></i> Log Simulation</a></li>
        <li><a href="usecase.php"><i class="fas fa-lightbulb"></i> UseCase</a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> Sharing</a></li>
        <li><a href="health.php"><i class="fas fa-heartbeat"></i> Health</a></li>
    </ul>

        <!-- Conteneur pour les crédits en bas de la nav-bar -->
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
                <a href="#" id="settings-link">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </button>
        </div>
    </div>


<!-- Service Status Section -->
<div class="health-section">
    <h2 class="health-section-title">🩺 Service Status</h2>
    <div class="health-dashboard">
    <!-- Service Kibana -->
    <div class="health-card">
        <h2>🔍 Kibana</h2>
        <div class="health-status <?= $kibanaRunning ? 'running' : 'stopped' ?>">
            <?= $kibanaRunning ? 'Running' : 'Stopped' ?>
        </div>
    </div>

    <!-- Service Logstash -->
    <div class="health-card">
        <h2>🔗 Logstash</h2>
        <div class="health-status <?= $logstashRunning ? 'running' : 'stopped' ?>">
            <?= $logstashRunning ? 'Running' : 'Stopped' ?>
        </div>
    </div>

    <!-- Service Elastic -->
    <div class="health-card">
        <h2>📊 Elastic</h2>
        <div class="health-status <?= $elasticRunning ? 'running' : 'stopped' ?>">
            <?= $elasticRunning ? 'Running' : 'Stopped' ?>
        </div>
    </div>

    <!-- Service VirtualBox -->
    <div class="health-card">
        <h2>🖥️ VirtualBox</h2>
        <div class="health-status <?= $virtualboxRunning ? 'running' : 'stopped' ?>">
            <?= $virtualboxRunning ? 'Running' : 'Stopped' ?>
        </div>
    </div>

    <div class="health-card">
        <h2>🔧 Flask Backend</h2>
    <div class="health-status <?= $flaskStatus ?>">
        <?= ucfirst($flaskStatus) ?>
    </div>
</div>    

</div>
<div class="health-section-separator"></div>



<!-- RAM & Disk Usage Section -->
<div class="health-section">
    <h2 class="health-section-title">💾 RAM & Disk Usage</h2>
  <div class="health-dashboard">
    
<!-- Mémoire RAM -->
<div class="health-card">
    <h2>🔋RAM Usage</h2>
    <div class="health-metric">
        <div style="width: <?= $memory['percent'] ?>%;">
            <?= round($memory['percent'], 2) ?>%
        </div>
    </div>
    <p><?= $memory['used'] ?> GB / <?= $memory['total'] ?> GB</p>
</div>
<!-- Espace disque -->
<div class="health-card">
    <h2>🛢️ Disk Usage</h2>
    <div class="health-metric">
        <div style="width: <?= $disk['percent'] ?>%;">
            <?= $disk['percent'] ?>%
        </div>
    </div>
    <p><?= $disk['used'] ?> GB / <?= $disk['total'] ?> GB</p>
</div>
</div>
</div>
<div class="health-section-separator"></div>




<!-- VM Status Section -->
<div class="health-section">
    <h2 class="health-section-title">🔨 VM Management</h2>
    <div class="health-dashboard">

<!-- VM Info -->
<div class="health-card">
    <h3>🗒️ VM Information</h3>
    <div>
        <?php
        // Itérer sur le tableau $vmInfo
        foreach ($vmInfo as $key => $value) {
            // Si la valeur est également un tableau, itérez sur celui-ci
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    echo formatInfoLine($subKey, $subValue);
                }
            } else {
                echo formatInfoLine($key, $value);
                if ($key == 'Name' || $key == 'State') {
                    echo '<br>'; // Ajoutez un saut de ligne après certaines sections
                }
            }
        }

        function formatInfoLine($key, $value) {

   // Mettez en gras les valeurs 'sandbox' et 'Snapshot1'
    $boldTerms = ['sandbox', 'Snapshot1'];
    foreach ($boldTerms as $term) {
        if (strpos($value, $term) !== false) {
            $value = str_replace($term, "<strong>$term</strong>", $value);
        }
    }

    // Retournez la ligne formatée avec un saut de ligne après
    return "<div><strong>$key:</strong>$value</span></div>";
}

        ?>
    </div>
</div>





<!-- Restore VM Snapshot Button -->

    <div class="health-card no-hover">
    <h3>⚙️ Actions</h3>
    <button id="restoreButton" onclick="restoreSnapshot()">Restore Windows VM snapshot</button>
    </div>

</div>
</div>

<script>
function restoreSnapshot() {
    var button = document.getElementById('restoreButton');
    button.innerHTML = 'Restoring...';
    button.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/restore_snapshot', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        console.log(response); // Ajoutez ceci pour voir l'objet de réponse
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        console.log(data); // Ajoutez ceci pour voir les données de réponse
        if (data.message) {
            alert(data.message);
        } else if (data.error) {
            alert('Erreur: ' + data.error);
        }
        button.innerHTML = 'Restore Windows VM snapshot';
        button.disabled = false;
    })
    .catch((error) => {
        console.error('Error:', error);
        alert('An error occurred during the snapshot restoration.');
        button.innerHTML = 'Restore Windows VM snapshot';
        button.disabled = false;
    });
}
</script>

<!-- start sidebar JS -->
<script>
    // When the document is ready
    document.addEventListener('DOMContentLoaded', function() {
        // When the settings link is clicked
        document.querySelector('#settings-link').addEventListener('click', function(e) {
            // Prevent the default link behavior
            e.preventDefault();

            // Toggle the active class on the sidebar
            let sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');

            // Stop propagation of the event to parent elements
            e.stopPropagation();
        });

        // When a click is detected outside the sidebar
        document.addEventListener('click', function(e) {
            let sidebar = document.getElementById('sidebar');
            if (!sidebar.contains(e.target) && sidebar.classList.contains('active')) {
                // Remove the active class from the sidebar
                sidebar.classList.remove('active');
            }
        });
    });
</script>



</body>
</html>

