import subprocess
import sys

def convert_sigma_rule(rule_path, plugin):
    """
    :param rule_path: The path to the Sigma rule file (.yml).
    :param plugin: The plugin to use ('splunk' or 'eql').
    """
    try:
     
        command = f"sudo sigma convert -t {plugin} {rule_path} --without-pipeline"
        
       
        result = subprocess.run(command, shell=True, check=False, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        
    
        print(result.stdout.decode().strip())
        
    except subprocess.CalledProcessError as e:
        print(f"Error during command execution : {e}")

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python script.py <path_to_rule.yml> <plugin>")
        sys.exit(1)
    
    rule_path = sys.argv[1]
    plugin = sys.argv[2]


    if plugin not in ['splunk', 'lucene']:
        print("The specified plugin must be 'splunk' or 'lucene'.")
        sys.exit(1)
    
    convert_sigma_rule(rule_path, plugin)