import sys
import random
from datetime import datetime, timedelta
import socket
import struct
import os 
import json


def random_date(start, end):
    """Generates a random date between start and end."""
    delta = end - start
    int_delta = (delta.days * 24 * 60 * 60) + delta.seconds
    random_second = random.randrange(int_delta)
    return start + timedelta(seconds=random_second)

def generate_ubuntu_log(date):
    processes = ["systemd[1]", "logstash[18718]"]
    messages = [
        "Main process exited, code=exited, status=1/FAILURE",
        "Failed with result 'exit-code'.",
        "Scheduled restart job, restart counter is at 180.",
        "Stopped logstash.",
        "Started logstash.",
        "org.jruby.exceptions.SystemExit: (SystemExit) exit",
        "logstash.service: Consumed 30.102s CPU time."
    ]

    process = random.choice(processes)
    message = random.choice(messages)
    return f"{date} user-virtual-machine {process}: {message}"



def generate_firewall_log(date):
    def random_ip():
        return socket.inet_ntoa(struct.pack('>I', random.randint(1, 0xffffffff)))

    ip_source = random_ip()
    ip_destination = random_ip()
    port = random.randint(1, 65535)
    protocol = random.choice(["TCP", "UDP"])
    action = random.choice(["ACCEPTED", "BLOCKED", "REJECTED"])

    return {
        "date": date,
        "source_ip": ip_source,
        "destination_ip": ip_destination,
        "port": port,
        "protocol": protocol,
        "action": action
    }

def generate_log(log_type, count, time_range, output_dir):
    if count > 5000:
        print("The number of logs must not exceed 5000.")
        return

    end_date = datetime.now()
    start_date = end_date - timedelta(days=time_range)

    log_filename = os.path.join(output_dir, f"{log_type}.json")

    with open(log_filename, 'w') as file:
        for _ in range(count):
            log_date = random_date(start_date, end_date).strftime("%b %d %H:%M:%S")
            if log_type == "ubuntu":
                log_message = generate_ubuntu_log(log_date)
            elif log_type == "firewall":
                log_message = generate_firewall_log(log_date)
            else:
                print(f"Log type unknown : {log_type}")
                return
            file.write(json.dumps(log_message) + "\n")


if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python script.py <log_type> <log_count> <time_range_in_days>")
        sys.exit(1)

    log_type = sys.argv[1].lower()
    log_count = int(sys.argv[2])
    time_range_in_days = int(sys.argv[3])

    output_dir = "/var/www/html/Downloaded/Log_simulation"
    generate_log(log_type, log_count, time_range_in_days, output_dir)
