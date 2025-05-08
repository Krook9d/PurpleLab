import requests
import json
from datetime import datetime, timedelta
import time
import sys
import os
import base64
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.backends import default_backend

# Path to the encrypted API key file
ENCRYPTED_KEY_FILE = '/var/www/html/alienvault/api_key.enc'
SECRET_KEY_FILE = '/var/www/html/alienvault/.secret_key'
BASE_URL = "https://otx.alienvault.com/api/v1"

def log(message):
    """Function to display logs with timestamp"""
    timestamp = datetime.now().strftime("%H:%M:%S")
    print(f"[{timestamp}] {message}", flush=True)

def get_api_key():
    """Retrieve and decrypt the API key"""
    if not os.path.exists(ENCRYPTED_KEY_FILE) or not os.path.exists(SECRET_KEY_FILE):
        log("ERROR: API key files not found. Please configure the API key in the admin panel.")
        return None
    
    try:
        # Read the secret key
        with open(SECRET_KEY_FILE, 'r') as f:
            secret_key = bytes.fromhex(f.read().strip())
        
        # Read the encrypted API key with IV
        with open(ENCRYPTED_KEY_FILE, 'r') as f:
            data = f.read().strip()
        
        # Split the IV and encrypted data
        iv_b64, encrypted_key = data.split(':', 1)
        iv = base64.b64decode(iv_b64)
        
        # Initialize cipher with secret key and IV
        cipher = Cipher(algorithms.AES(secret_key), modes.CBC(iv), backend=default_backend())
        decryptor = cipher.decryptor()
        
        # Decrypt the API key
        decrypted = decryptor.update(base64.b64decode(encrypted_key)) + decryptor.finalize()
        
        # Remove padding (PKCS7)
        padding_length = decrypted[-1]
        api_key = decrypted[:-padding_length].decode('utf-8')
        
        return api_key
    except Exception as e:
        log(f"Error decrypting API key: {str(e)}")
        return None

def get_headers():
    api_key = get_api_key()
    if not api_key:
        log("No valid API key found. Using demo data.")
        return {}
    
    return {
        "X-OTX-API-KEY": api_key,
        "User-Agent": "Python Script"
    }

def get_recent_pulses(limit=20):
    log(f"Retrieving {limit} recent pulses...")
    url = f"{BASE_URL}/pulses/subscribed"
    params = {"limit": limit}
    
    try:
        headers = get_headers()
        if not headers:
            log("No API key available. Returning empty pulses list.")
            return []
            
        response = requests.get(url, headers=headers, params=params, timeout=10)
        log(f"Response status for pulses: {response.status_code}")
        if response.status_code != 200:
            log(f"Error retrieving pulses: {response.status_code} {response.text}")
            return []
        
        results = response.json().get("results", [])
        log(f"Number of pulses retrieved: {len(results)}")
        return results
    except requests.exceptions.Timeout:
        log("Timeout while retrieving pulses")
        return []
    except Exception as e:
        log(f"Exception while retrieving pulses: {str(e)}")
        return []

def get_pulse_indicators(pulse_id):
    log(f"Retrieving indicators for pulse {pulse_id}")
    url = f"{BASE_URL}/pulses/{pulse_id}/indicators"
    try:
        headers = get_headers()
        if not headers:
            log("No API key available. Returning empty indicators list.")
            return []
            
        response = requests.get(url, headers=headers, timeout=10)
        log(f"Response status for indicators: {response.status_code}")
        if response.status_code != 200:
            log(f"Error retrieving indicators: {response.status_code}")
            return []
        
        results = response.json().get("results", [])
        log(f"Number of indicators retrieved: {len(results)}")
        return results
    except requests.exceptions.Timeout:
        log("Timeout while retrieving indicators")
        return []
    except Exception as e:
        log(f"Exception while retrieving indicators: {str(e)}")
        return []

def get_geo_data():
    log("Retrieving geographic data...")
    # To avoid blocking, use fewer pulses and add demo data
    pulses = get_recent_pulses(10)  # Reduced to 10 pulses
    geo_data = {
        "United States": 35,
        "Russia": 28,
        "China": 25,
        "Germany": 18,
        "France": 15,
        "United Kingdom": 12
    }  # Default data
    
    try:
        processed = 0
        for pulse in pulses[:5]:  # Limit to 5 pulses to speed up
            log(f"Processing pulse {pulse.get('id')} for geo data")
            indicators = get_pulse_indicators(pulse.get("id", ""))
            ip_indicators = [i for i in indicators if i.get("type") in ["IPv4", "IPv6"]]
            log(f"Number of IP indicators found: {len(ip_indicators)}")
            
            # Limit the number of IPs to process to avoid blocking
            for indicator in ip_indicators[:3]:  
                ip = indicator.get("indicator")
                log(f"Retrieving geo data for IP {ip}")
                ip_data = get_ip_geo(ip)
                country = ip_data.get("country_name")
                if country:
                    geo_data[country] = geo_data.get(country, 0) + 1
                processed += 1
                if processed > 10:  # Limit the total number of IPs processed
                    break
            if processed > 10:
                break
    except Exception as e:
        log(f"Exception in get_geo_data: {str(e)}")
    
    log(f"Geographic data retrieved for {len(geo_data)} countries")
    return geo_data

def get_ip_geo(ip):
    url = f"{BASE_URL}/indicators/IPv4/{ip}/geo"
    try:
        headers = get_headers()
        if not headers:
            log(f"No API key available. Returning empty geo data for IP {ip}.")
            return {}
            
        response = requests.get(url, headers=headers, timeout=10)
        log(f"Response status for geo IP {ip}: {response.status_code}")
        if response.status_code != 200:
            return {}
        
        return response.json()
    except requests.exceptions.Timeout:
        log(f"Timeout while retrieving geo data for {ip}")
        return {}
    except Exception as e:
        log(f"Exception while retrieving geo data: {str(e)}")
        return {}

def get_top_cves(limit=10):
    log("Retrieving most active CVEs...")
    # To avoid blocking, use demo data
    demo_cves = [
        {"cve": "CVE-2024-50623", "count": 30},
        {"cve": "CVE-2025-0283", "count": 28},
        {"cve": "CVE-2022-41328", "count": 28},
        {"cve": "CVE-2023-7624", "count": 25},
        {"cve": "CVE-2024-3312", "count": 22}
    ]
    
    try:
        url = f"{BASE_URL}/search/pulses"
        params = {"q": "CVE", "limit": 20}  # Reduced to 20
        log(f"API request for CVEs: {url}")
        
        headers = get_headers()
        if not headers:
            log("No API key available. Returning demo CVE data.")
            return demo_cves
            
        response = requests.get(url, headers=headers, params=params, timeout=10)
        
        log(f"Response status for CVEs: {response.status_code}")
        if response.status_code != 200:
            log("Reverting to demo data for CVEs")
            return demo_cves
        
        pulses = response.json().get("results", [])
        log(f"Number of CVE pulses retrieved: {len(pulses)}")
        
        cve_counts = {}
        for pulse in pulses:
            for tag in pulse.get("tags", []):
                if tag.startswith("CVE-"):
                    cve_counts[tag] = cve_counts.get(tag, 0) + 1
        
        # Sort CVEs by number of occurrences
        sorted_cves = sorted(cve_counts.items(), key=lambda x: x[1], reverse=True)
        result = [{"cve": cve, "count": count} for cve, count in sorted_cves[:limit]]
        
        log(f"Number of unique CVEs found: {len(cve_counts)}")
        return result if result else demo_cves
    except Exception as e:
        log(f"Exception in get_top_cves: {str(e)}")
        return demo_cves

def get_targeted_industries(pulses):
    log("Analyzing targeted industries...")
    # Demo data
    demo_industries = [
        {"name": "Finance", "count": 45},
        {"name": "Government", "count": 38},
        {"name": "Technology", "count": 32},
        {"name": "Healthcare", "count": 28},
        {"name": "Manufacturing", "count": 22}
    ]
    
    try:
        industries = {}
        industry_keywords = [
            "finance", "healthcare", "government", "education", "technology", 
            "retail", "manufacturing", "energy", "telecommunications"
        ]
        
        for pulse in pulses:
            desc = pulse.get("description", "").lower()
            for industry in industry_keywords:
                if industry in desc:
                    industries[industry.capitalize()] = industries.get(industry.capitalize(), 0) + 1
        
        # Sort industries by number of occurrences
        sorted_industries = sorted(industries.items(), key=lambda x: x[1], reverse=True)
        result = [{"name": name, "count": count} for name, count in sorted_industries[:5]]
        
        log(f"Number of identified industries: {len(industries)}")
        return result if result else demo_industries
    except Exception as e:
        log(f"Exception in get_targeted_industries: {str(e)}")
        return demo_industries

def filter_malware_pulses(pulses):
    log("Filtering pulses related to malware...")
    keywords = ['malware', 'ransomware', 'trojan', 'spyware', 'virus']
    try:
        malware_pulses = [
            pulse for pulse in pulses
            if any(kw in pulse.get("name", "").lower() or kw in pulse.get("description", "").lower()
                for kw in keywords)
        ]
        log(f"Number of malware pulses found: {len(malware_pulses)}")
        return malware_pulses
    except Exception as e:
        log(f"Exception in filter_malware_pulses: {str(e)}")
        return []

def generate_dashboard_data():
    log("Generating data for the dashboard...")
    try:
        pulses = get_recent_pulses(100)  # Increased to 100 pulses for more data
        if not pulses:
            log("No pulses retrieved, generating demo data")
            # Demo data
            return {
                "summary": {
                    "intrusion_sets": 1020,
                    "malware": 4370,
                    "reports": 53760,
                    "indicators": 5770000
                },
                "top_threats": [
                    {"name": "Ransomware", "count": 48},
                    {"name": "Trojan", "count": 42},
                    {"name": "Spyware", "count": 35},
                    {"name": "Backdoor", "count": 28},
                    {"name": "Worm", "count": 22}
                ],
                "most_targeted": get_targeted_industries([]),
                "top_cves": get_top_cves(),
                "activity_by_month": [],
                "geo_data": get_geo_data()
            }
        
        log("Filtering malware pulses...")
        malware_pulses = filter_malware_pulses(pulses)
        
        log("Counting threat types...")
        # Count threat types
        threat_types = {}
        for pulse in malware_pulses:
            name = pulse.get("name", "").lower()
            for threat in ["ransomware", "trojan", "spyware", "worm", "backdoor"]:
                if threat in name:
                    threat_types[threat.capitalize()] = threat_types.get(threat.capitalize(), 0) + 1
        
        # Sort threats by number of occurrences
        sorted_threats = sorted(threat_types.items(), key=lambda x: x[1], reverse=True)
        top_threats = [{"name": name, "count": count} for name, count in sorted_threats[:5]]
        
        if not top_threats:
            top_threats = [
                {"name": "Ransomware", "count": 48},
                {"name": "Trojan", "count": 42},
                {"name": "Spyware", "count": 35},
                {"name": "Backdoor", "count": 28},
                {"name": "Worm", "count": 22}
            ]
        
        log("Retrieving geographic data...")
        geo_data = get_geo_data()
        
        log("Preparing recent pulses for the dashboard...")
        # Prepare recent pulses for the dashboard
        recent_pulses = []
        # Sort pulses by date (from most recent to oldest)
        sorted_pulses = sorted(pulses, key=lambda x: x.get("created", ""), reverse=True)
        
        for pulse in sorted_pulses[:10]:  # Take the 10 most recent, the dashboard will display 5
            # Clean and filter data to avoid JSON serialization issues
            recent_pulse = {
                "id": pulse.get("id", ""),
                "name": pulse.get("name", ""),
                "created": pulse.get("created", ""),
                "description": pulse.get("description", ""),  # Full description
                "tags": pulse.get("tags", [])[:5],  # Limit to 5 tags
                "author_name": pulse.get("author_name", ""),
                "references": pulse.get("references", [])[:3]  # Limit to 3 references
            }
            recent_pulses.append(recent_pulse)
        
        # Calculate the time range covered by the pulses
        time_metadata = {
            "oldest_pulse": pulses[-1].get("created", "") if pulses else "",
            "newest_pulse": pulses[0].get("created", "") if pulses else "",
            "pulse_count": len(pulses)
        }
        
        log("Calculating statistics...")
        # Generate data for the dashboard
        dashboard_data = {
            "summary": {
                "intrusion_sets": len(pulses),
                "malware": len(malware_pulses),
                "reports": sum(1 for p in pulses if p.get("reference")),
                "indicators": sum(len(p.get("indicators", [])) for p in pulses)
            },
            "top_threats": top_threats,
            "most_targeted": get_targeted_industries(pulses),
            "top_cves": get_top_cves(),
            "activity_by_month": [],
            "geo_data": geo_data,
            "recent_pulses": recent_pulses,
            "time_metadata": time_metadata
        }
        
        log("Dashboard data successfully generated")
        return dashboard_data
    except Exception as e:
        log(f"Exception in generate_dashboard_data: {str(e)}")
        # Return demo data in case of error
        return {
            "summary": {
                "intrusion_sets": 1020,
                "malware": 4370,
                "reports": 53760,
                "indicators": 5770000
            },
            "top_threats": [
                {"name": "Ransomware", "count": 48},
                {"name": "Trojan", "count": 42},
                {"name": "Spyware", "count": 35},
                {"name": "Backdoor", "count": 28},
                {"name": "Worm", "count": 22}
            ],
            "most_targeted": [
                {"name": "Finance", "count": 45},
                {"name": "Government", "count": 38},
                {"name": "Technology", "count": 32},
                {"name": "Healthcare", "count": 28},
                {"name": "Manufacturing", "count": 22}
            ],
            "top_cves": [
                {"cve": "CVE-2024-50623", "count": 30},
                {"cve": "CVE-2025-0283", "count": 28},
                {"cve": "CVE-2022-41328", "count": 28},
                {"cve": "CVE-2023-7624", "count": 25},
                {"cve": "CVE-2024-3312", "count": 22}
            ],
            "activity_by_month": [],
            "geo_data": {
                "United States": 35,
                "Russia": 28,
                "China": 25,
                "Germany": 18,
                "France": 15,
                "United Kingdom": 12
            }
        }

def main():
    log("Starting OTX AlienVault application")
    log("Retrieving data from OTX AlienVault...")
    
    try:
        dashboard_data = generate_dashboard_data()
        
        # Create directory if it doesn't exist
        os.makedirs("/var/www/html/alienvault", exist_ok=True)
        
        log("Saving data to /var/www/html/alienvault/dashboard_data.json")
        # Save data to a JSON file
        with open("/var/www/html/alienvault/dashboard_data.json", "w") as f:
            json.dump(dashboard_data, f, indent=2)
        
        log("Dashboard data successfully generated and saved to /var/www/html/alienvault/dashboard_data.json")
        
        # Also display malware-related pulses as before
        log("Retrieving examples of malware pulses")
        pulses = get_recent_pulses(10)  # Reduced to 10
        malware_pulses = filter_malware_pulses(pulses)
        
        log("\n=== Pulses related to malware ===")
        for pulse in malware_pulses[:3]:  # Limit to 3 for readability
            log(f"\nName: {pulse.get('name')}")
            log(f"Created on: {pulse.get('created')}")
            log(f"Description: {pulse.get('description')[:100]}...")  # Truncate description
            log(f"Link: https://otx.alienvault.com/pulse/{pulse.get('id')}")
    
    except Exception as e:
        log(f"Exception in main function: {str(e)}")

if __name__ == "__main__":
    main()
