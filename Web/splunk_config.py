import requests

response = requests.get('http://localhost/scripts/php/decryptSplunkConfig.php')
try:
    config = response.json()
except requests.exceptions.JSONDecodeError:
    print("Failed to decode JSON response:")
    print(response.text)
    raise

if 'error' in config:
    raise Exception(config['error'])

SPLUNK_HOST = config['SPLUNK_HOST']
SPLUNK_PORT = config['SPLUNK_PORT']
SPLUNK_API_TOKEN = config['SPLUNK_API_TOKEN']
