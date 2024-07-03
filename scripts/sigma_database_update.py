import subprocess
import shutil
import os
import tempfile

CLONE_PATH = '/temp/sigma_rules'
DESTINATION_PATH = '/Downloaded/Sigma/rules'

REPO_URL = 'https://github.com/SigmaHQ/sigma.git'
RULES_DIR = 'rules'


sigma_py_path = os.path.join(DESTINATION_PATH, 'sigma.py')

try:
    
    sigma_py_temp = None
    if os.path.exists(sigma_py_path):
        sigma_py_temp = tempfile.mktemp()
        shutil.copy(sigma_py_path, sigma_py_temp)

  
    if os.path.exists(CLONE_PATH):
        shutil.rmtree(CLONE_PATH)
    subprocess.check_call(['git', 'clone', '--depth', '1', '--filter=blob:none', '--sparse', REPO_URL, CLONE_PATH])


    os.chdir(CLONE_PATH)
    subprocess.check_call(['git', 'sparse-checkout', 'set', RULES_DIR])

    
    if os.path.exists(DESTINATION_PATH):
        shutil.rmtree(DESTINATION_PATH)
    shutil.move(os.path.join(CLONE_PATH, RULES_DIR), DESTINATION_PATH)


    if sigma_py_temp:
        shutil.copy(sigma_py_temp, sigma_py_path)
        os.unlink(sigma_py_temp)

except Exception as e:
    print(f"An error occurred while updating the Sigma database: {e}")
finally:
    if os.path.exists(CLONE_PATH):
        shutil.rmtree(CLONE_PATH)

    print("Sigma database update successfully completed.")
