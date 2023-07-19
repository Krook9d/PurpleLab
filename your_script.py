import os
import json
import pandas as pd
import sys

# Getting the tag from user
SearchMalwareFamily = sys.argv[1]

# Directory path
base_dir = "/var/www/html/"
json_dir = os.path.join(base_dir, "Downloaded/json/")
samples_dir = os.path.join(base_dir, "Downloaded/samples/")
csv_dir = os.path.join(base_dir, "csv/")

if not os.path.exists(json_dir):
    os.makedirs(json_dir)

if not os.path.exists(samples_dir):
    os.makedirs(samples_dir)

if not os.path.exists(csv_dir):
    os.makedirs(csv_dir)

# Saving json
os.system('wget --post-data "query=get_taginfo&tag=%s&file_type=exe&limit=10" https://mb-api.abuse.ch/api/v1/ -O %s%s.json' % (SearchMalwareFamily, json_dir, SearchMalwareFamily))

# Converting the json to csv
file = open('%s%s.json' % (json_dir, SearchMalwareFamily))
data = json.load(file)
malwares = []

# Function to Download Malware sample with <sha256 hash>.zip
def DownloadSample(sha256):
    print(f"Downloading {sha256}...")
    file_path = os.path.join(samples_dir, f"{sha256}.zip")
    result = os.system('wget --post-data "query=get_file&sha256_hash=%s" https://mb-api.abuse.ch/api/v1/ -O %s' % (sha256, file_path))
    if result == 0:
        print(f"Downloaded {sha256} at {file_path}")
    else:
        print(f"Failed to download {sha256}.")

for i in data['data']:
    malwares.append(i)
    # Uncomment the following line to Download Sample
    DownloadSample(i['sha256_hash'])

# Saving dataframe in the form of csv
df = pd.DataFrame(malwares)
df.to_csv("%s/Malware-Bazar-%s-scraped-api.csv" % (csv_dir, SearchMalwareFamily))

print("Success.....")

