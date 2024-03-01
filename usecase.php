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
<!-- End Connexion -->

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="MD_image/logowhite.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab</title>
    <link rel="stylesheet" href="styles.css?v=5.3" >
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
            <button id="user-button" class="user-button">
            <span><?= $first_name ?> <?= $last_name ?></span>
                <div class="dropdown-content">
                <a href="usecase.php" id="settings-link">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </button>
        </div>
    </div>

<div class="content">
<div class="usecase-content">
    <h1 class="usecase-h1">🧩 Usage Case</h1>
    <p class="usecase-p">On this page, you will find several use cases to play out, each of which triggers a sequence of actions that you will need to analyze and reconstruct in order to determine what actually happened on the machine</p>
    
    <select id="useCaseSelect" class="usecase-select">
        <option value="">Select a Use Case</option>
        <option value="useCase1">Use Case 1</option>
        <option value="useCase2">Use Case 2</option>
        <!-- ... other options ... -->
    </select>

    <div id="buttons" class="usecase-buttons" style="display: none;">
        <button id="runUseCase" class="usecase-button">Run Use Case</button>
        <button id="details" class="usecase-button">Detail</button>
    </div>

    <div id="useCaseDetails" class="usecase-details" style="display: none;">
    <h3 class="usecase-h1">Use Case Detail</h3>
    <table class="usecase-table">
    <tr>
        <th>Scenario 📜</th>
        <td id="scenario"></td>
    </tr>
    <tr>
        <th>Actions Performed 🔨</th>
        <td id="actions"></td>
    </tr>
    <tr>
        <th>IOC 🚨</th>
        <td id="ioc"></td>
    </tr>
</table>

</div>

</div>

<script>
$(document).ready(function() {
    var detailsShown = false; // Status to see if details are displayed

    $('#useCaseSelect').change(function() {
        var selectedValue = $(this).val();
        if (selectedValue != '') {
            $('#buttons').show();
            $('#useCaseDetails').css({'opacity': 0, 'display': 'none'}); // Reset the detail box
            detailsShown = false;
            $('#scenario').html('');
            $('#actions').html('');
            $('#ioc').html('');
            $(this).find('option[value=""]').remove(); // Remove placeholder after selection
        } else {
            $('#buttons').hide();
        }
    });

    $('#details').click(function() {
        var useCaseId = $('#useCaseSelect').val();

        if (detailsShown) {
            // Hide with animation
            $('#useCaseDetails').animate({
                opacity: 0
            }, 500, function() {
                $(this).css('display', 'none');
            });
            detailsShown = false;
        } else {
                
                var details = {
                    'useCase1': {
    "scenario": "This use case involves a Windows executable derived from a Python script, which serves a malicious purpose.<br>The script is engineered to surreptitiously encrypt files within a specified user directory using the Fernet cryptography library.<br>It stealthily scans the specified directory, encrypting each file that matches defined extensions, and subsequently replaces the original files with their encrypted counterparts.",
    "actions": "🔑 1. Covert generation of a Fernet encryption key, used for the encryption of files without user consent.<br>🔍 2. Silent traversal and scanning of the specified directory for files with extensions '.xlsx', '.pdf', '.doc', and '.docx'.<br>🔐 3. Encryption of each identified file, followed by the creation of an encrypted version with the '.encrypted' extension.<br>🗑️ 4. Removal of the original files, effectively rendering the data inaccessible to the user.",
    "ioc": "Unexpected appearance of '.encrypted' files in user directories, replacing commonly used file formats.<br>Sudden disappearance or inaccessibility of original files after the execution of the executable.<br>Presence of a new, unknown executable likely generated from a Python script, with file encryption capabilities.<br>User notifications or prompts related to encryption completion, coupled with demands or instructions, often indicative of ransomware."
}
,
                    'useCase2': {
    "scenario": "This use case revolves around an executable derived from a Python script designed for data aggregation and exfiltration.<br>The script systematically scans specified directories for files with common extensions, compresses them into a ZIP archive, and then uploads this archive to an external server.",
    "actions": "1. Scanning of directories for target files. 2. Compression of files into a ZIP archive. 3. Automatic upload of the archive to a remote server. 4. Execution of these actions silently without user consent.",
    "ioc": "Creation of a ZIP archive in the user's directory.<br>Network activity related to file upload.<br>Presence of an executable performing unauthorized actions.<br>Unusual disappearance of disk space."
}

                    // ... other use cases ...
                };

                if (useCaseId && details[useCaseId]) {
                $('#scenario').html(details[useCaseId].scenario);
                $('#actions').html(details[useCaseId].actions);
                $('#ioc').html(details[useCaseId].ioc);
                
                // Show with animation
                $('#useCaseDetails').css('display', 'block').animate({
                    opacity: 1
                }, 500);
                detailsShown = true;
            }
        }
    });
});
</script>

<script>
    $(document).ready(function() {
        $('#runUseCase').click(function() {
            var useCaseId = $('#useCaseSelect').val();
            if (useCaseId) {
               
                $(this).html('<i class="fa fa-spinner fa-spin"></i> In Progress...');

                
                $.ajax({
                    url: 'http://' + window.location.hostname + ':5000/execute_usecase',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ use_case_name: useCaseId }),
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                           
                            $('#runUseCase').html('Done');
                        } else {
                            alert('Error: ' + response.error);
                            $('#runUseCase').html('Run Use Case');
                        }

                        // Reset button after timeout
                        setTimeout(function() {
                            $('#runUseCase').html('Run Use Case');
                        }, 3000); // 3 secondes
                    },
                    error: function(xhr, status, error) {
                        $('#runUseCase').html('Run Use Case');
                        alert('An error occurred: ' + error + useCaseId);
                    }
                });
            }
        });
    });
</script>

</div>
        
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

</body>
</html>
