<!-- Improved compatibility of back to top link -->
<a id="readme-top"></a>

<!-- PROJECT SHIELDS -->
[![Issues][issues-shield]][issues-url]
[![MIT License][license-shield]][license-url]
[![LinkedIn][linkedin-shield]][linkedin-url]

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/Krook9d/PurpleLab">
    <img src="/MD_image/Logotest.png" alt="Logo PurpleLab" width="120" height="120"/>
  </a>

  <h1 align="center">PurpleLab</h1>

  <p align="center">
    A comprehensive cybersecurity lab for creating and testing detection rules, simulating attacks, and training analysts
    <br />
    <a href="#installation"><strong>Get Started »</strong></a>
    <br />
    <br />
    <a href="#usage">View Demo</a>
    ·
    <a href="https://github.com/Krook9d/PurpleLab/issues">Report Bug</a>
    ·
    <a href="https://github.com/Krook9d/PurpleLab/issues">Request Feature</a>
  </p>
</div>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>📋 Table of Contents</summary>
  <ol>
    <li><a href="#-what-is-purplelab">What is PurpleLab?</a></li>
    <li>
      <a href="#-installation-procedure">Installation</a>
      <ul>
        <li><a href="#requirements">Requirements</a></li>
        <li><a href="#installation">Installation Steps</a></li>
        <li><a href="#accounts">Accounts Setup</a></li>
        <li><a href="#elk-configuration">ELK Configuration</a></li>
        <li><a href="#vm-logs-configuration">VM Logs Configuration</a></li>
      </ul>
    </li>
    <li>
      <a href="#-usage">Usage</a>
      <ul>
        <li><a href="#home-page-">Home Page</a></li>
        <li><a href="#hunting-page-">Hunting Page</a></li>
        <li><a href="#mitre-attck-page-️">MITRE ATT&CK Page</a></li>
        <li><a href="#malware-page-">Malware Page</a></li>
        <li><a href="#log-simulation-page-">Log Simulation Page</a></li>
        <li><a href="#usage-case-page-">Usage Case Page</a></li>
        <li><a href="#sharing-page-️">Sharing Page</a></li>
        <li><a href="#sigma-page-️">Sigma Page</a></li>
        <li><a href="#health-page-">Health Page</a></li>
        <li><a href="#admin-page-">Admin Page</a></li>
      </ul>
    </li>
    <li><a href="#-splunk-app">Splunk App</a></li>
    <li><a href="#-cortex-analyzer">Cortex Analyzer</a></li>
    <li><a href="#-api-documentation">API Documentation</a></li>
  </ol>
</details>

<br />

<!-- ABOUT THE PROJECT -->
## 🚀 What is PurpleLab ?

**PurpleLab** is a comprehensive cybersecurity laboratory that enables security professionals to easily deploy an entire testing environment for creating and validating detection rules, simulating realistic attack scenarios, and training security analysts.

### 🏗️ Architecture Components

The lab includes:

- **🌐 Web Interface** - Complete frontend for controlling all features
- **💻 VirtualBox Environment** - Ready-to-use Windows 10 VM with forensic tools
- **⚙️ Flask Backend** - Robust API and application logic
- **🗄️ PostgreSQL Database** - Secure data storage
- **🔍 Elasticsearch Server** - Advanced log analysis and search capabilities

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

<!-- INSTALLATION -->
## 🔧 Installation procedure

> ⚠️ **Important**: For a completely clean installation, follow ALL chapters of the installation procedure from requirements to accounts configuration.

> ⚠️ **Security Notice**: This lab has not been hardened and runs with basic credentials. Do not connect it to production networks or secure it with proper PKI and authentication systems.

### Requirements

**Minimum Hardware Resources:**
- **Storage**: 200GB available space
- **CPU**: 8 cores minimum
- **RAM**: 13GB minimum

**Software Requirements:**
- Clean installation of **Ubuntu Server 22.04** - [Download Here](https://ubuntu.com/download/server?ref=linuxhandbook.com)

> ⚠️ **Note**: Ubuntu Server 23.10 may cause issues with Python library installation.

**⚠️ Hardware Virtualization Setup:**

<details>
<summary>Click to expand virtualization setup instructions</summary>

**VMware Workstation:**
1. Go to VM settings → Processors → Virtualization engine
2. Enable "Virtualize Intel VT-x/EPT or AMD-V/RVI"

**VirtualBox:**
1. Select VM → Right-click → Settings → System → Processor
2. Check "Enable Nested VT-x/AMD-V"

**Physical Machine (Host):**
1. Access BIOS/UEFI settings
2. Enable hardware virtualization (VT-x/AMD-V)
3. Save changes and restart

</details>

**Download Repository:**
```bash
git clone https://github.com/Krook9d/PurpleLab.git && mv PurpleLab/install.sh .
```

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Installation

Execute the installation script:

```bash
sudo bash install.sh
```

During installation, you'll be prompted to:
1. **ELK Installation**: Choose whether to install the default ELK SIEM
2. **Network Interface**: Select the network interface for the application

> ⚠️ **Warning**: If you skip ELK installation, PHP errors will appear on the home page.

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Accounts

#### 👤 Admin Account

A default admin account is automatically created and stored in `~/admin.txt` with the format:
```
admin@local.com:password
```

#### 👥 User Account Setup

1. **Access the application** using your server's IP address
2. **Click "Register"** button
3. **Fill required fields:**
   - **First Name**: Your first name
   - **Last Name**: Your last name  
   - **Analyst Level**: Your analyst level (N1/N2/N3)
   - **Avatar**: Select an avatar (< 1MB)
   - **Password**: Must contain at least 8 characters with uppercase, lowercase, number, and special character

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### ELK Configuration

1. **Generate enrollment token:**
```bash
sudo /usr/share/elasticsearch/bin/elasticsearch-create-enrollment-token --scope kibana
```

2. **Navigate to "Hunting" page** and paste the token

3. **Get verification code:**
```bash
sudo /usr/share/kibana/bin/kibana-verification-code
```

**Troubleshooting:**
If token submission fails, restart Elasticsearch:
```bash
service elasticsearch restart
```

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### VM logs configuration

1. **Connect to the VM** (IP available on health page):
```bash
sudo VBoxManage guestproperty get sandbox "/VirtualBox/GuestInfo/Net/0/V4/IP"
```

2. **Edit winlogbeat configuration** at `C:\Program Files\winlogbeat\winlogbeat.yml`:
   - Update password field with elastic superuser password from `admin.txt`
   - Replace all IP addresses (192.168.142.130) with your ELK server IP
   - Update `ca_trusted_fingerprint` with the output from:

```bash
sudo openssl x509 -fingerprint -sha256 -in /etc/elasticsearch/certs/http_ca.crt | awk -F '=' '/Fingerprint/{print $2}' | tr -d ':'
```

3. **Test configuration** (Admin PowerShell):
```powershell
cd 'C:\Program Files\winlogbeat'
& "C:\Program Files\Winlogbeat\winlogbeat.exe" test config -c "C:\Program Files\Winlogbeat\winlogbeat.yml" -e
```

4. **Setup assets:**
```powershell
.\winlogbeat.exe setup -e
```

5. **Create snapshot** after VM restart:
```bash
sudo VBoxManage snapshot "sandbox" take "Snapshot1" --description "snapshot before the mess"
```

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

<!-- USAGE -->
## 💡 Usage

**Start the Flask server:**
```bash
sudo python3 /home/$(logname)/app.py
```

**Ensure VM is running:**
```bash
sudo VBoxManage showvminfo sandbox --machinereadable | grep "VMState=" | awk -F'"' '{print $2}'
```

**Start VM if needed:**
```bash
sudo VBoxManage startvm sandbox --type headless
```

### 🪟 Windows 10 Sandbox VM 

The VM includes pre-installed tools:
- **Browser** for web-based activities
- **Atomic Red Team modules** for attack simulation
- **LibreOffice** for document-based attacks
- **Forensic Tools** collection - [More Info](https://github.com/cristianzsh/forensictools)

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Home Page 🏠

The dashboard displays key performance indicators from Elasticsearch:
- **Event Count** from Windows machine
- **Unique IP Addresses** detected in logs
- **MITRE ATT&CK** techniques and sub-techniques count
- **Log Distribution** from VM collection

<img src="/MD_image/home_page.png" width="800" alt="Home Page Dashboard">

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Hunting Page 🎯

Direct access to **Kibana server** for log analysis. Navigate to **Discover** to examine:
- VM logs and events
- Simulated log data
- Real-time security events

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Mitre Att&ck Page 🛡️

Interactive MITRE ATT&CK framework interface for:

**🔍 Technique Discovery:**
- Search using technique IDs (e.g., "T1070")
- Browse sub-techniques and detailed information
- Access comprehensive technique documentation

**⚡ Payload Execution:**
- Execute Atomic Red Team payloads
- Simulate real attack scenarios
- Generate detection-worthy events

**📊 Database Management:**
- Update MITRE ATT&CK database with latest data
- Maintain current threat intelligence

> **Reference**: [Atomic Red Team Tests](https://atomicredteam.io/discovery/)

<img src="/MD_image/mitre.png" width="800" alt="MITRE ATT&CK Interface">

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Malware Page 🦠

Comprehensive malware management platform with dual functionality:

#### 📥 Malware Downloader
- **Search & Download**: Enter malware types (e.g., "Trojan")
- **Auto-Integration**: Automatically uploads to Windows VM
- **Batch Processing**: Downloads 10 latest samples from [Malware Bazaar](https://bazaar.abuse.ch)
- **Execution Control**: Run malware with single-click execution

#### 📤 Malware Uploader
- **Custom Uploads**: Upload your own executables and scripts
- **Supported Formats**: `.exe`, `.dll`, `.bin`, `.py`, `.ps1`
- **Inventory Management**: List and manage uploaded malware

> **Storage Location**: `/var/www/html/Downloaded/malware_upload/`

<img src="/MD_image/malware.png" width="800" alt="Malware Management Interface">

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Log simulation Page 📊

Generate realistic log data for enhanced detection training:

#### 🔥 Available Log Types
- **Ubuntu Logs** *(under development)*
- **Firewall Logs** *(fully functional)*

#### ⚙️ Configuration Options
- **Volume Control**: Specify quantity of logs to generate
- **Time Range**: Customize timestamp ranges
- **Randomization**: Automatic randomization of values (IPs, actions, etc.)

**Output Location**: `/var/www/html/Downloaded/Log_simulation`
**Format**: JSON (firewall.json, ubuntu.json)

<img src="/MD_image/log_simulation.png" width="800" alt="Log Simulation Interface">

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Usage Case Page 🧩

End-to-end attack scenario simulation:

#### 🎭 Available Use Cases
- **Scenario Execution**: Run complete compromise scenarios
- **Detailed Breakdown**: Step-by-step attack analysis
- **IOC Discovery**: Identify Indicators of Compromise

#### 🔍 Challenge Mode
Try to trace the entire compromise path through log analysis before revealing the solution details!

<img src="/MD_image/usecase.png" width="800" alt="Use Case Scenarios">

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Sharing Page ✏️

Collaborative knowledge sharing platform:

- **Query Sharing**: Publish effective detection queries
- **Rule Exchange**: Share custom detection rules
- **Community Benefit**: Learn from other analysts' discoveries

<img src="/MD_image/sharing.png" width="800" alt="Knowledge Sharing Platform">

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Sigma Page 🛡️

Advanced Sigma rule management:

#### 🔍 Search Capabilities
- **Keyword Search**: Find rules by technique IDs or keywords (e.g., "powershell")
- **Rule Display**: View complete Sigma rule details
- **Format Conversion**: Convert rules to Splunk or Lucene syntax

#### 🔄 Conversion Features
- **Splunk Format**: One-click conversion to Splunk queries
- **Lucene Format**: Transform to Elasticsearch-compatible syntax

<img src="/MD_image/sigma.png" width="800" alt="Sigma Rule Management">

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Health Page 🩺

Comprehensive system monitoring dashboard:

#### 🖥️ Component Status
- **Kibana** - Web interface status
- **Logstash** - Data processing pipeline
- **Elasticsearch** - Search engine status
- **VirtualBox** - Virtualization platform
- **Flask Backend** - Application server

#### 📊 Resource Monitoring
- **RAM Usage** - Memory utilization
- **Disk Usage** - Storage consumption

#### 🔧 VM Management
- **Status Monitoring** - Current VM state
- **IP Information** - Network configuration
- **Snapshot Control** - Restore points management

> **Note**: Snapshot restoration may show errors even when successful - verify by connecting to the VM.

<img src="/MD_image/health_page.png" width="800" alt="System Health Dashboard">

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Admin Page 🔐

Administrative control center for system configuration:

#### 🔑 Key Features
- **LDAP Configuration**: Centralized authentication setup
- **API Key Generation**: Secure API access management
- **System Settings**: Core configuration management

#### 🔐 Access Requirements
Login with administrator account: `admin@local.com`

<img src="/MD_image/admin.png" width="800" alt="Administration Panel">

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

<!-- INTEGRATIONS -->
## 🔌 Splunk App

**Repository**: [TA-Purplelab-Splunk](https://github.com/Krook9d/TA-Purplelab-Splunk)

### Features
- **🚀 Atomic Red Team Integration**: Execute tests directly from Splunk
- **🔍 Threat Hunting Dashboard**: Dedicated hunting interface
- **🔗 Seamless Integration**: Easy PurpleLab-Splunk connectivity

### Demo
[![Splunk Integration Demo](https://github.com/Krook9d/TA-Purplelab-Splunk/assets/40600995/eb5d0c27-06e5-416d-b707-af806c02323e)](https://github.com/Krook9d/TA-Purplelab-Splunk)

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

## 🔍 Cortex Analyzer

**Repository**: [PurpleLab-Cortex-Analyzer](https://github.com/Krook9d/PurpleLab-Cortex-Analyzer)

### Capabilities
- **📤 Automated Uploads**: Seamless executable transfer to PurpleLab
- **💥 Detonation Analysis**: Automated malware execution and analysis
- **🔗 TheHive Integration**: Enhanced incident response workflows

### Demo
[![Cortex Analyzer Demo](https://github.com/Krook9d/PurpleLab-Cortex-Analyzer/assets/40600995/690a8728-4ba7-4fda-a12e-48708e9b7d1d)](https://github.com/Krook9d/PurpleLab-Cortex-Analyzer)

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

<!-- API DOCUMENTATION -->
## 📚 API documentation

For comprehensive API usage and integration details, see our complete documentation:

**[📖 API Documentation](/Documentation/flask_app_documentation.md)**

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

<!-- MARKDOWN LINKS & IMAGES -->
[issues-shield]: https://img.shields.io/github/issues/Krook9d/PurpleLab.svg?style=for-the-badge
[issues-url]: https://github.com/Krook9d/PurpleLab/issues
[license-shield]: https://img.shields.io/github/license/Krook9d/PurpleLab.svg?style=for-the-badge
[license-url]: https://github.com/Krook9d/PurpleLab/blob/master/LICENSE
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-black.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/your-profile
