
# Flask Application API Documentation

This documentation provides an overview and details of the API endpoints available in the Flask application. The application supports various operations, including user authentication, malware retrieval, MITRE ATT&CK execution, VM management, and log generation.

## Configuration

- **Logging:** Configured to use DEBUG level.
- **CORS:** Enabled for all routes.
- **JWT Authentication:** Uses a secret key for JWT token creation and management. Access tokens do not expire.

## Endpoints

### Authentication

#### POST /login
Authenticates a user and returns an access token.

- **Responses:**
  - 200: Returns an access token if authentication is successful.
  - 400: Bad request if username or password is not provided.
  - 401: Unauthorized if username or password is incorrect.

### Malware Retrieval

#### POST /api/malware_retrieval
Retrieves information about a specific malware family.

- **Authorization:** JWT token.
- **Parameters:** JSON body with `malwareFamily`.
- **Responses:**
  - 200: Malware information (Not fully implemented).
  - 400: Bad request if `malwareFamily` is not provided.
  - 401: Unauthorized if JWT token is not provided or invalid.

### MITRE ATT&CK Execution

#### POST /mitre_attack_execution
Executes a MITRE ATT&CK technique in a sandbox environment.

- **Parameters:** JSON body with `id` for the technique.
- **Responses:**
  - 200: Success message.
  - 500: Error if execution fails.

#### POST /api/mitre_attack_execution
Similar to `/mitre_attack_execution`, but with JWT authentication.

- **Authorization:**  JWT token.
- **Parameters:** URL parameter `technique_id`.
- **Responses:** Similar to `/mitre_attack_execution`.

### VM Management

#### POST /vm_state
Returns the state of a VM.

- **Responses:**
  - 200: Current state of the VM.
  - 400: Error if execution fails.

#### POST /vm_ip
Returns the IP address of a VM.

- **Responses:**
  - 200: IP address of the VM.
  - 400: Error if execution fails.

#### Various VM management endpoints
Includes `/start_vm_headless`, `/poweroff_vm`, `/restart_winlogbeat`, `/enable_av`, `/disable_av`, `/restore_snapshot`, and `/upload_to_vm`, which manage various VM operations like starting, powering off, managing antivirus, and more.

### Log Generation

#### POST /generate_logs
Generates logs based on specified parameters.

- **Parameters:** Form data with `logType`, `logCount`, and `timeRange`.
- **Responses:**
  - 200: Success message.

#### POST /api/generate_logs
Similar to `/generate_logs`, but with JWT authentication.

### Updating MITRE Database

#### POST /update_mitre_database
Updates the MITRE ATT&CK database.

- **Responses:**
  - 200: Success message.
  - 500: Error if execution fails.

#### POST /api/update_mitre_database
Similar to `/update_mitre_database`, but with JWT authentication.

### Use Case Execution

#### POST /execute_usecase
Executes a specified use case.

- **Parameters:** JSON body with `use_case_name`.
- **Responses:**
  - 200: Success if use case is valid and executed.
  - 400: Error if use case name is invalid.

#### POST /api/execute_usecase
Similar to `/execute_usecase`, but with JWT authentication.

### Error Handling

- **Error 422:** Handles unprocessable entity errors.

## Running the Application

The application can be run with the following command:

```
python3 app.py
```


