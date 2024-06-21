<?php
session_start();

// Redirect to login page if user is not logged in or is not admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@local.com') {
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
    die("Error retrieving user information.");
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="MD_image/logowhite.png" type="image/png">
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


    <?php
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
    die("Error retrieving user information.");
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="styles.css?v=1">
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
        <h1>🔐  Admin page</h1>
        <br>
        <h2>Token generation</h2>
        <div class="admin-container">
            <button id="generateToken" class="admin-button">Generate API token</button>
            <div id="tokenContainer" style="display:none;">
                <input type="text" id="apiToken" class="admin-input" readonly>
                <button id="copyToken" class="admin-button">Copy</button>
            </div>
        </div>

        <div class="health-section-separator"></div>

        <h2>LDAP configuration</h2>

        <div class="ldap-configuration">
    <form id="ldapConfigForm" method="post" action="/scripts/php/saveLdapConfig.php">
        <div class="form-group">
            <label for="ldapServer">LDAP Server:</label>
            <input type="text" id="ldapServer" name="ldapServer" required>
        </div>
        <div class="form-group">
            <label for="ldapDn">Base DN (Base DN):</label>
            <input type="text" id="ldapDn" name="ldapDn" required>
        </div>
        <div class="form-group">
            <label for="ldapUser">Search User (Bind DN):</label>
            <input type="text" id="ldapUser" name="ldapUser" required>
        </div>
        <div class="form-group">
            <label for="ldapPassword">Password:</label>
            <input type="password" id="ldapPassword" name="ldapPassword" required>
        </div>
        <button type="submit" class="admin-button">Save Configuration</button>
    </form>

    <?php if (isset($_SESSION['ldap_config_saved']) && $_SESSION['ldap_config_saved'] === true): ?>
    <div style="color: green; margin-top: 20px;">
        The LDAP configuration has been successfully saved.
    </div>
    <?php unset($_SESSION['ldap_config_saved']); ?>
<?php endif; ?>


</div>




    </div>

    <script>
        $(document).ready(function() {
            $('#generateToken').click(function() {
                $.ajax({
                    url: 'http://' + window.location.hostname + ':5000/login', 
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ username: 'admin', password: 'password' }),
                    success: function(response) {
                        $('#apiToken').val(response.access_token);
                        $('#tokenContainer').show();
                    },
                    error: function() {
                        alert("Error during token generation");
                    }
                });
            });

            $('#copyToken').click(function() {
                var tokenInput = $('#apiToken');
                tokenInput.select();
                document.execCommand('copy');
                $('#tokenContainer').hide();
                tokenInput.val('');
                alert('Token copied!');
            });
        });
    </script>
</body>
</html>

