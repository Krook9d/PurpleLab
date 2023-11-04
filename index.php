  <!-- start Connexion -->
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['email'])) {
    header('Location: blank.html');
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
$directory = '/var/www/html/Mittre/Technic/';
$folders = scandir($directory);
$folderCount = count($folders) - 2; // Soustraire 2 pour exclure les dossiers "." et ".."
?>

  <!-- End Connexion -->



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogHunter</title>
    <link rel="stylesheet" href="styles.css?v=4.1" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
</head>
<body>

<div class="nav-bar">
    <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="http://<?= $_SERVER['SERVER_ADDR'] ?>:5601" target="_blank"><i class="fas fa-crosshairs"></i> Hunting</a></li>
        <li><a href="mittre.php"><i class="fas fa-book"></i> Mitre Att&ck</a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> Malware</a></li>
        <li><a href="usecase.php"><i class="fas fa-lightbulb"></i> UseCase</a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> Sharing</a></li>
    </ul>
</div>

    <div class="user-info-bar">
        <div class="avatar-info">
            <img src="<?= $avatar ?>" alt="Avatar">
            <button id="user-button" class="user-button">
            <span><?= $first_name ?> <?= $last_name ?></span>
                <div class="dropdown-content">
                <a href="#" id="settings-link">Paramètres</a>
                    <a href="logout.php">Déconnexion</a>
                </div>
            </button>
        </div>
    </div>

    <div class="content">
        <h1>Titre de la page</h1>
        <p>Ceci est un paragraphe de texte pour votre blank.</p><br>
        <div class="chart-container">
        <canvas id="myChart" ></canvas>
        </div><br>
       
    </div>

            <!-- start sidebar -->
<div id="sidebar" class="sidebar">
        <div class="sidebar-section">
            <h3 style="text-align: center; color: white">Profil</h2>
        
            <!-- Content goes here -->
<p>Firstname: <?php echo $first_name; ?></p>
<p>Lastname: <?php echo $last_name; ?></p>
<p>Email: <?php echo $email; ?></p>
<p>Analyst Level: <?php echo $analyst_level; ?></p>
        </div>

        <div class="sidebar-section">
            <h3 style="text-align: center; color: white">Activité</h2><br><br><br><br><br><br>
            <!-- Content goes here -->
        </div>

        <div class="sidebar-section">
            <h3 style="text-align: center; color: white">Autre</h2><br><br><br><br><br><br>
            <!-- Content goes here -->
        </div>
    </div>
            <!-- End sidebar -->

            
    <!-- JS pour la chart Number of attack by type  -->
    <script>
var ctx = document.getElementById('myChart').getContext('2d');
var myChart = new Chart(ctx, {
    type: 'polarArea',
    data: {
    labels: ['Mittre & Attack Technique', 'Malware', 'Use Case'],
    datasets: [{
        label: '# of Votes',
        data: [<?= $folderCount ?>, 100, 20], // Remplacer le 1 par la variable $folderCount
        backgroundColor: [
            'rgba(255, 99, 132, 0.2)',
            'rgba(54, 162, 235, 0.2)',
            'rgba(255, 206, 86, 0.2)'
        ],
        borderColor: [
            'rgba(255, 99, 132, 1)',
            'rgba(54, 162, 235, 1)',
            'rgba(255, 206, 86, 1)'
        ],
        borderWidth: 1
    }]
},
options: {
    scales: {
        r: {
            beginAtZero: true
        }
    },
    plugins: {
        title: {
            display: true,
            text: 'Number of test by type'
        }
    }
}
}); 
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

