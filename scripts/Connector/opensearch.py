import requests
from requests.auth import HTTPBasicAuth
import urllib3
import argparse
import sys
from datetime import datetime
import json
import os

# 🔒 Ignore SSL self-signed warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# 🔧 Configuration from environment variables
BASE_URL = os.environ.get("BASE_URL", "https://localhost:9200")
USERNAME = os.environ.get("OS_USERNAME", "admin")
PASSWORD = os.environ.get("OS_PASSWORD", "admin123")
AUTH = HTTPBasicAuth(USERNAME, PASSWORD)

# Log environment variables and configuration
print(f"Configuration: BASE_URL={BASE_URL}, USERNAME={USERNAME}")
print(f"Environment variables: {list(os.environ.keys())}")

if 'BASE_URL' in os.environ:
    print(f"Using BASE_URL from environment: {BASE_URL}")
if 'OS_USERNAME' in os.environ:
    print(f"Using OS_USERNAME from environment: {os.environ.get('OS_USERNAME')}")
if 'OS_PASSWORD' in os.environ:
    print("Using OS_PASSWORD from environment (value hidden)")

def list_all_triggers(json_output=False):
    """List all existing triggers in the SIEM"""
    print(f"Connecting to OpenSearch at {BASE_URL} with user {USERNAME}")
    monitors_url = f"{BASE_URL}/_plugins/_alerting/monitors/_search"
    body = {
        "query": {"match_all": {}},
        "size": 100
    }
    
    try:
        print(f"Sending POST request to {monitors_url}")
        response = requests.post(monitors_url, auth=AUTH, json=body, verify=False)
        
        print(f"Response status: {response.status_code}")
        if response.status_code != 200:
            error_msg = f"❌ Error {response.status_code}: {response.text}"
            print(error_msg)
            if json_output:
                return {"error": error_msg}
            sys.exit(1)
            
        monitors_data = response.json()
        monitors = monitors_data.get("hits", {}).get("hits", [])
        
        if not monitors:
            print("No monitors found.")
            if json_output:
                return {"triggers": []}
            return
        
        print(f"Found {len(monitors)} monitors")
        
        alerts_url = f"{BASE_URL}/_plugins/_alerting/monitors/alerts"
        print(f"Retrieving alerts from {alerts_url}")
        alerts_response = requests.get(alerts_url, auth=AUTH, verify=False)
        
        active_alerts = {}
        if alerts_response.status_code == 200:
            print(f"Alerts response status: {alerts_response.status_code}")
            alerts_data = alerts_response.json()
            for alert in alerts_data.get("alerts", []):
                # Create a key using monitor and trigger name
                key = f"{alert.get('monitor_name')}|{alert.get('trigger_name')}"
                active_alerts[key] = {
                    "start_time": alert.get("start_time"),
                    "last_notification_time": alert.get("last_notification_time"),
                    "state": alert.get("state"),
                    "id": alert.get("id")
                }
        else:
            print(f"Failed to retrieve alerts: {alerts_response.status_code}: {alerts_response.text}")
        
        # Collect all triggers with their complete details
        all_triggers = []
        for monitor in monitors:
            monitor_source = monitor.get("_source", {})
            monitor_name = monitor_source.get("name", "Unknown")
            monitor_enabled = monitor_source.get("enabled", False)
            monitor_id = monitor.get("_id", "Unknown")
            
            # Get the triggers for this monitor - extracting them correctly
            triggers = monitor_source.get("triggers", [])
            print(f"Monitor '{monitor_name}' has {len(triggers)} triggers")
            for trigger in triggers:
                # Extract trigger name correctly based on the monitor type
                trigger_name = "Unknown"
                trigger_severity = "N/A"
                
                # Handle different trigger types
                if "query_level_trigger" in trigger:
                    query_trigger = trigger["query_level_trigger"]
                    trigger_name = query_trigger.get("name", "Unknown")
                    trigger_severity = query_trigger.get("severity", "N/A")
                elif "bucket_level_trigger" in trigger:
                    bucket_trigger = trigger["bucket_level_trigger"]
                    trigger_name = bucket_trigger.get("name", "Unknown")
                    trigger_severity = bucket_trigger.get("severity", "N/A")
                elif "document_level_trigger" in trigger:
                    doc_trigger = trigger["document_level_trigger"]
                    trigger_name = doc_trigger.get("name", "Unknown")
                    trigger_severity = doc_trigger.get("severity", "N/A")
                elif "name" in trigger:
                    # Fallback for simple trigger structure
                    trigger_name = trigger.get("name", "Unknown")
                    trigger_severity = trigger.get("severity", "N/A")
                
                trigger_info = {
                    "trigger_name": trigger_name,
                    "monitor_name": monitor_name,
                    "monitor_id": monitor_id,
                    "monitor_enabled": monitor_enabled,
                    "severity": trigger_severity,
                    "is_active": False,
                    "start_time": None,
                    "last_notification_time": None
                }
                
                # Check if this trigger has an active alert
                key = f"{monitor_name}|{trigger_name}"
                if key in active_alerts:
                    trigger_info["is_active"] = active_alerts[key]["state"] == "ACTIVE"
                    trigger_info["start_time"] = active_alerts[key]["start_time"]
                    trigger_info["last_notification_time"] = active_alerts[key]["last_notification_time"]
                    trigger_info["alert_id"] = active_alerts[key]["id"]
                
                all_triggers.append(trigger_info)
        
        if not all_triggers:
            print("No triggers found.")
            if json_output:
                return {"triggers": []}
            return
        
        # Sort triggers - first active ones, then by monitor name
        all_triggers.sort(key=lambda x: (not x["is_active"], x["monitor_name"], x["trigger_name"]))
        
        if json_output:
            return {"triggers": all_triggers}
        
        print(f"📢 {len(all_triggers)} trigger(s) found:\n")
        
        for trigger in all_triggers:
            severity = trigger["severity"]
            severity_emoji = "🔴" if severity == "1" else "🟠" if severity == "2" else "🟡" if severity == "3" else "⚪"
            status_emoji = "✅" if trigger["monitor_enabled"] else "❌"
            active_emoji = "🚨" if trigger["is_active"] else "⚫"
            
            print(f"{active_emoji} {severity_emoji} Trigger: {trigger['trigger_name']}")
            print(f"   Monitor: {trigger['monitor_name']} {status_emoji}")
            print(f"   Severity: {severity}")
            
            # Show trigger times if active
            if trigger["is_active"]:
                print(f"   State: ACTIVE")
                print(f"   Alert ID: {trigger.get('alert_id', 'N/A')}")
                
                if trigger["start_time"]:
                    start_time = datetime.fromtimestamp(trigger["start_time"]/1000).strftime('%Y-%m-%d %H:%M:%S')
                    print(f"   Trigger start time: {start_time}")
                
                if trigger["last_notification_time"]:
                    last_updated = datetime.fromtimestamp(trigger["last_notification_time"]/1000).strftime('%Y-%m-%d %H:%M:%S')
                    print(f"   Trigger last updated: {last_updated}")
            else:
                print(f"   State: INACTIVE")
            
            print("")
            
    except Exception as e:
        print(f"❌ Exception while retrieving triggers: {str(e)}")
        sys.exit(1)

def list_triggered_alerts(json_output=False):
    """List only alerts with a trigger that has been executed (ACTIVE state)"""
    # Use the API that works to retrieve alerts
    alerts_url = f"{BASE_URL}/_plugins/_alerting/monitors/alerts"
    
    try:
        response = requests.get(alerts_url, auth=AUTH, verify=False)
        
        if response.status_code != 200:
            error_msg = f"❌ Error {response.status_code}: {response.text}"
            if json_output:
                return {"error": error_msg}
            print(error_msg)
            sys.exit(1)
            
        alerts_data = response.json()
        all_alerts = alerts_data.get("alerts", [])
        
        # Filter only active alerts
        triggered_alerts = [a for a in all_alerts if a.get("state") == "ACTIVE"]
        
        if not triggered_alerts:
            if json_output:
                return {"alerts": []}
            print("No active alerts found.")
            return
        
        # Sort alerts by start time (newest first)
        triggered_alerts.sort(key=lambda x: x.get("start_time", 0), reverse=True)
        
        if json_output:
            return {"alerts": triggered_alerts}
        
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
    parser.add_argument("--list", action="store_true", help="List all triggers in the SIEM")
    parser.add_argument("--triggered", action="store_true", help="List only alerts with a trigger that has been executed")
    parser.add_argument("--list-json", action="store_true", help="List all triggers in JSON format")
    parser.add_argument("--triggered-json", action="store_true", help="List active alerts in JSON format")

    args = parser.parse_args()

    if args.list:
        list_all_triggers()
    elif args.triggered:
        list_triggered_alerts()
    elif args.list_json:
        result = list_all_triggers(json_output=True)
        print(json.dumps(result))
    elif args.triggered_json:
        result = list_triggered_alerts(json_output=True)
        print(json.dumps(result))
    else:
        parser.print_help()

if __name__ == "__main__":
    main()
