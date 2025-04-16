import requests
from requests.auth import HTTPBasicAuth
import urllib3
import argparse
import sys
from datetime import datetime

# 🔒 Ignore SSL self-signed warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# 🔧 Base configuration
BASE_URL = "https://localhost:9200"
AUTH = HTTPBasicAuth("admin", "S3cure@Test123")

def list_alerts():
    """List all existing alerts in the SIEM"""
    # Use the API that works to retrieve alerts
    alerts_url = f"{BASE_URL}/_plugins/_alerting/monitors/alerts"
    
    try:
        response = requests.get(alerts_url, auth=AUTH, verify=False)
        
        if response.status_code != 200:
            print(f"❌ Error {response.status_code}: {response.text}")
            sys.exit(1)
            
        alerts_data = response.json()
        alerts = alerts_data.get("alerts", [])
        
        if not alerts:
            print("No alerts found.")
            return
        
        # Sort alerts by start time (newest first)
        alerts.sort(key=lambda x: x.get("start_time", 0), reverse=True)
        
        print(f"📢 {len(alerts)} alert(s) found:\n")
        
        for alert in alerts:
            severity = alert.get("severity", "N/A")
            severity_emoji = "🔴" if severity == 1 else "🟠" if severity == 2 else "🟡" if severity == 3 else "⚪"
            
            print(f"{severity_emoji} ID: {alert.get('id')}")
            print(f"   Monitor: {alert.get('monitor_name')}")
            print(f"   Trigger: {alert.get('trigger_name')}")
            print(f"   State: {alert.get('state')}")
            print(f"   Severity: {severity}")
            print("")
            
    except Exception as e:
        print(f"❌ Exception while retrieving alerts: {str(e)}")
        sys.exit(1)

def list_triggered_alerts():
    """List only alerts with a trigger that has been executed (ACTIVE state)"""
    # Use the API that works to retrieve alerts
    alerts_url = f"{BASE_URL}/_plugins/_alerting/monitors/alerts"
    
    try:
        response = requests.get(alerts_url, auth=AUTH, verify=False)
        
        if response.status_code != 200:
            print(f"❌ Error {response.status_code}: {response.text}")
            sys.exit(1)
            
        alerts_data = response.json()
        all_alerts = alerts_data.get("alerts", [])
        
        # Filter only active alerts
        triggered_alerts = [a for a in all_alerts if a.get("state") == "ACTIVE"]
        
        if not triggered_alerts:
            print("No active alerts found.")
            return
        
        # Sort alerts by start time (newest first)
        triggered_alerts.sort(key=lambda x: x.get("start_time", 0), reverse=True)
        
        print(f"🚨 {len(triggered_alerts)} active alert(s) found:\n")
        
        for alert in triggered_alerts:
            severity = alert.get("severity", "N/A")
            severity_emoji = "🔴" if severity == 1 else "🟠" if severity == 2 else "🟡" if severity == 3 else "⚪"
            
            print(f"{severity_emoji} ID: {alert.get('id')}")
            print(f"   Monitor: {alert.get('monitor_name')}")
            print(f"   Trigger: {alert.get('trigger_name')}")
            print(f"   State: {alert.get('state')}")
            print(f"   Severity: {severity}")
            
            # Convert timestamps to readable dates
            if alert.get("start_time"):
                start_time = datetime.fromtimestamp(alert.get("start_time")/1000).strftime('%Y-%m-%d %H:%M:%S')
                print(f"   Trigger start time: {start_time}")
            
            if alert.get("last_notification_time"):
                last_updated = datetime.fromtimestamp(alert.get("last_notification_time")/1000).strftime('%Y-%m-%d %H:%M:%S')
                print(f"   Trigger last updated: {last_updated}")
            
            print("")
            
    except Exception as e:
        print(f"❌ Exception while retrieving alerts: {str(e)}")
        sys.exit(1)

def main():
    parser = argparse.ArgumentParser(description="OpenSearch Alerting CLI")
    parser.add_argument("--list", action="store_true", help="List all existing alerts in the SIEM")
    parser.add_argument("--triggered", action="store_true", help="List only alerts with a trigger that has been executed")

    args = parser.parse_args()

    if args.list:
        list_alerts()
    elif args.triggered:
        list_triggered_alerts()
    else:
        parser.print_help()

if __name__ == "__main__":
    main()
