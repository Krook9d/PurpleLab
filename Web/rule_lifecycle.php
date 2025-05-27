<?php
// start Connection
session_start();

if (!isset($_SESSION['email'])) {
    header('Location: connexion.html');
    exit();
}

// PostgreSQL Connection
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
// End Connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="MD_image/logowhite.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purplelab - Rule Lifecycle</title>
    <link rel="stylesheet" href="css/main.css?v=<?= filemtime('css/main.css') ?>">
    <link rel="stylesheet" href="css/pages/rule-lifecycle.css?v=<?= filemtime('css/pages/rule-lifecycle.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>

<!-- Navigation Bar -->
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
        <li><a href="custom_payloads.php"><i class="fas fa-code"></i> Custom Payloads</a></li>
        <li><a href="malware.php"><i class="fas fa-virus"></i> Malware</a></li>
        <li><a href="simulation.php"><i class="fas fa-project-diagram"></i> Log Simulation</a></li>
        <li><a href="usecase.php"><i class="fas fa-lightbulb"></i> UseCase</a></li>
        <li><a href="sharing.php"><i class="fas fa-pencil-alt"></i> Sharing</a></li>
        <li><a href="sigma.php"><i class="fas fa-shield-alt"></i> Sigma Rules</a></li>
        <li><a href="rule_lifecycle.php" class="active"><i class="fas fa-cogs"></i> Rule Lifecycle</a></li>
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

<!-- User Info Bar -->
<div class="user-info-bar">
    <div class="avatar-info">
        <img src="<?= $avatar ?>" alt="Avatar">
        <button id="user-button" class="user-button">
            <span><?= $first_name ?> <?= $last_name ?></span>
            <div class="dropdown-content">
                <a href="index.php" id="settings-link">Settings</a>
                <a href="logout.php">Logout</a>
            </div>
        </button>
    </div>
</div>

<!-- Main Content -->
<div class="content">
    <div class="rule-lifecycle-wrapper">
        <!-- Onglets navigation -->
        <div class="rlc-tabs">
            <button class="rlc-tab active" data-tab="tab-connectors">Connectors</button>
            <button class="rlc-tab" data-tab="tab-rules">Rules & Payloads</button>
            <button class="rlc-tab" data-tab="tab-execution">Execution & Results</button>
        </div>

        <!-- Contenu des onglets -->
        <div class="rlc-tab-content" id="tab-connectors" style="display: block;">
            <div class="connector-section">
                <h1>Rule Lifecycle Management</h1>
                <p>Connect your SIEM platforms to synchronize and manage your security rules.</p>
            </div>
            <div class="connector-cards-container">
                <!-- Splunk Connector -->
                <div class="connector-card" id="splunk-connector" data-type="splunk">
                    <img src="MD_image/connectors/splunk.png" alt="Splunk" class="connector-icon">
                    <h3 class="connector-title">Splunk Connector</h3>
                    <div class="connector-status" id="splunk-status"></div>
                </div>
                <!-- OpenSearch Connector -->
                <div class="connector-card" id="opensearch-connector" data-type="opensearch">
                    <img src="MD_image/connectors/opensearch.png" alt="OpenSearch" class="connector-icon">
                    <h3 class="connector-title">OpenSearch Connector</h3>
                    <div class="connector-status" id="opensearch-status"></div>
                </div>
            </div>
        </div>
        <div class="rlc-tab-content" id="tab-rules" style="display: none;">
            <div class="rules-section" id="rules-section">
                <div class="rules-header">
                    <button class="btn btn-test" id="sync-rules-btn">Synchronize Rules</button>
                    <span id="last-sync-info" class="last-sync-info">Last synchronization: --</span>
                    <select id="rules-connector-select" class="rules-connector-select">
                        <option value="opensearch">OpenSearch</option>
                        <option value="splunk">Splunk</option>
                    </select>
                </div>
                <div id="rules-table-container" class="rules-table-container"></div>
            </div>
        </div>
        <div class="rlc-tab-content" id="tab-execution" style="display: none;">
            <!-- Section Exécution & Résultats -->
            <div class="execution-section">
                <div class="execution-controls">
                    <label for="execution-connector-select">Connector:</label>
                    <select id="execution-connector-select" class="select-execution">
                        <option value="opensearch">OpenSearch</option>
                        <option value="splunk">Splunk</option>
                    </select>
                    <label for="execution-status-filter">Status:</label>
                    <select id="execution-status-filter" class="select-execution">
                        <option value="all">All</option>
                        <option value="triggered">Triggered</option>
                        <option value="not_triggered">Not triggered</option>
                        <option value="error">Error</option>
                    </select>
                    <label for="execution-time-filter">Time:</label>
                    <select id="execution-time-filter" class="select-execution">
                        <option value="all">All time</option>
                        <option value="24h">Last 24h</option>
                        <option value="7d">Last 7 days</option>
                        <option value="1m">Last month</option>
                        <option value="3m">Last 3 months</option>
                    </select>
                    <button class="btn" id="display-execution-rules">Display Rules</button>
                    <button class="btn btn-test" id="execute-all-payloads">Execute All Payloads</button>
                </div>
                <div id="execution-results-table" class="execution-results-table"></div>
            </div>
        </div>
    </div>

    <!-- Modals existants (inchangés) -->
    <div id="connection-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modal-message"></div>
        </div>
    </div>
    <div id="connectorFormModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 id="modalTitle">Configure Connector</h2>
            <div id="connectorForm"></div>
        </div>
    </div>
    <div id="rulesModal" class="modal">
        <div class="modal-content modal-content-large">
            <span class="close-button">&times;</span>
            <h2 id="rulesModalTitle">Rules</h2>
            <div id="rulesContent" class="rules-container"></div>
        </div>
    </div>
    <!-- Ajout de la modale pour création/édition de payload -->
    <div id="payload-modal" class="modal">
        <div class="modal-content" style="max-width: 700px; width: 90%;">
            <span class="close" id="close-payload-modal">&times;</span>
            <h2 id="payload-modal-title">Create PowerShell Payload</h2>
            <form id="payload-form">
                <input type="hidden" id="payload-id" name="id">
                <input type="hidden" id="payload-rule-id" name="rule_id">
                <div class="form-group">
                    <label for="payload-name">Payload Name</label>
                    <input type="text" id="payload-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="payload-description">Description</label>
                    <textarea id="payload-description" name="description" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label for="payload-code">Payload Code</label>
                    <textarea id="payload-code" name="code" rows="12" required style="font-family: Consolas, Monaco, 'Courier New', monospace; font-size: 14px; line-height: 1.4; padding: 12px; background-color: #1e1e1e; color: #d4d4d4; border: 1px solid #3d3d3d; border-radius: 4px;"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-save" id="save-payload-btn">Save</button>
                </div>
            </form>
        </div>
    </div>
    <div id="toast" class="toast"></div>
</div>

<!-- Side panel pour la configuration du connecteur -->
<div id="connector-sidepanel" class="sidepanel">
    <div class="sidepanel-content">
        <span class="sidepanel-close" id="close-sidepanel">&times;</span>
        <h2 id="sidepanel-title">Connector Configuration</h2>
        <form id="sidepanel-form"></form>
    </div>
</div>

<!-- Modale générique pour l'affichage des résultats d'exécution -->
<div id="generic-modal" class="modal">
    <div class="modal-content modal-content-large">
        <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:10px;">
            <h2 id="generic-modal-title" style="flex:1;text-align:center;margin:0;color:#fff;font-size:24px;text-transform:uppercase;letter-spacing:1px;">Result</h2>
            <button id="close-generic-modal-btn" class="modal-close-btn" style="font-size:24px;background:none;border:none;color:#fff;cursor:pointer;width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:all 0.2s ease;margin-left:10px;">&times;</button>
        </div>
        <div id="generic-modal-body"></div>
    </div>
</div>

<!-- JS pour la navigation par onglets -->
<script>
// --- Déclaration des variables globales en tout début de script ---
let RULES = [];
let PAYLOADS = [];
let LAST_SYNC = null;
let SELECTED_CONNECTOR = 'opensearch';
let rulePayloadMap = {};

// --- Déclaration des fonctions globales ---
// Fonction de rendu du tableau des règles (déplacée au niveau global)
function renderRulesTable() {
    const tableContainer = document.getElementById('rules-table-container');
    
    console.log('Rendering rules table with', RULES.length, 'rules and', PAYLOADS.length, 'payloads');
    console.log('Current rule-payload associations:', rulePayloadMap);
    
    // Si aucune règle, montrer message
    if (!RULES || RULES.length === 0) {
        tableContainer.innerHTML = '<div class="alert alert-info">No rules synchronized for this connector.</div>';
        return;
    }
    
    // Préparer les en-têtes
    const tableHeaders = ['Name', 'Rule ID', 'Type', 'Severity', 'Actions'];
    const payloadHeaders = ['Name', 'Rule ID', 'Type', 'Severity', 'Assigned Payload', 'Actions'];
    
    // Créer le tableau
    let html = '<div class="rules-payloads-header">';
    html += '<button id="refresh-payloads-btn" class="btn btn-secondary">Refresh Payloads</button>';
    html += '<select id="payload-filter" class="payload-filter">';
    html += '<option value="all">All rules</option>';
    html += '<option value="with-payload">Rules with payload</option>';
    html += '<option value="without-payload">Rules without payload</option>';
    html += '</select>';
    html += '</div>';
    html += '<table class="rules-table">';
    
    // En-têtes
    html += '<thead><tr>';
    for (const header of payloadHeaders) {
        html += `<th>${header}</th>`;
    }
    html += '</tr></thead>';
    
    // Corps
    html += '<tbody>';
    
    for (const rule of RULES) {
        const ruleId = rule.id || rule.name || rule.monitor_id || rule.trigger_name || 'Unknown';
        const ruleName = rule.name || rule.trigger_name || rule.monitor_name || ruleId;
        const ruleType = rule.rule_type || rule.type || (rule.monitor_type ? 'Monitor' : 'Trigger') || '-';
        
        html += '<tr class="rule-row">';
        html += `<td class="rule-name">${ruleName}</td>`;
        html += `<td class="rule-id">${ruleId}</td>`;
        html += `<td class="rule-type">${ruleType}</td>`;
        html += `<td class="rule-severity">${rule.severity || '-'}</td>`;
        
        // Cellule pour payload associé
        html += '<td class="rule-payload">';
        html += '<select class="payload-select" data-rule-id="' + ruleId + '">';
        html += '<option value="">-- Select Payload --</option>';
        for (const payload of PAYLOADS) {
            const selected = rulePayloadMap[ruleId] === payload.id ? 'selected' : '';
            html += `<option value="${payload.id}" ${selected}>${payload.name}</option>`;
        }
        html += '</select>';
        html += '</td>';
        
        // Actions
        html += '<td class="rule-actions">';
        html += `<button class="btn btn-small execute-payload" data-rule-id="${ruleId}">Execute Payload</button>`;
        html += `<button class="btn btn-small view-rule" data-rule-id="${ruleId}">View</button>`;
        html += `<button class="btn btn-small create-payload" data-rule-id="${ruleId}">Create Payload</button>`;
        html += '</td>';
        
        html += '</tr>';
    }
    
    html += '</tbody></table>';
    
    // Afficher le tableau
    tableContainer.innerHTML = html;
    
    // Attach event handlers after rendering the table
    document.querySelectorAll('.view-rule').forEach(button => {
        button.addEventListener('click', function() {
            const ruleId = this.getAttribute('data-rule-id');
            const rule = RULES.find(r => (r.id === ruleId || r.name === ruleId || r.monitor_id === ruleId || r.trigger_name === ruleId));
            if (rule) {
                showRuleModal(rule);
            }
        });
    });
    
    // Attacher des gestionnaires d'événements pour les sélecteurs de payload
    document.querySelectorAll('.payload-select').forEach(select => {
        select.addEventListener('change', function() {
            const ruleId = this.getAttribute('data-rule-id');
            const payloadId = this.value;
            console.log(`Associating rule ${ruleId} with payload ${payloadId}`);
            
            // Mettre à jour la map locale
            if (payloadId) {
                rulePayloadMap[ruleId] = payloadId;
            } else {
                delete rulePayloadMap[ruleId];
            }
            
            // Sauvegarder l'association dans la base de données
            saveRulePayloadAssociation(ruleId, payloadId);
        });
    });
    
    document.querySelectorAll('.execute-payload').forEach(button => {
        button.addEventListener('click', function() {
            const ruleId = this.getAttribute('data-rule-id');
            const select = document.querySelector(`.payload-select[data-rule-id="${ruleId}"]`);
            const payloadId = select ? select.value : '';

            if (!payloadId) {
                showToast('Please select a payload for this rule first');
                return;
            }

            // --- Ajout de l'état de chargement sur le bouton ---
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="loading-spinner"></span> Loading...';
            this.disabled = true;
            const btn = this;

            // Exécution du payload sur cette règle
            executePayload(payloadId, ruleId, function() {
                // Callback pour restaurer le bouton
                btn.innerHTML = 'Execute Payload';
                btn.disabled = false;
            });
        });
    });
    
    document.querySelectorAll('.create-payload').forEach(button => {
        button.addEventListener('click', function() {
            const ruleId = this.getAttribute('data-rule-id');
            showPayloadModal(null, ruleId);
        });
    });
    
    // Gérer rafraîchissement des payloads
    const refreshPayloadsBtn = document.getElementById('refresh-payloads-btn');
    if (refreshPayloadsBtn) {
        refreshPayloadsBtn.addEventListener('click', function() {
            fetchPayloads();
            showToast('Payloads refreshed');
        });
    }
}

// Fonction pour sauvegarder l'association règle-payload dans la base de données
function saveRulePayloadAssociation(ruleId, payloadId) {
    console.log(`Sauvegarde de l'association: règle ${ruleId} avec payload ${payloadId}`);
    
    fetch('scripts/php/connector_api.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'save_rule_payload',
            rule_id: ruleId,
            payload_id: payloadId
        })
    })
    .then(r => r.json())
    .then(data => {
        console.log('Réponse de save_rule_payload:', data);
        if (data.success) {
            showToast('Payload association saved');
        } else {
            showToast(data.error || 'Error saving payload association', 'error');
            console.error('Error saving payload association:', data.error);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showToast('Network error while saving association', 'error');
    });
}

// Fonction pour exécuter un payload sur une règle
function executePayload(payloadId, ruleId, onComplete) {
    console.log(`Exécution du payload ${payloadId} sur la règle ${ruleId}`);
    // Récupérer le payload
    fetch('scripts/php/connector_api.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'payload_get',
            id: payloadId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            showToast(data.error, 'error');
            if (onComplete) onComplete();
            return;
        }
        if (!data.payload) {
            showToast('Payload not found', 'error');
            if (onComplete) onComplete();
            return;
        }
        // Récupérer les données de la règle
        const rule = RULES.find(r => (r.id === ruleId || r.name === ruleId || r.monitor_id === ruleId || r.trigger_name === ruleId));
        if (!rule) {
            showToast('Rule not found', 'error');
            if (onComplete) onComplete();
            return;
        }
        // Exécution réelle du code PowerShell via PHP (comme custom_payloads.php)
        const formData = new FormData();
        formData.append('action', 'execute_payload');
        formData.append('content', data.payload.code);
        fetch('scripts/php/connector_api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            // Afficher le résultat dans la modale
            const modalContent = document.createElement('div');
            modalContent.innerHTML = `
                <div class="payload-execution-details">
                    <p><strong>Payload:</strong> ${data.payload.name}</p>
                    <p><strong>Rule:</strong> ${rule.name || ruleId}</p>
                    <pre class="code-block">${data.payload.code}</pre>
                    <div class="execution-result">
                        <h4>Execution Result:</h4>
                        <pre class="result-block">${result.status === 'success' ? result.output : result.error || result.message || 'Unknown error'}</pre>
                    </div>
                </div>
            `;
            const modal = document.getElementById('generic-modal');
            const modalTitle = document.getElementById('generic-modal-title');
            const modalBody = document.getElementById('generic-modal-body');
            if (modal && modalTitle && modalBody) {
                modalTitle.textContent = 'Payload Execution';
                modalBody.innerHTML = '';
                modalBody.appendChild(modalContent);
                modal.style.display = 'block';
            }
            if (onComplete) onComplete();
        })
        .catch(error => {
            console.error('Error executing payload:', error);
            showToast('Error executing payload', 'error');
            if (onComplete) onComplete();
        });
    })
    .catch(error => {
        console.error('Error executing payload:', error);
        showToast('Error executing payload', 'error');
        if (onComplete) onComplete();
    });
}

// Fonction pour afficher les résultats d'exécution dans l'onglet Execution & Results
function renderExecutionResults() {
    console.log('Rendering execution results');
    const container = document.getElementById('execution-results-table');
    if (!container) return;
    container.innerHTML = '<div class="no-results">No rules match your criteria.</div>';
    // Ajout d'un gestionnaire pour le bouton d'exécution de tous les payloads
    const executeAllBtn = document.getElementById('execute-all-payloads');
    if (executeAllBtn) {
        executeAllBtn.onclick = function() {
            executeAllPayloadsForDisplayedRules();
        };
    }
}

function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.textContent = msg;
    toast.className = 'toast ' + type;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 2500);
}

document.addEventListener('DOMContentLoaded', function() {
    // Navigation onglets
    document.querySelectorAll('.rlc-tab').forEach(function(tabBtn) {
        tabBtn.addEventListener('click', function() {
            document.querySelectorAll('.rlc-tab').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.rlc-tab-content').forEach(tab => tab.style.display = 'none');
            tabBtn.classList.add('active');
            document.getElementById(tabBtn.dataset.tab).style.display = 'block';
            
            // Charger les données appropriées selon l'onglet actif
            if (tabBtn.dataset.tab === 'tab-rules') {
                fetchPayloads();
                fetchRulesAndPayloads();
            } else if (tabBtn.dataset.tab === 'tab-execution') {
                // Synchroniser automatiquement les règles à chaque accès à l'onglet Execution & Results
                synchronizeRulesForExecutionTab();
            }
        });
    });

    // Gestionnaire d'événements pour la modale générique
    const genericModal = document.getElementById('generic-modal');
    const closeGenericModalBtn = document.getElementById('close-generic-modal-btn');
    if (closeGenericModalBtn) {
        closeGenericModalBtn.onclick = function() {
            genericModal.style.display = 'none';
        };
    }
    
    // Connector card click handlers
    const splunkConnector = document.getElementById('splunk-connector');
    if (splunkConnector) {
        splunkConnector.addEventListener('click', function() {
            const opensearchForm = document.getElementById('opensearch-form');
            if (opensearchForm) opensearchForm.classList.remove('active');
            let splunkForm = document.getElementById('splunk-form');
            if (splunkForm) splunkForm.classList.toggle('active');
        });
    }
    const opensearchConnector = document.getElementById('opensearch-connector');
    if (opensearchConnector) {
        opensearchConnector.addEventListener('click', function() {
            const splunkForm = document.getElementById('splunk-form');
            if (splunkForm) splunkForm.classList.remove('active');
            let opensearchForm = document.getElementById('opensearch-form');
            if (opensearchForm) opensearchForm.classList.toggle('active');
        });
    }
    // Test connection buttons
    const testSplunkBtn = document.getElementById('test-splunk-connection');
    if (testSplunkBtn) testSplunkBtn.addEventListener('click', function() { testConnection('splunk'); });
    const testOpenSearchBtn = document.getElementById('test-opensearch-connection');
    if (testOpenSearchBtn) testOpenSearchBtn.addEventListener('click', function() { testConnection('opensearch'); });
    // Save connection buttons
    const saveSplunkBtn = document.getElementById('save-splunk-connection');
    if (saveSplunkBtn) saveSplunkBtn.addEventListener('click', function() { saveConnection('splunk'); });
    const saveOpenSearchBtn = document.getElementById('save-opensearch-connection');
    if (saveOpenSearchBtn) saveOpenSearchBtn.addEventListener('click', function() { saveConnection('opensearch'); });
    // Close modal
    const closeModalBtn = document.getElementsByClassName('close')[0];
    if (closeModalBtn) closeModalBtn.addEventListener('click', function() {
        const modal = document.getElementById('connection-modal');
        if (modal) modal.style.display = 'none';
    });
    // Modal click outside to close
    window.addEventListener('click', function(event) {
        let modal = document.getElementById('connection-modal');
        if (modal && event.target == modal) {
            modal.style.display = 'none';
        }
    });
    // Check if connections are already established
    loadConnectorConfigs();
    // Écouteurs pour les boutons Retrieve Rules
    const retrieveOpenSearchBtn = document.getElementById('retrieve-opensearch-rules');
    if (retrieveOpenSearchBtn) retrieveOpenSearchBtn.addEventListener('click', function() { retrieveRules('opensearch'); });
    const retrieveSplunkBtn = document.getElementById('retrieve-splunk-rules');
    if (retrieveSplunkBtn) retrieveSplunkBtn.addEventListener('click', function() { retrieveRules('splunk'); });

    // --- Gestion du panneau latéral de configuration connecteur ---
    function openConnectorSidePanel(type) {
        const panel = document.getElementById('connector-sidepanel');
        const form = document.getElementById('sidepanel-form');
        
        if (!panel || !form) {
            console.error("Panneau latéral ou formulaire non trouvé");
            return;
        }
        
        console.log("Ouverture du panneau pour", type);
        
        // Configuration du titre
        const titleEl = document.getElementById('sidepanel-title');
        if (titleEl) {
            titleEl.textContent = type === 'opensearch' ? 'Configuration OpenSearch' : 'Configuration Splunk';
        }
        
        // Construction du formulaire
        let html = '';
        if (type === 'opensearch') {
            html += `<div class='form-group'><label>Host</label><input type='text' id='opensearch-host' placeholder='https://localhost:9200'></div>`;
            html += `<div class='form-group'><label>Username</label><input type='text' id='opensearch-username' placeholder='admin'></div>`;
            html += `<div class='form-group'><label>Password</label><input type='password' id='opensearch-password'></div>`;
            html += `<div class='form-actions'><button class='btn btn-test' id='test-opensearch-connection'>Tester</button> <button class='btn btn-save' id='save-opensearch-connection'>Enregistrer</button></div>`;
        } else {
            html += `<div class='form-group'><label>Host</label><input type='text' id='splunk-host' placeholder='127.0.0.1'></div>`;
            html += `<div class='form-group'><label>Port</label><input type='text' id='splunk-port' placeholder='8089'></div>`;
            html += `<div class='form-group'><label>Username</label><input type='text' id='splunk-username' placeholder='admin'></div>`;
            html += `<div class='form-group'><label>Password</label><input type='password' id='splunk-password'></div>`;
            html += `<div class='form-actions'><button class='btn btn-test' id='test-splunk-connection'>Tester</button> <button class='btn btn-save' id='save-splunk-connection'>Enregistrer</button></div>`;
        }
        form.innerHTML = html;
        
        // Ouvrir le panneau
        panel.classList.add('open');
        
        // Gestionnaires d'événements pour les boutons
        if (type === 'opensearch') {
            const testBtn = document.getElementById('test-opensearch-connection');
            if (testBtn) testBtn.onclick = function(e) { 
                e.preventDefault();
                testConnection('opensearch'); 
            };
            const saveBtn = document.getElementById('save-opensearch-connection');
            if (saveBtn) saveBtn.onclick = function(e) { 
                e.preventDefault();
                saveConnection('opensearch'); 
            };
        } else {
            const testBtn = document.getElementById('test-splunk-connection');
            if (testBtn) testBtn.onclick = function(e) { 
                e.preventDefault();
                testConnection('splunk'); 
            };
            const saveBtn = document.getElementById('save-splunk-connection');
            if (saveBtn) saveBtn.onclick = function(e) { 
                e.preventDefault();
                saveConnection('splunk'); 
            };
        }
        
        // Charger les configurations existantes
        loadConnectorConfigInPanel(type);
    }

    function loadConnectorConfigInPanel(type) {
        let formData = new FormData();
        formData.append('action', 'get');
        formData.append('type', type);
        
        fetch('/scripts/php/connector_api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (Object.keys(data).length > 0) {
                if (type === 'opensearch') {
                    const host = document.getElementById('opensearch-host');
                    const username = document.getElementById('opensearch-username');
                    const password = document.getElementById('opensearch-password');
                    
                    if (host) host.value = data.host || '';
                    if (username) username.value = data.username || '';
                    if (password) password.value = data.password || '';
                } else if (type === 'splunk') {
                    const host = document.getElementById('splunk-host');
                    const port = document.getElementById('splunk-port');
                    const username = document.getElementById('splunk-username');
                    const password = document.getElementById('splunk-password');
                    
                    if (host) host.value = data.host || '';
                    if (port) port.value = data.port || '';
                    if (username) username.value = data.username || '';
                    if (password) password.value = data.password || '';
                }
            }
        })
        .catch(error => {
            console.error(`Error loading ${type} configuration:`, error);
        });
    }

    document.getElementById('close-sidepanel').onclick = function() {
        document.getElementById('connector-sidepanel').classList.remove('open');
    };

    // Gestionnaire de clic pour fermer le panneau quand on clique en dehors
    document.addEventListener('click', function(event) {
        const sidepanel = document.getElementById('connector-sidepanel');
        const splunkConnector = document.getElementById('splunk-connector');
        const opensearchConnector = document.getElementById('opensearch-connector');
        
        // Fermer le panneau si le clic est en dehors du panneau et des connecteurs
        if (sidepanel && sidepanel.classList.contains('open') && 
            !sidepanel.contains(event.target) && 
            event.target !== splunkConnector &&
            event.target !== opensearchConnector &&
            !splunkConnector.contains(event.target) &&
            !opensearchConnector.contains(event.target)) {
            sidepanel.classList.remove('open');
        }
    });

    const splunkConnector2 = document.getElementById('splunk-connector');
    if (splunkConnector2) splunkConnector2.onclick = function() { openConnectorSidePanel('splunk'); };
    const opensearchConnector2 = document.getElementById('opensearch-connector');
    if (opensearchConnector2) opensearchConnector2.onclick = function() { openConnectorSidePanel('opensearch'); };

    // --- Gestion des règles & payloads ---
    function fetchRulesAndPayloads() {
        // Afficher un indicateur de chargement
        const container = document.getElementById('rules-table-container');
        if (container) {
            container.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Loading rules...</p></div>';
        }

        console.log('Fetching rules for connector:', SELECTED_CONNECTOR);
        
        // Chargeons directement les règles sans dépendre de get_rule_payload_map
        fetch('scripts/php/connector_api.php', {
            method: 'POST',
            body: new URLSearchParams({ 
                action: 'get_rules',
                connector: SELECTED_CONNECTOR
            })
        })
        .then(r => r.json())
        .then(data => {
            console.log('API response get_rules:', data);
            
            if (data.rules) {
                RULES = data.rules;
                LAST_SYNC = data.last_sync;
                console.log(`${RULES.length} rules retrieved, last sync: ${LAST_SYNC}`);
                
                // Ensuite, essayons de charger les associations règle-payload si disponibles
                fetch('scripts/php/connector_api.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'get_rule_payload_map' })
                })
                .then(r => r.json())
                .then(mapData => {
                    console.log('API response get_rule_payload_map:', mapData);
                    if (mapData.map) {
                        rulePayloadMap = mapData.map;
                        console.log('Rule-payload associations loaded:', rulePayloadMap);
                    } else {
                        console.warn('No rule-payload associations found or invalid response');
                        rulePayloadMap = {}; // En cas d'échec, on initialise avec un objet vide
                    }
                })
                .catch(error => {
                    console.warn('Failed to load rule-payload associations:', error);
                    rulePayloadMap = {}; // En cas d'erreur, on initialise avec un objet vide
                })
                .finally(() => {
                    // On affiche les règles dans tous les cas, avec ou sans associations
                    renderRulesTable();
                    document.getElementById('last-sync-info').textContent = 'Last synchronization: ' + (LAST_SYNC ? LAST_SYNC : '--');
                });
            } else {
                console.warn('No rules found in API response');
                RULES = [];
                rulePayloadMap = {};
                document.getElementById('last-sync-info').textContent = 'Last synchronization: --';
                renderRulesTable();
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            if (container) {
                container.innerHTML = '<div class="alert alert-error">Error retrieving rules</div>';
            }
        });
    }

    document.getElementById('rules-connector-select').onchange = function() {
        SELECTED_CONNECTOR = this.value;
        fetchRulesAndPayloads();
    };

    document.getElementById('sync-rules-btn').onclick = function() {
        // Afficher un indicateur de chargement
        const syncBtn = document.getElementById('sync-rules-btn');
        const origText = syncBtn.textContent;
        syncBtn.innerHTML = '<span class="loading-spinner"></span> Synchronizing...';
        syncBtn.disabled = true;
        
        console.log('Synchronizing rules for connector:', SELECTED_CONNECTOR);
        
        fetch('scripts/php/connector_api.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'retrieve_rules', connector_type: SELECTED_CONNECTOR })
        })
            .then(r => r.json())
            .then(data => {
                console.log('API response retrieve_rules:', data);
                
                if (data.triggers || data.saved_searches) {
                    let rules = [];
                    
                    // Handle OpenSearch response
                    if (data.triggers) {
                        rules = data.triggers;
                        console.log(`Retrieved ${rules.length} OpenSearch rules`);
                    }
                    
                    // Handle Splunk response
                    if (data.saved_searches) {
                        rules = data.saved_searches;
                        console.log(`Retrieved ${rules.length} Splunk rules`);
                    }
                    
                    // If rules found, save them to database
                    if (rules.length > 0) {
                        fetch('scripts/php/connector_api.php', {
                            method: 'POST',
                            body: new URLSearchParams({
                                action: 'sync_rules',
                                connector: SELECTED_CONNECTOR,
                                rules: JSON.stringify(rules)
                            })
                        })
                            .then(r2 => r2.json())
                            .then(data2 => {
                                console.log('Sync response:', data2);
                                
                                // Reset button
                                syncBtn.innerHTML = origText;
                                syncBtn.disabled = false;
                                
                                if (data2.success) {
                                    // Load the newly synchronized rules
                                    RULES = rules;
                                    showToast('Rules synchronized!');
                                    LAST_SYNC = data2.synced_at || (new Date()).toLocaleString();
                                    document.getElementById('last-sync-info').textContent = 'Last synchronization: ' + LAST_SYNC;
                                } else {
                                    console.warn('Database synchronization failed, but rules are displayed');
                                }
                                
                                // Update the display
                                renderRulesTable();
                            })
                            .catch(error => {
                                console.error('Sync error:', error);
                                syncBtn.innerHTML = origText;
                                syncBtn.disabled = false;
                                showToast('Error synchronizing rules with database');
                            });
                    } else {
                        syncBtn.innerHTML = origText;
                        syncBtn.disabled = false;
                        showToast('No rules found to synchronize');
                    }
                } else if (data.error) {
                    syncBtn.innerHTML = origText;
                    syncBtn.disabled = false;
                    showToast(`Error: ${data.error}`);
                } else {
                    syncBtn.innerHTML = origText;
                    syncBtn.disabled = false;
                    showToast('No rules found. Check connector configuration.');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                syncBtn.innerHTML = origText;
                syncBtn.disabled = false;
                showToast('Error retrieving rules from connector');
            });
    };

    window.showRuleModal = function(rule) {
        const modal = document.getElementById('rulesModal');
        const title = document.getElementById('rulesModalTitle');
        const content = document.getElementById('rulesContent');
        
        if (!modal || !title || !content) return;
        
        // Récupérer l'identifiant de la règle
        const ruleId = rule.id || rule.name || rule.monitor_id || rule.trigger_name || 'Unknown';
        
        // Définir le titre
        title.textContent = rule.name || rule.trigger_name || rule.monitor_name || 'Rule Details';
        
        // Rechercher le payload associé à cette règle
        const payloadId = rulePayloadMap[ruleId];
        let payloadPromise = Promise.resolve(null);
        
        if (payloadId) {
            payloadPromise = fetch('scripts/php/connector_api.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'payload_get', id: payloadId })
            })
            .then(r => r.json())
            .then(data => data.payload || null)
            .catch(err => {
                console.error('Error fetching payload:', err);
                return null;
            });
        }
        
        // Afficher un chargeur
        content.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Loading rule details...</p></div>';
        
        // Afficher la modale pendant le chargement
        modal.style.display = 'block';
        
        // Attendre que le payload soit chargé si nécessaire
        payloadPromise.then(payload => {
            // Formater le contenu de la règle
            let html = '<div class="rule-details">';
            
            // Section d'informations générales
            html += '<div class="rule-primary-details">';
            const primaryFields = ['name', 'id', 'monitor_id', 'trigger_name', 'severity', 'type', 'rule_type', 'monitor_type'];
            for (const field of primaryFields) {
                if (rule[field]) {
                    html += `<div class="rule-detail"><span class="detail-label">${field}:</span> <span class="detail-value">${rule[field]}</span></div>`;
                }
            }
            html += '</div>';
            
            // Section pour la requête de la règle
            html += '<div class="rule-query">';
            html += '<h3>Query</h3>';
            
            // Extraire la requête selon le type de règle
            let query = '';
            if (rule.search) {
                query = rule.search; // Pour Splunk
            } else if (rule.query) {
                query = rule.query; // Pour certains types d'OpenSearch
            } else if (rule.triggers && rule.triggers[0] && rule.triggers[0].query) {
                query = rule.triggers[0].query; // Pour d'autres types d'OpenSearch
            }
            
            html += `<pre class="code-block">${query || 'No query available for this rule'}</pre>`;
            html += '</div>';
            
            // Section pour le payload associé
            html += '<div class="rule-payload-section">';
            html += '<h3>Associated Payload</h3>';
            
            if (payload) {
                html += `
                    <div class="payload-info">
                        <div class="payload-header">
                            <span class="payload-name">${payload.name}</span>
                            <button class="btn btn-small edit-payload-btn" data-payload-id="${payload.id}">Edit</button>
                        </div>
                        <div class="payload-description">${payload.description || 'No description'}</div>
                        <pre class="payload-code code-block">${payload.code || 'No code available'}</pre>
                    </div>
                `;
            } else {
                html += `
                    <div class="no-payload-message">
                        <p>No payload associated with this rule.</p>
                        <button class="btn create-payload-modal-btn" data-rule-id="${ruleId}">Create Payload</button>
                    </div>
                `;
            }
            html += '</div>';
            
            html += '</div>';
            content.innerHTML = html;
            
            // Gérer le bouton d'édition du payload
            const editPayloadBtn = content.querySelector('.edit-payload-btn');
            if (editPayloadBtn) {
                editPayloadBtn.addEventListener('click', function() {
                    const payloadId = this.getAttribute('data-payload-id');
                    showPayloadModal(payloadId);
                    modal.style.display = 'none'; // Fermer la modale des règles
                });
            }
            
            // Gérer le bouton de création de payload
            const createPayloadBtn = content.querySelector('.create-payload-modal-btn');
            if (createPayloadBtn) {
                createPayloadBtn.addEventListener('click', function() {
                    const ruleId = this.getAttribute('data-rule-id');
                    showPayloadModal(null, ruleId);
                    modal.style.display = 'none'; // Fermer la modale des règles
                });
            }
        });
        
        // Gérer la fermeture
        const closeBtn = modal.querySelector('.close-button');
        if (closeBtn) {
            closeBtn.onclick = function() {
                modal.style.display = 'none';
            };
        }
        
        // Fermer en cliquant à l'extérieur
        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
    };

    // Charger les connecteurs, mais pas les payloads ni les règles initialement
    loadConnectorConfigs();

    // Améliorer la modale de création/édition de payload
    const payloadModal = document.getElementById('payload-modal');
    if (payloadModal) {
        const modalContent = payloadModal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.style.maxWidth = '700px';
            modalContent.style.width = '90%';
        }
    }
    
    // Améliorer le style du formulaire de payload
    const payloadForm = document.getElementById('payload-form');
    if (payloadForm) {
        const codeField = document.getElementById('payload-code');
        if (codeField) {
            codeField.style.fontFamily = 'Consolas, Monaco, "Courier New", monospace';
            codeField.style.fontSize = '14px';
            codeField.style.lineHeight = '1.4';
            codeField.style.padding = '12px';
            codeField.style.backgroundColor = '#1e1e1e';
            codeField.style.color = '#d4d4d4';
            codeField.style.border = '1px solid #3d3d3d';
            codeField.style.borderRadius = '4px';
            codeField.rows = 12;
        }
        
        // Ajouter une info-bulle sur les PowerShell payloads
        const infoElement = document.createElement('div');
        infoElement.className = 'payload-info-tooltip';
        infoElement.innerHTML = `
            <i class="fas fa-info-circle"></i>
            <span>The payload should be written in PowerShell (.ps1) format and will be executed on the target system to simulate the security event.</span>
        `;
        
        const descField = document.getElementById('payload-description');
        if (descField && descField.parentNode) {
            descField.parentNode.appendChild(infoElement);
        }
    }

    const displayRulesBtn = document.getElementById('display-execution-rules');
    if (displayRulesBtn) {
        displayRulesBtn.onclick = function() {
            renderExecutionRulesTable();
        };
    }

    // Gestionnaire pour la synchronisation automatique lors du changement de connecteur dans l'onglet Execution & Results
    const executionConnectorSelect = document.getElementById('execution-connector-select');
    if (executionConnectorSelect) {
        executionConnectorSelect.addEventListener('change', function() {
            // Synchroniser automatiquement les règles du nouveau connecteur sélectionné
            synchronizeRulesForExecutionTab();
        });
    }

    // Ajout d'un gestionnaire pour le bouton d'exécution de tous les payloads
    function attachExecuteAllPayloadsHandler() {
        const executeAllBtn = document.getElementById('execute-all-payloads');
        if (executeAllBtn) {
            executeAllBtn.onclick = function() {
                console.log('[DEBUG] Clicked Execute All Payloads');
                executeAllPayloadsForDisplayedRules();
            };
        }
    }
    attachExecuteAllPayloadsHandler();
    // Réattacher à chaque changement d'onglet
    document.querySelectorAll('.rlc-tab').forEach(function(tabBtn) {
        tabBtn.addEventListener('click', function() {
            setTimeout(attachExecuteAllPayloadsHandler, 100); // Pour s'assurer que le DOM est prêt
        });
    });
});

function testConnection(connectorType) {
    // Show loading spinner and disable button
    let button = document.getElementById(`test-${connectorType}-connection`);
    let buttonText = button.innerText;
    button.innerHTML = '<span class="loading-spinner"></span> Testing...';
    button.disabled = true;
    
    // Get connection parameters
    let params = getConnectorConfig(connectorType);
    
    // Send API request to test connection
    let formData = new FormData();
    formData.append('action', 'test');
    formData.append('type', connectorType);
    formData.append('config', JSON.stringify(params));
    
    fetch('/scripts/php/connector_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Reset button state
        button.innerHTML = buttonText;
        button.disabled = false;
        
        // Afficher le résultat dans le panneau latéral
        let resultElement = document.createElement('div');
        resultElement.id = `${connectorType}-connection-result`;
        
        // Remplacer l'ancien résultat s'il existe
        let oldResult = document.getElementById(`${connectorType}-connection-result`);
        if (oldResult) {
            oldResult.remove();
        }
        
        if (data.success) {
            resultElement.innerHTML = '<div class="alert alert-success">Connexion réussie !</div>';
            
            // Update status indicator in main view
            const statusElement = document.getElementById(`${connectorType}-status`);
            if (statusElement) statusElement.classList.add('connected');
            updateConnectorStatus(connectorType, 'active', params);
        } else {
            resultElement.innerHTML = `<div class="alert alert-error">Échec de la connexion : ${data.message || 'Veuillez vérifier vos informations'}</div>`;
            const statusElement = document.getElementById(`${connectorType}-status`);
            if (statusElement) statusElement.classList.remove('connected');
            updateConnectorStatus(connectorType, 'inactive');
        }
        
        // Ajouter le résultat au panneau
        document.getElementById('sidepanel-form').appendChild(resultElement);
    })
    .catch(error => {
        // Reset button state on error
        button.innerHTML = buttonText;
        button.disabled = false;
        
        // Afficher l'erreur dans le panneau
        let resultElement = document.createElement('div');
        resultElement.id = `${connectorType}-connection-result`;
        
        // Remplacer l'ancien résultat s'il existe
        let oldResult = document.getElementById(`${connectorType}-connection-result`);
        if (oldResult) {
            oldResult.remove();
        }
        
        resultElement.innerHTML = `<div class="alert alert-error">Erreur API : ${error.message}</div>`;
        document.getElementById('sidepanel-form').appendChild(resultElement);
        
        const statusElement = document.getElementById(`${connectorType}-status`);
        if (statusElement) statusElement.classList.remove('connected');
        updateConnectorStatus(connectorType, 'error');
    });
}

function getConnectorConfig(connectorType) {
    if (connectorType === 'splunk') {
        return {
            host: document.getElementById('splunk-host').value,
            port: document.getElementById('splunk-port').value,
            username: document.getElementById('splunk-username').value,
            password: document.getElementById('splunk-password').value
        };
    } else if (connectorType === 'opensearch') {
        return {
            host: document.getElementById('opensearch-host').value,
            username: document.getElementById('opensearch-username').value,
            password: document.getElementById('opensearch-password').value
        };
    }
    return {};
}

function saveConnection(connectorType) {
    // Get connection parameters
    let params = getConnectorConfig(connectorType);
    
    // Check if all required fields are filled
    let valid = true;
    for (let key in params) {
        if (!params[key]) {
            valid = false;
            break;
        }
    }
    
    if (!valid) {
        // Afficher un message d'erreur dans le panneau
        let resultElement = document.createElement('div');
        resultElement.id = `${connectorType}-save-result`;
        resultElement.innerHTML = '<div class="alert alert-error">Veuillez remplir tous les champs requis.</div>';
        
        // Remplacer l'ancien résultat s'il existe
        let oldResult = document.getElementById(`${connectorType}-save-result`);
        if (oldResult) {
            oldResult.remove();
        }
        
        const sidepanelForm = document.getElementById('sidepanel-form');
        if (sidepanelForm) sidepanelForm.appendChild(resultElement);
        return;
    }
    
    // Afficher un message de chargement
    let saveButton = document.getElementById(`save-${connectorType}-connection`);
    let saveButtonText = '';
    if (saveButton) {
        saveButtonText = saveButton.innerText;
        saveButton.innerHTML = '<span class="loading-spinner"></span> Enregistrement...';
        saveButton.disabled = true;
    }
    
    // Save connection settings through the API
    let formData = new FormData();
    formData.append('action', 'save');
    formData.append('type', connectorType);
    formData.append('config', JSON.stringify(params));
    
    fetch('/scripts/php/connector_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Restaurer le bouton
        if (saveButton) {
            saveButton.innerHTML = saveButtonText;
            saveButton.disabled = false;
        }
        
        // Créer un élément pour afficher le résultat
        let resultElement = document.createElement('div');
        resultElement.id = `${connectorType}-save-result`;
        
        // Remplacer l'ancien résultat s'il existe
        let oldResult = document.getElementById(`${connectorType}-save-result`);
        if (oldResult) {
            oldResult.remove();
        }
        
        if (data.success) {
            // Update connector status
            const statusElement = document.getElementById(`${connectorType}-status`);
            if (statusElement) statusElement.classList.add('connected');
            
            // Afficher le message de succès
            resultElement.innerHTML = '<div class="alert alert-success">Configuration enregistrée avec succès !</div>';
            showToast('Configuration enregistrée !');
            
            // Mettre à jour le statut
            updateConnectorStatus(connectorType, 'active', params);
            
            // Fermer le panneau après un court délai
            setTimeout(() => {
                const sidepanel = document.getElementById('connector-sidepanel');
                if (sidepanel) sidepanel.classList.remove('open');
            }, 1500);
        } else {
            // Afficher l'erreur
            resultElement.innerHTML = `<div class="alert alert-error">Échec de l'enregistrement : ${data.message || 'Erreur inconnue'}</div>`;
        }
        
        // Ajouter le résultat au panneau
        const sidepanelForm = document.getElementById('sidepanel-form');
        if (sidepanelForm) sidepanelForm.appendChild(resultElement);
    })
    .catch(error => {
        // Restaurer le bouton
        if (saveButton) {
            saveButton.innerHTML = saveButtonText;
            saveButton.disabled = false;
        }
        
        // Afficher l'erreur
        let resultElement = document.createElement('div');
        resultElement.id = `${connectorType}-save-result`;
        
        // Remplacer l'ancien résultat s'il existe
        let oldResult = document.getElementById(`${connectorType}-save-result`);
        if (oldResult) {
            oldResult.remove();
        }
        
        resultElement.innerHTML = `<div class="alert alert-error">Erreur API : ${error.message}</div>`;
        
        // Ajouter le résultat au panneau
        const sidepanelForm = document.getElementById('sidepanel-form');
        if (sidepanelForm) sidepanelForm.appendChild(resultElement);
    });
}

function loadConnectorConfigs() {
    // Load Splunk config
    let splunkFormData = new FormData();
    splunkFormData.append('action', 'get');
    splunkFormData.append('type', 'splunk');
    
    fetch('/scripts/php/connector_api.php', {
        method: 'POST',
        body: splunkFormData
    })
    .then(response => response.json())
    .then(data => {
        if (Object.keys(data).length > 0) {
            // Fill form fields
            const splunkHost = document.getElementById('splunk-host');
            const splunkPort = document.getElementById('splunk-port');
            const splunkUsername = document.getElementById('splunk-username');
            const splunkPassword = document.getElementById('splunk-password');
            const splunkStatus = document.getElementById('splunk-status');
            const retrieveSplunkRules = document.getElementById('retrieve-splunk-rules');
            
            if (splunkHost) splunkHost.value = data.host || '';
            if (splunkPort) splunkPort.value = data.port || '';
            if (splunkUsername) splunkUsername.value = data.username || '';
            if (splunkPassword) splunkPassword.value = data.password || '';
            
            // Update status indicator
            if (splunkStatus) splunkStatus.classList.add('connected');
            updateConnectorStatus('splunk', 'active', data);
            
            // Afficher le bouton Retrieve Rules
            if (retrieveSplunkRules) retrieveSplunkRules.style.display = 'inline-block';
        }
    })
    .catch(error => {
        console.error('Error loading Splunk configuration:', error);
    });
    
    // Load OpenSearch config
    let opensearchFormData = new FormData();
    opensearchFormData.append('action', 'get');
    opensearchFormData.append('type', 'opensearch');
    
    fetch('/scripts/php/connector_api.php', {
        method: 'POST',
        body: opensearchFormData
    })
    .then(response => response.json())
    .then(data => {
        if (Object.keys(data).length > 0) {
            // Fill form fields
            const opensearchHost = document.getElementById('opensearch-host');
            const opensearchUsername = document.getElementById('opensearch-username');
            const opensearchPassword = document.getElementById('opensearch-password');
            const opensearchStatus = document.getElementById('opensearch-status');
            const retrieveOpensearchRules = document.getElementById('retrieve-opensearch-rules');
            
            if (opensearchHost) opensearchHost.value = data.host || '';
            if (opensearchUsername) opensearchUsername.value = data.username || '';
            if (opensearchPassword) opensearchPassword.value = data.password || '';
            
            // Update status indicator
            if (opensearchStatus) opensearchStatus.classList.add('connected');
            updateConnectorStatus('opensearch', 'active', data);
            
            // Afficher le bouton Retrieve Rules
            if (retrieveOpensearchRules) retrieveOpensearchRules.style.display = 'inline-block';
        }
    })
    .catch(error => {
        console.error('Error loading OpenSearch configuration:', error);
    });
}

function updateConnectorStatus(type, status, data) {
    const connector = document.querySelector(`.connector-card[data-type="${type}"]`);
    if (connector) {
        connector.classList.remove('inactive', 'active', 'error');
        connector.classList.add(status);

        // Store connector data in dataset
        if (data) {
            connector.dataset.connectorData = JSON.stringify(data);
        }
        
        // Ne plus ajouter de bouton sur la carte du connecteur
    }
}

function retrieveRules(connectorType, connectorData) {
    // Afficher l'état de chargement dans la section
    const rulesSection = document.getElementById('rules-section');
    const rulesContent = document.getElementById('rules-content');
    const rulesSectionTitle = document.getElementById('rules-section-title');
    
    rulesSection.style.display = 'block';
    rulesSectionTitle.textContent = `${connectorType.charAt(0).toUpperCase() + connectorType.slice(1)} Rules`;
    rulesContent.innerHTML = '<div class="loading-spinner"></div><p>Loading rules...</p>';
    
    // Faire défiler jusqu'à la section des règles
    rulesSection.scrollIntoView({ behavior: 'smooth' });
    
    // Appel AJAX pour récupérer les règles en fonction du type de connecteur
    const formData = new FormData();
    formData.append('action', 'retrieve_rules');
    formData.append('connector_type', connectorType);
    
    if (connectorData) {
        formData.append('connector_data', JSON.stringify(connectorData));
    }
    
    fetch('scripts/php/connector_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            rulesContent.innerHTML = `<div class="error-message">${data.error}</div>`;
            return;
        }
        
        // Pour OpenSearch, afficher les moniteurs et déclencheurs
        if (connectorType === 'opensearch') {
            displayOpenSearchRules(data, rulesContent);
        } 
        // Pour Splunk, afficher les recherches sauvegardées et les alertes
        else if (connectorType === 'splunk') {
            displaySplunkRules(data, rulesContent);
        }
    })
    .catch(error => {
        console.error('Error retrieving rules:', error);
        rulesContent.innerHTML = `<div class="error-message">Failed to retrieve rules: ${error.message}</div>`;
    });
}

function displayOpenSearchRules(data, container) {
    if (!data.triggers || data.triggers.length === 0) {
        container.innerHTML = '<p>No rules found.</p>';
        return;
    }
    
    let html = '<div class="rules-list">';
    html += '<h3>OpenSearch Monitors and Triggers</h3>';
    
    // Group triggers by monitor
    const monitorGroups = {};
    data.triggers.forEach(trigger => {
        if (!monitorGroups[trigger.monitor_name]) {
            monitorGroups[trigger.monitor_name] = {
                id: trigger.monitor_id,
                enabled: trigger.monitor_enabled,
                triggers: []
            };
        }
        monitorGroups[trigger.monitor_name].triggers.push(trigger);
    });
    
    // Create UI for each monitor and its triggers
    for (const [monitorName, monitorData] of Object.entries(monitorGroups)) {
        const statusClass = monitorData.enabled ? 'monitor-enabled' : 'monitor-disabled';
        
        html += `
            <div class="monitor-item ${statusClass}">
                <div class="monitor-header">
                    <h4>${monitorName}</h4>
                    <span class="monitor-status">${monitorData.enabled ? 'Enabled' : 'Disabled'}</span>
                </div>
                <div class="triggers-container">
        `;
        
        monitorData.triggers.forEach(trigger => {
            const severityClass = `severity-${trigger.severity}`;
            const activeClass = trigger.is_active ? 'trigger-active' : 'trigger-inactive';
            
            html += `
                <div class="trigger-item ${severityClass} ${activeClass}">
                    <div class="trigger-header">
                        <span class="trigger-name">${trigger.trigger_name}</span>
                        <span class="trigger-severity">Severity: ${trigger.severity}</span>
                    </div>
                    <div class="trigger-status">
                        Status: ${trigger.is_active ? 'ACTIVE' : 'INACTIVE'}
                    </div>
            `;
            
            if (trigger.is_active && trigger.start_time) {
                const startDate = new Date(trigger.start_time);
                html += `<div class="trigger-time">Triggered on: ${startDate.toLocaleString()}</div>`;
            }
            
            html += `</div>`;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

function displaySplunkRules(data, container) {
    if (!data.saved_searches || data.saved_searches.length === 0) {
        container.innerHTML = '<p>No rules found.</p>';
        return;
    }
    
    let html = '<div class="rules-list">';
    html += '<h3>Splunk Saved Searches and Alerts</h3>';
    
    // Create UI for each saved search
    data.saved_searches.forEach(search => {
        const isAlert = search.is_scheduled && search.alert_type;
        const statusClass = search.is_scheduled ? 'search-scheduled' : 'search-unscheduled';
        const alertClass = isAlert ? 'search-alert' : '';
        
        html += `
            <div class="search-item ${statusClass} ${alertClass}">
                <div class="search-header">
                    <h4>${search.name}</h4>
                    <span class="search-status">${isAlert ? 'Alert' : (search.is_scheduled ? 'Scheduled' : 'Not Scheduled')}</span>
                </div>
                <div class="search-details">
                    <div class="search-query">${search.search}</div>
        `;
        
        if (isAlert) {
            html += `
                    <div class="alert-info">
                        <div>Alert Type: ${search.alert_type}</div>
                        <div>Schedule: ${search.cron_schedule || 'N/A'}</div>
                    </div>
            `;
        }
        
        html += `
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function fetchPayloads(callback) {
    console.log('[DEBUG] fetchPayloads called');
    fetch('scripts/php/connector_api.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'payload_list' })
    })
    .then(r => {
        if (!r.ok) {
            throw new Error(`Network response was not ok: ${r.status}`);
        }
        return r.json();
    })
    .then(data => {
        console.log('Payloads received:', data);
        if (data.error) {
            showToast(data.error, 'error');
            console.error('Error fetching payloads:', data.error);
            PAYLOADS = [];
            if (callback) callback();
            return;
        }
        PAYLOADS = data.payloads || [];
        console.log('Payloads updated:', PAYLOADS.length, 'payloads');
        if (callback) callback();
    })
    .catch(error => {
        console.error('Fetch error:', error);
        PAYLOADS = [];
        if (callback) callback();
    });
}

// Function to show the payload creation/edit modal (amélioration visuelle)
function showPayloadModal(payloadId = null, ruleId = null) {
    const modal = document.getElementById('payload-modal');
    const title = document.getElementById('payload-modal-title');
    const form = document.getElementById('payload-form');
    const idField = document.getElementById('payload-id');
    const nameField = document.getElementById('payload-name');
    const descField = document.getElementById('payload-description');
    const codeField = document.getElementById('payload-code');
    let ruleField = document.getElementById('payload-rule-id');
    if (!ruleField) {
        ruleField = document.createElement('input');
        ruleField.type = 'hidden';
        ruleField.id = 'payload-rule-id';
        ruleField.name = 'rule_id';
        form.appendChild(ruleField);
    }
    
    if (!modal || !form) return;
    
    // Reset form
    form.reset();
    
    if (payloadId) {
        // Edit mode
        title.textContent = 'Edit PowerShell Payload';
        // Fetch payload data
        fetch('scripts/php/connector_api.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'payload_get', id: payloadId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.payload) {
                idField.value = data.payload.id;
                nameField.value = data.payload.name;
                descField.value = data.payload.description;
                codeField.value = data.payload.code;
                ruleField.value = data.payload.rule_id ? data.payload.rule_id : '';
                
                // Mise en évidence de la syntaxe (simulation basique)
                highlightPowerShellSyntax();
            } else if (data.error) {
                showToast(data.error);
                modal.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showToast('Error retrieving payload');
            modal.style.display = 'none';
        });
    } else {
        // Create mode
        title.textContent = 'Create PowerShell Payload';
        idField.value = '';
        ruleField.value = ruleId ? ruleId : '';
        
        // Exemple de code PowerShell pour aider l'utilisateur
        codeField.value = '# PowerShell payload template\n$OutputEncoding = [System.Text.Encoding]::UTF8\n\n# Add your code to simulate the security event here\nWrite-Host "Executing rule simulation payload"\n\n# Example: Create a suspicious process\n# Start-Process -FilePath "cmd.exe" -ArgumentList "/c echo Test > C:\\Windows\\Temp\\test.txt"';
        
        // Mise en évidence de la syntaxe
        highlightPowerShellSyntax();
    }
    
    // Show modal with improved styling
    modal.style.display = 'block';
    
    // Close handling
    const closeBtn = document.getElementById('close-payload-modal');
    if (closeBtn) {
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        };
    }
    
    // Close when clicking outside modal
    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
    
    // Handle form submission
    form.onsubmit = function(e) {
        e.preventDefault();
        
        // Validation des champs
        if (!nameField.value.trim()) {
            showToast('Please enter a name for the payload', 'error');
            return;
        }
        
        if (!codeField.value.trim()) {
            showToast('Please enter PowerShell code for the payload', 'error');
            return;
        }
        
        const formData = new FormData(form);
        const action = idField.value ? 'payload_update' : 'payload_create';
        formData.append('action', action);
        if (ruleField.value) {
            formData.set('rule_id', ruleField.value);
        }
        
        // Désactiver le bouton pendant l'enregistrement
        const saveBtn = document.getElementById('save-payload-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="loading-spinner"></span> Saving...';
        }
        
        fetch('scripts/php/connector_api.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            // Réactiver le bouton
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = 'Save';
            }
            
            if (data.success) {
                modal.style.display = 'none';
                showToast(action === 'payload_create' ? 'Payload created' : 'Payload updated');
                
                // Mettre à jour l'association règle-payload si c'est une création
                if (action === 'payload_create' && ruleField.value && data.payload_id) {
                    rulePayloadMap[ruleField.value] = data.payload_id;
                }
                
                // Rafraîchir la liste des payloads
                fetchPayloads();
            } else if (data.error) {
                showToast(data.error, 'error');
            }
        })
        .catch(error => {
            // Réactiver le bouton
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = 'Save';
            }
            
            console.error('Save error:', error);
            showToast('Error saving payload', 'error');
        });
    };
    
    // Ajouter un événement pour mettre à jour la coloration syntaxique lors de la saisie
    codeField.addEventListener('input', highlightPowerShellSyntax);
}

// Fonction basique pour simuler une coloration syntaxique PowerShell
function highlightPowerShellSyntax() {
    // Dans une implémentation réelle, vous pourriez utiliser une bibliothèque comme highlight.js
    // Cette fonction est juste un placeholder
    console.log('Syntax highlighting for PowerShell would be applied here');
}

function renderExecutionRulesTable() {
    // Récupérer les sélections
    const connector = document.getElementById('execution-connector-select').value;
    const status = document.getElementById('execution-status-filter').value;
    const timeFilter = document.getElementById('execution-time-filter').value;
    // Filtrer les règles selon le connecteur et le statut
    let filteredRules = RULES.filter(rule => {
        let matchConnector = true;
        if (connector && connector !== 'all') {
            matchConnector = (rule.connector || SELECTED_CONNECTOR) === connector;
        }
        let matchStatus = true;
        // Gestion du filtre de temps pour les règles triggered
        let isTriggered = !!rule.is_active;
        let triggerTime = rule.start_time || rule.trigger_time || null;
        let now = Date.now();
        let triggeredRecently = true;
        if (isTriggered && triggerTime && timeFilter && timeFilter !== 'all') {
            let triggerTimestamp = typeof triggerTime === 'string' ? Date.parse(triggerTime) : triggerTime;
            if (String(triggerTimestamp).length === 10) triggerTimestamp = triggerTimestamp * 1000; // Si timestamp en secondes
            let diffMs = now - triggerTimestamp;
            let maxMs = 0;
            if (timeFilter === '24h') maxMs = 24 * 3600 * 1000;
            else if (timeFilter === '7d') maxMs = 7 * 24 * 3600 * 1000;
            else if (timeFilter === '1m') maxMs = 30 * 24 * 3600 * 1000;
            else if (timeFilter === '3m') maxMs = 90 * 24 * 3600 * 1000;
            triggeredRecently = diffMs <= maxMs;
        }
        if (status === 'triggered') matchStatus = isTriggered && triggeredRecently;
        else if (status === 'not_triggered') matchStatus = !isTriggered || (isTriggered && !triggeredRecently);
        else if (status === 'error') matchStatus = rule.status === 'error';
        return matchConnector && matchStatus;
    });
    // Afficher les règles filtrées
    const container = document.getElementById('execution-results-table');
    if (!container) return;
    if (filteredRules.length === 0) {
        container.innerHTML = '<div class="no-results">No rules match your criteria.</div>';
        return;
    }
    let html = '<table class="rules-table"><thead><tr>';
    html += '<th>Name</th><th>Rule ID</th><th>Type</th><th>Severity</th><th>Payload</th><th>Status</th></tr></thead><tbody>';
    for (const rule of filteredRules) {
        const ruleId = rule.id || rule.monitor_id || rule.trigger_name || '-';
        let payloadCell = '<span class="no-payload">None</span>';
        if (rulePayloadMap && rulePayloadMap[ruleId]) {
            const payloadId = rulePayloadMap[ruleId];
            const payload = PAYLOADS.find(p => p.id == payloadId);
            if (payload) {
                payloadCell = `<span class=\"has-payload\">${payload.name}</span>`;
            } else {
                payloadCell = '<span class=\"no-payload\">Unknown</span>';
            }
        }
        html += `<tr><td>${rule.name || rule.trigger_name || rule.monitor_name || '-'}</td>`;
        html += `<td>${ruleId}</td>`;
        html += `<td>${rule.rule_type || rule.type || '-'}</td>`;
        html += `<td>${rule.severity || '-'}</td>`;
        html += `<td>${payloadCell}</td>`;
        html += `<td>${rule.is_active ? '<span class=\"status-triggered\">Triggered</span>' : '<span class=\"status-not-triggered\">Not triggered</span>'}</td></tr>`;
    }
    html += '</tbody></table>';
    container.innerHTML = html;
}

function synchronizeRulesForExecutionTab() {
    // Récupérer le connecteur sélectionné dans l'onglet Execution & Results
    const executionConnectorSelect = document.getElementById('execution-connector-select');
    const selectedConnector = executionConnectorSelect ? executionConnectorSelect.value : 'opensearch';
    
    // Afficher un indicateur de chargement
    const container = document.getElementById('execution-results-table');
    if (container) {
        container.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Synchronizing ' + selectedConnector + ' rules...</p></div>';
    }
    
    // Appel API pour synchroniser les règles du connecteur sélectionné
    fetch('scripts/php/connector_api.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'retrieve_rules', connector_type: selectedConnector })
    })
    .then(r => r.json())
    .then(data => {
        let rules = [];
        if (data.triggers) {
            rules = data.triggers;
        } else if (data.saved_searches) {
            rules = data.saved_searches;
        }
        
        // Ajouter le type de connecteur à chaque règle pour le filtrage
        rules = rules.map(rule => ({
            ...rule,
            connector: selectedConnector
        }));
        
        // Mettre à jour RULES
        RULES = rules;
        
        // Charger les payloads puis afficher le message
        fetchPayloads(function() {
            if (container) container.innerHTML = '<div class="no-results">Select filters and display rules.</div>';
        });
    })
    .catch(error => {
        console.error('Error synchronizing rules:', error);
        if (container) container.innerHTML = '<div class="no-results">Error synchronizing rules.</div>';
    });
}

function executeAllPayloadsForDisplayedRules() {
    console.log('[DEBUG] Début executeAllPayloadsForDisplayedRules');
    // Récupérer les règles actuellement affichées (après filtrage)
    const connector = document.getElementById('execution-connector-select').value;
    const status = document.getElementById('execution-status-filter').value;
    const timeFilter = document.getElementById('execution-time-filter').value;
    let filteredRules = RULES.filter(rule => {
        let matchConnector = true;
        if (connector && connector !== 'all') {
            matchConnector = (rule.connector || SELECTED_CONNECTOR) === connector;
        }
        let matchStatus = true;
        let isTriggered = !!rule.is_active;
        let triggerTime = rule.start_time || rule.trigger_time || null;
        let now = Date.now();
        let triggeredRecently = true;
        if (isTriggered && triggerTime && timeFilter && timeFilter !== 'all') {
            let triggerTimestamp = typeof triggerTime === 'string' ? Date.parse(triggerTime) : triggerTime;
            if (String(triggerTimestamp).length === 10) triggerTimestamp = triggerTimestamp * 1000;
            let diffMs = now - triggerTimestamp;
            let maxMs = 0;
            if (timeFilter === '24h') maxMs = 24 * 3600 * 1000;
            else if (timeFilter === '7d') maxMs = 7 * 24 * 3600 * 1000;
            else if (timeFilter === '1m') maxMs = 30 * 24 * 3600 * 1000;
            else if (timeFilter === '3m') maxMs = 90 * 24 * 3600 * 1000;
            triggeredRecently = diffMs <= maxMs;
        }
        if (status === 'triggered') matchStatus = isTriggered && triggeredRecently;
        else if (status === 'not_triggered') matchStatus = !isTriggered || (isTriggered && !triggeredRecently);
        else if (status === 'error') matchStatus = rule.status === 'error';
        return matchConnector && matchStatus;
    });
    console.log('[DEBUG] filteredRules:', filteredRules);
    // Pour chaque règle, exécuter le payload associé si présent
    let executions = [];
    for (const rule of filteredRules) {
        const ruleId = rule.id || rule.monitor_id || rule.trigger_name || '-';
        const payloadId = rulePayloadMap && rulePayloadMap[ruleId];
        if (payloadId) {
            const payload = PAYLOADS.find(p => p.id == payloadId);
            if (payload) {
                executions.push({ rule, payload });
            }
        }
    }
    console.log('[DEBUG] executions à lancer:', executions);
    if (executions.length === 0) {
        showToast('No payloads to execute for the displayed rules.', 'error');
        return;
    }
    // Afficher un loading global
    const modal = document.getElementById('generic-modal');
    const modalTitle = document.getElementById('generic-modal-title');
    const modalBody = document.getElementById('generic-modal-body');
    if (modal && modalTitle && modalBody) {
        modalTitle.textContent = 'Batch Payload Execution';
        modalBody.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p>Executing all payloads...</p></div>';
        modal.style.display = 'block';
    }
    // Exécution séquentielle (pour éviter surcharge)
    let results = [];
    function executeNext(index) {
        if (index >= executions.length) {
            // Afficher le récapitulatif
            let html = '<h4>Execution Results</h4>';
            html += '<table class="rules-table"><thead><tr><th>Rule</th><th>Payload</th><th>Status</th><th>Output/Error</th></tr></thead><tbody>';
            for (const res of results) {
                html += `<tr><td>${res.ruleName}</td><td>${res.payloadName}</td><td>${res.status}</td><td><pre class="result-block">${res.output}</pre></td></tr>`;
            }
            html += '</tbody></table>';
            if (modalBody) modalBody.innerHTML = html;
            return;
        }
        const { rule, payload } = executions[index];
        // Appel API pour exécuter le payload
        const formData = new FormData();
        formData.append('action', 'execute_payload');
        formData.append('content', payload.code);
        fetch('scripts/php/connector_api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            results.push({
                ruleName: rule.name || rule.trigger_name || rule.monitor_name || '-',
                payloadName: payload.name,
                status: result.status === 'success' ? 'Success' : 'Error',
                output: result.status === 'success' ? result.output : (result.error || result.message || 'Unknown error')
            });
            executeNext(index + 1);
        })
        .catch(error => {
            results.push({
                ruleName: rule.name || rule.trigger_name || rule.monitor_name || '-',
                payloadName: payload.name,
                status: 'Error',
                output: error.message || 'Network error'
            });
            executeNext(index + 1);
        });
    }
    executeNext(0);
}
</script>

<style>
/* Style spécifique pour le bouton de fermeture de la modale */
.modal-close-btn:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

/* Style pour les titres des modales */
.modal-header h2 {
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

/* Style pour améliorer l'apparence de la modale */
#generic-modal .modal-content {
    border-radius: 8px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.5);
    border: 1px solid rgba(255,255,255,0.1);
}
</style>

</body>
</html> 
 
