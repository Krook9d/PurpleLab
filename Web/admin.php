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
    <title>Purplelab - Admin Panel</title>
    <link rel="stylesheet" href="css/main.css?v=<?= filemtime('css/main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="nav-bar">
    <div class="nav-logo">
        <img src="MD_image/logowhiteV3.png" alt="Logo" /> 
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'].'/scripts/php/version.php'; ?>
    <div class="software-version">
        <?php echo SOFTWARE_VERSION; ?>
    </div>

    <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
        <li><a href="https://<?= $_SERVER['SERVER_ADDR'] ?>:5601" target="_blank"><i class="fas fa-crosshairs"></i> <span>Hunting</span></a></li>
        <li><a href="mittre.php"><i class="fas fa-book"></i> <span>Mitre Att&ck</span></a></li>
        <li><a href="custom_payloads.php"><i class="fas fa-code"></i> <span>Custom Payloads</span></a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> <span>Malware</span></a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> <span>Sharing</span></a></li>
        <li><a href="sigma.php"><i class="fas fa-shield-alt"></i> <span>Sigma Rules</span></a></li>
        <li><a href="rule_lifecycle.php"><i class="fas fa-cogs"></i> <span>Rule Lifecycle</span></a></li>
        <li><a href="health.php"><i class="fas fa-heartbeat"></i> <span>Health</span></a></li>
        <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@local.com'): ?>
        <li><a href="admin.php" class="active"><i class="fas fa-user-shield"></i> <span>Admin</span></a></li>
        <?php endif; ?>
    </ul>

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
                <a href="sharing.php" id="settings-link"><i class="fas fa-cog"></i>Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </button>
    </div>
</div>

<div class="content">
    <div class="admin-container">
        <!-- Token Generation Section -->
        <div class="admin-section">
            <h2 class="admin-section-title"><i class="fas fa-key"></i> Token Generation</h2>
            
            <div class="admin-card">
                <div class="token-generation-content">
                    <p class="section-description">Generate API tokens for external applications and services</p>
                    
                    <div class="token-controls">
                        <button id="generateToken" class="admin-button primary">
                            <i class="fas fa-plus-circle"></i> Generate API Token
                        </button>
                    </div>
                    
                    <div id="tokenContainer" class="token-container" style="display:none;">
                        <div class="token-field-wrapper">
                            <label for="apiToken">Generated Token:</label>
                            <div class="token-input-group">
                                <input type="text" id="apiToken" class="token-input" readonly>
                                <button id="copyToken" class="admin-button copy">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LDAP Configuration Section -->
        <div class="admin-section">
            <h2 class="admin-section-title"><i class="fas fa-users-cog"></i> LDAP Configuration</h2>
            
            <div class="admin-card">
                <div class="ldap-configuration-content">
                    <p class="section-description">Configure LDAP settings for user authentication</p>
                    
                    <form id="ldapConfigForm" method="post" action="/scripts/php/saveLdapConfig.php" class="admin-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="ldapServer"><i class="fas fa-server"></i> LDAP Server</label>
                                <input type="text" id="ldapServer" name="ldapServer" placeholder="ldap://your-server.com:389" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="ldapDn"><i class="fas fa-sitemap"></i> Base DN</label>
                                <input type="text" id="ldapDn" name="ldapDn" placeholder="dc=example,dc=com" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="ldapUser"><i class="fas fa-user-tie"></i> Search User (Bind DN)</label>
                                <input type="text" id="ldapUser" name="ldapUser" placeholder="cn=admin,dc=example,dc=com" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="ldapPassword"><i class="fas fa-lock"></i> Password</label>
                                <input type="password" id="ldapPassword" name="ldapPassword" placeholder="Enter LDAP password" required>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="admin-button primary">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                        </div>
                    </form>

                    <?php if (isset($_SESSION['ldap_config_saved']) && $_SESSION['ldap_config_saved'] === true): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        The LDAP configuration has been successfully saved.
                    </div>
                    <?php unset($_SESSION['ldap_config_saved']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- AlienVault OTX API Configuration Section -->
        <div class="admin-section">
            <h2 class="admin-section-title"><i class="fas fa-shield-alt"></i> AlienVault OTX API Configuration</h2>
            
            <div class="admin-card">
                <div class="alienvault-configuration-content">
                    <p class="section-description">Manage your AlienVault OTX API integration for threat intelligence</p>
                    
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
                    <div class="api-status-card active">
                        <div class="api-status-header">
                            <div class="status-indicator active"></div>
                            <div class="api-status-info">
                                <h3 class="api-status-title">API Key Active</h3>
                                <p class="api-key-preview">Key: <code><?php echo $maskedKey; ?></code></p>
                                <?php if ($keyLastUpdated): ?>
                                <p class="api-last-updated">Last updated: <?php echo $keyLastUpdated; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="api-actions">
                            <button id="refreshAlienvaultBtn" class="admin-button info">
                                <i class="fas fa-sync-alt"></i> Update KPI
                            </button>
                            <form action="/scripts/php/saveAlienVaultConfig.php" method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this API key?');">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="admin-button danger">
                                    <i class="fas fa-trash"></i> Delete API Key
                                </button>
                            </form>
                        </div>
                        
                        <div id="refreshStatus" class="refresh-status" style="display: none;"></div>
                    </div>
                    <?php else: ?>
                    <div class="api-status-card inactive">
                        <div class="api-status-header">
                            <div class="status-indicator inactive"></div>
                            <div class="api-status-info">
                                <h3 class="api-status-title">No API Key Configured</h3>
                                <p class="api-status-description">Please enter your AlienVault OTX API key to enable threat intelligence features</p>
                            </div>
                        </div>
                    </div>
                    
                    <form id="alienVaultConfigForm" method="post" action="/scripts/php/saveAlienVaultConfig.php" class="admin-form">
                        <div class="form-group">
                            <label for="apiKey"><i class="fas fa-key"></i> AlienVault OTX API Key</label>
                            <input type="text" id="apiKey" name="apiKey" placeholder="Enter your AlienVault OTX API key" required>
                            <div class="form-help">
                                <i class="fas fa-info-circle"></i>
                                You can get your API key from the <a href="https://otx.alienvault.com/api" target="_blank">AlienVault OTX portal</a> after registering.
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="admin-button primary">
                                <i class="fas fa-save"></i> Save API Key
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['alienvault_config_saved']) && $_SESSION['alienvault_config_saved'] === true): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        The AlienVault API key has been successfully saved.
                    </div>
                    <?php unset($_SESSION['alienvault_config_saved']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['alienvault_config_deleted']) && $_SESSION['alienvault_config_deleted'] === true): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        The AlienVault API key has been successfully deleted.
                    </div>
                    <?php unset($_SESSION['alienvault_config_deleted']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['alienvault_config_error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        Error: <?php echo $_SESSION['alienvault_config_error']; ?>
                    </div>
                    <?php unset($_SESSION['alienvault_config_error']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#generateToken').click(function() {
        var button = $(this);
        button.prop('disabled', true);
        button.html('<i class="fas fa-spinner fa-spin"></i> Generating...');
        
        $.ajax({
            url: 'http://' + window.location.hostname + ':5000/login', 
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ username: 'admin', password: 'password' }),
            success: function(response) {
                $('#apiToken').val(response.access_token);
                $('#tokenContainer').slideDown(300);
            },
            error: function() {
                alert("Error during token generation");
            },
            complete: function() {
                button.prop('disabled', false);
                button.html('<i class="fas fa-plus-circle"></i> Generate API Token');
            }
        });
    });

    $('#copyToken').click(function() {
        var tokenInput = $('#apiToken')[0];
        tokenInput.select();
        tokenInput.setSelectionRange(0, 99999); // For mobile
        
        try {
            document.execCommand('copy');
            var button = $(this);
            var originalContent = button.html();
            button.html('<i class="fas fa-check"></i> Copied!');
            button.addClass('copied');
            
            setTimeout(function() {
                button.html(originalContent);
                button.removeClass('copied');
                $('#tokenContainer').slideUp(300);
                $('#apiToken').val('');
            }, 2000);
        } catch (err) {
            alert('Failed to copy token');
        }
    });
    
   
    $('#refreshAlienvaultBtn').click(function() {
        var refreshBtn = $(this);
        var statusDiv = $('#refreshStatus');
        
    
        refreshBtn.prop('disabled', true);
        refreshBtn.html('<i class="fas fa-spinner fa-spin"></i> Updating...');
        statusDiv.removeClass('success error').addClass('loading').html('<i class="fas fa-spinner fa-spin"></i> Refreshing AlienVault data...').show();
        
        // Appel à l'API
        $.ajax({
            url: 'http://' + window.location.hostname + ':5000/refresh_alienvault',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({}),
            success: function(response) {
                // Afficher le succès
                statusDiv.removeClass('loading error').addClass('success')
                       .html('<i class="fas fa-check-circle"></i> KPI data successfully updated. The changes will be reflected on the dashboard.');
                
        
                var now = new Date();
                var formattedDate = now.getFullYear() + '-' + 
                                  ('0' + (now.getMonth() + 1)).slice(-2) + '-' + 
                                  ('0' + now.getDate()).slice(-2) + ' ' + 
                                  ('0' + now.getHours()).slice(-2) + ':' + 
                                  ('0' + now.getMinutes()).slice(-2) + ':' + 
                                  ('0' + now.getSeconds()).slice(-2);
                $('.api-last-updated').text('Last updated: ' + formattedDate);
            },
            error: function(xhr, status, error) {
  
                var errorMessage = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : 'An error occurred while refreshing the data.';
                
                statusDiv.removeClass('loading success').addClass('error')
                       .html('<i class="fas fa-exclamation-circle"></i> Error: ' + errorMessage);
            },
            complete: function() {
       
                refreshBtn.prop('disabled', false);
                refreshBtn.html('<i class="fas fa-sync-alt"></i> Update KPI');
            }
        });
    });
});
</script>

</body>
</html>
