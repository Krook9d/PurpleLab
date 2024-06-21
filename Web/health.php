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
    die("Error retrieving user information.");
}

$stmt->close();
$conn->close();
?>

<?php


function isServiceRunning($serviceName) {
    $result = shell_exec("systemctl is-active " . escapeshellarg($serviceName));
    return (strpos($result, "active") !== false);
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
    $load = sys_getloadavg();
    $cpuCoreCount = (int)trim(shell_exec("grep -c processor /proc/cpuinfo")); 
    $cpuUsagePercent = 0;
    if ($cpuCoreCount > 0) {
        $cpuUsagePercent = round($load[0] * 100 / $cpuCoreCount);
    }
    return $cpuUsagePercent;
}



$memory = getServerMemoryUsage();
$disk = getServerDiskUsage();
$cpuUsagePercent = getServerCpuUsage();



$kibanaRunning = isServiceRunning("kibana");
$logstashRunning = isServiceRunning("logstash");
$elasticRunning = isServiceRunning("elasticsearch");
$virtualboxRunning = isServiceRunning("virtualbox");



$memoryUsagePercent = getServerMemoryUsage();
$diskUsagePercent = getServerDiskUsage();



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



  <!-- End Connexion -->

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="MD_image/logowhite.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="css/main.css?v=<?= filemtime('css/main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
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

    <br><br>
<!-- Service Status Section -->
<div class="health-section" id="service-status">
    <h2 class="health-section-title"><i class="fas fa-heartbeat"></i> Service Status</h2>
    
    <div class="health-dashboard">
    <!-- Service Kibana -->
    <div class="health-card">
        <h2><i class="fas fa-search"></i> Kibana</h2>
        <div class="health-status <?= $kibanaRunning ? 'running' : 'stopped' ?>">
            <?= $kibanaRunning ? 'Running' : 'Stopped' ?>
        </div>
    </div>

    <!-- Service Logstash -->
    <div class="health-card">
        <h2><i class="fas fa-stream"></i> Logstash</h2>
        <div class="health-status <?= $logstashRunning ? 'running' : 'stopped' ?>">
            <?= $logstashRunning ? 'Running' : 'Stopped' ?>
        </div>
    </div>

    <!-- Service Elastic -->
    <div class="health-card">
        <h2><i class="fas fa-chart-bar"></i> Elastic</h2>
        <div class="health-status <?= $elasticRunning ? 'running' : 'stopped' ?>">
            <?= $elasticRunning ? 'Running' : 'Stopped' ?>
        </div>
    </div>

    <!-- Service VirtualBox -->
    <div class="health-card">
        <h2><i class="fas fa-desktop"></i> VirtualBox</h2>
        <div class="health-status <?= $virtualboxRunning ? 'running' : 'stopped' ?>">
            <?= $virtualboxRunning ? 'Running' : 'Stopped' ?>
        </div>
    </div>

    <div class="health-card">
        <h2><i class="fas fa-toolbox"></i> Flask Backend</h2>
    <div class="health-status <?= $flaskStatus ?>">
        <?= ucfirst($flaskStatus) ?>
    </div>
</div>    

</div>
<div class="health-section-separator"></div>



<!-- RAM & Disk Usage Section -->
<div class="health-section">
<h2 class="health-section-title"><i class="fas fa-tachometer-alt"></i> RAM & Disk Usage</h2>
  <div class="health-dashboard">
    
<!-- RAM -->
<div class="health-card">
    <h2><i class="fas fa-memory"></i> RAM Usage</h2>
    <div class="health-metric">
        <div style="width: <?= $memory['percent'] ?>%;">
            <?= round($memory['percent'], 2) ?>%
        </div>
    </div>
    <p><?= $memory['used'] ?> GB / <?= $memory['total'] ?> GB</p>
</div>
<!-- Disk space -->
<div class="health-card">
    <h2><i class="fas fa-hdd"></i> Disk Usage</h2>
    <div class="health-metric">
        <div style="width: <?= $disk['percent'] ?>%;">
            <?= $disk['percent'] ?>%
        </div>
    </div>
    <p><?= $disk['used'] ?> GB / <?= $disk['total'] ?> GB</p>
</div>

<!-- CPU Usage -->
<div class="health-card">
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
<div class="health-section-separator"></div>


<!-- VM Status Section -->
<div class="health-section">
    <h2 class="health-section-title"><i class="fas fa-server"></i> VM Management</h2>
    <div class="health-dashboard">
        <div class="health-card-management">
            <div>
                <!-- VM Info -->
                <h3><i class="fas fa-info-circle"></i> VM Information</h3>
                <?php
                foreach ($vmInfo as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $subKey => $subValue) {
                            $formattedLine = formatInfoLine($subKey, $subValue);
                            $formattedLine = str_replace('output:', '', $formattedLine); 
                            echo preg_replace('/([A-Z][a-z]+)/', '<br>$1', $formattedLine);
                        }
                    } else {
                        $formattedLine = formatInfoLine($key, $value);
                        $formattedLine = str_replace('output:', '', $formattedLine); 
                        echo preg_replace('/([A-Z][a-z]+)/', '<br>$1', $formattedLine);
                    }
                }

                function formatInfoLine($key, $value) {
                    $boldTerms = ['sandbox', 'Snapshot1', 'running', 'powered off'];
                    foreach ($boldTerms as $term) {
                        if (strpos($value, $term) !== false) {
                            if ($term == 'running') {
                                $value = str_replace($term, "<strong style='color: green;'>$term</strong>", $value);
                            } elseif ($term == 'powered off') {
                                $value = str_replace($term, "<strong style='color: red;'>$term</strong>", $value);
                            } else {
                                $value = str_replace($term, "<strong style='color: green;'>$term</strong>", $value);
                            }
                        }
                    }

                    if ($key == 'IP') {
                        $value = "<span style='color: blue;'>$value</span>";
                    }

                    return "<div class='info-line'><strong>$key:</strong> $value</div>";
                }
                ?>

                <!-- Actions -->
                <h3><i class="fas fa-cogs"></i> Actions</h3><br>
                <button id="restoreButton" onclick="restoreSnapshot()">Restore Windows VM snapshot</button>
                <button id="powerOffButton" onclick="powerOffVM()">Power Off VM</button>
                <button id="startVmButton" onclick="startVMHeadless()">Start VM Headless</button>
                <button id="restartWinlogbeatButton" onclick="restartWinlogbeat()">Restart Winlogbeat Service</button>

                <!-- Antivirus Toggle -->
                <div><br>
                    <h3><i class="fas fa-shield-alt"></i> Antivirus</h3><br>
                    <label class="switch">
                        <input type="checkbox" id="antivirusSwitch" checked>
                        <span class="slider round"></span>
                    </label>
                    <span id="antivirusStatusLabel"></span>
                </div>

<!-- Forensic Information -->
<div><br>
    <h3><i class="fas fa-search"></i> Forensic Acquisition</h3><br>
    
    <button class="forensic-button" id="memoryAcquisitionButton" onclick="memoryAcquisition()">Memory Acquisition</button><br><br><br>
    <button class="forensic-button" id="diskAcquisitionButton" onclick="diskAcquisition()">Disk Acquisition</button>
    <div id="acquisitionStatus"></div>

</div>

<script>
    function memoryAcquisition() {
        document.getElementById('acquisitionStatus').innerText = 'Memory acquisition in progress...';
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
                downloadFile(filename);
            } else {
                alert('Error: ' + data.error);
                document.getElementById('acquisitionStatus').innerText = '';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('acquisitionStatus').innerText = '';
        });
    }

    function diskAcquisition() {
        document.getElementById('acquisitionStatus').innerText = 'Disk acquisition in progress...';
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
                downloadFile(filename);
            } else {
                alert('Error: ' + data.error);
                document.getElementById('acquisitionStatus').innerText = '';
            }
        })
        .catch(error => {
            console.error('Error:', error);
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
</script>

               


            </div>
        </div>
    </div>
</div>


<script>

function restartWinlogbeat() {
    var button = document.getElementById('restartWinlogbeatButton');
    button.innerHTML = 'Restarting...';
    button.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/restart_winlogbeat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(handleResponse)
    .catch(handleError);
}

document.getElementById('antivirusSwitch').addEventListener('change', function() {
    var statusLabel = document.getElementById('antivirusStatusLabel');
    if (this.checked) {
        statusLabel.innerHTML = 'Antivirus Status: On';
        // Call function to enable antivirus
        enableAntivirus();
    } else {
        statusLabel.innerHTML = 'Antivirus Status: Off';
        // Call function to disable antivirus
        disableAntivirus();
    }
});

function enableAntivirus() {
    fetch('http://' + window.location.hostname + ':5000/enable_av', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(handleResponse)
    .catch(handleError);
}

function disableAntivirus() {
    fetch('http://' + window.location.hostname + ':5000/disable_av', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(handleResponse)
    .catch(handleError);
}

function restoreSnapshot() {
    var button = document.getElementById('restoreButton');
    button.innerHTML = 'Restoring...';
    button.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/restore_snapshot', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        console.log(response); 
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        console.log(data); 
        if (data.message) {
            alert(data.message);
        } else if (data.error) {
            alert('Erreur: ' + data.error);
        }
        button.innerHTML = 'Restore Windows VM snapshot';
        button.disabled = false;
    })
    .catch((error) => {
        console.error('Error:', error);
        alert('An error occurred during the snapshot restoration.');
        button.innerHTML = 'Restore Windows VM snapshot';
        button.disabled = false;
    });
}

function powerOffVM() {
    var button = document.getElementById('powerOffButton');
    button.innerHTML = 'Powering Off...';
    button.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/poweroff_vm', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(handleResponse)
    .catch(handleError);
}

function startVMHeadless() {
    var button = document.getElementById('startVmButton');
    button.innerHTML = 'Starting...';
    button.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/start_vm_headless', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(handleResponse)
    .catch(handleError);
}

function handleResponse(response) {
    // Common response handling
    console.log(response);
    if (!response.ok) {
        throw new Error('Network response was not ok: ' + response.statusText);
    }
    return response.json().then(data => {
        console.log(data);
        if (data.message) {
            alert(data.message);
        } else if (data.error) {
            alert('Error: ' + data.error);
        }
        updateButtons();
    });
}




function handleError(error) {
    // Common error handling
    console.error('Error:', error);
    alert('An error occurred.');
    updateButtons();
}

function updateButtons() {
    document.getElementById('restoreButton').innerHTML = 'Restore Windows VM snapshot';
    document.getElementById('restoreButton').disabled = false;
    document.getElementById('powerOffButton').innerHTML = 'Power Off VM';
    document.getElementById('powerOffButton').disabled = false;
    document.getElementById('startVmButton').innerHTML = 'Start VM Headless';
    document.getElementById('startVmButton').disabled = false;
}
</script>



  
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

<script>
document.addEventListener('DOMContentLoaded', (event) => {
   
    const bars = document.querySelectorAll('.health-metric div');

    bars.forEach(bar => {
       
        const percent = bar.textContent.trim();

       
        bar.style.setProperty('--target-width', percent);

       
        bar.classList.add('animate-bar');
    });
});
</script>


</body>
</html>
