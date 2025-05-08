<?php
session_start();

// Redirect to login page if user is not logged in or is not admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@local.com') {
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
    die("Échec de connexion à PostgreSQL");
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
    die("Erreur lors de la récupération des informations utilisateur.");
}

pg_free_result($result);
pg_close($conn);

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
        <br>
        <h1 class="title">Token Generation</h1>
        <div class="admin-container">
            <button id="generateToken" class="admin-button">Generate API token</button>
            <div id="tokenContainer" style="display:none;"><br>
                <input type="text" id="apiToken" class="admin-input" readonly>
                <button id="copyToken" class="admin-button">Copy</button>
            </div>
        </div>
        <br><br>
       

        <h1 class="title">LDAP configuration</h1>

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

        <h1 class="title">AlienVault OTX API Configuration</h1>
        <div class="ldap-configuration">
            <?php
            $configFile = '/var/www/html/alienvault/config.json';
            $hasApiKey = false;
            $keyLastUpdated = '';
            
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                if (isset($config['api_key_configured']) && $config['api_key_configured'] === true) {
                    $hasApiKey = true;
                    $maskedKey = $config['key_preview'] ?? '****';
                    $keyLastUpdated = isset($config['last_updated']) ? $config['last_updated'] : '';
                }
            }
            ?>
            
            <?php if ($hasApiKey): ?>
            <div class="alienvault-key-status active">
                <div class="alienvault-status-indicator"></div>
                <p>API Key is active: <strong><?php echo $maskedKey; ?></strong></p>
                <p class="alienvault-key-updated">Last updated: <?php echo $keyLastUpdated; ?></p>
                <div class="alienvault-button-group">
                    <button id="refreshAlienvaultBtn" class="admin-button alienvault-refresh-button">
                        <i class="fas fa-sync-alt"></i> Update KPI
                    </button>
                    <form action="/scripts/php/saveAlienVaultConfig.php" method="post" onsubmit="return confirm('Are you sure you want to delete this API key?');">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="admin-button alienvault-delete-button">Delete API Key</button>
                    </form>
                </div>
                <div id="refreshStatus" class="alienvault-refresh-status" style="display: none;"></div>
            </div>
            <?php else: ?>
            <div class="alienvault-key-status inactive">
                <div class="alienvault-status-indicator"></div>
                <p>No API key configured. Please enter your AlienVault OTX API key below.</p>
            </div>
            
            <form id="alienVaultConfigForm" method="post" action="/scripts/php/saveAlienVaultConfig.php">
                <div class="form-group">
                    <label for="apiKey">AlienVault OTX API Key:</label>
                    <input type="text" id="apiKey" name="apiKey" placeholder="Enter your AlienVault OTX API key" required>
                </div>
                <div class="form-group">
                    <p class="alienvault-api-info">You can get your API key from the <a href="https://otx.alienvault.com/api" target="_blank">AlienVault OTX portal</a> after registering.</p>
                </div>
                <button type="submit" class="admin-button">Save API Key</button>
            </form>
            <?php endif; ?>

            <?php if (isset($_SESSION['alienvault_config_saved']) && $_SESSION['alienvault_config_saved'] === true): ?>
            <div style="color: green; margin-top: 20px;">
                The AlienVault API key has been successfully saved.
            </div>
            <?php unset($_SESSION['alienvault_config_saved']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['alienvault_config_deleted']) && $_SESSION['alienvault_config_deleted'] === true): ?>
            <div style="color: green; margin-top: 20px;">
                The AlienVault API key has been successfully deleted.
            </div>
            <?php unset($_SESSION['alienvault_config_deleted']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['alienvault_config_error'])): ?>
            <div style="color: red; margin-top: 20px;">
                Error: <?php echo $_SESSION['alienvault_config_error']; ?>
            </div>
            <?php unset($_SESSION['alienvault_config_error']); ?>
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
            
            // Gestion du bouton de rafraîchissement des KPI AlienVault
            $('#refreshAlienvaultBtn').click(function() {
                var refreshBtn = $(this);
                var statusDiv = $('#refreshStatus');
                
                // Désactiver le bouton et afficher le statut de chargement
                refreshBtn.prop('disabled', true);
                refreshBtn.html('<i class="fas fa-spinner fa-spin"></i> Updating...');
                statusDiv.removeClass('success error').addClass('loading').html('Refreshing AlienVault data...').show();
                
                // Appel à l'API
                $.ajax({
                    url: 'http://' + window.location.hostname + ':5000/refresh_alienvault',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({}),
                    xhrFields: {
                        withCredentials: false
                    },
                    crossDomain: true,
                    success: function(response) {
                        // Afficher le succès
                        statusDiv.removeClass('loading error').addClass('success')
                               .html('<i class="fas fa-check-circle"></i> KPI data successfully updated. The changes will be reflected on the dashboard.');
                        
                        // Mise à jour de l'heure de dernière mise à jour
                        var now = new Date();
                        var formattedDate = now.getFullYear() + '-' + 
                                          ('0' + (now.getMonth() + 1)).slice(-2) + '-' + 
                                          ('0' + now.getDate()).slice(-2) + ' ' + 
                                          ('0' + now.getHours()).slice(-2) + ':' + 
                                          ('0' + now.getMinutes()).slice(-2) + ':' + 
                                          ('0' + now.getSeconds()).slice(-2);
                        $('.alienvault-key-updated').text('Last updated: ' + formattedDate);
                    },
                    error: function(xhr, status, error) {
                        // Afficher l'erreur
                        var errorMessage = xhr.responseJSON && xhr.responseJSON.message 
                            ? xhr.responseJSON.message 
                            : 'An error occurred while refreshing the data.';
                        
                        statusDiv.removeClass('loading success').addClass('error')
                               .html('<i class="fas fa-exclamation-circle"></i> Error: ' + errorMessage);
                    },
                    complete: function() {
                        // Réactiver le bouton
                        refreshBtn.prop('disabled', false);
                        refreshBtn.html('<i class="fas fa-sync-alt"></i> Update KPI');
                    }
                });
            });
        });
    </script>
</body>
</html>
