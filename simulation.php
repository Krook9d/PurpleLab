  <!-- start Connexion -->
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['email'])) {
    header('Location: connexion.html');
    exit();
}

$conn = new mysqli(
    getenv('DB_HOST'), 
    getenv('DB_USER'), 
    getenv('DB_PASS'), 
    getenv('DB_NAME')
);


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

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="styles.css?v=5.2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
</head>
<body>

<div class="nav-bar">

        <!-- Add logo to top of nav-bar -->
        <div class="nav-logo">
        <img src="logo.png" alt="Logo" /> 
    </div>

    <!-- Display software version -->
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
                    <a href="simulation.php" id="settings-link">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </button>
        </div>
    </div>

    <div class="content">
<div class="log_simulation_container">
    <h2> 📊 Log simulation</h2>
    <form id="logSimulationForm">
        <div class="log_simulation_field">
            <label for="logType">Log type:</label>
            <select id="logType" name="logType">
                <option value="ubuntu">Ubuntu</option>
                <option value="firewall">Firewall</option>
                <!-- More options can be added here -->
            </select>
        </div>

        <div class="log_simulation_field">
            <label for="logCount">Number of logs:</label>
            <input type="number" id="logCount" name="logCount" min="1" max="5000">
        </div>

        <div class="log_simulation_field">
            <label for="timeRange">Time range (days):</label>
            <input type="number" id="timeRange" name="timeRange" min="1">
        </div>

        <button type="submit">Generate the logs</button>
       
<div id="loadingIndicator" style="display:none;">Loading...</div>

    </form>
    <div id="logSimulationResult"></div>
</div>

<script>
document.getElementById('logSimulationForm').addEventListener('submit', function(e) {
    e.preventDefault();

    var logType = document.getElementById('logType').value;
    var logCount = document.getElementById('logCount').value;
    var timeRange = document.getElementById('timeRange').value;

    if (logCount > 5000) {
        alert("Le nombre de logs ne doit pas dépasser 5000.");
        return;
    }

    
    document.getElementById('loadingIndicator').style.display = 'block';

    // Effectuer la requête AJAX
    $.ajax({
        type: 'POST',
        url: 'http://' + window.location.hostname + ':5000/generate_logs',
        data: {
            logType: logType,
            logCount: logCount,
            timeRange: timeRange
        },
        success: function(response) {
            
            document.getElementById('loadingIndicator').style.display = 'none';
            document.getElementById('logSimulationResult').innerText = "Logs successfully generated.";
        },
        error: function(error) {
            
            document.getElementById('loadingIndicator').style.display = 'none';
            console.error("Erreur lors de la génération des logs:", error);
            document.getElementById('logSimulationResult').innerText = "Error occurred during log generation.";
        }
    });
});
</script>
</body>
</html>
