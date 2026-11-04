import psutil


def get_system_metrics() -> dict:
    """Replaces the free -m / disk_total_space() / top-vmstat parsing done
    on the PHP side (Web/health.php) with psutil ("psutil instead of parsing
    top/free/vmstat"). Runs here,
    on the host (the agent isn't containerized), to reflect the server's
    actual resources rather than a container's view."""
    memory = psutil.virtual_memory()
    disk = psutil.disk_usage("/")
    cpu_percent = psutil.cpu_percent(interval=0.5)

    return {
        "memory": {
            "percent": memory.percent,
            "total_gb": round(memory.total / (1024**3), 2),
            "used_gb": round(memory.used / (1024**3), 2),
        },
        "disk": {
            "percent": disk.percent,
            "total_gb": round(disk.total / (1024**3), 2),
            "used_gb": round(disk.used / (1024**3), 2),
        },
        "cpu": {
            "percent": cpu_percent,
        },
    }
