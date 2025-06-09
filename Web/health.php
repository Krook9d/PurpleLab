<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['email'])) {
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
    die("PostgreSQL connection failure");
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
    die("Error retrieving user information.");
}

pg_free_result($result);
pg_close($conn);

function isServiceRunning($serviceName) {
    $result = shell_exec("systemctl is-active " . escapeshellarg($serviceName));
    return (trim($result) === "active");
}

function getServerMemoryUsage() {
    $free = shell_exec('free -m'); // 
    $free = (string)trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem);
    $mem = array_merge($mem);
    $memory_usage = $mem[2]/$mem[1]*100;
    $memory_total = round($mem[1]/1024, 2); 
    $memory_used = round($mem[2]/1024, 2); 

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
        'total' => round($disktotal/1024/1024/1024, 2), 
        'used' => round($diskused/1024/1024/1024, 2) 
    ];
}

function getServerCpuUsage() {
    // Get CPU usage from top command
    $top_output = shell_exec("top -bn1 | grep 'Cpu(s)' 2>/dev/null");
    
    if ($top_output) {
        // Parse format: %Cpu(s):  1.8 us,  1.8 sy,  0.0 ni, 96.4 id,  0.0 wa,  0.0 hi,  0.0 si,  0.0 st
        // We want everything except 'id' (idle)
        preg_match('/(\d+\.\d+)\s+us,\s+(\d+\.\d+)\s+sy,\s+(\d+\.\d+)\s+ni,\s+(\d+\.\d+)\s+id/', $top_output, $matches);
        
        if (count($matches) >= 5) {
            $user = floatval($matches[1]);
            $system = floatval($matches[2]);
            $nice = floatval($matches[3]);
            $idle = floatval($matches[4]);
            
            // CPU usage = 100 - idle
            $cpu_usage = 100 - $idle;
            
            // Ensure it's within reasonable bounds
            if ($cpu_usage < 0) $cpu_usage = 0;
            if ($cpu_usage > 100) $cpu_usage = 100;
            
            return round($cpu_usage);
        }
    }
    
    // Fallback: use vmstat
    $vmstat_output = shell_exec("vmstat 1 2 2>/dev/null | tail -1 | awk '{print 100-\$15}'");
    if ($vmstat_output && is_numeric(trim($vmstat_output))) {
        $cpu_usage = floatval(trim($vmstat_output));
        if ($cpu_usage < 0) $cpu_usage = 0;
        if ($cpu_usage > 100) $cpu_usage = 100;
        return round($cpu_usage);
    }
    
    return 0;
}

$memory = getServerMemoryUsage();
$disk = getServerDiskUsage();
$cpuUsagePercent = getServerCpuUsage();

$opensearchDashboardRunning = isServiceRunning("dashboards");
$postgresRunning = isServiceRunning("postgresql@14-main");
$opensearchRunning = isServiceRunning("opensearch");
$virtualboxRunning = isServiceRunning("virtualbox");

function isFlaskRunning() {
    $url = 'http://127.0.0.1:5000/'; 
    $headers = @get_headers($url);
    if ($headers !== false && is_array($headers)) {
        return strpos($headers[0], '200') !== false;
    } else {
        return false;
    }
}

$flaskStatus = isFlaskRunning() ? 'running' : 'stopped';

$flask_vm_state_url = 'http://127.0.0.1:5000/vm_state';
$curl = curl_init($flask_vm_state_url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$response = curl_exec($curl);
$vmInfo = "Error retrieving VM information";

if (!curl_errno($curl)) {
    $response_data = json_decode($response, true);
    if ($response_data) {
        $vmInfo = $response_data;
    }
}
curl_close($curl);

$flask_vm_ip_url = 'http://127.0.0.1:5000/vm_ip';
$curl_ip = curl_init($flask_vm_ip_url);
curl_setopt($curl_ip, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_ip, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$response_ip = curl_exec($curl_ip);
$vmIP = "Error retrieving VM IP";

if (!curl_errno($curl_ip)) {
    $response_ip_data = json_decode($response_ip, true);
    if ($response_ip_data) {
        $vmIP = $response_ip_data['ip'];
    }
}
curl_close($curl_ip);

if (!is_array($vmInfo)) {
    $vmInfo = [];
}
$vmInfo['IP'] = $vmIP;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="MD_image/logowhite.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="css/main.css?v=<?= filemtime('css/main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="https://<?= $_SERVER['SERVER_ADDR'] ?>:5601" target="_blank"><i class="fas fa-crosshairs"></i> Hunting</a></li>
        <li><a href="mittre.php"><i class="fas fa-book"></i> Mitre Att&ck</a></li>
        <li><a href="custom_payloads.php"><i class="fas fa-code"></i> Custom Payloads</a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> Malware</a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> Sharing</a></li>
        <li><a href="sigma.php"><i class="fas fa-shield-alt"></i> Sigma Rules</a></li>
        <li><a href="rule_lifecycle.php" class="active"><i class="fas fa-cogs"></i> Rule Lifecycle</a></li>
        <li><a href="health.php"><i class="fas fa-heartbeat"></i> Health</a></li>
        <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@local.com'): ?>
        <li><a href="admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
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
        <button id="user-button" class="user-button">
            <span><?= $first_name ?> <?= $last_name ?></span>
            <div class="dropdown-content">
                <a href="#" id="settings-link">Settings</a>
                <a href="logout.php">Logout</a>
            </div>
        </button>
    </div>
</div>

<div class="content">
    <div class="health-container">
        <!-- Service Status Section -->
        <div class="health-section" id="service-status">
            <h2 class="health-section-title"><i class="fas fa-heartbeat"></i> Service Status</h2>
            
            <div class="health-dashboard">
                <!-- OpenSearch Dashboard -->
                <div class="health-card">
                    <h2><i class="fas fa-chart-line"></i> OpenSearch Dashboard</h2>
                    <div class="health-status <?= $opensearchDashboardRunning ? 'running' : 'stopped' ?>">
                        <?= $opensearchDashboardRunning ? 'Running' : 'Stopped' ?>
                    </div>
                </div>

                <!-- PostgreSQL -->
                <div class="health-card">
                    <h2><i class="fas fa-database"></i> PostgreSQL</h2>
                    <div class="health-status <?= $postgresRunning ? 'running' : 'stopped' ?>">
                        <?= $postgresRunning ? 'Running' : 'Stopped' ?>
                    </div>
                </div>

                <!-- OpenSearch -->
                <div class="health-card">
                    <h2><i class="fas fa-search"></i> OpenSearch</h2>
                    <div class="health-status <?= $opensearchRunning ? 'running' : 'stopped' ?>">
                        <?= $opensearchRunning ? 'Running' : 'Stopped' ?>
                    </div>
                </div>

                <!-- Service VirtualBox -->
                <div class="health-card">
                    <h2><i class="fas fa-desktop"></i> VirtualBox</h2>
                    <div class="health-status <?= $virtualboxRunning ? 'running' : 'stopped' ?>">
                        <?= $virtualboxRunning ? 'Running' : 'Stopped' ?>
                    </div>
                </div>

                <!-- Flask Backend -->
                <div class="health-card">
                    <h2><i class="fas fa-toolbox"></i> Flask Backend</h2>
                    <div class="health-status <?= $flaskStatus ?>">
                        <?= ucfirst($flaskStatus) ?>
                    </div>
                </div>    
            </div>
        </div>

        <!-- RAM & Disk Usage Section -->
        <div class="health-section">
            <h2 class="health-section-title"><i class="fas fa-tachometer-alt"></i> System Resources</h2>
            <div class="health-dashboard">
                <!-- RAM -->
                <div class="resource-card">
                    <h2><i class="fas fa-memory"></i> RAM Usage</h2>
                    <div class="health-metric">
                        <div style="width: <?= $memory['percent'] ?>%;">
                            <?= round($memory['percent'], 1) ?>%
                        </div>
                    </div>
                    <p><?= $memory['used'] ?> GB / <?= $memory['total'] ?> GB</p>
                </div>
                
                <!-- Disk space -->
                <div class="resource-card">
                    <h2><i class="fas fa-hdd"></i> Disk Usage</h2>
                    <div class="health-metric">
                        <div style="width: <?= $disk['percent'] ?>%;">
                            <?= $disk['percent'] ?>%
                        </div>
                    </div>
                    <p><?= $disk['used'] ?> GB / <?= $disk['total'] ?> GB</p>
                </div>

                <!-- CPU Usage -->
                <div class="resource-card">
                    <h2><i class="fas fa-microchip"></i> CPU Usage</h2>
                    <div class="health-metric">
                        <div style="width: <?= $cpuUsagePercent ?>%;">
                            <?= $cpuUsagePercent ?>%
                        </div>
                    </div>
                    <p>Current CPU Usage</p>
                </div>
            </div>
        </div>

        <!-- VM Management Section -->
        <div class="health-section">
            <h2 class="health-section-title"><i class="fas fa-server"></i> VM Management</h2>
            <div class="health-dashboard">
                <div class="health-card-management">
                    <!-- VM Info -->
                    <h1 class="title"><i class="fas fa-info-circle"></i> VM Information</h1>
                    
                    <div class="vm-info-grid">
                        <?php
                        $vmName = 'Unknown';
                        $vmState = 'Unknown';
                        $vmSnapshots = 'None';
                        $vmIP = $vmInfo['IP'] ?? 'Unknown';
                        
                        $mainDataString = '';
                        foreach ($vmInfo as $key => $value) {
                            if ($key === 'IP') continue;
                            
                            if (is_string($value) && stripos($value, 'sandbox') !== false && stripos($value, 'State:') !== false) {
                                $mainDataString = $value;
                                break;
                            } elseif (is_array($value)) {
                                foreach ($value as $subValue) {
                                    if (is_string($subValue) && stripos($subValue, 'sandbox') !== false && stripos($subValue, 'State:') !== false) {
                                        $mainDataString = $subValue;
                                        break 2;
                                    }
                                }
                            }
                        }
                        
                        if (!empty($mainDataString)) {
                            if (preg_match('/^([^S]+)State:/', $mainDataString, $matches)) {
                                $vmName = trim($matches[1]);
                            }
                            
                            if (preg_match('/State:\s*([^(]+)(?:\s*\(|Snapshots:|$)/', $mainDataString, $matches)) {
                                $vmState = trim($matches[1]);
                            }
                            
                            if (preg_match('/Snapshots:\s*Name:\s*([^*]+)/', $mainDataString, $matches)) {
                                $vmSnapshots = trim($matches[1]);
                                $vmSnapshots = rtrim($vmSnapshots, ' )');
                            } elseif (stripos($mainDataString, 'Snapshots:') !== false && stripos($mainDataString, 'Name:') === false) {
                                $vmSnapshots = 'No snapshots available';
                            }
                        }
                        
                        if ($vmName === 'Unknown' || $vmState === 'Unknown') {
                            foreach ($vmInfo as $key => $value) {
                                if ($key === 'IP') continue;
                                
                                if (is_string($value)) {
                                    if (stripos($value, 'sandbox') !== false && $vmName === 'Unknown') {
                                        $vmName = 'sandbox';
                                    }
                                    if ((stripos($value, 'powered off') !== false || stripos($value, 'running') !== false) && $vmState === 'Unknown') {
                                        if (stripos($value, 'powered off') !== false) {
                                            $vmState = 'powered off';
                                        } elseif (stripos($value, 'running') !== false) {
                                            $vmState = 'running';
                                        }
                                    }
                                }
                            }
                        }
                        
                        $isRunning = (stripos($vmState, 'running') !== false);
                        $stateClass = $isRunning ? 'status-running' : 'status-stopped';
                        $stateIcon = $isRunning ? 'fas fa-play-circle' : 'fas fa-stop-circle';
                        ?>
                        
                        <!-- VM Name -->
                        <div class="vm-info-card">
                            <div class="vm-info-icon info-general">
                                <i class="fas fa-desktop"></i>
                            </div>
                            <div class="vm-info-content">
                                <div class="vm-info-label">VM Name</div>
                                <div class="vm-info-value"><?= htmlspecialchars($vmName) ?></div>
                            </div>
                        </div>
                        
                        <!-- VM State -->
                        <div class="vm-info-card">
                            <div class="vm-info-icon <?= $stateClass ?>">
                                <i class="<?= $stateIcon ?>"></i>
                            </div>
                            <div class="vm-info-content">
                                <div class="vm-info-label">State</div>
                                <div class="vm-info-value status-text <?= $stateClass ?>"><?= htmlspecialchars($vmState) ?></div>
                            </div>
                        </div>
                        
                        <!-- VM IP -->
                        <div class="vm-info-card">
                            <div class="vm-info-icon info-network">
                                <i class="fas fa-network-wired"></i>
                            </div>
                            <div class="vm-info-content">
                                <div class="vm-info-label">IP Address</div>
                                <div class="vm-info-value"><?= htmlspecialchars($vmIP) ?></div>
                            </div>
                        </div>
                        
                        <!-- VM Snapshots -->
                        <div class="vm-info-card">
                            <div class="vm-info-icon info-general">
                                <i class="fas fa-camera"></i>
                            </div>
                            <div class="vm-info-content">
                                <div class="vm-info-label">Snapshots</div>
                                <div class="vm-info-value"><?= htmlspecialchars($vmSnapshots) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="actions-section">
                        <h1 class="title"><i class="fas fa-play-circle"></i> Actions</h1>
                        <button id="restoreButton" onclick="confirmAction('restore', 'Restore Windows VM snapshot', 'This will restore the VM to its last snapshot. Any unsaved work will be lost.')">
                            <i class="fas fa-undo"></i> Restore Windows VM snapshot
                        </button>
                        <button id="powerOffButton" onclick="confirmAction('poweroff', 'Power Off VM', 'This will forcefully shut down the virtual machine. Make sure to save any work first.')">
                            <i class="fas fa-power-off"></i> Power Off VM
                        </button>
                        <button id="startVmButton" onclick="confirmAction('start', 'Start VM Headless', 'This will start the virtual machine in headless mode (no GUI).')">
                            <i class="fas fa-play"></i> Start VM Headless
                        </button>
                        <button id="restartWinlogbeatButton" onclick="confirmAction('restart', 'Restart Winlogbeat Service', 'This will restart the Winlogbeat service on the VM.')">
                            <i class="fas fa-sync-alt"></i> Restart Winlogbeat Service
                        </button>
                    </div>
                    
                    <!-- Antivirus Toggle -->
                    <h1 class="title"><i class="fas fa-shield-virus"></i> Antivirus</h1>
                    <div style="display: flex; align-items: center; margin: 15px 0;">
                        <label class="switch">
                            <input type="checkbox" id="antivirusSwitch" checked>
                            <span class="slider round"></span>
                        </label>
                        <span id="antivirusStatusLabel">Antivirus Status: On</span>
                    </div>

                    <!-- Forensic Information -->
                    <h1 class="title"><i class="fas fa-search-plus"></i> Forensic Acquisition</h1>
                    <button class="forensic-button" id="memoryAcquisitionButton" onclick="memoryAcquisition()">
                        <i class="fas fa-memory"></i> Memory Acquisition
                    </button>
                    <button class="forensic-button" id="diskAcquisitionButton" onclick="diskAcquisition()">
                        <i class="fas fa-hdd"></i> Disk Acquisition
                    </button>
                    <div id="acquisitionStatus"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modale de confirmation d'action -->
<div id="actionConfirmModal" class="action-confirm-modal">
    <div class="action-confirm-modal-content">
        <div class="action-confirm-modal-header">
            <i class="fas fa-exclamation-triangle action-warning-icon"></i>
            <h3 id="confirmActionTitle">Confirm Action</h3>
        </div>
        <div class="action-confirm-modal-body">
            <p id="confirmActionMessage">Are you sure you want to perform this action?</p>
            <p class="action-warning-text">Please confirm before proceeding.</p>
        </div>
        <div class="action-confirm-modal-actions">
            <button type="button" class="btn btn-cancel" id="cancelAction">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-confirm" id="confirmActionBtn">
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

<!-- Toast Notifications Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
// Variables globales pour la modal
let currentAction = null;

// Toast Notification System
function createToast(type, title, message, duration = 5000) {
    const container = document.getElementById('toastContainer');
    const toastId = 'toast-' + Date.now();
    
    const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        info: 'fas fa-info-circle',
        warning: 'fas fa-exclamation-triangle'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.id = toastId;
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="${icons[type]}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="removeToast('${toastId}')">
            <i class="fas fa-times"></i>
        </button>
        <div class="toast-progress"></div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('toast-show');
    }, 100);
    
    setTimeout(() => {
        removeToast(toastId);
    }, duration);
    
    return toastId;
}

function removeToast(toastId) {
    const toast = document.getElementById(toastId);
    if (toast) {
        toast.style.transform = 'translateX(120%)';
        toast.style.opacity = '0';
        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }
}

// Fonction de confirmation générique
function confirmAction(actionType, title, message) {
    currentAction = actionType;
    
    document.getElementById('confirmActionTitle').textContent = title;
    document.getElementById('confirmActionMessage').textContent = message;
    
    const modal = document.getElementById('actionConfirmModal');
    modal.classList.add('modal-show');
}

// Gestionnaires d'événements
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('actionConfirmModal');
    const cancelBtn = document.getElementById('cancelAction');
    const confirmBtn = document.getElementById('confirmActionBtn');
    
    cancelBtn.addEventListener('click', function() {
        modal.classList.remove('modal-show');
        currentAction = null;
    });
    
    confirmBtn.addEventListener('click', function() {
        modal.classList.remove('modal-show');
        
        if (currentAction) {
            executeAction(currentAction);
        }
        
        currentAction = null;
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('modal-show');
            currentAction = null;
        }
    });
    
    // Antivirus Switch Handler
    document.getElementById('antivirusSwitch').addEventListener('change', function() {
        var statusLabel = document.getElementById('antivirusStatusLabel');
        if (this.checked) {
            statusLabel.innerHTML = 'Antivirus Status: On';
            enableAntivirus();
        } else {
            statusLabel.innerHTML = 'Antivirus Status: Off';
            disableAntivirus();
        }
    });
    
    // Progress bars animation
    const bars = document.querySelectorAll('.health-metric div');
    bars.forEach(bar => {
        const percent = bar.textContent.trim();
        bar.style.setProperty('--target-width', percent);
        bar.classList.add('animate-bar');
    });
});

// Fonction pour exécuter les actions
function executeAction(actionType) {
    switch(actionType) {
        case 'restore':
            restoreSnapshot();
            break;
        case 'poweroff':
            powerOffVM();
            break;
        case 'start':
            startVMHeadless();
            break;
        case 'restart':
            restartWinlogbeat();
            break;
        default:
            console.error('Action inconnue:', actionType);
    }
}

// Fonctions d'action avec toasts
function restartWinlogbeat() {
    var button = document.getElementById('restartWinlogbeatButton');
    button.innerHTML = '<i class="fas fa-sync fa-spin"></i> Restarting...';
    button.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/restart_winlogbeat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.message) {
            createToast('success', 'Service Restarted', data.message);
        } else if (data.error) {
            createToast('error', 'Restart Failed', data.error);
        }
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    })
    .catch(error => {
        console.error('Error:', error);
        createToast('error', 'Connection Error', 'Failed to restart Winlogbeat service');
        updateButtons();
    });
}

function restoreSnapshot() {
    var button = document.getElementById('restoreButton');
    button.innerHTML = '<i class="fas fa-sync fa-spin"></i> Restoring...';
    button.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/restore_snapshot', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.message) {
            createToast('success', 'Snapshot Restored', data.message);
        } else if (data.error) {
            createToast('error', 'Restore Failed', data.error);
        }
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    })
    .catch(error => {
        console.error('Error:', error);
        createToast('error', 'Restore Error', 'An error occurred during the snapshot restoration');
        button.innerHTML = '<i class="fas fa-undo"></i> Restore Windows VM snapshot';
        button.disabled = false;
    });
}

function powerOffVM() {
    var button = document.getElementById('powerOffButton');
    button.innerHTML = '<i class="fas fa-sync fa-spin"></i> Powering Off...';
    button.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/poweroff_vm', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.message) {
            createToast('success', 'VM Powered Off', data.message);
        } else if (data.error) {
            createToast('error', 'Power Off Failed', data.error);
        }
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    })
    .catch(error => {
        console.error('Error:', error);
        createToast('error', 'Connection Error', 'Failed to power off the VM');
        updateButtons();
    });
}

function startVMHeadless() {
    var button = document.getElementById('startVmButton');
    button.innerHTML = '<i class="fas fa-sync fa-spin"></i> Starting...';
    button.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/start_vm_headless', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.message) {
            createToast('success', 'VM Started', data.message);
        } else if (data.error) {
            createToast('error', 'Start Failed', data.error);
        }
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    })
    .catch(error => {
        console.error('Error:', error);
        createToast('error', 'Connection Error', 'Failed to start the VM');
        updateButtons();
    });
}

function enableAntivirus() {
    fetch('http://' + window.location.hostname + ':5000/enable_av', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.message) {
            createToast('success', 'Antivirus Enabled', data.message);
        } else if (data.error) {
            createToast('error', 'Enable Failed', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        createToast('error', 'Connection Error', 'Failed to enable antivirus');
    });
}

function disableAntivirus() {
    fetch('http://' + window.location.hostname + ':5000/disable_av', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.message) {
            createToast('success', 'Antivirus Disabled', data.message);
        } else if (data.error) {
            createToast('error', 'Disable Failed', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        createToast('error', 'Connection Error', 'Failed to disable antivirus');
    });
}

function memoryAcquisition() {
    document.getElementById('acquisitionStatus').innerText = 'Memory acquisition in progress...';
    createToast('info', 'Starting Acquisition', 'Memory acquisition in progress...');
    
    fetch('http://' + window.location.hostname + ':5000/forensic_acquisition', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ type: 'memory' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.output) {
            let filename = 'sandbox.dmp';
            createToast('success', 'Memory Acquired', 'Memory dump ready for download');
            downloadFile(filename);
        } else {
            createToast('error', 'Acquisition Failed', data.error || 'Unknown error');
            document.getElementById('acquisitionStatus').innerText = '';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        createToast('error', 'Connection Error', 'Failed to acquire memory dump');
        document.getElementById('acquisitionStatus').innerText = '';
    });
}

function diskAcquisition() {
    document.getElementById('acquisitionStatus').innerText = 'Disk acquisition in progress...';
    createToast('info', 'Starting Acquisition', 'Disk acquisition in progress...');
    
    fetch('http://' + window.location.hostname + ':5000/forensic_acquisition', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ type: 'disk' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.output) {
            let filename = 'sandbox.vdi';
            createToast('success', 'Disk Acquired', 'Disk image ready for download');
            downloadFile(filename);
        } else {
            createToast('error', 'Acquisition Failed', data.error || 'Unknown error');
            document.getElementById('acquisitionStatus').innerText = '';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        createToast('error', 'Connection Error', 'Failed to acquire disk image');
        document.getElementById('acquisitionStatus').innerText = '';
    });
}

function downloadFile(filename) {
    const link = document.createElement('a');
    link.href = 'http://' + window.location.hostname + ':5000/download/' + filename;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    document.getElementById('acquisitionStatus').innerText = 'Acquisition completed. Downloading ' + filename;
}

function updateButtons() {
    document.getElementById('restoreButton').innerHTML = '<i class="fas fa-undo"></i> Restore Windows VM snapshot';
    document.getElementById('restoreButton').disabled = false;
    document.getElementById('powerOffButton').innerHTML = '<i class="fas fa-power-off"></i> Power Off VM';
    document.getElementById('powerOffButton').disabled = false;
    document.getElementById('startVmButton').innerHTML = '<i class="fas fa-play"></i> Start VM Headless';
    document.getElementById('startVmButton').disabled = false;
    document.getElementById('restartWinlogbeatButton').innerHTML = '<i class="fas fa-sync-alt"></i> Restart Winlogbeat Service';
    document.getElementById('restartWinlogbeatButton').disabled = false;
}
</script>

</body>
</html>
