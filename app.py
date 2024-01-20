# app.py (Côté Flask)

from flask import Flask, request, jsonify
from flask_cors import CORS, cross_origin
import subprocess
import os
import logging
import shlex
from datetime import datetime



# Configurer le logging


app = Flask(__name__)

logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

CORS(app)

@app.route('/malware_retrieval', methods=['POST'])
def malware_retrieval():
    malware_family = request.json.get('malwareFamily')
    if not malware_family:
        return jsonify({"error": "Missing malwareFamily parameter"}), 400

    # Sécurisez l'argument avant de l'insérer dans la commande shell
    malware_family = malware_family.replace('"', '\"').replace('`', '\`').replace('$', '\$')

    # Exécutez le script Python et renvoyez le PID
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
    # Définissez le chemin complet du script manageVM.py
    script_path = '/var/www/html/scripts/manageVM.py'

    # Utilisez subprocess pour exécuter le script avec l'argument 'ip'
    result = subprocess.run(['python3', script_path, 'ip'], capture_output=True, text=True)

    # Vérifiez si l'exécution a réussi
    if result.returncode == 0:
        # Parsez la sortie pour extraire l'IP
        ip_address = result.stdout.strip().split(' ')[-1]
        return jsonify({"ip": ip_address}), 200
    else:
        # Renvoyez la sortie d'erreur du script
        return jsonify({"error": result.stderr}), 400


@app.route('/')
def index():
    return "Flask server is running", 200


@app.route('/restore_snapshot', methods=['POST'])
def restore_snapshot():
    script_path = '/var/www/html/scripts/manageVM.py'
    # Assurez-vous que l'argument 'restore' est bien passé au script
    command = ["sudo", "python3", script_path, "restore"]
    
    try:
        # Exécutez le script avec l'argument 'restore'
        subprocess.run(command, check=True)
        print("Le script a été exécuté avec succès.")  # Ajoutez ceci pour le débogage
        return jsonify({"message": "La VM a bien été restaurée."}), 200
    except subprocess.CalledProcessError as e:
        print(f"Erreur lors de l'exécution du script: {e}")  # Ajoutez ceci pour le débogage
        return jsonify({"error": "Une erreur est survenue lors de l'exécution du script.", "details": str(e)}), 500

@app.route('/upload_to_vm', methods=['POST'])
@cross_origin()
def upload_to_vm():
    # Obtenez des données supplémentaires de la requête si nécessaire, par exemple :
    # file_path = request.json.get('filePath')

    script_path = '/var/www/html/scripts/manageVM.py'
    command = ["sudo", "python3", script_path, "upload"]
    
    try:
        # Exécutez le script avec l'argument 'upload'
        result = subprocess.run(command, capture_output=True, text=True, check=True)
        # Afficher la sortie du script pour déboguer
        print("Sortie du script:", result.stdout)
        return jsonify({"message": "Les fichiers ont été téléchargés vers la VM."}), 200
    except subprocess.CalledProcessError as e:
        print(f"Erreur lors de l'exécution du script: {e}")  # Débogage
        return jsonify({"error": "Une erreur est survenue lors de l'exécution du script.", "details": str(e)}), 500



@app.route('/execute_upload', methods=['POST'])
def execute_upload():
    # Récupérer les données envoyées en POST
    data = request.json
    file_name = data.get('file_name')

    if file_name:
        # Chemin vers votre script Python
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
            'message': 'Nom du fichier manquant ou vide.'
        }
        log_message = f"{datetime.now()} - action : Missing or empty file name\n"

    

    # Renvoyer la réponse au format JSON
    return jsonify(response)



@app.route('/generate_logs', methods=['POST'])
def generate_logs():
    log_type = request.form.get('logType')
    log_count = request.form.get('logCount')
    time_range = request.form.get('timeRange')

    # Appeler votre script Python ici
    script_path = "/var/www/html/scripts/log_simulation.py"
    command = f"python3 {script_path} {log_type} {log_count} {time_range}"
    subprocess.run(command, shell=True)

    return jsonify({"message": "Logs générés avec succès"})



@app.route('/update_mitre_database', methods=['POST'])
def update_mitre_database():
    try:
        # Définir le répertoire de travail pour la commande
        working_directory = '/var/www/html/'

        # Exécuter le script Python dans le répertoire spécifié
        subprocess.run(['python3', 'scripts/attackToExcel.py'], check=True, cwd=working_directory)

        # Retourner une réponse de succès
        return jsonify({'status': 'success'}), 200
    except subprocess.CalledProcessError as e:
        # Retourner une réponse d'erreur si quelque chose ne va pas
        return jsonify({'status': 'error', 'message': str(e)}), 500



@app.route('/mitre_attack_execution', methods=['POST'])
@cross_origin()
def mitre_attack_execution():
    try:
        # Récupérer l'ID de la technique envoyé depuis le front-end
        data = request.get_json()
        technique_id = data['id']

        # Construire la commande PowerShell
        powershell_command = f"& {{Import-Module 'C:\\AtomicRedTeam\\invoke-atomicredteam\\Invoke-AtomicRedTeam.psd1' -Force; Invoke-AtomicTest {technique_id}}}"
        
        # Exécuter la commande sur la machine virtuelle
        subprocess.run(
            ['VBoxManage', 'guestcontrol', 'sandbox', '--username', 'oem', '--password', 'oem', 'run', '--exe', 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe', '--', 'powershell.exe', '-NoProfile', '-NonInteractive', '-Command', powershell_command],
            check=True,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL
        )

        # Retourner une réponse de succès
        return jsonify({'status': 'success'}), 200

    except subprocess.CalledProcessError as e:
        # Retourner une réponse d'erreur si la commande échoue
        return jsonify({'status': 'error', 'message': str(e)}), 500


@app.route('/execute_usecase', methods=['POST'])
@cross_origin()
def execute_usecase():
    data = request.get_json()
    use_case_name = data.get('use_case_name')

    script_path = '/var/www/html/scripts/usecase_executable.py'

    # Assurez-vous que les noms des use cases correspondent à ceux attendus par votre script Python
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
