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

$mittreAiConfigured = false;
$mittreAiMetaPath = __DIR__ . '/mitre_ai/config.json';
if (is_readable($mittreAiMetaPath)) {
    $mittreAiMeta = json_decode((string) file_get_contents($mittreAiMetaPath), true);
    $mittreAiConfigured = is_array($mittreAiMeta) && !empty($mittreAiMeta['configured']);
}
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
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</head>
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

<?php if ($mittreAiConfigured): ?>
<div id="mittre-ai-widget" class="mittre-ai-widget">
    <div id="mittre-ai-panel" class="mittre-ai-panel" style="display:none;" aria-hidden="true">
        <div class="mittre-ai-drag-handle mittre-ai-panel-head">
            <span class="mittre-ai-panel-title"><i class="fas fa-magic"></i> MITRE scenario assistant</span>
            <button type="button" class="mittre-ai-close" id="mittreAiCloseBtn" aria-label="Close chat">&times;</button>
        </div>
        <div id="mittreAiMessages" class="mittre-ai-messages"></div>
        <div class="mittre-ai-input-row">
            <textarea id="mittreAiInput" class="mittre-ai-input" rows="3" placeholder="Describe a scenario (e.g. Lumma stealer, ransomware encryption phase...)"></textarea>
            <button type="button" id="mittreAiSend" class="mittre-ai-send">Send</button>
        </div>
    </div>
    <div class="mittre-ai-fab-row">
        <button type="button" id="mittre-ai-fab" class="mittre-ai-fab mittre-ai-drag-handle" aria-expanded="false" aria-controls="mittre-ai-panel" title="MITRE AI assistant">
            <img class="mittre-ai-fab-logo" src="/MD_image/PurplelabIA.png" alt="Purplelab AI" draggable="false">
        </button>
    </div>
</div>
<?php endif; ?>

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
            if (window.history && window.history.replaceState) {
                var u = new URL(window.location.href);
                u.searchParams.set('technique', techniqueId);
                window.history.replaceState(null, '', u.toString());
            }
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
    var params = new URLSearchParams(window.location.search);
    var tid = params.get('technique');
    if (tid && /^T\d{4}(?:\.\d{3})?$/.test(tid)) {
        document.getElementById('searchInput').value = tid.substring(0, Math.min(5, tid.length));
        loadTechniqueDetails(tid);
    }
};
</script>

<?php if ($mittreAiConfigured): ?>
<script>
(function() {
    var chatHistory = [];

    if (window.marked) {
        marked.setOptions({ gfm: true, breaks: true });
    }

    function renderAssistantHtml(text) {
        var withLinks = text.replace(/\b(T\d{4}(?:\.\d{3})?)\b/g, function(_, id) {
            return '[' + id + '](mittre.php?technique=' + encodeURIComponent(id) + ')';
        });
        if (window.marked) {
            var html = marked.parse(withLinks);
            return html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
        }
        return $('<div>').text(text).html().replace(/\n/g, '<br>');
    }

    function appendBubble(role, htmlOrText, isHtml) {
        var $box = $('#mittreAiMessages');
        var $b = $('<div class="mittre-ai-bubble mittre-ai-bubble-' + role + '"></div>');
        if (isHtml) {
            $b.html(htmlOrText);
        } else {
            $b.text(htmlOrText);
        }
        $box.append($b);
        $box.scrollTop($box[0].scrollHeight);
    }

    function setLoading(on) {
        $('#mittreAiSend').prop('disabled', on);
        $('#mittreAiInput').prop('disabled', on);
    }

    function sendMitreAi() {
        var text = ($('#mittreAiInput').val() || '').trim();
        if (!text) return;
        appendBubble('user', text, false);
        $('#mittreAiInput').val('');
        chatHistory.push({ role: 'user', content: text });
        setLoading(true);
        var $pending = $(
            '<div class="mittre-ai-bubble mittre-ai-bubble-assistant mittre-ai-thinking-bubble">' +
                '<div class="mittre-ai-thinking">' +
                    '<i class="fas fa-spinner fa-spin"></i>' +
                    '<span class="mittre-ai-thinking-text">Thinking...</span>' +
                '</div>' +
            '</div>'
        );
        $('#mittreAiMessages').append($pending);
        $('#mittreAiMessages').scrollTop($('#mittreAiMessages')[0].scrollHeight);

        $.ajax({
            url: '/scripts/php/mitre_ai_chat.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ messages: chatHistory }),
            dataType: 'json'
        }).done(function(res) {
            $pending.remove();
            if (res.ok && res.message) {
                chatHistory.push({ role: 'assistant', content: res.message });
                appendBubble('assistant', renderAssistantHtml(res.message), true);
            } else {
                appendBubble('assistant', res.error || 'Request failed.', false);
            }
        }).fail(function(xhr) {
            $pending.remove();
            var msg = 'Request failed.';
            try {
                var j = xhr.responseJSON;
                if (j && j.error) msg = j.error;
            } catch (e) {}
            appendBubble('assistant', msg, false);
        }).always(function() {
            setLoading(false);
        });
    }

    var $widget = $('#mittre-ai-widget');
    var $panel = $('#mittre-ai-panel');
    var resizableInited = false;

    function clampWidgetDrag(ui) {
        var margin = 8;
        var maxLeft = window.innerWidth - $widget.outerWidth() - margin;
        var maxTop = window.innerHeight - $widget.outerHeight() - margin;
        ui.position.left = Math.max(margin, Math.min(ui.position.left, maxLeft));
        ui.position.top = Math.max(margin, Math.min(ui.position.top, maxTop));
    }

    function setDefaultWidgetPosition() {
        var marginRight = 24;
        var marginTop = 70; // leave space for the top UI
        var left = window.innerWidth - $widget.outerWidth() - marginRight;
        $widget.css({
            position: 'fixed',
            zIndex: 100050,
            left: Math.max(8, left) + 'px',
            top: marginTop + 'px',
            right: 'auto',
            bottom: 'auto'
        });
    }

    function initResizableIfNeeded() {
        if (resizableInited) return;
        $panel.resizable({
            handles: 'w,s,sw,se',
            minWidth: 280,
            minHeight: 220,
            resize: function() {
                $('#mittreAiMessages').css({ overflowY: 'auto' });
            },
            stop: function(event, ui) {
                // ui.position is relative to the panel; clamp the widget to viewport.
                var widgetUi = { position: $widget.position() };
                clampWidgetDrag(widgetUi);
                $widget.css({ left: widgetUi.position.left + 'px', top: widgetUi.position.top + 'px' });
            }
        });
        resizableInited = true;
    }

    setDefaultWidgetPosition();

    $(window).on('resize', function() {
        var widgetUi = { position: $widget.position() };
        clampWidgetDrag(widgetUi);
        $widget.css({ left: widgetUi.position.left + 'px', top: widgetUi.position.top + 'px' });
    });

    $widget.draggable({
        handle: '.mittre-ai-drag-handle, .mittre-ai-panel-head',
        scroll: false,
        containment: false,
        drag: function(event, ui) {
            clampWidgetDrag(ui);
        },
        stop: function(event, ui) {
            clampWidgetDrag(ui);
        }
    });

    $('#mittre-ai-fab').on('click', function(e) {
        e.stopPropagation();
        var $p = $('#mittre-ai-panel');
        var open = $p.is(':visible');
        if (open) {
            $p.hide().attr('aria-hidden', 'true');
            $(this).attr('aria-expanded', 'false');
        } else {
            $p.show().attr('aria-hidden', 'false');
            $(this).attr('aria-expanded', 'true');
            initResizableIfNeeded();
        }
    });

    $('#mittreAiCloseBtn').on('click', function() {
        $('#mittre-ai-panel').hide().attr('aria-hidden', 'true');
        $('#mittre-ai-fab').attr('aria-expanded', 'false');
    });

    $('#mittreAiSend').on('click', sendMitreAi);
    $('#mittreAiInput').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMitreAi();
        }
    });

    $(document).on('click', '.mittre-ai-tech-link', function(ev) {
        var href = $(this).attr('href');
        if (!href) return;
        try {
            var u = new URL(href, window.location.origin);
            if (u.pathname.indexOf('mittre.php') === -1) return;
            var t = u.searchParams.get('technique');
            if (!t || !/^T\d{4}(?:\.\d{3})?$/.test(t)) return;
            ev.preventDefault();
            document.getElementById('searchInput').value = t.substring(0, Math.min(5, t.length));
            loadTechniqueDetails(t);
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', u.pathname + u.search);
            }
        } catch (err) {}
    });
})();
</script>
<?php endif; ?>


</body>
</html>
