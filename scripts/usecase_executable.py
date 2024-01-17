import subprocess
import sys

def execute_use_case(use_case_name):
    # Dictionnaire des chemins des exécutables pour chaque use case
    use_cases = {
        "useCase1": "C:\\Users\\oem\\Documents\\usecase\\encrypt.exe",
        "useCase2": "C:\\Users\\oem\\Documents\\usecase\\excel.exe"
    }

    # Vérifier si le use case donné est dans notre dictionnaire
    if use_case_name in use_cases:
        file_path = use_cases[use_case_name]
        command = [
            "VBoxManage",
            "guestcontrol",
            "sandbox",
            "run",
            "--exe",
            file_path,
            "--username",
            "oem",
            "--password",
            "oem",
            "--"
        ]
        
        try:
            subprocess.run(command, check=True)
            print(f"Use case '{use_case_name}' executed successfully.")
        except subprocess.CalledProcessError as e:
            print(f"Failed to execute use case '{use_case_name}': {e}", file=sys.stderr)
    else:
        print(f"Use case '{use_case_name}' is not recognized.", file=sys.stderr)

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python usecase_executable.py <usecase_name>", file=sys.stderr)
        sys.exit(1)

    use_case_name = sys.argv[1]
    execute_use_case(use_case_name)
