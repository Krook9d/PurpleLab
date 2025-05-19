import sys
import splunklib.client as client
from splunklib.binding import HTTPError
import json
from datetime import datetime
import time
import argparse
from collections import Counter
import requests
import urllib3
import os

# Configuration from environment variables
SPLUNK_HOST = os.environ.get("SPLUNK_HOST", "localhost")
SPLUNK_PORT = os.environ.get("SPLUNK_PORT", "8089")
SPLUNK_USERNAME = os.environ.get("SPLUNK_USERNAME", "admin")
SPLUNK_PASSWORD = os.environ.get("SPLUNK_PASSWORD", "changeme")

# Log environment variables for debugging
if 'SPLUNK_HOST' in os.environ:
    print(f"Using SPLUNK_HOST from environment: {SPLUNK_HOST}")
if 'SPLUNK_PORT' in os.environ:
    print(f"Using SPLUNK_PORT from environment: {SPLUNK_PORT}")
if 'SPLUNK_USERNAME' in os.environ:
    print(f"Using SPLUNK_USERNAME from environment: {os.environ.get('SPLUNK_USERNAME')}")
if 'SPLUNK_PASSWORD' in os.environ:
    print("Using SPLUNK_PASSWORD from environment (value hidden)")

def connect_to_splunk(host, port, username, password):
    return client.connect(
        host=host,
        port=port,
        username=username,
        password=password,
        scheme="https"
    )

def list_saved_alerts(service):
    print("\nList of saved alerts:")
    for saved_search in service.saved_searches:
        if saved_search['alert_type'] != 'always':
            print(f" - {saved_search.name}")

def list_triggered_alerts(service, sid=None, verbose=False):
    """
    Retrieves and displays triggered alerts.
    
    Args:
        service: The Splunk connection
        sid: If specified, displays details for this specific SID
        verbose: If True, displays all details for each alert
    """
    print("\nRecently triggered alerts:")
    try:
        if sid:
            # If a SID is specified, display only details for this SID
            print(f"Retrieving details for SID: {sid}")
            return get_alert_details_by_sid(service, sid)
            
        # Approach 1: Use the /services/alerts/fired_alerts endpoint as recommended on the Splunk forum
        print("Retrieving triggered alerts via the Splunk API...")
        
        try:
            # Retrieve all triggered alerts
            response = service.get('/services/alerts/fired_alerts', output_mode='json', count=0)
            api_data = json.loads(response.body.read().decode('utf-8'))
            
            # Extract the list of alert names (ignore empty names or '-')
            alert_names = []
            for entry in api_data.get('entry', []):
                alert_name = entry.get('name', '')
                if alert_name and alert_name != '-' and alert_name not in alert_names:
                    alert_names.append(alert_name)
            
            if not alert_names:
                print("No triggered alerts found via the API (with a valid name).")
                
                # Check if there are unnamed alerts but with SIDs
                unnamed_alerts_found = False
                for entry in api_data.get('entry', []):
                    if entry.get('name', '') == '-':
                        unnamed_alerts_found = True
                        break
                
                if unnamed_alerts_found:
                    print("Unnamed alerts have been found. They will be named after their SID.")
                    # Add a special entry for unnamed alerts
                    alert_names.append("(Unnamed Alerts)")
            else:
                print(f"Alerts found: {', '.join(alert_names)}")
                
            # For each found alert, retrieve the details
            alerts_details = {}
            
            # First process alerts with a name
            for alert_name in alert_names:
                if alert_name == "(Unnamed Alerts)":
                    # Process unnamed alerts separately
                    continue
                
                print(f"\nRetrieving details for alert: {alert_name}")
                
                # Retrieve the last N triggers of this alert, sorted by date
                alert_response = service.get(
                    f'/services/alerts/fired_alerts/{alert_name}',
                    output_mode='json',
                    count=50,
                    sort_dir='desc',
                    sort_key='trigger_time'
                )
                alert_data = json.loads(alert_response.body.read().decode('utf-8'))
                
                # Extract entries
                entries = alert_data.get('entry', [])
                if not entries:
                    print(f"  No triggers found for alert: {alert_name}")
                    continue
                
                sids_set = set()  # To detect duplicates
                
                # Initialize the entry for this alert
                alerts_details[alert_name] = {
                    "name": alert_name,
                    "count": 0,  # Will be incremented while processing triggers
                    "triggers": [],
                    "first_time": None,
                    "last_time": None,
                    "app": "search",
                    "severity": "Medium",  # Default value
                    "sids": []
                }
                
                # For each trigger, extract details
                for entry in entries:
                    content = entry.get('content', {})
                    trigger_time_unix = content.get('trigger_time', '')
                    
                    # Convert Unix timestamp to readable date
                    try:
                        trigger_time = datetime.fromtimestamp(float(trigger_time_unix)).strftime('%Y-%m-%d %H:%M:%S')
                    except (ValueError, TypeError):
                        trigger_time = trigger_time_unix  # Keep the original value if conversion fails
                    
                    sid = None
                    
                    # Retrieve the SID from the links
                    links = entry.get('links', {})
                    job_link = links.get('job', '')
                    if job_link:
                        sid = job_link.split('/')[-1]
                    
                    # Avoid duplicate SIDs
                    if sid and sid not in sids_set:
                        sids_set.add(sid)
                        alerts_details[alert_name]["sids"].append(sid)
                        alerts_details[alert_name]["count"] += 1
                        
                        trigger_info = {
                            "time": trigger_time,
                            "time_unix": trigger_time_unix,
                            "sid": sid
                        }
                        alerts_details[alert_name]["triggers"].append(trigger_info)
                
                # Establish first/last timestamps in readable format
                if alerts_details[alert_name]["triggers"]:
                    alerts_details[alert_name]["first_time"] = alerts_details[alert_name]["triggers"][-1]["time"]
                    alerts_details[alert_name]["last_time"] = alerts_details[alert_name]["triggers"][0]["time"]
                
                # If we have at least one SID, retrieve additional info
                if alerts_details[alert_name]["sids"]:
                    sample_sid = alerts_details[alert_name]["sids"][0]
                    try:
                        # Retrieve job details
                        job_response = service.get(f'/services/search/jobs/{sample_sid}', output_mode='json')
                        job_data = json.loads(job_response.body.read().decode('utf-8'))
                        
                        # Extract app and severity if available
                        job_entry = job_data.get('entry', [{}])[0]
                        job_content = job_entry.get('content', {})
                        
                        if 'eai:acl' in job_content and 'app' in job_content['eai:acl']:
                            alerts_details[alert_name]["app"] = job_content['eai:acl']['app']
                        
                        # For severity, we could find it in the results
                        if verbose:
                            print(f"  Retrieving sample results for SID: {sample_sid}")
                            get_alert_details_by_sid(service, sample_sid, show_header=False, sample_only=True)
                    except Exception as e:
                        print(f"  Error retrieving job details: {str(e)}")
            
            # Process unnamed alerts if necessary
            if "(Unnamed Alerts)" in alert_names:
                print("\nRetrieving details for unnamed alerts")
                
                # Retrieve unnamed alerts
                response = service.get('/services/alerts/fired_alerts/-', output_mode='json', count=50, sort_dir='desc', sort_key='trigger_time')
                unnamed_data = json.loads(response.body.read().decode('utf-8'))
                
                entries = unnamed_data.get('entry', [])
                if not entries:
                    print("  No unnamed alerts found.")
                else:
                    # Group by base SID to identify unique alerts
                    sids_by_base = {}
                    for entry in entries:
                        links = entry.get('links', {})
                        job_link = links.get('job', '')
                        if not job_link:
                            continue
                            
                        sid = job_link.split('/')[-1]
                        # Extract base ID (before _at_)
                        sid_parts = sid.split('_at_')
                        sid_base = sid_parts[0]
                        
                        if sid_base not in sids_by_base:
                            sids_by_base[sid_base] = []
                        
                        sids_by_base[sid_base].append({
                            "sid": sid,
                            "entry": entry
                        })
                    
                    # For each unique alert (based on base SID)
                    for base_id, entries in sids_by_base.items():
                        # Generate a name based on the ID
                        alert_name = f"Alert {base_id.split('__')[-1]}"
                        
                        alerts_details[alert_name] = {
                            "name": alert_name,
                            "count": len(entries),
                            "triggers": [],
                            "first_time": None,
                            "last_time": None,
                            "app": "search",
                            "severity": "Medium",
                            "sids": []
                        }
                        
                        # Process each trigger
                        for entry_info in entries:
                            entry = entry_info["entry"]
                            sid = entry_info["sid"]
                            
                            content = entry.get('content', {})
                            trigger_time_unix = content.get('trigger_time', '')
                            
                            # Convert timestamp to readable date
                            try:
                                trigger_time = datetime.fromtimestamp(float(trigger_time_unix)).strftime('%Y-%m-%d %H:%M:%S')
                            except (ValueError, TypeError):
                                trigger_time = trigger_time_unix
                            
                            alerts_details[alert_name]["sids"].append(sid)
                            
                            trigger_info = {
                                "time": trigger_time,
                                "time_unix": trigger_time_unix,
                                "sid": sid
                            }
                            alerts_details[alert_name]["triggers"].append(trigger_info)
                        
                        # Sort triggers by time (most recent to oldest)
                        alerts_details[alert_name]["triggers"].sort(key=lambda x: x.get("time_unix", "0"), reverse=True)
                        
                        # Establish first/last timestamps
                        if alerts_details[alert_name]["triggers"]:
                            alerts_details[alert_name]["first_time"] = alerts_details[alert_name]["triggers"][-1]["time"]
                            alerts_details[alert_name]["last_time"] = alerts_details[alert_name]["triggers"][0]["time"]
                        
                        # Retrieve additional info from the first SID
                        if alerts_details[alert_name]["sids"]:
                            sample_sid = alerts_details[alert_name]["sids"][0]
                            try:
                                job_response = service.get(f'/services/search/jobs/{sample_sid}', output_mode='json')
                                job_data = json.loads(job_response.body.read().decode('utf-8'))
                                
                                job_entry = job_data.get('entry', [{}])[0]
                                job_content = job_entry.get('content', {})
                                
                                if 'eai:acl' in job_content and 'app' in job_content['eai:acl']:
                                    alerts_details[alert_name]["app"] = job_content['eai:acl']['app']
                                
                                # If we find the real alert name somewhere
                                saved_search = job_content.get('savedsearch_name')
                                if saved_search and saved_search != '-':
                                    alerts_details[alert_name]["real_name"] = saved_search
                                    print(f"  Real name identified for {alert_name}: {saved_search}")
                            except Exception as e:
                                print(f"  Error retrieving job details: {str(e)}")
            
            # Display the summary
            print("\nSummary of triggered alerts:")
            for i, (name, details) in enumerate(alerts_details.items(), 1):
                display_name = details.get("real_name", name)
                print(f"\n[{i}] {display_name} ({details['count']} trigger(s))")
                print(f"  - First trigger: {details['first_time']}")
                print(f"  - Last trigger: {details['last_time']}")
                print(f"  - Application: {details['app']}")
                print(f"  - Severity: {details['severity']}")
                
                if verbose and details['triggers']:
                    print(f"  - Trigger details:")
                    for j, trigger in enumerate(details['triggers'][:10], 1):
                        print(f"    [{j}] Time: {trigger['time']} - SID: {trigger['sid'] if trigger['sid'] else 'N/A'}")
                    
                    if len(details['triggers']) > 10:
                        print(f"    ... and {len(details['triggers']) - 10} other triggers.")
                
                # Do not display SIDs unless in verbose mode
                if verbose and details['sids']:
                    print(f"  - SIDs of triggers:")
                    print(f"    {', '.join(details['sids'][:5])}" + (", ..." if len(details['sids']) > 5 else ""))
            
            return
            
        except Exception as e:
            print(f"Error using the fired_alerts API: {str(e)}")
            print("Trying the alternative approach...")
        
        # If the API approach failed, use the audit logs
        print("Retrieving triggered alerts from audit logs...")
        
        # Search to retrieve triggered alerts
        search_query = """
        search index=_audit action=alert_fired earliest=-24h
        | fillnull value="-" 
        | table _time, savedsearch_name, alert, app, severity, digest_mode, sid, result_count
        | sort -_time
        """
        
        job = service.jobs.create(search_query, earliest_time="-24h", latest_time="now", output_mode="json")
        
        # Wait for the job to finish
        while not job.is_done():
            time.sleep(0.5)
        
        # Retrieve results
        results = job.results(output_mode="json")
        search_data = json.loads(results.read().decode('utf-8'))
        
        results_list = search_data.get('results', [])
        alert_count = len(results_list)
        
        if alert_count == 0:
            print("No triggered alerts found.")
            return
            
        # Group by search ID to identify unique alerts
        alerts_by_search_id = {}
        
        for result in results_list:
            alert_sid = result.get('sid', '')
            if not alert_sid:
                continue
                
            # Extract the base search ID (before _at_)
            search_id_parts = alert_sid.split('_at_')
            search_id_base = search_id_parts[0] if len(search_id_parts) > 0 else alert_sid
            
            if search_id_base not in alerts_by_search_id:
                alerts_by_search_id[search_id_base] = []
            
            alerts_by_search_id[search_id_base].append(result)
        
        # Group alerts by name for aggregation
        alerts_by_name = {}
        
        # For each distinct search ID
        for search_id, results in alerts_by_search_id.items():
            # Try to determine the alert name for this group
            alert_names = []
            
            # Collect all possible names
            for result in results:
                name = result.get('savedsearch_name', '')
                if name and name != '-' and name != 'N/A':
                    alert_names.append(name)
                    
                alt_name = result.get('alert', '')
                if alt_name and alt_name != '-' and alt_name != 'N/A':
                    alert_names.append(alt_name)
            
            # Select the most frequent or relevant name
            alert_name = None
            if alert_names:
                # Take the most frequent name
                name_counter = Counter(alert_names)
                alert_name = name_counter.most_common(1)[0][0]
            
            # If no name found, use a generic name
            if not alert_name:
                alert_name = f"Alert (ID: {search_id.split('__')[-1] if '__' in search_id else search_id})"
            
            # Process all results associated with this search ID
            for result in results:
                alert_time = result.get('_time', 'N/A')
                app = result.get('app', 'N/A')
                severity = result.get('severity', 'N/A')
                alert_sid = result.get('sid', 'N/A')
                
                # For severity, convert numeric code to text
                severity_text = "Unknown"
                if severity == "3":
                    severity_text = "Medium"
                elif severity == "4":
                    severity_text = "High"
                elif severity == "5":
                    severity_text = "Critical"
                elif severity == "2":
                    severity_text = "Low"
                elif severity == "1":
                    severity_text = "Informational"
                
                # Aggregate alerts by name
                if alert_name not in alerts_by_name:
                    alerts_by_name[alert_name] = {
                        "name": alert_name,
                        "count": 0,
                        "first_time": alert_time,
                        "last_time": alert_time,
                        "severity": severity_text,
                        "app": app if app != "-" else "search",
                        "sids": [],
                        "details": []
                    }
                
                alerts_by_name[alert_name]["count"] += 1
                alerts_by_name[alert_name]["last_time"] = alert_time
                if alert_sid != "N/A" and alert_sid != "-":
                    alerts_by_name[alert_name]["sids"].append(alert_sid)
                alerts_by_name[alert_name]["details"].append(result)
        
        # Display the summary of alerts
        print(f"\nSummary of triggered alerts: {alert_count} triggers for {len(alerts_by_name)} alert(s)")
        
        for i, (name, info) in enumerate(alerts_by_name.items(), 1):
            print(f"\n[{i}] {name} ({info['count']} trigger(s))")
            print(f"  - First trigger: {info['first_time']}")
            print(f"  - Last trigger: {info['last_time']}")
            print(f"  - Severity: {info['severity']}")
            print(f"  - Application: {info['app']}")
            
            if verbose:
                # Display all triggers in verbose mode
                print(f"  - Trigger details:")
                for j, detail in enumerate(info['details'], 1):
                    alert_sid = detail.get('sid', 'N/A')
                    trigger_time = detail.get('_time', 'N/A')
                    print(f"    [{j}] {trigger_time} - SID: {alert_sid}")
                    
                # If we have SIDs, retrieve details of the first
                if info['sids'] and not sid:
                    sample_sid = info['sids'][0]
                    get_alert_details_by_sid(service, sample_sid, show_header=False, sample_only=True)
            elif info['sids']:
                print(f"  - To see details, use the --sid option with one of the following SIDs:")
                print(f"    {', '.join(info['sids'][:5])}" + (", ..." if len(info['sids']) > 5 else ""))

    except HTTPError as e:
        print(f"HTTP Error: {e}")
    except Exception as e:
        print(f"Error retrieving triggered alerts: {e}")
        import traceback
        print(traceback.format_exc())

def get_alert_details_by_sid(service, sid, show_header=True, sample_only=False):
    """
    Retrieves and displays details of an alert by its SID
    """
    try:
        if show_header:
            print(f"\nDetails for SID {sid}:")
        
        # Retrieve search results
        results_url = f'/services/search/jobs/{sid}/results'
        results_response = service.get(results_url, output_mode='json', count=10 if sample_only else 100)
        results_data = json.loads(results_response.body.read().decode('utf-8'))
        
        # Retrieve job summary
        summary_url = f'/services/search/jobs/{sid}'
        summary_response = service.get(summary_url, output_mode='json')
        summary_data = json.loads(summary_response.body.read().decode('utf-8'))
        
        # Extract job info
        entry = summary_data.get('entry', [{}])[0]
        content = entry.get('content', {})
        
        # Display general job information
        print(f"  - Query: {content.get('search', 'N/A')}")
        print(f"  - Status: {content.get('dispatchState', 'N/A')}")
        print(f"  - Execution time: {content.get('runDuration', 'N/A')} seconds")
        
        # Display results
        sample_results = results_data.get('results', [])
        result_count = len(sample_results)
        
        if result_count == 0:
            print("  - No results found")
            return
            
        max_display = 3 if sample_only else 10
        print(f"  - {result_count} result(s) found" + (" (sample)" if sample_only else ""))
        
        # Determine fields to display
        sample = sample_results[0]
        fields = []
        for key in sample:
            if key.startswith('_') and key not in ['_time', '_raw']:
                continue
            fields.append(key)
        
        # Limit fields to 5 if in sample mode
        if sample_only and len(fields) > 5:
            fields = fields[:5]
            
        # Display results
        for i, result in enumerate(sample_results[:max_display], 1):
            print(f"\n    Result {i}:")
            for field in fields:
                value = result.get(field, '')
                if field == '_raw' and len(value) > 100:
                    value = value[:100] + "..."
                print(f"      {field}: {value}")
                
        if result_count > max_display:
            print(f"\n    ... and {result_count - max_display} other results.")
            
        return True
            
    except Exception as e:
        print(f"  Error retrieving details for SID {sid}: {str(e)}")
        return False

def get_all_saved_searches():
    try:
        service = connect_to_splunk(SPLUNK_HOST, SPLUNK_PORT, SPLUNK_USERNAME, SPLUNK_PASSWORD)
        saved_searches = []
        
        for search in service.saved_searches:
            search_info = {
                "name": search.name,
                "is_scheduled": bool(search.get("is_scheduled", False)),
                "search": search.get("search", ""),
                "description": search.get("description", ""),
                "cron_schedule": search.get("cron_schedule", ""),
                "alert_type": search.get("alert_type", ""),
                "alert_threshold": search.get("alert_threshold", "")
            }
            
            # Add all properties that start with 'action.' or 'alert.'
            for key, value in search.content.items():
                if key.startswith('action.') or key.startswith('alert.'):
                    search_info[key] = value
            
            saved_searches.append(search_info)
        
        return saved_searches
    except Exception as e:
        return f"Error retrieving saved searches: {str(e)}"

def list_saved_searches(json_output=False):
    """List all saved searches with their properties"""
    if json_output:
        searches_data = get_all_saved_searches()
        if isinstance(searches_data, str) and searches_data.startswith("Error"):
            return {"error": searches_data}
        
        # Convert to a format suitable for the frontend
        saved_searches = []
        for search in searches_data:
            search_data = {
                "name": search.get("name", "Unknown"),
                "search": search.get("search", ""),
                "description": search.get("description", ""),
                "is_scheduled": search.get("is_scheduled", False),
                "cron_schedule": search.get("cron_schedule", ""),
                "alert_type": search.get("alert_type", ""),
                "alert_threshold": search.get("alert_threshold", ""),
                "severity": search.get("alert.severity", ""),
                "actions": []
            }
            
            # Extract actions
            for key in search:
                if key.startswith("action.") and search.get(key) == "1":
                    action_name = key.split(".")[1]
                    search_data["actions"].append(action_name)
            
            saved_searches.append(search_data)
        
        return {"saved_searches": saved_searches}
    
    # Regular console output
    searches_data = get_all_saved_searches()
    if isinstance(searches_data, str):
        print(searches_data)
        return
    
    print(f"\nFound {len(searches_data)} saved searches:")
    for i, search in enumerate(searches_data, 1):
        is_alert = search.get("alert_type") and search.get("is_scheduled")
        print(f"\n[{i}] {search.get('name')}")
        print(f"  - Is Alert: {is_alert}")
        print(f"  - Is Scheduled: {search.get('is_scheduled')}")
        print(f"  - Search: {search.get('search')[:100]}...")
        if search.get("description"):
            print(f"  - Description: {search.get('description')}")
        if search.get("is_scheduled"):
            print(f"  - Schedule: {search.get('cron_schedule')}")
        if is_alert:
            print(f"  - Alert Type: {search.get('alert_type')}")
            severity = search.get("alert.severity", "N/A")
            print(f"  - Severity: {severity}")
            
            # Display actions
            actions = []
            for key in search:
                if key.startswith("action.") and search.get(key) == "1":
                    action_name = key.split(".")[1]
                    actions.append(action_name)
            
            if actions:
                print(f"  - Actions: {', '.join(actions)}")

def main():
    parser = argparse.ArgumentParser(description="Splunk alert management tool")
    parser.add_argument("action", choices=["list_alerts", "triggered_alerts", "list_searches"], 
                        help="Action to perform")
    parser.add_argument("--sid", help="Specific SID to get details for")
    parser.add_argument("--verbose", "-v", action="store_true", 
                        help="Display all details of alerts")
    parser.add_argument("--list-saved-searches-json", action="store_true", 
                        help="List all saved searches in JSON format")
    
    args = parser.parse_args()
    
    if args.list_saved_searches_json:
        result = list_saved_searches(json_output=True)
        print(json.dumps(result))
        return
    
    if args.action == "list_searches":
        list_saved_searches()
        return
    
    service = connect_to_splunk(SPLUNK_HOST, SPLUNK_PORT, SPLUNK_USERNAME, SPLUNK_PASSWORD)
    
    if args.action == "list_alerts":
        list_saved_alerts(service)
    elif args.action == "triggered_alerts":
        list_triggered_alerts(service, args.sid, args.verbose)
    else:
        print("Unknown action. Use: list_alerts, triggered_alerts, or list_searches")

if __name__ == "__main__":
    main()
