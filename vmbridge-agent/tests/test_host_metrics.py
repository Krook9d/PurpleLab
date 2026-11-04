from unittest.mock import MagicMock, patch

from app.host_metrics import get_system_metrics


def test_get_system_metrics_shape():
    fake_memory = MagicMock(percent=42.5, total=16 * 1024**3, used=6.8 * 1024**3)
    fake_disk = MagicMock(percent=61.2, total=100 * 1024**3, used=61.2 * 1024**3)

    with patch("app.host_metrics.psutil.virtual_memory", return_value=fake_memory), \
         patch("app.host_metrics.psutil.disk_usage", return_value=fake_disk), \
         patch("app.host_metrics.psutil.cpu_percent", return_value=13.3):
        result = get_system_metrics()

    assert result["memory"]["percent"] == 42.5
    assert result["memory"]["total_gb"] == 16.0
    assert result["disk"]["percent"] == 61.2
    assert result["cpu"]["percent"] == 13.3
