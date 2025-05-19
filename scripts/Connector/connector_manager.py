#!/usr/bin/env python3
"""
Connector Manager for Rule Lifecycle
Manages secure storage of connector credentials
"""

import os
import json
import base64
import sys
from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC

# Base directory for storing connector information
STORAGE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "storage")
KEY_FILE = os.path.join(STORAGE_DIR, ".key")
SALT_FILE = os.path.join(STORAGE_DIR, ".salt")
CONNECTOR_FILE = os.path.join(STORAGE_DIR, "connectors.enc")

# Ensure storage directory exists
os.makedirs(STORAGE_DIR, exist_ok=True)

def generate_key(password, salt=None):
    """Generate a Fernet key from a password and salt"""
    if salt is None:
        salt = os.urandom(16)
    
    kdf = PBKDF2HMAC(
        algorithm=hashes.SHA256(),
        length=32,
        salt=salt,
        iterations=100000,
    )
    
    key = base64.urlsafe_b64encode(kdf.derive(password.encode()))
    return key, salt

def get_key():
    """Retrieve or create the encryption key"""
    if not os.path.exists(KEY_FILE) or not os.path.exists(SALT_FILE):
        # Generate a new key using a random password
        password = base64.b64encode(os.urandom(32)).decode('utf-8')
        key, salt = generate_key(password)
        
        # Save the key and salt
        with open(KEY_FILE, 'wb') as f:
            f.write(key)
        
        with open(SALT_FILE, 'wb') as f:
            f.write(salt)
        
        return key
    
    # Load existing key
    with open(KEY_FILE, 'rb') as f:
        key = f.read()
    
    return key

def encrypt_data(data):
    """Encrypt data using the encryption key"""
    key = get_key()
    f = Fernet(key)
    encrypted_data = f.encrypt(json.dumps(data).encode())
    return encrypted_data

def decrypt_data():
    """Decrypt stored data"""
    if not os.path.exists(CONNECTOR_FILE):
        return {}
    
    key = get_key()
    f = Fernet(key)
    
    try:
        with open(CONNECTOR_FILE, 'rb') as file:
            encrypted_data = file.read()
        
        decrypted_data = f.decrypt(encrypted_data)
        return json.loads(decrypted_data.decode())
    except Exception as e:
        print(f"Error decrypting data: {str(e)}")
        return {}

def save_connector(connector_type, config):
    """Save connector configuration"""
    data = decrypt_data()
    data[connector_type] = config
    
    encrypted_data = encrypt_data(data)
    
    with open(CONNECTOR_FILE, 'wb') as file:
        file.write(encrypted_data)
    
    return True

def get_connector(connector_type):
    """Retrieve connector configuration"""
    data = decrypt_data()
    return data.get(connector_type, {})

def list_connectors():
    """List all configured connectors"""
    data = decrypt_data()
    return list(data.keys())

def delete_connector(connector_type):
    """Delete a connector configuration"""
    data = decrypt_data()
    
    if connector_type in data:
        del data[connector_type]
        
        if data:
            encrypted_data = encrypt_data(data)
            with open(CONNECTOR_FILE, 'wb') as file:
                file.write(encrypted_data)
        else:
            # If no connectors left, remove the file
            if os.path.exists(CONNECTOR_FILE):
                os.remove(CONNECTOR_FILE)
        
        return True
    
    return False

def test_connector(connector_type, config):
    """Test connector connection"""
    if connector_type == "opensearch":
        return test_opensearch_connection(config)
    elif connector_type == "splunk":
        return test_splunk_connection(config)
    else:
        return False, "Unsupported connector type"

def test_opensearch_connection(config):
    """Test OpenSearch connection"""
    try:
        import requests
        from requests.auth import HTTPBasicAuth
        import urllib3
        
        # Ignore SSL warnings for self-signed certificates
        urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
        
        host = config.get("host", "")
        username = config.get("username", "")
        password = config.get("password", "")
        
        # Extract port from host if included
        port = config.get("port", "9200")
        
        # Remove protocol if present
        if host.startswith("http://") or host.startswith("https://"):
            host = host.split("://")[1]
        
        # Remove port if included in host
        if ":" in host:
            host_parts = host.split(":")
            host = host_parts[0]
            if len(host_parts) > 1 and host_parts[1].isdigit():
                port = host_parts[1]
        
        # Construct the URL
        url = f"https://{host}:{port}"
        
        # Test connection
        response = requests.get(
            f"{url}/_cluster/health",
            auth=HTTPBasicAuth(username, password),
            verify=False,
            timeout=5
        )
        
        if response.status_code == 200:
            return True, "Connection successful"
        else:
            return False, f"Failed to connect: HTTP {response.status_code}"
    
    except Exception as e:
        return False, f"Error connecting to OpenSearch: {str(e)}"

def test_splunk_connection(config):
    """Test Splunk connection"""
    try:
        import splunklib.client as client
        
        host = config.get("host", "")
        port = config.get("port", "8089")
        username = config.get("username", "")
        password = config.get("password", "")
        
        # Remove protocol if present
        if host.startswith("http://") or host.startswith("https://"):
            host = host.split("://")[1]
        
        # Remove port if included in host
        if ":" in host:
            host_parts = host.split(":")
            host = host_parts[0]
            if len(host_parts) > 1 and host_parts[1].isdigit():
                port = host_parts[1]
        
        # Test connection
        service = client.connect(
            host=host,
            port=port,
            username=username,
            password=password,
            scheme="https"
        )
        
        # Check if we can access apps (a basic operation)
        apps = service.apps
        list(apps)  # Force iteration to verify connection
        
        return True, "Connection successful"
    
    except Exception as e:
        return False, f"Error connecting to Splunk: {str(e)}"

def main():
    if len(sys.argv) < 2:
        print(f"Usage: {sys.argv[0]} action [type] [config_json]")
        sys.exit(1)
    
    action = sys.argv[1]
    
    if action == "test" and len(sys.argv) >= 4:
        connector_type = sys.argv[2]
        config = json.loads(sys.argv[3])
        
        success, message = test_connector(connector_type, config)
        print(json.dumps({"success": success, "message": message}))
    
    elif action == "save" and len(sys.argv) >= 4:
        connector_type = sys.argv[2]
        config = json.loads(sys.argv[3])
        
        success = save_connector(connector_type, config)
        print(json.dumps({"success": success}))
    
    elif action == "get" and len(sys.argv) >= 3:
        connector_type = sys.argv[2]
        config = get_connector(connector_type)
        
        print(json.dumps(config))
    
    elif action == "list":
        connectors = list_connectors()
        print(json.dumps(connectors))
    
    elif action == "delete" and len(sys.argv) >= 3:
        connector_type = sys.argv[2]
        success = delete_connector(connector_type)
        
        print(json.dumps({"success": success}))
    
    else:
        print(f"Unknown action or missing parameters: {action}")
        sys.exit(1)

if __name__ == "__main__":
    main() 
