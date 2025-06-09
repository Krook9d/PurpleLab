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
    die("Connection failed: " . pg_last_error());
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
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="MD_image/logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="css/main.css?v=<?= filemtime('css/main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    
<body>



<div class="nav-bar">

        <!-- Add logo to top of nav-bar -->
        <div class="nav-logo">
        <img src="MD_image/logowhiteV3.png" alt="Logo" /> 
    </div>

    <!-- Display software version -->
    <?php include $_SERVER['DOCUMENT_ROOT'].'/scripts/php/version.php'; ?>
        <div class="software-version">
        <?php echo SOFTWARE_VERSION; ?>
    </div>

    <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
        <li><a href="https://<?= $_SERVER['SERVER_ADDR'] ?>:5601" target="_blank"><i class="fas fa-crosshairs"></i> <span>Hunting</span></a></li>
        <li><a href="mittre.php" class="active"><i class="fas fa-book"></i> <span>Mitre Att&ck</span></a></li>
        <li><a href="custom_payloads.php"><i class="fas fa-code"></i> <span>Custom Payloads</span></a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> <span>Malware</span></a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> <span>Sharing</span></a></li>
        <li><a href="sigma.php"><i class="fas fa-shield-alt"></i> <span>Sigma Rules</span></a></li>
        <li><a href="rule_lifecycle.php"><i class="fas fa-cogs"></i> <span>Rule Lifecycle</span></a></li>
        <li><a href="health.php"><i class="fas fa-heartbeat"></i> <span>Health</span></a></li>
           <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@local.com'): ?>
        <li><a href="admin.php"><i class="fas fa-user-shield"></i> <span>Admin</span></a></li>
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
            <a href="#" id="settings-link"><i class="fas fa-cog"></i>Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </button>
    </div>
</div>


<div class="content">


    <div class="mitre-attack-content">
       
        <button id="updateDatabaseBtn" class="update-btn" onclick="updateDatabase()">
            <i id="updateIcon" class="fas fa-sync"></i> Mitre ATT&CK update database
        </button> 
    </div>
    
    <div class="mitre-attack-image">
        <img src="/MD_image/MITRE_ATTACK.png" alt="MITRE ATTACK Framework">
    </div>

<div class="atomic-image">
    <img src="/MD_image/atomic.png" alt="Atomic Image">
</div>


    <div class="search-container-mittre">
        <i class="fas fa-search search-icon"></i>
    <input type="text" id="searchInput" placeholder="Please type the first 5 letters of the ID.." onkeyup="searchFunction()">
    <div id="loadingIcon" class="loading-icon" style="display: none;">
        <img src="/MD_image/loading.gif" alt="Loading...">
    </div>
    <div id="searchResults" class="search-results-mittre"></div>
<table id="searchResultsTable" class="search-results-mittre">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
            </tr>
        </thead>
        <tbody id="searchResultsMitre">
            <!-- Search results will be inserted here -->
        </tbody>
    </table>
</div>

<table id="techniqueDetailsTable" class="details-table" style="display:none;">
    <thead>
        <tr>
            <th>Field</th>
            <th>Value</th>
        </tr>
    </thead>
    <tbody>
        <!-- Details of the technique will be inserted here -->
    </tbody>
</table>

<table id="csvDataTable" class="details-table" style="display:none;">
    <thead>
       
    </thead>
    <tbody>
       
    </tbody>
</table>



</div>

<script>
function searchFunction() {
    var input = document.getElementById('searchInput');
    var filter = input.value.toUpperCase();
    var resultsTable = document.getElementById('searchResultsMitre');
    var detailsTable = document.getElementById('techniqueDetailsTable'); 
    var csvDataTable = document.getElementById('csvDataTable');
    var loadingIcon = document.getElementById('loadingIcon');

    if (filter.length < 5) {
        resultsTable.innerHTML = '';
        detailsTable.style.display = 'none';
        csvDataTable.style.display = 'none';
        document.getElementById('searchResultsTable').style.display = 'none';
        loadingIcon.style.display = 'none';
        return;
    }

    loadingIcon.style.display = 'block';
    var searchTerm = filter.substring(0, 5);

    $.ajax({
        url: '/scripts/php/search_techniques.php',
        type: 'POST',
        dataType: 'json',
        data: { searchTerm: searchTerm },
        success: function(techniques) {
            resultsTable.innerHTML = '';
            techniques.forEach(function(technique) {
                var row = resultsTable.insertRow(-1);
                var cell1 = row.insertCell(0);
                var cell2 = row.insertCell(1);
                cell1.textContent = technique.id;
                cell2.textContent = technique.name;
                row.addEventListener('click', function() {
                    loadTechniqueDetails(technique.id);
                });
            });
            document.getElementById('searchResultsTable').style.display = techniques.length ? 'table' : 'none';
            loadingIcon.style.display = 'none';
        },
        error: function() {
            loadingIcon.style.display = 'none';
        }
    });
}

function loadTechniqueDetails(techniqueId) {
    var loadingIcon = document.getElementById('loadingIcon');
    var detailsTable = document.getElementById('techniqueDetailsTable');
    var resultsTable = document.getElementById('searchResultsTable');
    var csvDataTable = document.getElementById('csvDataTable');
    
    loadingIcon.style.display = 'block';
    detailsTable.style.display = 'none';
    resultsTable.style.display = 'none';
    csvDataTable.style.display = 'none';

    $.ajax({
        url: '/scripts/php/search_techniques.php',
        type: 'POST',
        dataType: 'json',
        data: { id: techniqueId },
        success: function(techniqueDetails) {
            var tbody = detailsTable.getElementsByTagName('tbody')[0];
            tbody.innerHTML = '';
            Object.keys(techniqueDetails).forEach(function(key) {
                if (!['STIX ID', 'domain', 'is sub-technique', 'contributors', 'supports remote', 'relationship citations'].includes(key)) {
                    var row = tbody.insertRow();
                    var cellKey = row.insertCell(0);
                    var cellValue = row.insertCell(1);
                    cellKey.textContent = key;
                    cellValue.textContent = techniqueDetails[key];
                }
            });
            var runTestRow = tbody.insertRow();
            var cellKeyRunTest = runTestRow.insertCell(0);
            var cellValueRunTest = runTestRow.insertCell(1);
            cellKeyRunTest.textContent = 'Run test';
            var runTestButton = document.createElement('button');
            runTestButton.textContent = 'Run Test';
            runTestButton.onclick = function() {
                runTestButton.textContent = 'Running...';
                runTestButton.disabled = true;
                fetch('http://' + window.location.hostname + ':5000/mitre_attack_execution', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: techniqueId })
                })
                .then(response => response.json())
                .then(data => {
                    runTestButton.textContent = data.status === 'success' ? 'Done' : 'Error';
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    runTestButton.textContent = 'Error';
                })
                .finally(() => {
                    runTestButton.disabled = false;
                });
            };
            cellValueRunTest.appendChild(runTestButton);
            detailsTable.style.display = 'table';
            displayCsvData(techniqueId);
        },
        error: function() {
            loadingIcon.style.display = 'none';
        }
    });
}

function displayCsvData(techniqueId) {
    $.ajax({
        url: '/scripts/php/read_csv.php',
        type: 'GET',
        data: { techniqueId: techniqueId },
        success: function(data) {
            var csvDataTable = document.getElementById('csvDataTable');
            var tbody = csvDataTable.getElementsByTagName('tbody')[0];
            var thead = csvDataTable.getElementsByTagName('thead')[0];

            
            thead.innerHTML = '';
            tbody.innerHTML = '';

          
            var headers = ["Tactic", "ID", "Technique Name", "Test", "Test Name", "Test GUID", "Executor Name"];
            var headerRow = document.createElement('tr');
            headers.forEach(function(headerText) {
                var th = document.createElement('th');
                th.textContent = headerText;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);

        
            var th = document.createElement('th');
            th.textContent = 'Action';
            headerRow.appendChild(th);

    
            data.forEach(function(rowData, index) {
                var row = document.createElement('tr');
                rowData.forEach(function(cellData, cellIndex) {
                    var cell = document.createElement('td');
                    cell.textContent = cellData;
                    row.appendChild(cell);
                });


                var runCell = document.createElement('td');
                var runButton = document.createElement('button');
                runButton.textContent = 'Run';
                runButton.addEventListener('click', function() {
                    runTestWithArgument(techniqueId + '-' + rowData[3], this);
                });
                runCell.appendChild(runButton);
                row.appendChild(runCell);

                tbody.appendChild(row);
            });

            csvDataTable.style.display = 'table';
            document.getElementById('loadingIcon').style.display = 'none';
        },
        error: function() {
            console.error('Error loading CSV data');
            document.getElementById('loadingIcon').style.display = 'none';
        }
    });
}

function runTestWithArgument(testArgument) {
 
    runTestButton.textContent = 'Running...';
    runTestButton.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/mitre_attack_execution', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: testArgument })
    })
    .then(response => response.json())
    .then(data => {
        runTestButton.textContent = 'Run';
        runTestButton.disabled = false;
        if(data.status === 'success') {
            alert('Test ' + testArgument + ' completed successfully.');
        } else {
            alert('Test ' + testArgument + ' failed: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error running test:', error);
        runTestButton.textContent = 'Run';
        runTestButton.disabled = false;
        alert('Error running test: ' + error);
    });
}


function runTestWithArgument(testArgument, buttonElement) {
    buttonElement.textContent = 'Running...';
    buttonElement.disabled = true;

    fetch('http://' + window.location.hostname + ':5000/mitre_attack_execution', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: testArgument })
    })
    .then(response => response.json())
    .then(data => {
        buttonElement.textContent = data.status === 'success' ? 'Done' : 'Error';
        buttonElement.disabled = false;
        if(data.status === 'success') {
            alert('Test ' + testArgument + ' completed successfully.');
        } else {
            alert('Test ' + testArgument + ' failed: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error running test:', error);
        buttonElement.textContent = 'Error';
        buttonElement.disabled = false;
    });
}



function updateDatabase() {
    var button = document.getElementById('updateDatabaseBtn');
    var icon = document.getElementById('updateIcon');
    button.textContent = ' Updating';
    button.prepend(icon); 
    icon.classList.add('fa-spin'); 

    // Make an AJAX request to the Flask backend to update the database
    fetch('http://' + window.location.hostname + ':5000/update_mitre_database', { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            button.textContent = ' Done';
            icon.classList.remove('fa-spin'); 
            button.prepend(icon); 
            
        })
        .catch(error => {
            console.error('Error:', error);
            button.textContent = ' Error';
            button.prepend(icon); 
        });
}

</script>

<script>

window.onload = function() {
    document.getElementById('searchResultsTable').style.display = 'none';
    document.getElementById('techniqueDetailsTable').style.display = 'none'; 
};
</script>


</body>
</html>
