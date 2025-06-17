<!-- Improved compatibility of back to top link -->
<a id="readme-top"></a>

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/Krook9d/PurpleLab">
    <img src="/MD_image/Logotest.png" alt="Logo PurpleLab" width="400" height="400"/>
  </a>
  
  <!-- PROJECT SHIELDS -->
  [![Issues][issues-shield]][issues-url]
  [![MIT License][license-shield]][license-url]
  [![LinkedIn][linkedin-shield]][linkedin-url]
  [![Forks][forks-shield]][forks-url]
  [![Stargazers][stars-shield]][stars-url]

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
        <li><a href="#sharing-page-️">Sharing Page</a></li>
        <li><a href="#sigma-page-️">Sigma Page</a></li>
        <li><a href="#rule-lifecycle-page-️">Rule Lifecycle Page</a></li>
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

**PurpleLab** is a cybersecurity laboratory that enables security professionals to easily deploy an entire testing environment for creating and validating detection rules, simulating realistic attack scenarios, and training security analysts.

### 🏗️ Architecture Components

The lab includes:

- **🌐 Web Interface** - Complete frontend for controlling all features
- **💻 VirtualBox Environment** - Ready-to-use Windows server 2019 with sysmon and opensearch collector
- **⚙️ Flask Backend** - Robust API and application logic
- **🗄️ PostgreSQL Database** - Secure data storage
- **🔍 Opensearch Server** - Advanced log analysis and search capabilities

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

**Download Repository:**
```bash
git clone https://github.com/Krook9d/PurpleLab.git && mv PurpleLab/install_ansible.sh
```

### Installation

Execute the Ansible installation script:

```bash
sudo bash install_ansible.sh
```

The script will automatically:
1. **Install all components**: OpenSearch, PostgreSQL, VirtualBox, and web interface
2. **Configure the Windows Server VM**: Set up monitoring and security tools
3. **Generate credentials**: Save all login information to `admin.txt`

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

<!-- USAGE -->
## 💡 Usage

**Start the Flask server:**
```bash
sudo python3 /home/$(logname)/app.py
```

### 🪟 Windows Server 2019 Sandbox VM 

The automatically configured VM includes:
- **Windows Server 2019** with admin user `oem/oem`
- **Sysmon** with SwiftOnSecurity configuration for advanced logging
- **Winlogbeat OSS 7.12.1** automatically sending logs to OpenSearch
- **Atomic Red Team** with full test suite for attack simulation
- **Python environment** and **Chocolatey** package manager
- **PowerShell-YAML module** for YAML file processing
- **Pre-configured directories**: samples, malware_upload, and upload folders
- **Windows Defender exclusions** for testing scenarios

### Home Page 🏠

The dashboard displays key performance indicators from OpenSearch:
- **Event Count** from Windows Server VM
- **Unique IP Addresses** detected in logs
- **MITRE ATT&CK** techniques and sub-techniques count
- **Log Distribution** from VM collection

<img src="/MD_image/home_page.png" width="800" alt="Home Page Dashboard">

### Hunting Page 🎯

Direct access to **OpenSearch Dashboards** for log analysis. Navigate to **Discover** to examine:
- **Automatically collected VM logs** from Windows Server sandbox
- Simulated log data and security events
- Real-time monitoring of system activities
- **Sysmon events** with detailed process and network information

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

> **Reference**: [Atomic Red Team Tests](https://www.atomicredteam.io/atomic-red-team/docs)

<img src="/MD_image/mitre.png" width="800" alt="MITRE ATT&CK Interface">

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

### Sharing Page ✏️

Collaborative knowledge sharing platform:

- **Query Sharing**: Publish effective detection queries
- **Rule Exchange**: Share custom detection rules
- **Community Benefit**: Learn from other analysts' discoveries

<img src="/MD_image/sharing.png" width="800" alt="Knowledge Sharing Platform">

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

### Rule Lifecycle Page ⚙️

Advanced rule lifecycle management system for connecting and managing security rules across multiple SIEM platforms:

#### 🔌 Connectors Management
- **Splunk Integration**: Configure connections to Splunk instances with SSL support
- **OpenSearch Integration**: Connect to OpenSearch clusters for rule synchronization
- **Connection Testing**: Validate configurations before deployment
- **Status Monitoring**: Real-time connector health and connectivity status

#### 📋 Rules & Payloads
- **Rule Synchronization**: Automatically fetch detection rules from connected SIEM platforms
- **Payload Association**: Link PowerShell payloads to specific detection rules
- **Custom Payload Creation**: Build and edit PowerShell scripts for rule testing
- **Rule Filtering**: Filter rules by payload status and connector type
- **Last Sync Tracking**: Monitor synchronization timestamps and rule freshness

#### ⚡ Execution & Results
- **Payload Execution**: Run individual or batch payloads against associated rules
- **Result Analysis**: View detailed execution outputs and error messages
- **Status Filtering**: Filter results by triggered/not triggered/error states
- **Time-based Filtering**: Analyze executions over different time periods
- **Batch Operations**: Execute all payloads for displayed rules simultaneously

<img src="/MD_image/rule_lifecycle.png" width="800" alt="Rule Lifecycle Management">

<p align="right">(<a href="#readme-top">⬆️ back to top</a>)</p>

### Health Page 🩺

Comprehensive system monitoring dashboard:

#### 🖥️ Component Status
- **Opensearch Dashboard** - Web interface status
- **Postgres** - Database
- **Opensearch** - Search engine status
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

### Admin Page 🔐

Administrative control center for system configuration:

#### 🔑 Key Features
- **LDAP Configuration**: Centralized authentication setup
- **API Key Generation**: Secure API access management
- **AlienVault OTX API Key**: Configure threat intelligence integration for enhanced KPIs
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


https://github.com/Krook9d/TA-Purplelab-Splunk/assets/40600995/eb5d0c27-06e5-416d-b707-af806c02323e

## 🔍 Cortex Analyzer

**Repository**: [PurpleLab-Cortex-Analyzer](https://github.com/Krook9d/PurpleLab-Cortex-Analyzer)

### Capabilities
- **📤 Automated Uploads**: Seamless executable transfer to PurpleLab
- **💥 Detonation Analysis**: Automated malware execution and analysis
- **🔗 TheHive Integration**: Enhanced incident response workflows

### Demo


https://github.com/Krook9d/PurpleLab-Cortex-Analyzer/assets/40600995/690a8728-4ba7-4fda-a12e-48708e9b7d1d

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
[linkedin-url]: https://www.linkedin.com/in/martin-cayrol-47669a1a2/
[forks-shield]: https://img.shields.io/github/forks/Krook9d/PurpleLab.svg?style=for-the-badge
[forks-url]: https://github.com/Krook9d/PurpleLab/network/members
[stars-shield]: https://img.shields.io/github/stars/Krook9d/PurpleLab.svg?style=for-the-badge
[stars-url]: https://github.com/Krook9d/PurpleLab/stargazers

