import subprocess
import shutil
import os


CLONE_PATH = '/temp/sigma_rules'
DESTINATION_PATH = '/Downloaded/Sigma/rules'


REPO_URL = 'https://github.com/SigmaHQ/sigma.git'
RULES_DIR = 'rules'  

try:
   
    if os.path.exists(CLONE_PATH):
        shutil.rmtree(CLONE_PATH)  
    
  
    subprocess.check_call(['git', 'clone', '--depth', '1', '--filter=blob:none', '--sparse', REPO_URL, CLONE_PATH])
    

    os.chdir(CLONE_PATH)
    subprocess.check_call(['git', 'sparse-checkout', 'set', RULES_DIR])
    
 
    if os.path.exists(DESTINATION_PATH):
        shutil.rmtree(DESTINATION_PATH)
    

    shutil.move(os.path.join(CLONE_PATH, RULES_DIR), DESTINATION_PATH)

except Exception as e:
       print(f"An error occurred while updating the Sigma database: {e}")
finally:

    if os.path.exists(CLONE_PATH):
        shutil.rmtree(CLONE_PATH)

    print("Sigma database update successfully completed.")
