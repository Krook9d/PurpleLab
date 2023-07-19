<?php
session_start();

if (!isset($_SESSION['email'])) {
    header('Location: blank.html');
    exit();
}

$conn = new mysqli('localhost', 'root', 'root', 'myDatabase');

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
$directory = 'C:\xampp\htdocs\Mittre\Technic';
$folders = scandir($directory);
$folderCount = count($folders) - 2; // Soustraire 2 pour exclure les dossiers "." et ".."
?>



<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $searchQuery = $_POST['search'] ?? '';
    $filePath = 'C:\xampp\htdocs\Mittre\Technic\list.txt';
    $results = [];

    $fileContent = file_get_contents($filePath);
    $lines = explode(PHP_EOL, $fileContent);
    
    foreach ($lines as $line) {
        if (stripos($line, $searchQuery) !== false) {
            $results[] = $line;
        }
    }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Site Web</title>
    <link rel="stylesheet" href="styles.css?v=3.3" >
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
<body>

<div class="nav-bar">
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="mittre.php">Mitre Att&ck</a></li>
            <li><a href="malware.php">Malware</a></li>
            <li><a href="usecase.php">UseCase</a></li>
            <li><a href="sharing.php">Sharing</a></li>
        </ul>
    </div>

    <div class="user-info-bar">
    <div class="avatar-info">
        <img src="<?= $avatar ?>" alt="Avatar">
        <button class="user-button">
            <span><?= $first_name ?> <?= $last_name ?></span>
            <div class="dropdown-content">
            <a href="#" id="settings-link">Paramètres</a>
                <a href="logout.php">Déconnexion</a>
                
            </div>
        </button>
    </div>
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

            <div class="content">
        <h1>Titre de la page</h1>
        <p>Ceci est un paragraphe de texte pour votre blank.</p><br>
       
       
    </div>
</body>
</html>
