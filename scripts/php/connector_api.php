<?php
/**
 * Connector API
 * Bridge between frontend and Python connector manager
 */

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
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failure']);
    exit;
}

// Set JSON response header
header('Content-Type: application/json');

// Validate the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Allow access only for valid actions
if (!isset($_POST['action'])) {
    echo json_encode(['error' => 'Action non spécifiée']);
    exit;
}

$action = $_POST['action'];

// Log the action
log_message("Action requested: $action");

// Validate action
if (!in_array($action, ['test', 'save', 'get', 'list', 'delete', 'retrieve_rules', 'payload_list', 'payload_create', 'payload_update', 'payload_delete', 'payload_get', 'sync_rules', 'get_rules', 'get_rule_payload_map', 'save_rule_payload', 'execute_payload'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// Get action from request
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Path to Python script
$pythonScript = '/var/www/html/scripts/Connector/connector_manager.py';
$opensearchScript = '/var/www/html/scripts/Connector/opensearch.py';
$splunkScript = '/var/www/html/scripts/Connector/splunk_alerts.py';

// Validate action
if (!in_array($action, ['test', 'save', 'get', 'list', 'delete', 'retrieve_rules', 'payload_list', 'payload_create', 'payload_update', 'payload_delete', 'payload_get', 'sync_rules', 'get_rules', 'get_rule_payload_map', 'save_rule_payload', 'execute_payload'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// Log function for debugging
function log_message($message) {
    $log_file = "/var/www/html/scripts/Connector/connector_api.log";
    $timestamp = date("Y-m-d H:i:s");
    $log_message = "[$timestamp] $message\n";
    
    // Append to log file
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Handle each action
switch ($action) {
    case 'test':
        if (!isset($_POST['type']) || !isset($_POST['config'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        
        $type = escapeshellarg($_POST['type']);
        $config = escapeshellarg($_POST['config']);
        
        $command = "python3 $pythonScript test $type $config";
        $output = shell_exec($command);
        
        echo $output ?: json_encode(['success' => false, 'message' => 'Command execution failed']);
        break;
        
    case 'save':
        if (!isset($_POST['type']) || !isset($_POST['config'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        
        $type = escapeshellarg($_POST['type']);
        $config = escapeshellarg($_POST['config']);
        
        $command = "python3 $pythonScript save $type $config";
        $output = shell_exec($command);
        
        echo $output ?: json_encode(['success' => false, 'message' => 'Command execution failed']);
        break;
        
    case 'get':
        if (!isset($_POST['type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing type parameter']);
            exit;
        }
        
        $type = escapeshellarg($_POST['type']);
        
        $command = "python3 $pythonScript get $type";
        $output = shell_exec($command);
        
        echo $output ?: json_encode([]);
        break;
        
    case 'list':
        $command = "python3 $pythonScript list";
        $output = shell_exec($command);
        
        echo $output ?: json_encode([]);
        break;
        
    case 'delete':
        if (!isset($_POST['type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing type parameter']);
            exit;
        }
        
        $type = escapeshellarg($_POST['type']);
        
        $command = "python3 $pythonScript delete $type";
        $output = shell_exec($command);
        
        echo $output ?: json_encode(['success' => false]);
        break;
        
    case 'retrieve_rules':
        if (!isset($_POST['connector_type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing connector type']);
            exit;
        }
        
        $connectorType = $_POST['connector_type'];
        $connectorData = isset($_POST['connector_data']) ? json_decode($_POST['connector_data'], true) : [];
        
        log_message("Retrieving rules for $connectorType connector");
        
        try {
            // Get saved connector data if available
            $connectorData = json_decode(shell_exec("python3 $pythonScript get $connectorType"), true) ?: [];
            
            if (empty($connectorData)) {
                echo json_encode(['error' => 'No connection data found for this connector']);
                exit;
            }
            
            // Execute specific connector script based on connector type
            switch ($connectorType) {
                case 'opensearch':
                    // First, set environment variables for opensearch script if needed
                    $tempEnvFile = tempnam(sys_get_temp_dir(), 'opensearch_env_');
                    file_put_contents($tempEnvFile, "#!/bin/bash\n");
                    
                    if (isset($connectorData['host'])) {
                        // Extract host and port from the URL if it contains a protocol
                        $host = $connectorData['host'];
                        $port = isset($connectorData['port']) && !empty($connectorData['port']) ? $connectorData['port'] : '9200';
                        
                        // Remove protocol prefix if present
                        if (preg_match('~^https?://(.+)$~i', $host, $matches)) {
                            $host = $matches[1];
                        }
                        
                        // If host includes port, extract it
                        if (preg_match('~^(.+):(\d+)$~', $host, $matches)) {
                            $host = $matches[1];
                            $port = $matches[2];
                        }
                        
                        file_put_contents($tempEnvFile, "export BASE_URL=\"https://{$host}:{$port}\"\n", FILE_APPEND);
                        log_message("Setting BASE_URL to https://{$host}:{$port}");
                    }
                    
                    if (isset($connectorData['username']) && isset($connectorData['password'])) {
                        file_put_contents($tempEnvFile, "export OS_USERNAME=\"{$connectorData['username']}\"\n", FILE_APPEND);
                        file_put_contents($tempEnvFile, "export OS_PASSWORD=\"{$connectorData['password']}\"\n", FILE_APPEND);
                        log_message("Setting OS_USERNAME to {$connectorData['username']}");
                    }
                    
                    // Add debug information
                    file_put_contents($tempEnvFile, "echo \"Debug: Running opensearch script with environment variables\"\n", FILE_APPEND);
                    file_put_contents($tempEnvFile, "python3 $opensearchScript --list-json 2>&1\n", FILE_APPEND);
                    chmod($tempEnvFile, 0755);
                    
                    $output = shell_exec("bash $tempEnvFile 2>&1");
                    log_message("OpenSearch script output: " . $output);
                    
                    // Check if output has JSON data
                    $jsonStart = strpos($output, '{');
                    $jsonEnd = strrpos($output, '}');
                    
                    if ($jsonStart !== false && $jsonEnd !== false) {
                        // Extract only the JSON part
                        $jsonOutput = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
                        log_message("Extracted JSON: " . $jsonOutput);
                        
                        $rules = json_decode($jsonOutput, true);
                    } else {
                        log_message("No JSON found in output");
                        $rules = null;
                    }
                    
                    unlink($tempEnvFile);
                    
                    if (json_last_error() !== JSON_ERROR_NONE || $rules === null) {
                        log_message("Error parsing OpenSearch rules: " . json_last_error_msg());
                        log_message("Raw output: " . $output);
                        echo json_encode(['error' => 'Failed to parse OpenSearch rules. Check connector_api.log for details.']);
                        exit;
                    }
                    
                    echo json_encode($rules);
                    break;
                    
                case 'splunk':
                    // Similar approach for Splunk
                    $tempEnvFile = tempnam(sys_get_temp_dir(), 'splunk_env_');
                    file_put_contents($tempEnvFile, "#!/bin/bash\n");
                    
                    if (isset($connectorData['host'])) {
                        // Extract host and port from the URL if it contains a protocol
                        $host = $connectorData['host'];
                        $port = isset($connectorData['port']) && !empty($connectorData['port']) ? $connectorData['port'] : '8089';
                        
                        // Remove protocol prefix if present
                        if (preg_match('~^https?://(.+)$~i', $host, $matches)) {
                            $host = $matches[1];
                        }
                        
                        // If host includes port, extract it
                        if (preg_match('~^(.+):(\d+)$~', $host, $matches)) {
                            $host = $matches[1];
                            $port = $matches[2];
                        }
                        
                        file_put_contents($tempEnvFile, "export SPLUNK_HOST=\"{$host}\"\n", FILE_APPEND);
                        file_put_contents($tempEnvFile, "export SPLUNK_PORT=\"{$port}\"\n", FILE_APPEND);
                        log_message("Setting SPLUNK_HOST to {$host} and SPLUNK_PORT to {$port}");
                    }
                    
                    if (isset($connectorData['username']) && isset($connectorData['password'])) {
                        file_put_contents($tempEnvFile, "export SPLUNK_USERNAME=\"{$connectorData['username']}\"\n", FILE_APPEND);
                        file_put_contents($tempEnvFile, "export SPLUNK_PASSWORD=\"{$connectorData['password']}\"\n", FILE_APPEND);
                        log_message("Setting SPLUNK_USERNAME to {$connectorData['username']}");
                    }
                    
                    // Add debug information
                    file_put_contents($tempEnvFile, "echo \"Debug: Running splunk script with environment variables\"\n", FILE_APPEND);
                    file_put_contents($tempEnvFile, "python3 $splunkScript list_searches --list-saved-searches-with-triggers-json 2>&1\n", FILE_APPEND);
                    chmod($tempEnvFile, 0755);
                    
                    $output = shell_exec("bash $tempEnvFile 2>&1");
                    log_message("Splunk script output: " . $output);
                    
                    // Check if output has JSON data
                    $jsonStart = strpos($output, '{');
                    $jsonEnd = strrpos($output, '}');
                    
                    if ($jsonStart !== false && $jsonEnd !== false) {
                        // Extract only the JSON part
                        $jsonOutput = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
                        log_message("Extracted JSON: " . $jsonOutput);
                        
                        $rules = json_decode($jsonOutput, true);
                    } else {
                        log_message("No JSON found in output");
                        $rules = null;
                    }
                    
                    unlink($tempEnvFile);
                    
                    if (json_last_error() !== JSON_ERROR_NONE || $rules === null) {
                        log_message("Error parsing Splunk rules: " . json_last_error_msg());
                        log_message("Raw output: " . $output);
                        echo json_encode(['error' => 'Failed to parse Splunk rules. Check connector_api.log for details.']);
                        exit;
                    }
                    
                    echo json_encode($rules);
                    break;
                    
                default:
                    echo json_encode(['error' => 'Unsupported connector type']);
                    exit;
            }
        } catch (Exception $e) {
            log_message("Error retrieving rules: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to retrieve rules: ' . $e->getMessage()]);
        }
        break;
    case 'payload_list':
        // Vérifier si la table existe, la créer si nécessaire
        $table_exists = pg_query($conn, "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'rule_lifecycle_payloads')");
        
        if (!$table_exists) {
            log_message("Error checking if table exists");
            echo json_encode(['payloads' => []]);
            exit;
        }
        
        $exists = pg_fetch_result($table_exists, 0, 0);
        
        if ($exists === 'f') {
            log_message("Table rule_lifecycle_payloads does not exist, creating it");
            $create_table = "
                CREATE TABLE rule_lifecycle_payloads (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    code TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW()
                )
            ";
            $result = pg_query($conn, $create_table);
            if (!$result) {
                log_message("Error creating table: " . pg_last_error($conn));
                // Retourner un tableau vide au lieu d'une erreur
                echo json_encode(['payloads' => []]);
                exit;
            }
            log_message("Table created successfully");
            echo json_encode(['payloads' => []]);
            exit;
        }
        
        $result = pg_query($conn, 'SELECT * FROM rule_lifecycle_payloads ORDER BY id DESC');
        if (!$result) {
            log_message("Error retrieving payloads: " . pg_last_error($conn));
            // Retourner un tableau vide au lieu d'une erreur
            echo json_encode(['payloads' => []]);
            exit;
        }
        
        $payloads = [];
        while ($row = pg_fetch_assoc($result)) {
            $payloads[] = $row;
        }
        log_message("Found " . count($payloads) . " payloads");
        echo json_encode(['payloads' => $payloads]);
        exit;
    case 'payload_get':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $result = pg_query_params($conn, 'SELECT * FROM rule_lifecycle_payloads WHERE id = $1', [$id]);
        if (!$result || pg_num_rows($result) === 0) {
            echo json_encode(['error' => 'Payload not found']);
            exit;
        }
        $payload = pg_fetch_assoc($result);
        echo json_encode(['payload' => $payload]);
        exit;
    case 'payload_create':
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $code = trim($_POST['code'] ?? '');
        if (!$name || !$code) {
            echo json_encode(['error' => 'Name and code are required']);
            exit;
        }
        $result = pg_query_params($conn, 'INSERT INTO rule_lifecycle_payloads (name, description, code) VALUES ($1, $2, $3) RETURNING *', [$name, $description, $code]);
        if (!$result) {
            echo json_encode(['error' => 'Error creating payload']);
            exit;
        }
        $payload = pg_fetch_assoc($result);
        echo json_encode(['success' => true, 'payload' => $payload]);
        exit;
    case 'payload_update':
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $code = trim($_POST['code'] ?? '');
        if (!$id || !$name || !$code) {
            echo json_encode(['error' => 'ID, name and code are required']);
            exit;
        }
        $result = pg_query_params($conn, 'UPDATE rule_lifecycle_payloads SET name=$1, description=$2, code=$3, updated_at=NOW() WHERE id=$4 RETURNING *', [$name, $description, $code, $id]);
        if (!$result || pg_num_rows($result) === 0) {
            echo json_encode(['error' => 'Error updating payload']);
            exit;
        }
        $payload = pg_fetch_assoc($result);
        echo json_encode(['success' => true, 'payload' => $payload]);
        exit;
    case 'payload_delete':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        $result = pg_query_params($conn, 'DELETE FROM rule_lifecycle_payloads WHERE id=$1', [$id]);
        if (!$result) {
            echo json_encode(['error' => 'Error deleting payload']);
            exit;
        }
        echo json_encode(['success' => true]);
        exit;
    case 'sync_rules':
        $connector = $_POST['connector'] ?? '';
        $rules = isset($_POST['rules']) ? json_decode($_POST['rules'], true) : [];
        
        log_message("Synchronizing rules for connector: $connector, received " . count($rules) . " rules");
        
        if (!$connector || !is_array($rules)) {
            log_message("Missing or invalid parameters: connector=$connector, rules=" . json_encode($rules));
            echo json_encode(['error' => 'Missing or invalid parameters']);
            exit;
        }
        
        // Vérifier si la table existe, la créer si nécessaire
        $table_exists = pg_query($conn, "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'rule_lifecycle_rules')");
        $exists = pg_fetch_result($table_exists, 0, 0);
        
        if ($exists === 'f') {
            log_message("Table rule_lifecycle_rules does not exist, creating it");
            $create_table = "
                CREATE TABLE rule_lifecycle_rules (
                    id SERIAL PRIMARY KEY,
                    connector VARCHAR(32) NOT NULL,
                    rule_id TEXT NOT NULL,
                    rule_data JSONB NOT NULL,
                    synced_at TIMESTAMP DEFAULT NOW()
                )
            ";
            $result = pg_query($conn, $create_table);
            if (!$result) {
                log_message("Error creating table: " . pg_last_error($conn));
                echo json_encode(['error' => 'Failed to create rules table']);
                exit;
            }
            log_message("Table created successfully");
        }
        
        // Supprimer les anciennes règles de ce connecteur
        $delete_result = pg_query_params($conn, 'DELETE FROM rule_lifecycle_rules WHERE connector = $1', [$connector]);
        if (!$delete_result) {
            log_message("Error deleting old rules: " . pg_last_error($conn));
        } else {
            log_message("Deleted old rules for connector: $connector");
        }
        
        // Insérer les nouvelles règles
        $now = date('Y-m-d H:i:s');
        $success_count = 0;
        
        foreach ($rules as $rule) {
            // Déterminer l'ID de la règle en fonction du type de connecteur
            $rule_id = null;
            
            if ($connector === 'opensearch') {
                // Pour OpenSearch, l'ID est dans monitor_id ou trigger_name
                $rule_id = $rule['monitor_id'] ?? $rule['trigger_name'] ?? null;
            } else if ($connector === 'splunk') {
                // Pour Splunk, l'ID est dans name ou id
                $rule_id = $rule['name'] ?? $rule['id'] ?? null;
            } else {
                // Fallback pour autres connecteurs
                $rule_id = $rule['id'] ?? $rule['rule_id'] ?? $rule['name'] ?? null;
            }
            
            if (!$rule_id) {
                log_message("Skipping rule without ID: " . json_encode($rule));
                continue;
            }
            
            log_message("Saving rule with ID: $rule_id");
            
            // Enregistrer la règle en base
            $rule_json = json_encode($rule);
            $insert_result = pg_query_params(
                $conn, 
                'INSERT INTO rule_lifecycle_rules (connector, rule_id, rule_data, synced_at) VALUES ($1, $2, $3, $4) RETURNING id', 
                [$connector, $rule_id, $rule_json, $now]
            );
            
            if (!$insert_result) {
                log_message("Error inserting rule: " . pg_last_error($conn));
            } else {
                $success_count++;
                $row = pg_fetch_assoc($insert_result);
                log_message("Inserted rule with database ID: " . $row['id']);
            }
        }
        
        log_message("Synchronized $success_count rules out of " . count($rules) . " for connector: $connector");
        
        echo json_encode(['success' => true, 'synced_at' => $now, 'count' => $success_count]);
        exit;
    case 'get_rules':
        $connector = $_POST['connector'] ?? '';
        if (!$connector) {
            echo json_encode(['error' => 'Missing connector parameter']);
            exit;
        }
        
        log_message("Getting rules for connector: $connector");
        
        // Vérifier si la table existe, la créer si nécessaire
        $table_exists = pg_query($conn, "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'rule_lifecycle_rules')");
        $exists = pg_fetch_result($table_exists, 0, 0);
        
        if ($exists === 'f') {
            log_message("Table rule_lifecycle_rules does not exist, creating it");
            $create_table = "
                CREATE TABLE rule_lifecycle_rules (
                    id SERIAL PRIMARY KEY,
                    connector VARCHAR(32) NOT NULL,
                    rule_id TEXT NOT NULL,
                    rule_data JSONB NOT NULL,
                    synced_at TIMESTAMP DEFAULT NOW()
                )
            ";
            $result = pg_query($conn, $create_table);
            if (!$result) {
                log_message("Error creating table: " . pg_last_error($conn));
                echo json_encode(['error' => 'Failed to create rules table']);
                exit;
            }
            log_message("Table created successfully");
            // Retourne un tableau vide si la table vient d'être créée
            echo json_encode(['rules' => [], 'last_sync' => null]);
            exit;
        }
        
        $result = pg_query_params($conn, 'SELECT rule_id, rule_data, synced_at FROM rule_lifecycle_rules WHERE connector = $1 ORDER BY id ASC', [$connector]);
        if (!$result) {
            log_message("Error retrieving rules: " . pg_last_error($conn));
            echo json_encode(['error' => 'Error retrieving rules']);
            exit;
        }
        
        $rules = [];
        $last_sync = null;
        $rules_count = pg_num_rows($result);
        
        log_message("Found $rules_count rules for connector: $connector");
        
        while ($row = pg_fetch_assoc($result)) {
            $rule_data = json_decode($row['rule_data'], true);
            $rules[] = $rule_data;
            $last_sync = $row['synced_at'];
        }
        
        // Vérifier si le décodage JSON a réussi
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message("JSON decode error: " . json_last_error_msg());
        }
        
        log_message("Returning " . count($rules) . " rules for connector: $connector with last_sync: $last_sync");
        
        echo json_encode(['rules' => $rules, 'last_sync' => $last_sync]);
        exit;
    case 'get_rule_payload_map':
        echo get_rule_payload_map();
        exit;
    case 'save_rule_payload':
        $rule_id = $_POST['rule_id'] ?? '';
        $payload_id = $_POST['payload_id'] ?? '';
        echo save_rule_payload($rule_id, $payload_id);
        exit;
    case 'execute_payload':
        $content = $_POST['content'] ?? '';
        if (!$content) {
            echo json_encode(['status' => 'error', 'message' => 'Missing content']);
            exit;
        }
        $api_data = json_encode(['content' => $content]);
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
            echo $result;
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to execute payload'
            ]);
        }
        exit;
    default:
        echo json_encode(['error' => 'Action inconnue']);
        exit;
}

// Fonction pour récupérer les associations règle-payload
function get_rule_payload_map() {
    global $conn;
    
    log_message("Getting rule-payload map");
    
    // Vérifier si la table existe
    $check_table = "SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_name = 'rule_payload_map'
    ) as exists";
    
    $result = pg_query($conn, $check_table);
    $exists = pg_fetch_result($result, 0, 0);
    
    if ($exists == 'f') {
        // Créer la table si elle n'existe pas
        $create_table = "CREATE TABLE rule_payload_map (
            id SERIAL PRIMARY KEY,
            rule_id VARCHAR(255) NOT NULL,
            payload_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        pg_query($conn, $create_table);
        log_message("Created rule_payload_map table");
        
        // Retourner une map vide
        return json_encode(['map' => []]);
    }
    
    // Récupérer toutes les associations
    $query = "SELECT rule_id, payload_id FROM rule_payload_map";
    $result = pg_query($conn, $query);
    
    if (!$result) {
        log_message("Error querying rule_payload_map: " . pg_last_error($conn));
        return json_encode(['error' => 'Database error', 'map' => []]);
    }
    
    // Construire la map
    $map = [];
    while ($row = pg_fetch_assoc($result)) {
        $map[$row['rule_id']] = $row['payload_id'];
    }
    
    log_message("Found " . count($map) . " rule-payload associations");
    
    return json_encode(['map' => $map]);
}

// Fonction pour sauvegarder une association règle-payload
function save_rule_payload($rule_id, $payload_id) {
    global $conn;
    
    log_message("Saving rule-payload association: rule_id=$rule_id, payload_id=$payload_id");
    
    // Vérifier si la table existe
    $check_table = "SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_name = 'rule_payload_map'
    ) as exists";
    
    $result = pg_query($conn, $check_table);
    $exists = pg_fetch_result($result, 0, 0);
    
    if ($exists == 'f') {
        // Créer la table si elle n'existe pas
        $create_table = "CREATE TABLE rule_payload_map (
            id SERIAL PRIMARY KEY,
            rule_id VARCHAR(255) NOT NULL,
            payload_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        pg_query($conn, $create_table);
        log_message("Created rule_payload_map table");
    }
    
    // Supprimer l'ancienne association si payload_id est vide
    if (empty($payload_id)) {
        $delete_query = "DELETE FROM rule_payload_map WHERE rule_id = $1";
        $result = pg_query_params($conn, $delete_query, array($rule_id));
        
        if (!$result) {
            log_message("Error deleting rule-payload association: " . pg_last_error($conn));
            return json_encode(['error' => 'Database error while deleting association']);
        }
        
        return json_encode(['success' => true, 'message' => 'Association removed']);
    }
    
    // Vérifier si l'association existe déjà
    $check_query = "SELECT id FROM rule_payload_map WHERE rule_id = $1";
    $result = pg_query_params($conn, $check_query, array($rule_id));
    
    if (!$result) {
        log_message("Error checking rule-payload association: " . pg_last_error($conn));
        return json_encode(['error' => 'Database error while checking for existing association']);
    }
    
    // Si l'association existe, la mettre à jour
    if (pg_num_rows($result) > 0) {
        $update_query = "UPDATE rule_payload_map SET payload_id = $1 WHERE rule_id = $2";
        $result = pg_query_params($conn, $update_query, array($payload_id, $rule_id));
        
        if (!$result) {
            log_message("Error updating rule-payload association: " . pg_last_error($conn));
            return json_encode(['error' => 'Database error while updating association']);
        }
    } 
    // Sinon, créer une nouvelle association
    else {
        $insert_query = "INSERT INTO rule_payload_map (rule_id, payload_id) VALUES ($1, $2)";
        $result = pg_query_params($conn, $insert_query, array($rule_id, $payload_id));
        
        if (!$result) {
            log_message("Error inserting rule-payload association: " . pg_last_error($conn));
            return json_encode(['error' => 'Database error while creating association']);
        }
    }
    
    return json_encode(['success' => true]);
} 
 
