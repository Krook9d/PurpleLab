import sys
import os
import subprocess

def main(acquisition_type):
    vm_name = "sandbox"
    output_dir = "/var/www/html/Downloaded/Forensic"

    if not os.path.exists(output_dir):
        os.makedirs(output_dir)

    if acquisition_type == "memory":
        output_file = os.path.join(output_dir, f"{vm_name}.dmp")
        command = f"vboxmanage debugvm {vm_name} dumpvmcore --filename={output_file}"
    elif acquisition_type == "disk":
        vdi_path = "/root/VirtualBox VMs/sandbox/sandbox-disk001.vmdk"
        output_file = os.path.join(output_dir, f"{vm_name}.vdi")
        command = f"sudo vboxmanage clonemedium disk \"{vdi_path}\" \"{output_file}\" --format VDI --variant Standard"
    else:
        print("Invalid argument. Use 'memory' or 'disk'.")
        sys.exit(1)

    # Check if file exists and remove it if it does
    if os.path.exists(output_file):
        os.remove(output_file)

    # Execute the command
    try:
        print(f"Executing command: {command}")  # Print the command for debugging
        result = subprocess.run(command, shell=True, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        print(f"Success: {result.stdout.decode()}")
    except subprocess.CalledProcessError as e:
        print(f"Error: {e.stderr.decode()}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python forensic_acquisition.py <memory|disk>")
        sys.exit(1)

    acquisition_type = sys.argv[1]
    main(acquisition_type)
