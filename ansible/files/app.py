from flask import Flask, request, jsonify, current_app, send_from_directory
from flask_cors import CORS, cross_origin
from flask_jwt_extended import JWTManager, jwt_required, create_access_token, get_jwt_identity
import subprocess
import os
import logging
from logging.handlers import RotatingFileHandler
import shlex
from datetime import datetime
from werkzeug.utils import secure_filename
import base64
import json


app = Flask(__name__)

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)
logger.setLevel(logging.INFO)

# Configure CORS
CORS(app, resources={r"/*": {"origins": "*"}}) 

# Configure JWT
app.config['JWT_SECRET_KEY'] = 'changeandsecuriseme'  
app.config['JWT_ACCESS_TOKEN_EXPIRES'] = False
app.config['UPLOAD_FOLDER'] = '/var/www/html/Downloaded/upload/' 
jwt = JWTManager(app)


@app.before_request
def log_request_info():
    logger.debug(f"Headers: {request.headers}")
    logger.debug(f"Body: {request.get_data()}")

def clear_upload_folder():
    """ Deletes all files in the upload directory with detailed logging. """
    folder = app.config['UPLOAD_FOLDER']
    print(f"Clearing files in folder: {folder}")
    for filename in os.listdir(folder):
        file_path = os.path.join(folder, filename)
        try:
            if os.path.isfile(file_path) or os.path.islink(file_path):
                os.unlink(file_path)
                print(f"Deleted file: {file_path}")
            elif os.path.isdir(file_path):
                shutil.rmtree(file_path)
                print(f"Deleted directory: {file_path}")
        except Exception as e:
            print(f'Failed to delete {file_path}. Reason: {e}')

@app.route('/api/upload', methods=['POST'])
@jwt_required()
def upload_file():
    """ Route to upload files. Clears upload folder at the start. """
    clear_upload_folder()  # 

    if 'file' not in request.files:
        return jsonify({"error": "No file part"}), 400

    file = request.files['file']
    if file.filename == '':
        return jsonify({"error": "No selected file"}), 400

    filename = secure_filename(file.filename)
    file_path = os.path.join(current_app.config['UPLOAD_FOLDER'], filename)
    file.save(file_path)
    
    script_path = '/var/www/html/scripts/extension.py'
    subprocess.run(f"sudo python3 {script_path}", shell=True, check=True)

    # Get updated filename with extension
    updated_filename = next((f for f in os.listdir(current_app.config['UPLOAD_FOLDER']) if os.path.isfile(os.path.join(current_app.config['UPLOAD_FOLDER'], f))), None)
    
    return jsonify({"success": "File uploaded successfully", "filename": updated_filename}), 200


@app.route('/login', methods=['POST'])
def login():
    data = request.json
    if not data:
        return jsonify({"msg": "Bad request. Please provide username and password"}), 400

    username = data.get('username')
    password = data.get('password')
    if not username or not password:
        return jsonify({"msg": "Bad request. Please provide username and password"}), 400

    if username != 'admin' or password != 'password':  
        return jsonify({"msg": "Bad username or password"}), 401

    access_token = create_access_token(identity=username)
    return jsonify(access_token=access_token)



@app.route('/api/malware_retrieval', methods=['POST'])
@jwt_required(optional=True)
def api_malware_retrieval():
    if request.remote_addr != "127.0.0.1" and not get_jwt_identity():
        return jsonify({"msg": "Access denied"}), 401
    malware_family = request.json.get('malwareFamily')
    if not malware_family:
        return jsonify({"error": "Missing malwareFamily parameter"}), 400


@app.route('/mitre_attack_execution', methods=['POST'])
@cross_origin()
def mitre_attack_execution():
    try:
        # Retrieve the technique ID sent from the front-end
        data = request.get_json()
        technique_id = data['id']

        # Format the PowerShell command
        powershell_command = f"\"& {{Import-Module 'C:\\\\AtomicRedTeam\\\\invoke-atomicredteam\\\\Invoke-AtomicRedTeam.psd1' -Force; Invoke-AtomicTest {technique_id}}}\""

        # Execute the command using VBoxManage
        subprocess.run(
            ['VBoxManage', 'guestcontrol', 'sandbox', '--username', 'oem', '--password', 'oem', 'run', '--exe', 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe', '--', 'powershell.exe', '-NoProfile', '-NonInteractive', '-Command', powershell_command],
            check=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE
        )

        return jsonify({'status': 'success'}), 200

    except subprocess.CalledProcessError as e:
        # Output the error message
        error_message = e.stderr.decode()
        return jsonify({'status': 'error', 'message': error_message}), 500


@app.route('/api/mitre_attack_execution', methods=['POST'])
@jwt_required(optional=True)
def api_mitre_attack_execution():
    if request.remote_addr != "127.0.0.1" and not get_jwt_identity():
        return jsonify({"msg": "Access denied"}), 401

    
    technique_id = request.args.get('technique_id')
    if not technique_id:
        return jsonify({"msg": "technique_id is required"}), 400

  
    powershell_command = f"\"& {{Import-Module 'C:\\\\AtomicRedTeam\\\\invoke-atomicredteam\\\\Invoke-AtomicRedTeam.psd1' -Force; Invoke-AtomicTest {technique_id}}}\""

    # Execute the command using VBoxManage
    try:
        result = subprocess.run(
            ['VBoxManage', 'guestcontrol', 'sandbox', '--username', 'oem', '--password', 'oem', 'run', '--exe', 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe', '--', 'powershell.exe', '-NoProfile', '-NonInteractive', '-Command', powershell_command],
            check=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE
        )
        return jsonify({"msg": "Command executed successfully", "output": result.stdout.decode(), "error": result.stderr.decode()}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({"msg": "Command execution failed", "error": str(e)}), 500


    


@app.route('/malware_retrieval', methods=['POST'])
@cross_origin()
def malware_retrieval():
    malware_family = request.json.get('malwareFamily')
    if not malware_family:
        return jsonify({"error": "Missing malwareFamily parameter"}), 400

    malware_family = malware_family.replace('"', '\"').replace('`', '\`').replace('$', '\$')

    # Executes the Python script and returns the PID
    command = f"sudo python3 /var/www/html/scripts/malwareretrieval.py '{malware_family}' > /dev/null 2>&1 & echo $!"
    process = subprocess.Popen(command, shell=True, stdout=subprocess.PIPE)
    pid = process.stdout.read().decode('utf-8').strip()

    return jsonify({"pid": pid})


@app.route('/vm_state', methods=['GET'])
@cross_origin()

def vm_state():
 
    script_path = '/var/www/html/scripts/manageVM.py'

 
    result = subprocess.run(['python3', script_path, 'state'], capture_output=True, text=True)

 
    if result.returncode == 0:
     
        return jsonify({"output": result.stdout}), 200
    else:
  
        return jsonify({"error": result.stderr}), 400

@app.route('/vm_ip', methods=['GET'])
@cross_origin()
def vm_ip():
    script_path = '/var/www/html/scripts/manageVM.py'

    result = subprocess.run(['python3', script_path, 'ip'], capture_output=True, text=True)

    if result.returncode == 0:
        ip_address = result.stdout.strip().split(' ')[-1]
        return jsonify({"ip": ip_address}), 200
    else:
        return jsonify({"error": result.stderr}), 400

@app.route('/')
def index():
    return "Flask server is running", 200

@app.route('/restore_snapshot', methods=['POST'])
@cross_origin()
def restore_snapshot():
    script_path = '/var/www/html/scripts/manageVM.py'
    command = ["sudo", "python3", script_path, "restore"]
    
    try:
        
        subprocess.run(command, check=True)
        print("The script has been executed successfully.") 
        return jsonify({"message": "The VM has been restored."}), 200
    except subprocess.CalledProcessError as e:
        print(f"Error during script execution: {e}")  
        return jsonify({"error": "An error has occurred while executing the script.", "details": str(e)}), 500

@app.route('/upload_to_vm', methods=['POST'])
@cross_origin()
def upload_to_vm():
    # file_path = request.json.get('filePath')

    script_path = '/var/www/html/scripts/manageVM.py'
    command = ["sudo", "python3", script_path, "upload"]
    
    try:
        result = subprocess.run(command, capture_output=True, text=True, check=True)
        print("Script output:", result.stdout)
        return jsonify({"message": "The files have been uploaded to the VM."}), 200
    except subprocess.CalledProcessError as e:
        print(f"Error during script execution: {e}")  
        return jsonify({"error": "An error has occurred during script execution.", "details": str(e)}), 500

@app.route('/execute_upload', methods=['POST'])
@cross_origin()
def execute_upload():
    data = request.json
    file_name = data.get('file_name')

    if file_name:
        script_path = '/var/www/html/scripts/malware_executable.py'

        try:
            log_message = f"{datetime.now()} - action : Malware was downloaded\n"
                # Log the action
            with open('/var/www/html/purplelab.log', 'a') as log_file:
                log_file.write(log_message)
            result = subprocess.run(['python3', script_path, file_name], check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            response = {
                'status': 'success',
                'message': f'Script executed successfully for file {file_name}.',
                'output': result.stdout.decode()
            }
            
        except subprocess.CalledProcessError as e:
            response = {
                'status': 'error',
                'message': f'Error executing script for file {file_name}.',
                'error': e.stderr.decode()
            }
            log_message = f"{datetime.now()} - action : Malware download failed\n"
    else:
        response = {
            'status': 'error',
            'message': 'Missing or empty file name.'
        }
        log_message = f"{datetime.now()} - action : Missing or empty file name\n"

    return jsonify(response)

@app.route('/api/execute_upload', methods=['POST'])
@jwt_required(optional=True)
def api_execute_upload():
    if request.remote_addr != "127.0.0.1" and not get_jwt_identity():
        return jsonify({"msg": "Access denied"}), 401

    file_name = request.args.get('file_name')
    if file_name:
        script_path = '/var/www/html/scripts/malware_executable.py'

        try:
            log_message = f"{datetime.now()} - action : Malware was downloaded\n"
            with open('/var/www/html/purplelab.log', 'a') as log_file:
                log_file.write(log_message)

            result = subprocess.run(['python3', script_path, file_name], check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            response = {
                'status': 'success',
                'message': f'Script executed successfully for file {file_name}.',
                'output': result.stdout.decode()
            }

        except subprocess.CalledProcessError as e:
            response = {
                'status': 'error',
                'message': f'Error executing script for file {file_name}.',
                'error': str(e)
            }
            log_message = f"{datetime.now()} - action : Malware download failed\n"
            with open('/var/www/html/purplelab.log', 'a') as log_file:
                log_file.write(log_message)

    else:
        response = {
            'status': 'error',
            'message': 'Missing or empty file name.'
        }
        log_message = f"{datetime.now()} - action : Missing or empty file name\n"
        with open('/var/www/html/purplelab.log', 'a') as log_file:
            log_file.write(log_message)

    return jsonify(response)


@app.route('/generate_logs', methods=['POST'])
@cross_origin()
def generate_logs():
    log_type = request.form.get('logType')
    log_count = request.form.get('logCount')
    time_range = request.form.get('timeRange')

    script_path = "/var/www/html/scripts/log_simulation.py"
    command = f"python3 {script_path} {log_type} {log_count} {time_range}"
    subprocess.run(command, shell=True)

    return jsonify({"message": "Successfully generated logs"})

@app.route('/api/generate_logs', methods=['POST'])
@jwt_required(optional=True)
def api_generate_logs():
    if request.remote_addr != "127.0.0.1" and not get_jwt_identity():
        return jsonify({"msg": "Access denied"}), 401

    log_type = request.form.get('logType')
    log_count = request.form.get('logCount')
    time_range = request.form.get('timeRange')

    script_path = "/var/www/html/scripts/log_simulation.py"
    command = f"python3 {script_path} {log_type} {log_count} {time_range}"
    subprocess.run(command, shell=True)

    return jsonify({"message": "Successfully generated logs"})


@app.route('/update_mitre_database', methods=['POST'])
@cross_origin()
def update_mitre_database():
    try:
        # Utiliser le script PHP pour exécuter le script Python avec les variables d'environnement
        result = subprocess.run(['php', '/var/www/html/scripts/php/update_mitre.php'], 
                               check=True, 
                               stdout=subprocess.PIPE, 
                               stderr=subprocess.PIPE)
        
        # Essayer de parser la sortie JSON
        try:
            output = json.loads(result.stdout.decode('utf-8'))
            return jsonify(output), 200
        except json.JSONDecodeError:
            # Si la sortie n'est pas du JSON valide, retourner la sortie brute
            return jsonify({
                'status': 'success',
                'message': 'Le script de mise à jour a été exécuté',
                'raw_output': result.stdout.decode('utf-8')
            }), 200
            
    except subprocess.CalledProcessError as e:
        return jsonify({
            'status': 'error', 
            'message': 'Erreur lors de l\'exécution du script PHP',
            'error': str(e),
            'stderr': e.stderr.decode('utf-8') if e.stderr else ""
        }), 500

@app.route('/api/update_mitre_database', methods=['POST'])
@jwt_required(optional=True)
def api_update_mitre_database():
    if request.remote_addr != "127.0.0.1" and not get_jwt_identity():
        return jsonify({"msg": "Access denied"}), 401

    try:
        # Utiliser le script PHP pour exécuter le script Python avec les variables d'environnement
        result = subprocess.run(['php', '/var/www/html/scripts/php/update_mitre.php'], 
                               check=True, 
                               stdout=subprocess.PIPE, 
                               stderr=subprocess.PIPE)
        
        # Essayer de parser la sortie JSON
        try:
            output = json.loads(result.stdout.decode('utf-8'))
            return jsonify(output), 200
        except json.JSONDecodeError:
            # Si la sortie n'est pas du JSON valide, retourner la sortie brute
            return jsonify({
                'status': 'success',
                'message': 'Le script de mise à jour a été exécuté',
                'raw_output': result.stdout.decode('utf-8')
            }), 200
            
    except subprocess.CalledProcessError as e:
        return jsonify({
            'status': 'error', 
            'message': 'Erreur lors de l\'exécution du script PHP',
            'error': str(e),
            'stderr': e.stderr.decode('utf-8') if e.stderr else ""
        }), 500


@app.route('/execute_usecase', methods=['POST'])
@cross_origin()
def execute_usecase():
    data = request.get_json()
    use_case_name = data.get('use_case_name')

    script_path = '/var/www/html/scripts/usecase_executable.py'

    if use_case_name in ['useCase1', 'useCase2']:
        try:
            result = subprocess.run(['python3', script_path, use_case_name], capture_output=True, text=True)
            if result.returncode == 0:
                return jsonify({"success": True}), 200
            else:
                return jsonify({"success": False, "error": result.stderr}), 400
        except Exception as e:
            return jsonify({"success": False, "error": str(e)}), 500
    else:
        return jsonify({"success": False, "error": "Invalid use case name"}), 400
    
@app.route('/api/execute_usecase', methods=['POST'])
@jwt_required(optional=True)
def api_execute_usecase():
    if request.remote_addr != "127.0.0.1" and not get_jwt_identity():
        return jsonify({"msg": "Access denied"}), 401

    data = request.get_json()
    use_case_name = data.get('use_case_name')

    script_path = '/var/www/html/scripts/usecase_executable.py'

    if use_case_name in ['useCase1', 'useCase2']:
        try:
            result = subprocess.run(['python3', script_path, use_case_name], capture_output=True, text=True)
            if result.returncode == 0:
                return jsonify({"success": True}), 200
            else:
                return jsonify({"success": False, "error": result.stderr}), 400
        except Exception as e:
            return jsonify({"success": False, "error": str(e)}), 500
    else:
        return jsonify({"success": False, "error": "Invalid use case name"}), 400


@app.route('/poweroff_vm', methods=['POST'])
@cross_origin()
def poweroff_vm_route():
    script_path = '/var/www/html/scripts/manageVM.py'
    try:
        subprocess.run(['sudo', 'python3', script_path, 'poweroff'], check=True)
        return jsonify({"message": "VM successfully powered off."}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({"error": "Error occurred while powering off the VM.", "details": str(e)}), 500

@app.route('/api/poweroff_vm', methods=['POST'])
@jwt_required(optional=True)
def api_poweroff_vm_route():
    if request.remote_addr != "127.0.0.1" and not get_jwt_identity():
        return jsonify({"msg": "Access denied"}), 401

    script_path = '/var/www/html/scripts/manageVM.py'
    try:
        subprocess.run(['sudo', 'python3', script_path, 'poweroff'], check=True)
        return jsonify({"message": "VM successfully powered off."}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({"error": "Error occurred while powering off the VM.", "details": str(e)}), 500


@app.route('/start_vm_headless', methods=['POST'])
@cross_origin()
def start_vm_headless_route():
    script_path = '/var/www/html/scripts/manageVM.py'
    try:
        subprocess.run(['sudo', 'python3', script_path, 'startheadless'], check=True)
        return jsonify({"message": "VM successfully started in headless mode."}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({"error": "Error occurred while starting the VM in headless mode.", "details": str(e)}), 500
    
@app.route('/api/start_vm_headless', methods=['POST'])
@jwt_required(optional=True)
def api_start_vm_headless_route():
    if request.remote_addr != "127.0.0.1" and not get_jwt_identity():
        return jsonify({"msg": "Access denied"}), 401

    script_path = '/var/www/html/scripts/manageVM.py'
    try:
        subprocess.run(['sudo', 'python3', script_path, 'startheadless'], check=True)
        return jsonify({"message": "VM successfully started in headless mode."}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({"error": "Error occurred while starting the VM in headless mode.", "details": str(e)}), 500


@app.route('/disable_av', methods=['POST'])
@cross_origin()
def disable_av():
    script_path = '/var/www/html/scripts/manageVM.py'
    try:
        subprocess.run(['sudo', 'python3', script_path, 'disableav'], check=True)
        return jsonify({"message": "Antivirus successfully disabled."}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({"error": "Error occurred while disabling the antivirus.", "details": str(e)}), 500


@app.route('/enable_av', methods=['POST'])
@cross_origin()
def enable_av():
    script_path = '/var/www/html/scripts/manageVM.py'
    try:
        subprocess.run(['sudo', 'python3', script_path, 'enableav'], check=True)
        return jsonify({"message": "Antivirus successfully enabled."}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({"error": "Error occurred while enabling the antivirus.", "details": str(e)}), 500


@app.route('/restart_winlogbeat', methods=['POST'])
@cross_origin()
def restart_winlogbeat():
    script_path = '/var/www/html/scripts/manageVM.py'
    try:
        subprocess.run(['sudo', 'python3', script_path, 'restartwinlogbeat'], check=True)
        return jsonify({"message": "Winlogbeat service successfully restarted."}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({"error": "Error occurred while restarting Winlogbeat service.", "details": str(e)}), 500


@app.route('/convert_sigma', methods=['POST'])
@cross_origin()
def convert_sigma():
    data = request.get_json()
    rule_path = data.get('rule_path', '')  
    plugin = data.get('plugin', '') 

 
    sigma_script_path = '/var/www/html/Downloaded/Sigma/rules/sigma.py'
    absolute_rule_path = os.path.join('/var/www/html/Downloaded/Sigma/rules', rule_path)

    command = ['sudo', 'python3', sigma_script_path, absolute_rule_path, plugin]

    try:
    
        result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)

        if not result.stdout.strip() and result.stderr.strip():
            response = {
                "status": "error",
                "error": result.stderr
            }
        else:
            response = {
                "status": "success",
                "output": result.stdout
            }

        return jsonify(response), 200 if result.returncode == 0 else 400

    except subprocess.CalledProcessError as e:
        logger.error(f"Error executing sigma.py: {e}")
        return jsonify({
            "status": "error",
            "message": "An error occurred while converting the sigma rule.",
            "details": str(e)
        }), 500
    

@app.route('/forensic_acquisition', methods=['POST'])
@cross_origin()
def forensic_acquisition():
    acquisition_type = request.json.get('type')

    if acquisition_type not in ['memory', 'disk']:
        return jsonify({"error": "Invalid type parameter. Use 'memory' or 'disk'."}), 400

    script_path = '/var/www/html/scripts/forensic_acquisition.py'

    result = subprocess.run(['sudo', 'python3', script_path, acquisition_type], capture_output=True, text=True)

    if result.returncode == 0:
        return jsonify({"output": result.stdout}), 200
    else:
        return jsonify({"error": result.stderr}), 400

@app.route('/download/<filename>', methods=['GET'])
@cross_origin()
def download_file(filename):
    directory = '/var/www/html/Downloaded/Forensic'
    return send_from_directory(directory, filename)


@app.route('/update_sigma_rules', methods=['POST'])
@cross_origin()
def update_sigma_rules():
    try:
        subprocess.run(['python3', '/var/www/html/scripts/sigma_database_update.py'], check=True)
        return jsonify({"message": "Sigma rules updated successfully"}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({"error": str(e)}), 500




@app.errorhandler(422)
def handle_unprocessable_entity(err):
    logger.error(f"Unprocessable Entity: {err}")
    response = jsonify({'error': 'Unprocessable Entity', 'message': str(err)})
    return response, 422


@app.route('/api/execute_payload', methods=['POST'])
@jwt_required(optional=True)
def execute_payload():
    if request.remote_addr != "127.0.0.1" and not get_jwt_identity():
        return jsonify({"msg": "Access denied"}), 401

    payload_content = request.json.get('content')
    if not payload_content:
        return jsonify({"error": "Missing payload content"}), 400

    ps_script = f"""
$OutputEncoding = [System.Text.Encoding]::UTF8
$ErrorActionPreference = 'SilentlyContinue'
$ProgressPreference = 'SilentlyContinue'  # Désactiver l'affichage de progression

# Balise de début pour identifier le début des résultats réels
Write-Output "BEGIN_RESULTS"
try {{
{payload_content}
}} catch {{
    Write-Output "ERREUR: $($_.Exception.Message)"
}}
# Balise de fin pour identifier la fin des résultats réels
Write-Output "END_RESULTS"
"""

    ps_bytes = ps_script.encode('utf-16-le')
    encoded_ps = base64.b64encode(ps_bytes).decode('ascii')

    try:

        result = subprocess.run(
            ['VBoxManage', 'guestcontrol', 'sandbox', 
             '--username', 'oem', '--password', 'oem', 
             'run', '--exe', 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe', 
             '--', 'powershell.exe', '-NoProfile', '-EncodedCommand', encoded_ps],
            check=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=False  
        )
        
        stdout_decoded = result.stdout.decode('cp1252', errors='replace')
        stderr_decoded = result.stderr.decode('cp1252', errors='replace')
        
        start_marker = "BEGIN_RESULTS"
        end_marker = "END_RESULTS"
        
        if start_marker in stdout_decoded and end_marker in stdout_decoded:
            start_idx = stdout_decoded.index(start_marker) + len(start_marker)
            end_idx = stdout_decoded.index(end_marker)
            stdout_decoded = stdout_decoded[start_idx:end_idx].strip()
        

        if stderr_decoded and stderr_decoded.strip().startswith("<"):

            stderr_decoded = ""
        
        return jsonify({
            "status": "success",
            "output": stdout_decoded,
            "error": stderr_decoded
        }), 200

    except subprocess.CalledProcessError as e:
        stderr = e.stderr.decode('cp1252', errors='replace') if e.stderr else ""
        
        if stderr and stderr.strip().startswith("<"):
            stderr = "Commande exécutée avec des avertissements."
        
        return jsonify({
            "status": "error",
            "message": "Command execution failed",
            "error": str(e),
            "stderr": stderr
        }), 500

@app.route('/refresh_alienvault', methods=['POST'])
@cross_origin()
def refresh_alienvault():
    try:
        # Supprimer le fichier JSON avant de lancer le script
        json_path = '/var/www/html/alienvault/dashboard_data.json'
        if os.path.exists(json_path):
            os.remove(json_path)
        # Exécuter le script Python
        subprocess.run(['python3', '/var/www/html/alienvault/alienvault.py'],
                       stdout=subprocess.PIPE,
                       stderr=subprocess.PIPE,
                       check=True)
        return jsonify({
            "status": "success",
            "message": "AlienVault data successfully refreshed"
        }), 200
    except subprocess.CalledProcessError as e:
        return jsonify({
            "status": "error",
            "message": f"Failed to refresh AlienVault data: {str(e)}",
            "stderr": e.stderr.decode() if e.stderr else ""
        }), 500

@app.route('/api/refresh_alienvault', methods=['POST'])
@jwt_required(optional=True)
def api_refresh_alienvault():
    if request.remote_addr != "127.0.0.1" and not get_jwt_identity():
        return jsonify({"msg": "Access denied"}), 401
    
    try:
        # Supprimer le fichier JSON avant de lancer le script
        json_path = '/var/www/html/alienvault/dashboard_data.json'
        if os.path.exists(json_path):
            os.remove(json_path)
        # Exécuter le script Python
        result = subprocess.run(['python3', '/var/www/html/alienvault/alienvault.py'], 
                              stdout=subprocess.PIPE, 
                              stderr=subprocess.PIPE,
                              check=True)
        
        return jsonify({
            "status": "success", 
            "message": "AlienVault data successfully refreshed",
            "stdout": result.stdout.decode(),
            "stderr": result.stderr.decode()
        }), 200
    except subprocess.CalledProcessError as e:
        return jsonify({
            "status": "error", 
            "message": f"Failed to refresh AlienVault data: {str(e)}",
            "stderr": e.stderr.decode() if e.stderr else ""
        }), 500


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
