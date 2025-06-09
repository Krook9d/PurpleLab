<?php
session_start();

if (!isset($_SESSION['email'])) {
    header('Location: connexion.html');
    exit;
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
    die("PostgreSQL connection failure");
}

$email = $_SESSION['email'];

$sql = "SELECT id, first_name, last_name, email, analyst_level, avatar FROM users WHERE email=$1";
$result = pg_query_params($conn, $sql, array($email));

if ($result && $row = pg_fetch_assoc($result)) {
    $user_id = $row['id'];
    $first_name = $row['first_name'];
    $last_name = $row['last_name'];
    $email = $row['email'];
    $analyst_level = $row['analyst_level'];
    $avatar = $row['avatar'];
} else {
    die("Error retrieving user information.");
}
pg_free_result($result);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' && isset($_POST['name']) && isset($_POST['content'])) {
        $name = $_POST['name'];
        $content = $_POST['content'];
        
        $sql = "INSERT INTO custom_payloads (name, content, author_id) VALUES ($1, $2, $3)";
        $result = pg_query_params($conn, $sql, array($name, $content, $user_id));
        
        if (!$result) {
            echo "Error adding payload: " . pg_last_error($conn);
        }
    }
   
    elseif ($_POST['action'] === 'delete' && isset($_POST['payload_id'])) {
        $payload_id = $_POST['payload_id'];
        
       
        $sql = "DELETE FROM custom_payloads WHERE id = $1 AND author_id = $2";
        $result = pg_query_params($conn, $sql, array($payload_id, $user_id));
        
        if (!$result) {
            echo "Error deleting payload: " . pg_last_error($conn);
        }
    }
   
    elseif ($_POST['action'] === 'execute' && isset($_POST['payload_id'])) {
        $payload_id = $_POST['payload_id'];
        
 
        $sql = "SELECT content FROM custom_payloads WHERE id = $1 AND author_id = $2";
        $result = pg_query_params($conn, $sql, array($payload_id, $user_id));
        
        if ($result && $row = pg_fetch_assoc($result)) {
            
            $api_data = json_encode(['content' => $row['content']]);
            
            $ch = curl_init('http://127.0.0.1:5000/api/execute_payload');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $api_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($api_data)
            ]);
            
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $response = json_decode($result, true);
                echo json_encode([
                    'status' => 'success',
                    'output' => $response['output'],
                    'error' => $response['error']
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to execute payload'
                ]);
            }
            exit;
        }
    }
}


$sql = "SELECT cp.*, u.first_name, u.last_name FROM custom_payloads cp 
        JOIN users u ON cp.author_id = u.id 
        ORDER BY cp.created_at DESC";
$result = pg_query($conn, $sql);
$payloads = [];

if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        $payloads[] = $row;
    }
    pg_free_result($result);
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="MD_image/logowhite.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Payloads - Purplelab</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/line-numbers/prism-line-numbers.min.css" rel="stylesheet" />
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
        <li><a href="custom_payloads.php" class="active"><i class="fas fa-code"></i> <span>Custom Payloads</span></a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> <span>Malware</span></a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> <span>Sharing</span></a></li>
        <li><a href="sigma.php"><i class="fas fa-shield-alt"></i> <span>Sigma Rules</span></a></li>
        <li><a href="rule_lifecycle.php"><i class="fas fa-cogs"></i> <span>Rule Lifecycle</span></a></li>
        <li><a href="health.php"><i class="fas fa-heartbeat"></i> <span>Health</span></a></li>
        <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@local.com'): ?>
            <li><a href="admin.php"><i class="fas fa-user-shield"></i> <span>Admin</span></a></li>
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
                <a href="custom_payloads.php" id="settings-link"><i class="fas fa-cog"></i>Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </button>
    </div>
</div>

<div class="content">
    <h1 class="title">Custom Payloads</h1>
    
    <div class="payload-stats">
        <div class="stat-item">
            <span class="stat-value"><?= count($payloads) ?></span>
            <span class="stat-label">Total Payloads</span>
        </div>
        
    </div>

    <div class="payload-form">
        <form method="POST" action="custom_payloads.php">
            <input type="hidden" name="action" value="add">
            <input type="text" name="name" placeholder="Payload Name" required class="payload-input">
            <textarea name="content" placeholder="PowerShell Code" required class="payload-textarea"></textarea>
            <button type="submit" class="payload-submit-button">Add Payload</button>
        </form>
    </div>

    <div class="separator"></div>

    <h2 class="payloads-section-title">Available Payloads</h2>
    <div class="payloads-container">
        <?php foreach ($payloads as $payload): ?>
            <div class="payload-card" onclick="showPayloadDetails(<?= htmlspecialchars(json_encode($payload)) ?>)">
                <h3 class="payload-name"><?= htmlspecialchars($payload['name']) ?></h3>
                <p class="payload-author">By <?= htmlspecialchars($payload['first_name']) ?> <?= htmlspecialchars($payload['last_name']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="payload-modal" id="payloadModal">
        <div class="payload-modal-content">
            <span class="modal-close" onclick="closePayloadModal()">&times;</span>
            <h2 id="modalPayloadName"></h2>
            <p id="modalPayloadAuthor"></p>
            <pre class="payload-content line-numbers"><code class="language-powershell" id="modalPayloadContent"></code></pre>
            <div class="payload-actions" id="modalPayloadActions">
            </div>
        </div>
    </div>
</div>

<script>
function showPayloadDetails(payload) {
    const modal = document.getElementById('payloadModal');
    const modalName = document.getElementById('modalPayloadName');
    const modalAuthor = document.getElementById('modalPayloadAuthor');
    const modalContent = document.getElementById('modalPayloadContent');
    const modalActions = document.getElementById('modalPayloadActions');

    modalName.textContent = payload.name;
    modalAuthor.textContent = `By ${payload.first_name} ${payload.last_name}`;
    
    modalContent.textContent = payload.content;
    Prism.highlightElement(modalContent);

    modalActions.innerHTML = `
        <button onclick="executePayload(${payload.id})" class="payload-execute-button">Execute</button>
        ${payload.author_id == <?= $user_id ?> ? `
            <form method="POST" action="custom_payloads.php" style="display: inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="payload_id" value="${payload.id}">
                <button type="submit" class="payload-delete-button">Delete</button>
            </form>
        ` : ''}
    `;

    modal.style.display = 'flex';
}

function closePayloadModal() {
    const modal = document.getElementById('payloadModal');
    modal.style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('payloadModal');
    if (event.target == modal) {
        closePayloadModal();
    }
}

document.querySelector('.payload-modal-content').onclick = function(event) {
    event.stopPropagation();
}

function executePayload(payloadId) {
    const formData = new FormData();
    formData.append('action', 'execute');
    formData.append('payload_id', payloadId);

    fetch('custom_payloads.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Payload executed successfully!\n\nOutput:\n' + data.output + 
                  (data.error ? '\n\nErrors:\n' + data.error : ''));
        } else {
            alert('Error executing payload: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error executing payload: ' + error);
    });
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-powershell.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/line-numbers/prism-line-numbers.min.js"></script>

</body>
</html>
