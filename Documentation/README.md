<p align="center">
  <img src="/MD_image/logo.png" alt="Logo PurpleLab"/>
</p>


# Table of content

- [Table of content](#table-of-content)
- [What is PurpleLab ?](#what-is-purplelab-)
- [Installation procedure](#installation)
	- [Requirements](#Requirements)
	- [Installation](#installation)
                - [Accounts](#Accounts)
                - [ELK Configuration](#ELK-Configuration)
                - [VM logs configuration](#VM-logs-configuration)
- [Usage](#Usage)
	- [Home Page](#home-page-)
	- [Hunting Page](#hunting-page-)
	- [Mitre Att&ck Page](#mitre-attck-page-)
	- [Malware Page](#malware-page-)
	- [Log simulation Page](#log-simulation-page-)
	- [Usage Case Page](#usage-case-page-)
	- [Sharing Page](#sharing-page-)
	- [Health Page](#health-page-)


# What is PurpleLab ?

This solution will allow you to easily deploy an entire lab to create/test your detection rules, simulate logs, play tests, download and run malware and mitre attack techniques, restore the sandbox and many other features.

The lab contains : 

- A web interface with a complete front end to control features
- The Virtualbox tool with a ready-to-use Windows 10 VM
- A Flask back end
- A mysql database
- A pfsense (coming soon)
- A linux VM (coming soon)


# Installation procedure

> ⚠️ To have a fully clean installation, you have to follow all the chapiter of the installation procedure from requirements to account

> ⚠️ NOTE: This lab has not been hardened in any way and runs with basic credentials. Please do not connect or bridge it to any networks you care about, or secure it yourself with a PKI, better authentication systems, etc.

## Requirements

Minimum Hardware resources : 
- 8 cores
- 13GB RAM

A clean installation of Ubuntu server 22.04 available : [Here](https://ubuntu.com/download/server?ref=linuxhandbook.com)

> ⚠️ If you use Ubuntu server 23.10 you will have issues with python library installation 


⚠️ Enable Hardware Virtualization ⚠️ :

In VM Ware workstation -> Go to the settings of the VM -> Processors -> Virtualization engine -> enable "Virtualize Intel VT-x/EPT or AMD-V/RVI" 

In VirtualBox -> Select the relevant virtual machine -> Right-click -> Settings -> System -> Processor -> Check "Enable Nested VT-x/AMD-V".

On the physical machine (host) -> Access the BIOS/UEFI settings -> Look for an option to enable hardware virtualization (VT-x for Intel or AMD-V for AMD) in the CPU or motherboard settings. This option may be called "Intel Virtualization Technology," "VT-x," "AMD-V," "Virtualization Extensions," or something similar. Ensure it is enabled if not already, save the changes, and restart your computer.

In your home folder, Download repo :
```bash
git clone https://github.com/Krook9d/PurplelabDev.git
```
Preparing files : 
```bash
mv PurpleLab/install.sh .
```

## installation

run : 

```bash
sudo bash install.sh
```

### Accounts

#### Admin Account

By default, an admin account is created during the installation process. This account is essential for generating API tokens and has administrative privileges within the system. The password for this admin account is randomly generated as part of the installation and is automatically saved in the database to ensure secure access.

The password for the admin account can be found in the "admin.txt" file, which is located in the home directory of the user. This file contains the password and other critical installation details. It is crucial to keep this information secure and accessible only to authorized personnel to maintain the integrity and security of the PurpleLab environment.


#### User Account

You have to set up your accounts. after installation :

1. Type the IP adress of your server
<img src="/MD_image/connexion.png" width="800" alt="Health Page">

2. click on the button **Register**

3. Fill all the following fields :

- **Name**: The name of the account, it will be reused to link an account to an instance.  
- **Last name**: The username of this account. For now, it's not used but we recommand you to keep the same name as you have on your instance (TheHive or Cortex)
- **Analyst level**: The password field must be filled with **a valid API key** to use for authentification
- **Avatar**: The password field must be filled with **a valid API key** to use for authentification
- **Password**: The password field must be filled with **a valid API key** to use for authentification

> ⚠️Avatar have to be light (< 1mo)
> On the welcome page after a connection, there will be a php error, this is normal, we'll configure VM log collection in the next step

### ELK Configuration


1. In the admin.txt file on your home directory, copy the enrolment token 
The token is located below the line "he generated password for the elastic built-in superuser is". 
Then go to the "Hunting" page to open ELK and copy it when prompted.

2. After pasting the enrolment token, you'll be asked for a verification code. Here's how to obtain it
```bash
sudo /usr/share/kibana/bin/kibana-verification-code
```

> Note: To regenerate the token you can use this command : `/usr/share/elasticsearch/bin/elasticsearch-create-enrollment-token --scope kibana`


If you have issues submitting the enrolment token restart the elastic search service
```bash
service elasticsearch restart
```

### VM logs configuration

You have to connect to the VM, edit the winlogbeats.yml and do some commands 

1. Connect to the VM (you have the IP adress on the health.php page or you can do `sudo VBoxManage guestproperty get sandbox "/VirtualBox/GuestInfo/Net/0/V4/IP"
`)

2. Open an Administrator Powershell Prompt and go to this folder :

```bash
cd 'C:\Program Files\winlogbeat'
```

3. Open the file `C:\Program Files\winlogbeat` with notepadd or other
Change :
 the password at "password:" (put the password you have in admin.txt)
 Replace All the Ip that corresponding to 192.168.142.130 with the address of your ELK server 
the ca_trusted_fingerprint: (to have it, run this command in the purplelab server : `sudo openssl x509 -fingerprint -sha256 -in /etc/elasticsearch/certs/http_ca.crt` and REMOVE THE ":" characters with this command `echo "$Yourfingerprint" | tr -d ':'` )
 

4. test the configuration with : 

```bash
  & "C:\Program Files\Winlogbeat\winlogbeat.exe" test config -c "C:\Program Files\Winlogbeat\winlogbeat.yml" -e
```

5. If the configuration is OK, set up assets with the following command : 
```bash
.\winlogbeat.exe setup -e
```

6. Restart the Winlobeat Service :
```bash
 Restart-Service winlogbeat
```
7. ⚠️ On the purplelab server, Make a snapshot of the vm -> named: "Snapshot1"

```bash
sudo VBoxManage snapshot "sandbox" take "Snapshot1" --description "snapshot before the mess"
```

> ⚠️ After that, and once you've finished configuring the elastic search server, check if the service is running, go to kibana (Hunting page on Purplelab), click on the Discover tab, normally, you will see the Windows event from the VM. 
Indicators in the home page  should be fed

# Usage

Make sure that de VM is running :
```bash
sudo VBoxManage showvminfo sandbox --machinereadable | grep "VMState=" | awk -F'"' '{print $2}'
```
If not, do :
```bash
sudo VBoxManage startvm sandbox --type headless
```

Open a new prompt on the PurpleLab server and start the flask server on : 
```bash
sudo python3 /home/$(logname)/app.py
```
Once the application is fully configured lets explain all the pages and the features

## Home Page 🏠

This is the home page, she is composed of several KPI that are retreiving from the elasticsearch server
From this page you can saw the number of event from the Windows machine, the number of Unique IP detected from the log, the number of Mitre Attack technique/subtechnique, the repartition of your log that is collected from the VM

<img src="/MD_image/home_page.png" width="800" alt="Health Page">

## Hunting Page 🎯

This page redirect you to the Kibana server, go to discover to check the log of the VM or the log from the simulation page

## Mitre Att&ck Page 🛡️

This page is used to list the techniques from the MITRE ATT&CK framework and execute payloads that simulate attacks corresponding to each technique. This is done in order to create detection rules for each technique.

To search for a technique, you need to enter the first 5 characters of a technique, for example, T1070. The corresponding list for that technique along with its sub-techniques will load. You can then click on a specific technique, and a table with all the information about that technique will appear. At the very end, there is a "run test" button. Clicking on it will execute the payloads associated with that technique on the VM.

The payloads work with the Invoke-Atomic tool, which is installed on the VM. The list of tests for this tool can be found here: https://atomicredteam.io/discovery/

The "Mitre ATT&CK update database" button allows you to update the MITRE ATT&CK framework database with the most recent data.

> ⚠️ The loading time to display a technique is not instantaneous (2-3 seconds).

<img src="/MD_image/mitre.png" width="800" alt="Health Page">

## Malware Page 🦠

This page is divided into two parts:

The "Malware Downloader" section allows you to download malware. In the field, enter a type of malware, for example, "Trojan." This will download the 10 latest malware samples that have been reported on the website https://bazaar.abuse.ch with the tag "Trojan."

Once the download is complete, the malware is automatically uploaded to the Windows VM. The "Display the content of the CSV" button becomes clickable. By clicking on it, you can view a summary of the downloaded malware and then execute them by clicking on their respective "Run" buttons.

The "Malware Uploader" section allows you to upload your own executables, scripts, DLLs, etc. 
> ⚠️Please note that the accepted file extensions are as follows: .exe, .dll, .bin, .py, .ps1. 

The submitted executable is uploaded to the VM, and you can then click on "List of hosted malware" to display the available uploaded executables.

> Note: Malware is downloaded to the VM from the /var/www/html/Downloaded/malware_upload/ directory.

<img src="/MD_image/malware.png" width="800" alt="Health Page">

## Log simulation Page 📊

This page allows you to simulate logs to create more realistic traffic for log analysis. It also provides an opportunity to practice detecting suspicious behavior concealed within legitimate traffic.

Currently, two types of logs are offered in the current version:

Ubuntu Log (under construction)
Firewall Log (functional)
You can then choose the quantity of logs to generate and the time range for timestamping the logs.

The logs have randomized values; for example, the firewall logs will have different IP addresses, "Deny" and "Accept" values assigned randomly, as well as other fields.

Once the fields are filled and the button is clicked, the logs will be generated, and you can find them in the SIEM.

> Note : The logs are generated in JSON format with names like firewall.json or ubuntu.json and are located at path = `/var/www/html/Downloaded/Log_simulation`

<img src="/MD_image/log_simulation.png" width="800" alt="Health Page">

## Usage Case Page 🧩

This page allows you to play out custom-made use cases from start to finish, replicating a compromise scenario. Currently, two use cases are available.

Once a use case is selected, there are two buttons: one to execute the use case on the VM and another to display the use case details.

The details will provide you with a step-by-step scenario of the use case, the actions taken, and any IOCs (Indicators of Compromise). For an added challenge, try to trace the entire compromise path by analyzing the logs before displaying the details 😊

<img src="/MD_image/usecase.png" width="800" alt="Health Page">

## Sharing Page  ✏️

This page is a simple sharing platform. When you have found a good query or detection rule, you can publish it on this shared page to benefit other analysts, and vice versa.

<img src="/MD_image/sharing.png" width="800" alt="Health Page">

## Health Page  🩺

This page allows you to monitor all the components and resources of the PurpleLab tool.

First, you will see the status of the following components:

Kibana
Logstash
Elastic
VirtualBox
Flask Backend
Then, you can check the RAM and disk usage.

Next, you will find information about the VM, including its status, IP address, and snapshot.

Finally, there is a button to restore the VM.

> ⚠️ Sometimes, the restoration of the VM snapshot is reported with an error even though it is successfully completed. Please confirm this by connecting to the VM.

<img src="/MD_image/health_page.png" width="800" alt="Health Page">


# API documentation

For more information on using the PurpleLab API, see [API Documentation](/Documentation/flask_app_documentation.md).

