# app.py (Flask Side)

from flask import Flask, request, jsonify
from flask_cors import CORS, cross_origin
import subprocess
import os
import logging
import shlex
from datetime import datetime

app = Flask(__name__)

logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

CORS(app)

@app.route('/malware_retrieval', methods=['POST'])
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
    # Définissez le chemin complet du script manageVM.py
    script_path = '/var/www/html/scripts/manageVM.py'

    # Utilisez subprocess pour exécuter le script avec l'argument 'state'
    result = subprocess.run(['python3', script_path, 'state'], capture_output=True, text=True)

    # Vérifiez si l'exécution a réussi
    if result.returncode == 0:
        # Renvoyez la sortie standard du script
        return jsonify({"output": result.stdout}), 200
    else:
        # Renvoyez la sortie d'erreur du script
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
def restore_snapshot():
    script_path = '/var/www/html/scripts/manageVM.py'
    command = ["sudo", "python3", script_path, "restore"]
    
    try:
        
        subprocess.run(command, check=True)
        print("Le script a été exécuté avec succès.") 
        return jsonify({"message": "La VM a bien été restaurée."}), 200
    except subprocess.CalledProcessError as e:
        print(f"Erreur lors de l'exécution du script: {e}")  
        return jsonify({"error": "Une erreur est survenue lors de l'exécution du script.", "details": str(e)}), 500

@app.route('/upload_to_vm', methods=['POST'])
@cross_origin()
def upload_to_vm():
    # file_path = request.json.get('filePath')

    script_path = '/var/www/html/scripts/manageVM.py'
    command = ["sudo", "python3", script_path, "upload"]
    
    try:
        result = subprocess.run(command, capture_output=True, text=True, check=True)
        print("Sortie du script:", result.stdout)
        return jsonify({"message": "The files have been uploaded to the VM."}), 200
    except subprocess.CalledProcessError as e:
        print(f"Erreur lors de l'exécution du script: {e}")  
        return jsonify({"error": "An error has occurred during script execution.", "details": str(e)}), 500



@app.route('/execute_upload', methods=['POST'])
def execute_upload():
    data = request.json
    file_name = data.get('file_name')

    if file_name:
        script_path = '/var/www/html/scripts/malware_executable.py'

        # Exécuter le script avec subprocess
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



@app.route('/generate_logs', methods=['POST'])
def generate_logs():
    log_type = request.form.get('logType')
    log_count = request.form.get('logCount')
    time_range = request.form.get('timeRange')

    script_path = "/var/www/html/scripts/log_simulation.py"
    command = f"python3 {script_path} {log_type} {log_count} {time_range}"
    subprocess.run(command, shell=True)

    return jsonify({"message": "Successfully generated logs"})



@app.route('/update_mitre_database', methods=['POST'])
def update_mitre_database():
    try:
        working_directory = '/var/www/html/'

        subprocess.run(['python3', 'scripts/attackToExcel.py'], check=True, cwd=working_directory)

        return jsonify({'status': 'success'}), 200
    except subprocess.CalledProcessError as e:
        return jsonify({'status': 'error', 'message': str(e)}), 500


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


if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0')
