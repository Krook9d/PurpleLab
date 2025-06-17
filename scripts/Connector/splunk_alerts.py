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

def connect_to_splunk(host, port, username, password, ssl_enabled=True):
    scheme = "https" if ssl_enabled else "http"
    if host.startswith("http://"):
        host = host.split("://")[1]
    elif host.startswith("https://"):
        host = host.split("://")[1]
    return client.connect(
        host=host,
        port=port,
        username=username,
        password=password,
        scheme=scheme
    )

def list_saved_alerts(service):
    print("\nList of saved alerts:")
    for saved_search in service.saved_searches:
        if saved_search['alert_type']:
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
            print(f"Retrieving details for SID: {sid}")
            return get_alert_details_by_sid(service, sid)
            
        print("Retrieving triggered alerts via the Splunk API...")
        
        try:
            response = service.get('/services/alerts/fired_alerts', output_mode='json', count=0)
            api_data = json.loads(response.body.read().decode('utf-8'))
            
            alert_names = []
            for entry in api_data.get('entry', []):
                alert_name = entry.get('name', '')
                if alert_name and alert_name != '-' and alert_name not in alert_names:
                    alert_names.append(alert_name)
            
            if not alert_names:
                print("No triggered alerts found via the API (with a valid name).")
                
                unnamed_alerts_found = False
                for entry in api_data.get('entry', []):
                    if entry.get('name', '') == '-':
                        unnamed_alerts_found = True
                        break
                
                if unnamed_alerts_found:
                    print("Unnamed alerts have been found. They will be named after their SID.")
                    alert_names.append("(Unnamed Alerts)")
            else:
                print(f"Alerts found: {', '.join(alert_names)}")
                
            alerts_details = {}
            
            for alert_name in alert_names:
                if alert_name == "(Unnamed Alerts)":
                    continue
                
                # Skip system alerts
                if is_system_alert(alert_name):
                    continue
                
                print(f"\nRetrieving details for alert: {alert_name}")
                
                alert_response = service.get(
                    f'/services/alerts/fired_alerts/{alert_name}',
                    output_mode='json',
                    count=50,
                    sort_dir='desc',
                    sort_key='trigger_time'
                )
                alert_data = json.loads(alert_response.body.read().decode('utf-8'))
                
                entries = alert_data.get('entry', [])
                if not entries:
                    print(f"  No triggers found for alert: {alert_name}")
                    continue
                
                sids_set = set()
                
                alerts_details[alert_name] = {
                    "name": alert_name,
                    "count": 0,
                    "triggers": [],
                    "first_time": None,
                    "last_time": None,
                    "app": "search",
                    "severity": "Medium",
                    "sids": []
                }
                
                for entry in entries:
                    content = entry.get('content', {})
                    trigger_time_unix = content.get('trigger_time', '')
                    
                    try:
                        trigger_time = datetime.fromtimestamp(float(trigger_time_unix)).strftime('%Y-%m-%d %H:%M:%S')
                    except (ValueError, TypeError):
                        trigger_time = trigger_time_unix
                    
                    sid = None
                    
                    links = entry.get('links', {})
                    job_link = links.get('job', '')
                    if job_link:
                        sid = job_link.split('/')[-1]
                    
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
                
                if alerts_details[alert_name]["triggers"]:
                    alerts_details[alert_name]["first_time"] = alerts_details[alert_name]["triggers"][-1]["time"]
                    alerts_details[alert_name]["last_time"] = alerts_details[alert_name]["triggers"][0]["time"]
                
                if alerts_details[alert_name]["sids"]:
                    sample_sid = alerts_details[alert_name]["sids"][0]
                    try:
                        job_response = service.get(f'/services/search/jobs/{sample_sid}', output_mode='json')
                        job_data = json.loads(job_response.body.read().decode('utf-8'))
                        
                        job_entry = job_data.get('entry', [{}])[0]
                        job_content = job_entry.get('content', {})
                        
                        if 'eai:acl' in job_content and 'app' in job_content['eai:acl']:
                            alerts_details[alert_name]["app"] = job_content['eai:acl']['app']
                        
                        if verbose:
                            print(f"  Retrieving sample results for SID: {sample_sid}")
                            get_alert_details_by_sid(service, sample_sid, show_header=False, sample_only=True)
                    except Exception as e:
                        print(f"  Error retrieving job details: {str(e)}")
            
            if "(Unnamed Alerts)" in alert_names:
                print("\nRetrieving details for unnamed alerts")
                
                response = service.get('/services/alerts/fired_alerts/-', output_mode='json', count=50, sort_dir='desc', sort_key='trigger_time')
                unnamed_data = json.loads(response.body.read().decode('utf-8'))
                
                entries = unnamed_data.get('entry', [])
                if not entries:
                    print("  No unnamed alerts found.")
                else:
                    sids_by_base = {}
                    for entry in entries:
                        links = entry.get('links', {})
                        job_link = links.get('job', '')
                        if not job_link:
                            continue
                            
                        sid = job_link.split('/')[-1]
                        sid_parts = sid.split('_at_')
                        sid_base = sid_parts[0]
                        
                        if sid_base not in sids_by_base:
                            sids_by_base[sid_base] = []
                        
                        sids_by_base[sid_base].append({
                            "sid": sid,
                            "entry": entry
                        })
                    
                    for base_id, entries in sids_by_base.items():
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
                        
                        for entry_info in entries:
                            entry = entry_info["entry"]
                            sid = entry_info["sid"]
                            
                            content = entry.get('content', {})
                            trigger_time_unix = content.get('trigger_time', '')
                            
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
                        
                        alerts_details[alert_name]["triggers"].sort(key=lambda x: x.get("time_unix", "0"), reverse=True)
                        
                        if alerts_details[alert_name]["triggers"]:
                            alerts_details[alert_name]["first_time"] = alerts_details[alert_name]["triggers"][-1]["time"]
                            alerts_details[alert_name]["last_time"] = alerts_details[alert_name]["triggers"][0]["time"]
                        
                        if alerts_details[alert_name]["sids"]:
                            sample_sid = alerts_details[alert_name]["sids"][0]
                            try:
                                job_response = service.get(f'/services/search/jobs/{sample_sid}', output_mode='json')
                                job_data = json.loads(job_response.body.read().decode('utf-8'))
                                
                                job_entry = job_data.get('entry', [{}])[0]
                                job_content = job_entry.get('content', {})
                                
                                if 'eai:acl' in job_content and 'app' in job_content['eai:acl']:
                                    alerts_details[alert_name]["app"] = job_content['eai:acl']['app']
                                
                                saved_search = job_content.get('savedsearch_name')
                                if saved_search and saved_search != '-':
                                    alerts_details[alert_name]["real_name"] = saved_search
                                    print(f"  Real name identified for {alert_name}: {saved_search}")
                            except Exception as e:
                                print(f"  Error retrieving job details: {str(e)}")
            
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
                
                if verbose and details['sids']:
                    print(f"  - SIDs of triggers:")
                    print(f"    {', '.join(details['sids'][:5])}" + (", ..." if len(details['sids']) > 5 else ""))
            
            return
            
        except Exception as e:
            print(f"Error using the fired_alerts API: {str(e)}")
            print("Trying the alternative approach...")
        
        print("Retrieving triggered alerts from audit logs...")
        
        search_query = """
        search index=_audit action=alert_fired earliest=-24h
        | fillnull value="-" 
        | table _time, savedsearch_name, alert, app, severity, digest_mode, sid, result_count
        | sort -_time
        """
        
        job = service.jobs.create(search_query, earliest_time="-24h", latest_time="now", output_mode="json")
        
        while not job.is_done():
            time.sleep(0.5)
        
        results = job.results(output_mode="json")
        search_data = json.loads(results.read().decode('utf-8'))
        
        results_list = search_data.get('results', [])
        alert_count = len(results_list)
        
        if alert_count == 0:
            print("No triggered alerts found.")
            return
            
        alerts_by_search_id = {}
        
        for result in results_list:
            alert_sid = result.get('sid', '')
            if not alert_sid:
                continue
                
            search_id_parts = alert_sid.split('_at_')
            search_id_base = search_id_parts[0] if len(search_id_parts) > 0 else alert_sid
            
            if search_id_base not in alerts_by_search_id:
                alerts_by_search_id[search_id_base] = []
            
            alerts_by_search_id[search_id_base].append(result)
        
        alerts_by_name = {}
        
        for search_id, results in alerts_by_search_id.items():
            alert_names = []
            
            for result in results:
                name = result.get('savedsearch_name', '')
                if name and name != '-' and name != 'N/A':
                    alert_names.append(name)
                    
                alt_name = result.get('alert', '')
                if alt_name and alt_name != '-' and alt_name != 'N/A':
                    alert_names.append(alt_name)
            
            alert_name = None
            if alert_names:
                name_counter = Counter(alert_names)
                alert_name = name_counter.most_common(1)[0][0]
            
            if not alert_name:
                alert_name = f"Alert (ID: {search_id.split('__')[-1] if '__' in search_id else search_id})"
            
            # Skip system alerts
            if is_system_alert(alert_name):
                continue
            
            for result in results:
                alert_time = result.get('_time', 'N/A')
                app = result.get('app', 'N/A')
                severity = result.get('severity', 'N/A')
                alert_sid = result.get('sid', 'N/A')
                
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
        
        print(f"\nSummary of triggered alerts: {alert_count} triggers for {len(alerts_by_name)} alert(s)")
        
        for i, (name, info) in enumerate(alerts_by_name.items(), 1):
            print(f"\n[{i}] {name} ({info['count']} trigger(s))")
            print(f"  - First trigger: {info['first_time']}")
            print(f"  - Last trigger: {info['last_time']}")
            print(f"  - Severity: {info['severity']}")
            print(f"  - Application: {info['app']}")
            
            if verbose:
                print(f"  - Trigger details:")
                for j, detail in enumerate(info['details'], 1):
                    alert_sid = detail.get('sid', 'N/A')
                    trigger_time = detail.get('_time', 'N/A')
                    print(f"    [{j}] {trigger_time} - SID: {alert_sid}")
                    
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
        
        results_url = f'/services/search/jobs/{sid}/results'
        results_response = service.get(results_url, output_mode='json', count=10 if sample_only else 100)
        results_data = json.loads(results_response.body.read().decode('utf-8'))
        
        summary_url = f'/services/search/jobs/{sid}'
        summary_response = service.get(summary_url, output_mode='json')
        summary_data = json.loads(summary_response.body.read().decode('utf-8'))
        
        entry = summary_data.get('entry', [{}])[0]
        content = entry.get('content', {})
        
        print(f"  - Query: {content.get('search', 'N/A')}")
        print(f"  - Status: {content.get('dispatchState', 'N/A')}")
        print(f"  - Execution time: {content.get('runDuration', 'N/A')} seconds")
        
        sample_results = results_data.get('results', [])
        result_count = len(sample_results)
        
        if result_count == 0:
            print("  - No results found")
            return
            
        max_display = 3 if sample_only else 10
        print(f"  - {result_count} result(s) found" + (" (sample)" if sample_only else ""))
        
        sample = sample_results[0]
        fields = []
        for key in sample:
            if key.startswith('_') and key not in ['_time', '_raw']:
                continue
            fields.append(key)
        
        if sample_only and len(fields) > 5:
            fields = fields[:5]
            
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

def is_system_alert(search_name, search_info=None):
    """
    Determine if an alert is a system/default alert that should be filtered out
    """
    # System app names to exclude (more restrictive on 'search' app)
    system_apps = ['launcher', 'learned', 'legacy', 'sample_app', 'introspection_generator_addon', 'splunk_monitoring_console', 'splunk_instrumentation']
    
    # Default alert name patterns to exclude
    default_patterns = [
        'Errors in the last',
        'Splunk errors',
        'Indexing workload',
        'License usage',
        'Search head cluster',
        'Deployment server',
        'DMC ',  # Distributed Management Console alerts
        'SplunkEnterpriseSecuritySuite',
        'Notable Event',
        'Risk Notable',
        'Incident Review',
        'Asset Investigator',
        'Identity Investigator',
        'Network Investigator',
        'Splunk_TA_',
        'SA-',
        'TA-',
        'Add-on',
        'Technology Add-on',
        'Orphaned scheduled searches',
        'Bucket Merge',
        'Retrieve Conf Settings',
        'Actions Accelerate',
        'Accelerate',
        'Data Model Acceleration',
        'Summary indexing',
        'Report acceleration',
        'KV Store',
        'Internal audit',
        'System health',
        'Performance monitoring',
        'Scheduler',
        'Internal logs',
        'Splunk platform',
        'Configuration',
        'Messages by minute',
        'Messages by hour',
        'Events per second',
        'Volume by sourcetype',
        'Data quality',
        'Index size',
        'Forwarder monitoring'
    ]
    
    # Check alert name patterns
    search_name_lower = search_name.lower()
    for pattern in default_patterns:
        if pattern.lower() in search_name_lower:
            return True
    
    # Check if it's from a system app
    if search_info:
        app = search_info.get('eai:acl', {}).get('app', '')
        if app in system_apps:
            return True
        
        # Special handling for 'search' app - more selective filtering
        if app == 'search':
            # If it's from 'search' app and matches system patterns, exclude it
            for pattern in default_patterns:
                if pattern.lower() in search_name_lower:
                    return True
            
            # Check if owned by 'nobody' or 'system' (often indicates system alerts)
            owner = search_info.get('eai:acl', {}).get('owner', '')
            if owner in ['nobody', 'system', '']:
                # Additional check: if it's a simple report without alert actions
                if search_info.get('is_scheduled', False) and not search_info.get('alert_type'):
                    return True
                
                # Check if search query looks like system monitoring
                search_query = search_info.get('search', '').lower()
                system_query_patterns = [
                    'index=_internal',
                    'index=_audit',
                    'index=_introspection',
                    'component=',
                    'sourcetype=splunk',
                    'splunkd_access',
                    'scheduler',
                    'metrics.log'
                ]
                
                if any(pattern in search_query for pattern in system_query_patterns):
                    return True
        
        # Check if it's a report rather than an alert
        if search_info.get('is_scheduled', False) and not search_info.get('alert_type'):
            return True
            
        # Check if alert actions are only basic ones (might indicate system alert)
        actions = []
        for key, value in search_info.items():
            if key.startswith('action.') and value == '1':
                action_name = key.split('.')[1]
                actions.append(action_name)
        
        # If only email/log actions, might be system alert
        system_actions = ['email', 'log', 'outputlookup', 'summary_index']
        if actions and all(action in system_actions for action in actions):
            # Additional check: if it has very generic search terms
            search_query = search_info.get('search', '').lower()
            generic_terms = ['error', 'warn', 'fail', 'exception', 'license', 'usage', 'status']
            if any(term in search_query for term in generic_terms) and len(search_query) < 200:
                return True
    
    return False

def get_all_saved_searches():
    try:
        service = connect_to_splunk(SPLUNK_HOST, SPLUNK_PORT, SPLUNK_USERNAME, SPLUNK_PASSWORD)
        saved_searches = []
        
        for search in service.saved_searches:
            # Skip system alerts
            if is_system_alert(search.name, search.content):
                continue
                
            search_info = {
                "name": search.name,
                "is_scheduled": bool(search.content.get("is_scheduled", False)),
                "search": search.content.get("search", ""),
                "description": search.content.get("description", ""),
                "cron_schedule": search.content.get("cron_schedule", ""),
                "alert_type": search.content.get("alert_type", ""),
                "alert_threshold": search.content.get("alert_threshold", ""),
                "app": search.content.get("eai:acl", {}).get("app", "unknown")
            }
            
            for key, value in search.content.items():
                if key.startswith('action.') or key.startswith('alert.'):
                    search_info[key] = value
            
            saved_searches.append(search_info)
        
        return saved_searches
    except Exception as e:
        return f"Error retrieving saved searches: {str(e)}"

def list_saved_searches(json_output=False):
    """List saved searches that are configured as alerts (including real-time alerts)"""
    if json_output:
        searches_data = get_all_saved_searches()
        if isinstance(searches_data, str) and searches_data.startswith("Error"):
            return {"error": searches_data}
        
        saved_searches = []
        for search in searches_data:
            alert_type = search.get("alert_type", "")
            is_scheduled = search.get("is_scheduled", False)
            
            # Include all alerts with alert_type, including real-time alerts ("always")
            if not alert_type:
                continue
                
            # Include both scheduled and real-time alerts
            if not is_scheduled and alert_type != "always":
                continue
            
            search_data = {
                "name": search.get("name", "Unknown"),
                "search": search.get("search", ""),
                "description": search.get("description", ""),
                "is_scheduled": is_scheduled,
                "cron_schedule": search.get("cron_schedule", ""),
                "alert_type": alert_type,
                "alert_threshold": search.get("alert_threshold", ""),
                "severity": search.get("alert.severity", ""),
                "actions": []
            }
            
            for key in search:
                if key.startswith("action.") and search.get(key) == "1":
                    action_name = key.split(".")[1]
                    search_data["actions"].append(action_name)
            
            saved_searches.append(search_data)
        
        return {"saved_searches": saved_searches}
    
    searches_data = get_all_saved_searches()
    if isinstance(searches_data, str):
        print(searches_data)
        return
    
    alert_searches = []
    for search in searches_data:
        alert_type = search.get("alert_type", "")
        is_scheduled = search.get("is_scheduled", False)
        
        # Include all alerts with alert_type, including real-time alerts
        if alert_type and (is_scheduled or alert_type == "always"):
            alert_searches.append(search)
    
    print(f"\nFound {len(alert_searches)} alerts (filtered from {len(searches_data)} total saved searches):")
    for i, search in enumerate(alert_searches, 1):
        print(f"\n[{i}] {search.get('name')}")
        print(f"  - Alert Type: {search.get('alert_type')}")
        print(f"  - Schedule: {search.get('cron_schedule')}")
        print(f"  - Search: {search.get('search')[:100]}...")
        if search.get("description"):
            print(f"  - Description: {search.get('description')}")
        
        severity = search.get("alert.severity", "N/A")
        print(f"  - Severity: {severity}")
        
        actions = []
        for key in search:
            if key.startswith("action.") and search.get(key) == "1":
                action_name = key.split(".")[1]
                actions.append(action_name)
        
        if actions:
            print(f"  - Actions: {', '.join(actions)}")

def get_saved_searches_with_trigger_info(json_output=False):
    """Get saved searches with trigger information from fired alerts (including real-time alerts)"""
    try:
        service = connect_to_splunk(SPLUNK_HOST, SPLUNK_PORT, SPLUNK_USERNAME, SPLUNK_PASSWORD)
        
        saved_searches = []
        for search in service.saved_searches:
            # Skip system alerts
            if is_system_alert(search.name, search.content):
                continue
                
            alert_type = search.content.get("alert_type", "")
            is_scheduled = bool(search.content.get("is_scheduled", False))
            
            # Include all alerts with alert_type, including real-time alerts ("always")
            if not alert_type:
                continue
                
            # Include both scheduled and real-time alerts
            if not is_scheduled and alert_type != "always":
                continue
            
            search_info = {
                "name": search.name,
                "id": search.name,
                "is_scheduled": is_scheduled,
                "search": search.content.get("search", ""),
                "description": search.content.get("description", ""),
                "cron_schedule": search.content.get("cron_schedule", ""),
                "alert_type": alert_type,
                "alert_threshold": search.content.get("alert_threshold", ""),
                "severity": search.content.get("alert.severity", ""),
                "rule_type": "alert",
                "type": "alert",
                "is_active": False,
                "start_time": None,
                "last_notification_time": None,
                "trigger_time": None,
                "actions": [],
                "app": search.content.get("eai:acl", {}).get("app", "unknown")
            }
            
            for key, value in search.content.items():
                if key.startswith("action.") and value == "1":
                    action_name = key.split(".")[1]
                    search_info["actions"].append(action_name)
                elif key.startswith('action.') or key.startswith('alert.'):
                    search_info[key] = value
            
            saved_searches.append(search_info)
        
        try:
            response = service.get('/services/alerts/fired_alerts', output_mode='json', count=0)
            api_data = json.loads(response.body.read().decode('utf-8'))
            
            alert_trigger_map = {}
            
            for entry in api_data.get('entry', []):
                alert_name = entry.get('name', '')
                if not alert_name or alert_name == '-':
                    continue
                
                # Skip system alerts from fired alerts too
                if is_system_alert(alert_name):
                    continue
                
                try:
                    alert_response = service.get(
                        f'/services/alerts/fired_alerts/{alert_name}',
                        output_mode='json',
                        count=1,
                        sort_dir='desc',
                        sort_key='trigger_time'
                    )
                    alert_data = json.loads(alert_response.body.read().decode('utf-8'))
                    
                    entries = alert_data.get('entry', [])
                    if entries:
                        latest_entry = entries[0]
                        content = latest_entry.get('content', {})
                        trigger_time_unix = content.get('trigger_time', '')
                        
                        if trigger_time_unix:
                            alert_trigger_map[alert_name] = {
                                'trigger_time': float(trigger_time_unix),
                                'is_active': True
                            }
                except Exception as e:
                    print(f"Error getting trigger info for {alert_name}: {e}")
                    continue
        
        except Exception as e:
            print(f"Error retrieving fired alerts: {e}")
        
        for search in saved_searches:
            search_name = search['name']
            if search_name in alert_trigger_map:
                trigger_info = alert_trigger_map[search_name]
                search['is_active'] = trigger_info['is_active']
                search['trigger_time'] = trigger_info['trigger_time']
                search['start_time'] = trigger_info['trigger_time']
                search['last_notification_time'] = trigger_info['trigger_time']
        
        if json_output:
            return {"saved_searches": saved_searches}
        else:
            return saved_searches
            
    except Exception as e:
        error_msg = f"Error retrieving saved searches with trigger info: {str(e)}"
        if json_output:
            return {"error": error_msg}
        else:
            print(error_msg)
            return []

def main():
    parser = argparse.ArgumentParser(description="Splunk alert management tool")
    parser.add_argument("action", choices=["list_alerts", "triggered_alerts", "list_searches", "list_searches_with_triggers"], 
                        help="Action to perform")
    parser.add_argument("--sid", help="Specific SID to get details for")
    parser.add_argument("--verbose", "-v", action="store_true", 
                        help="Display all details of alerts")
    parser.add_argument("--list-saved-searches-json", action="store_true", 
                        help="List all saved searches in JSON format")
    parser.add_argument("--list-saved-searches-with-triggers-json", action="store_true", 
                        help="List all saved searches with trigger information in JSON format")
    
    args = parser.parse_args()
    
    if args.list_saved_searches_json:
        result = list_saved_searches(json_output=True)
        print(json.dumps(result))
        return
    
    if args.list_saved_searches_with_triggers_json:
        result = get_saved_searches_with_trigger_info(json_output=True)
        print(json.dumps(result))
        return
    
    if args.action == "list_searches":
        list_saved_searches()
        return
    
    if args.action == "list_searches_with_triggers":
        searches = get_saved_searches_with_trigger_info(json_output=False)
        print(f"\nFound {len(searches)} saved searches with trigger information:")
        for search in searches:
            print(f"\n- {search['name']}")
            print(f"  Type: {search['type']}")
            print(f"  Scheduled: {search['is_scheduled']}")
            print(f"  Active: {search['is_active']}")
            if search['trigger_time']:
                from datetime import datetime
                trigger_date = datetime.fromtimestamp(search['trigger_time'])
                print(f"  Last triggered: {trigger_date.strftime('%Y-%m-%d %H:%M:%S')}")
        return
    
    service = connect_to_splunk(SPLUNK_HOST, SPLUNK_PORT, SPLUNK_USERNAME, SPLUNK_PASSWORD)
    
    if args.action == "list_alerts":
        list_saved_alerts(service)
    elif args.action == "triggered_alerts":
        list_triggered_alerts(service, args.sid, args.verbose)
    else:
        print("Unknown action. Use: list_alerts, triggered_alerts, list_searches, or list_searches_with_triggers")

if __name__ == "__main__":
    main()
