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
    die("The user information retrieval encountered an error.");
}

$stmt->close();
$conn->close();
?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="styles.css?v=5.3" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    
<body>



<div class="nav-bar">

        <!-- Add logo to top of nav-bar -->
        <div class="nav-logo">
        <img src="logo.png" alt="Logo" /> 
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
        <li><a href="health.php"><i class="fas fa-heartbeat"></i> Health</a></li>
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
            <a href="#" id="settings-link">Settings</a>
                <a href="logout.php">Logout</a>
            </div>
        </button>
    </div>
</div>


<div class="content">


    <div class="mitre-attack-content">
        <h1>Mitre ATT&CK FRAMEWORK 🛡️</h1>
        <button id="updateDatabaseBtn" class="update-btn" onclick="updateDatabase()">
            <i id="updateIcon" class="fas fa-sync"></i> Mitre ATT&CK update database
        </button> 
    </div>
    
    <div class="mitre-attack-image">
        <img src="MITRE_ATTACK.png" alt="MITRE ATTACK Framework">
    </div>

<div class="atomic-image">
    <img src="atomic.png" alt="Atomic Image">
</div>


    <div class="search-container-mittre">
        <i class="fas fa-search search-icon"></i>
    <input type="text" id="searchInput" placeholder="Please type the first 5 letters of the ID.." onkeyup="searchFunction()">
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




</div>

<script>
function searchFunction() {
    var input = document.getElementById('searchInput');
    var filter = input.value.toUpperCase();
    var resultsTable = document.getElementById('searchResultsMitre');
    var detailsTable = document.getElementById('techniqueDetailsTable'); 


    if (filter.length < 5) {
        resultsTable.innerHTML = ''; 
        detailsTable.style.display = 'none'; 
        document.getElementById('searchResultsTable').style.display = 'none'; 
        return;
    }

    
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

            // Display results table if techniques are present
            document.getElementById('searchResultsTable').style.display = techniques.length ? 'table' : 'none';
        }
    });
}



function loadTechniqueDetails(techniqueId) {
    $.ajax({
        url: '/scripts/php/search_techniques.php',
        type: 'POST',
        dataType: 'json',
        data: { id: techniqueId },
        success: function(techniqueDetails) {
            
            document.getElementById('searchResultsTable').style.display = 'none';

            
            var detailsTable = document.getElementById('techniqueDetailsTable');
            var tbody = detailsTable.getElementsByTagName('tbody')[0];
            tbody.innerHTML = ''; 

            
            Object.keys(techniqueDetails).forEach(function(key, index) {
                
                if (!['STIX ID', 'domain', 'is sub-technique', 'contributors', 'supports remote', 'relationship citations'].includes(key)) {
                    var row = tbody.insertRow();
                    var cellKey = row.insertCell(0);
                    var cellValue = row.insertCell(1);
                    cellKey.textContent = key;
                    cellValue.textContent = techniqueDetails[key];
                }
            });


            //  add the line "Run test".
            var tbody = document.getElementById('techniqueDetailsTable').getElementsByTagName('tbody')[0];
            var runTestRow = tbody.insertRow();
            var cellKeyRunTest = runTestRow.insertCell(0);
            var cellValueRunTest = runTestRow.insertCell(1);
            cellKeyRunTest.textContent = 'Run test';

            // Create the "Run Test" button
        var runTestButton = document.createElement('button');
        runTestButton.textContent = 'Run Test';
        runTestButton.onclick = function() {
            console.log('Running test for technique ID:', techniqueId);

            // Modify button status and appearance during command execution
            runTestButton.textContent = 'Running...';
            runTestButton.disabled = true;

            // Call the Flask function with the technical ID
            fetch('http://' + window.location.hostname + ':5000/mitre_attack_execution', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: techniqueId })
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    runTestButton.textContent = 'Done';
                } else {
                    runTestButton.textContent = 'Error';
                    console.error('Error:', data.message);
                }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            runTestButton.textContent = 'Error';
        })
        .finally(() => {
            runTestButton.disabled = false;
        });
    };

        // Add the button to the "Value" cell
        cellValueRunTest.appendChild(runTestButton);

            // Show details table
            detailsTable.style.display = 'table';
        }
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
