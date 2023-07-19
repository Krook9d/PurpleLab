<?php
session_start();

if (!isset($_SESSION['email'])) {
    header('Location: blank.html');
    exit();
}

$conn = new mysqli('localhost', 'root', 'root', 'myDatabase');

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
    die("Erreur lors de la récupération des informations de l'utilisateur.");
}

$stmt->close();
$conn->close();
?>




<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $searchQuery = $_POST['search'] ?? '';
    $filePath = 'C:\xampp\htdocs\Mittre\Technic\list.txt';
    $results = [];

    $fileContent = file_get_contents($filePath);
    $lines = explode(PHP_EOL, $fileContent);
    
    foreach ($lines as $line) {
        if (stripos($line, $searchQuery) !== false) {
            $results[] = $line;
        }
    }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogHunter</title>
    <link rel="stylesheet" href="styles.css?v=3.4" >
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>

 html,

.container {
  
  text-align: center;
  color: #2c3e50;
  width: 100%;
  height: 100%; /* Changez ceci pour occuper toute la hauteur de la fenêtre */
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-top: -50px; /* Ajoutez cette ligne pour déplacer le conteneur vers le haut */
}


.finder__outer {
  display: flex;
  width: 400px;
  padding: 1.5rem 2rem;
  border-radius: 50px;
  box-shadow: inset 10px 10px 15px -10px #c3c3c3,
    inset -10px -10px 15px -10px #ffffff;
}


.finder__input {
  height: calc(100% + 3rem);
  border: none;
  background-color: transparent;
  outline: none;
  font-size: 1.5rem;
  letter-spacing: 0.75px;
}

.finder__icon {
  width: 40px;
  height: 40px;
  margin-right: 1rem;
  transition: all 0.2s;
  box-shadow: inset 0 0 0 20px #292929;
  border-radius: 50%;
  position: relative;

  &:after,
  &:before {
    display: block;
    content: "";
    position: absolute;
    transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
  }

  &:after {
    width: 10px;
    height: 10px;
    background-color: #292929;
    border: 3px solid #f6f5f0;
    top: 50%;
    position: absolute;
    transform: translateY(-50%);
    left: 0px;
    right: 0;
    margin: auto;
    border-radius: 50%;

    @at-root .active & {
      border-width: 10px;
      background-color: #f6f5f0;
    }
  }

  &:before {
    width: 4px;
    height: 13px;
    background-color: #f6f5f0;
    top: 50%;
    left: 20px;
    transform: rotateZ(45deg) translate(-50%, 0);
    transform-origin: 0 0;
    border-radius: 4px;

    @at-root .active & {
      background-color: #292929;
      width: 6px;
      transform: rotateZ(45deg) translate(-50%, 25px);
    }
  }

  @at-root .processing & {
    transform-origin: 50%;
    animation: spinner 0.3s linear infinite;
    animation-delay: 0.5s;
  }

  @at-root .active & {
    transform: translateY(-5px);
  }
}

@keyframes spinner {
  0% {
    transform: rotateZ(45deg);
  }
  100% {
    transform: rotateZ(405deg);
  }
}

/* Added CSS for buttons */
#search-results {
    
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    margin-top: 20px; /* Adjust according to your design preference */
}
#search-results button {
    
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    background: #3498db;
    color: #fff;
    font-size: 1.2em;
    transition: background 0.3s ease;
}
#search-results button:hover {
    background: #2980b9;
    cursor: pointer;
}
table {
    border-collapse: collapse; /* Collapse borders */
    margin: 0 auto; /* Center table on page */
    width: 80%; /* Adjust to your preference */
}

table, th, td {
    border: 1px solid #000000; /* Add borders to table and cells */
}

th, td {
    padding: 10px; /* Add some padding to cells */
}

th {
    background-color: #3498db; /* Color header cells */
    color: #ffffff;
}

.run-test-button {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    background: #28a745; /* Bootstrap success green */
    color: #fff;
    font-size: 1.2em;
    transition: background 0.3s ease;
    cursor: pointer;
}

.run-test-button:hover {
    background: #218838; /* Darker green */
}


    </style>
    
<body>

<div class="nav-bar">
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="mittre.php">Mitre Att&ck</a></li>
            <li><a href="malware.php">Malware</a></li>
            <li><a href="usecase.php">UseCase</a></li>
            <li><a href="sharing.php">Sharing</a></li>
        </ul>
    </div>

    <div class="user-info-bar">
    <div class="avatar-info">
        <img src="<?= $avatar ?>" alt="Avatar">
        <button class="user-button">
            <span><?= $first_name ?> <?= $last_name ?></span>
            <div class="dropdown-content">
            <a href="#" id="settings-link">Paramètres</a>
                <a href="logout.php">Déconnexion</a>
            </div>
        </button>
    </div>
</div>

<div class="finder__outer">
    <div class="finder__icon"></div>
    <input id="search-input" class="finder__input" type="text" name="q" placeholder="Search test..." />
</div>
        
   
    <div id="search-results">
        <?php
            foreach ($results as $testName) {
                echo '<button class="test-button" onclick="fetchTest(\'' . $testName . '\')">' . $testName . '</button>';
            }
        ?>
    </div>
    <br/><br/>

    <div id="test-list" style="display:none">
        <table id="test-details-table">
            <thead>
                <tr>
                    <th>Test Details</th>
                </tr>
            </thead>
            <tbody id="test-details-body">
            </tbody>
        </table>
    </div>

    <div id="detailed-test-list" style="display:none">
        <table id="detailed-test-details-table">
            <thead>
                <tr>
                    <th>Detailed Test Details</th>
                </tr>
            </thead>
            <tbody id="detailed-test-details-body">
            </tbody>
        </table>
    </div>

    <script>
        var lastTestNameClicked = null;
        var lastTestDetailClicked = null;

        $('.test-button').hide();

        $('#search-input').on('input', function() {
            var query = $(this).val().toLowerCase();

            $('.test-button').each(function() {
                var testName = $(this).text().toLowerCase();
                if (testName.indexOf(query) !== -1 && query.length >= 5) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });

            $('#test-details-body').empty();
            $('#test-list').hide();
            $('#detailed-test-details-body').empty();
            $('#detailed-test-list').hide();
        });

        function fetchTest(testName) {
            if (lastTestNameClicked === testName) {
                $('#test-list').toggle();
            } else {
                var url = 'http://192.168.1.79:8080/Mittre/Technic/' + testName + '/' + testName + '.md';

                $.get(url, function(data) {
                    var start = data.indexOf('## Atomic Tests') + '## Atomic Tests'.length;
                    var end = data.indexOf('<br/>', start);
                    var testListContent = data.substring(start, end).trim();

                    var testDetails = testListContent.split('\n');
                    $('#test-details-body').empty();

                    $.each(testDetails, function(i, detail) {
                        var bracketStart = detail.indexOf('[');
                        var bracketEnd = detail.indexOf(']');
                        if (bracketStart !== -1 && bracketEnd !== -1) {
                            var bracketContent = detail.substring(bracketStart + 1, bracketEnd);
                            var row = $('<tr>').append($('<td>').text(bracketContent).on('click', function() {
                                fetchDetailedTest(bracketContent, data);
                            }));
                            $('#test-details-body').append(row);
                        }
                    });

                    $('#test-list').show();

                    // Clear the detailed test details
                    $('#detailed-test-details-body').empty();
                    $('#detailed-test-list').hide();
                    lastTestDetailClicked = null;
                }).fail(function() {
                    console.error('Failed to fetch test data.');
                });

                lastTestNameClicked = testName;
            }
        }

        function fetchDetailedTest(testDetail, data) {
    if (lastTestDetailClicked === testDetail) {
        $('#detailed-test-list').toggle();
    } else {
        var start = data.indexOf('## ' + testDetail);
        var end = data.indexOf('<br/>', start);
        var testDetailContent = data.substring(start, end).trim();

        $('#detailed-test-details-body').empty();
        var detailRows = testDetailContent.split('\n');

        var currentTitle = '';
        var currentContent = '';

        $.each(detailRows, function(i, detailRowContent) {
            if (detailRowContent.startsWith('## ') ||
                detailRowContent.startsWith('#### ') ||
                detailRowContent.startsWith('##### ')) {
                if (currentTitle !== '') {
                    // If there is a current title, add a row for it and its content
                    var detailRow = $('<tr>').append($('<td>').text(currentTitle)).append($('<td>').text(currentContent));
                    $('#detailed-test-details-body').append(detailRow);
                }
                // Set the new title and clear the content
                currentTitle = detailRowContent;
                currentContent = '';
            } else if (detailRowContent.startsWith('**Supported Platforms:**') || detailRowContent.startsWith('**auto_generated_guid:**')) {
                var splittedRowContent = detailRowContent.split('**');
                currentTitle = splittedRowContent[1];
                currentContent = splittedRowContent[2].trim();
                var detailRow = $('<tr>').append($('<td>').text(currentTitle)).append($('<td>').text(currentContent));
                $('#detailed-test-details-body').append(detailRow);
                currentTitle = '';
                currentContent = '';
            } else {
                // Add the line to the current content
                currentContent += detailRowContent + '\n';
            }
        });

        // Add the last title and its content
        if (currentTitle !== '') {
            var detailRow = $('<tr>').append($('<td>').text(currentTitle)).append($('<td>').text(currentContent));
            $('#detailed-test-details-body').append(detailRow);
        }

            // Add the Run Test button
    var runTestButtonRow = $('<tr>').append($('<td>').attr('colspan', 2).append($('<button>').addClass('run-test-button').text('Run Test').on('click', function() {
        // Add your button action here
        console.log('Run Test button clicked.');
    })));
    $('#detailed-test-details-body').append(runTestButtonRow);

        $('#detailed-test-list').show();
        lastTestDetailClicked = testDetail;
    }
}



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
</body>
</html>
