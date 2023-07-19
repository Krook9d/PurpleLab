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

// Vérifier si le formulaire d'écriture a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    // Récupérer le contenu du formulaire
    $content = $_POST['content'];
    
    // Insérer le contenu dans la base de données
    $sql = "INSERT INTO contents (content) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $content);
    
    if ($stmt->execute()) {
        // Rediriger vers la même page pour éviter la soumission du formulaire lors d'un rechargement de page
        header('Location: sharing.php');
        exit;
    } else {
        echo "Erreur lors de l'insertion du contenu : " . $conn->error;
    }
}

// Vérifier si une demande de suppression a été effectuée
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['id'])) {
    $id = $_POST['id'];
    
    // Vérifier si l'utilisateur est autorisé à supprimer ce contenu (vérifiez par exemple un identifiant d'utilisateur)
    $canDelete = true;
    
    if ($canDelete) {
        // Supprimer le contenu correspondant à l'identifiant
        $sql = "DELETE FROM contents WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            // Rediriger vers la même page après la suppression
            header('Location: sharing.php');
            exit;
        } else {
            echo "Erreur lors de la suppression du contenu : " . $conn->error;
        }
    }
}

// Récupérer les contenus depuis la base de données
$sql = "SELECT id, content FROM contents";
$result = $conn->query($sql);

// Vérifier si des contenus existent
if ($result->num_rows > 0) {
    $contents = array();
    
    // Parcourir les résultats de la requête et stocker les contenus dans un tableau
    while ($row = $result->fetch_assoc()) {
        $contents[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Site Web</title>
    <link rel="stylesheet" href="styles.css?v=3.3">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>



    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var textarea = document.querySelector('textarea[name="content"]');
        var submitButton = document.querySelector('button[type="submit"]');
        var errorMessage = document.querySelector('.error-message');

        textarea.addEventListener('input', function() {
            var contentLength = textarea.value.length;

            if (contentLength > 1000) {
                submitButton.disabled = true;
                errorMessage.textContent = 'Le contenu ne doit pas dépasser 1000 caractères.';
            } else {
                submitButton.disabled = false;
                errorMessage.textContent = '';
            }
        });
    });
</script>

<style>
    /* Style du formulaire "content" */
    form {
        margin-bottom: 20px;
    }

    textarea {
        width: 80%;
        height: 100px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        resize: vertical;
    }

    button[type="submit"] {
        margin-top: 10px;
        padding: 5px 10px;
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .error-message {
        color: red;
    }

    /* Ligne de séparation */
    .content div:not(:last-child) {
        border-bottom: 1px solid #ccc;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    /* Fond blanc pour les blocs de contenu */
    .white-background {
        background-color: white;
    }
</style>

</head>
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

    <div id="sidebar" class="sidebar">
        <div class="sidebar-section">
            <h3 style="text-align: center; color: white">Profil</h2>
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

    <div class="content">
        <h1>Page de partage</h1>

        <form method="POST" action="sharing.php">
    <textarea name="content" placeholder="Écrivez votre contenu ici"></textarea>
    <p class="error-message" style="color: red;"></p>
    <button type="submit">Ajouter</button>
</form>



<?php
// Afficher les contenus existants
if (!empty($contents)) {
    foreach ($contents as $content) {
        $id = $content['id'];
        $text = $content['content'];

        echo '<div class="white-background">'; // Add the class "white-background" here
        echo '<p>' . $text . '</p>';
        echo '<p>Par ' . $first_name . ' ' . $last_name . '</p>';

        // Ajouter le formulaire de suppression
        echo '<form method="POST" action="sharing.php">';
        echo '<input type="hidden" name="id" value="' . $id . '">';
        echo '<button type="submit" name="delete">Supprimer</button>';
        echo '</form>';

        echo '</div>';
    }
} else {
    echo '<p>Aucun contenu disponible.</p>';
}
?>
    </div>
</body>
</html>
