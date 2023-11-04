# app.py (Côté Flask)

from flask import Flask, request, jsonify
import subprocess
import os

app = Flask(__name__)

@app.route('/malware_retrieval', methods=['POST'])
def run_script():
    malware_family = request.json.get('malwareFamily')
    if not malware_family:
        return jsonify({"error": "Missing malwareFamily parameter"}), 400

    # Sécurisez l'argument avant de l'insérer dans la commande shell
    malware_family = malware_family.replace('"', '\"').replace('`', '\`').replace('$', '\$')

    # Exécutez le script Python et renvoyez le PID
    command = f"sudo python3 /var/www/html/scripts/malwareretrieval.py '{malware_family}' > /dev/null 2>&1 & ec>
    process = subprocess.Popen(command, shell=True, stdout=subprocess.PIPE)
    pid = process.stdout.read().decode('utf-8').strip()

    return jsonify({"pid": pid})

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0')
