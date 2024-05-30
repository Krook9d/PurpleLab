<?php
session_start();

if (!isset($_SESSION['email'])) {
    header('Location: connexion.html');
    exit;
}


$conn = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = $_SESSION['email'];


$sql = "SELECT id, first_name, last_name, email, analyst_level, avatar FROM users WHERE email=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($user_id, $first_name, $last_name, $email, $analyst_level, $avatar);

if (!$stmt->fetch()) {
    die("Error retrieving user information.");
}
$stmt->close();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = $_POST['content'];

    $sql = "INSERT INTO contents (author_id, content) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $user_id, $content); 
    
    if ($stmt->execute()) {
        header('Location: sharing.php');
        exit;
    } else {
        echo "Error during content insertion: " . $conn->error;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['id'])) {
    $id = $_POST['id'];
    
    $canDelete = true;
    
    if ($canDelete) {
        $sql = "DELETE FROM contents WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            echo "Error when deleting content: " . $conn->error;
        }
    }
}


$sql = "SELECT contents.id, contents.content, users.first_name, users.last_name, contents.author_id FROM contents JOIN users ON contents.author_id = users.id";
$result = $conn->query($sql);
$contents = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $contents[] = $row;
    }
}



$conn->close();
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="MD_image/logowhite.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="styles.css?v=5.2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
                errorMessage.textContent = 'The content must not exceed 1000 characters.';
            } else {
                submitButton.disabled = false;
                errorMessage.textContent = '';
            }
        });
    });
</script>

<style>
    /* "content" form style */
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


    .content div:not(:last-child) {
        border-bottom: 1px solid #ccc;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .white-background {
        background-color: white;
    }
    
</style>

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
        <li><a href="https://<?= $_SERVER['SERVER_ADDR'] ?>:5601" target="_blank"><i class="fas fa-crosshairs"></i> Hunting</a></li>
        <li><a href="mittre.php"><i class="fas fa-book"></i> Mitre Att&ck</a></li>
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
            <button class="user-button">
                <span><?= $first_name ?> <?= $last_name ?></span>
                <div class="dropdown-content">
                    <a href="sharing.php" id="settings-link">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </button>
        </div>
    </div>


    <div class="content">
        <h1>✏️ Sharing page</h1>

        <form method="POST" action="sharing.php">
    <textarea name="content" placeholder="Write your content here"></textarea>
    <p class="error-message" style="color: red;"></p>
    <button type="submit">Add</button>
</form>



<?php
// Show existing content
if (!empty($contents)) {
    foreach ($contents as $content) {
        echo '<div class="white-background">';
        echo '<p>' . htmlspecialchars($content['content']) . '</p>';
        echo '<p>Par ' . htmlspecialchars($content['first_name']) . ' ' . htmlspecialchars($content['last_name']) . '</p>';
    
    
        if (isset($content['author_id']) && $user_id == $content['author_id']) {
            echo '<form method="POST" action="sharing.php">';
            echo '<input type="hidden" name="id" value="' . $content['id'] . '">';
            echo '<button type="submit" name="delete">Delete</button>';
            echo '</form>';
        }
        echo '</div>';
    }
    
} else {
    echo '<p>No content available.</p>';
}

?>
    </div>
</body>
</html>
