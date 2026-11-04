from unittest.mock import MagicMock, patch

import pytest

from app.services import sigma_service
from app.services.sigma_service import SigmaServiceError

TEST_RULE = """title: Test Rule
status: test
logsource:
    category: process_creation
    product: windows
detection:
    selection:
        Image|endswith: '\\\\whoami.exe'
    condition: selection
level: medium
"""


@pytest.fixture
def rules_dir(tmp_path, monkeypatch):
    rules_root = tmp_path / "sigma_rules"
    (rules_root / "windows").mkdir(parents=True)
    (rules_root / "windows" / "test_rule.yml").write_text(TEST_RULE)

    settings_mock = MagicMock()
    settings_mock.sigma_rules_dir = str(rules_root)
    with patch("app.services.sigma_service.get_settings", return_value=settings_mock):
        yield rules_root


def test_convert_rule_rejects_unknown_plugin(rules_dir):
    with pytest.raises(SigmaServiceError):
        sigma_service.convert_rule("windows/test_rule.yml", "not-a-real-plugin")


def test_convert_rule_rejects_missing_file(rules_dir):
    with pytest.raises(SigmaServiceError):
        sigma_service.convert_rule("windows/does_not_exist.yml", "splunk")


def test_resolve_rule_path_rejects_traversal(rules_dir):
    with pytest.raises(SigmaServiceError):
        sigma_service.get_rule_content("../../../../etc/passwd")


def test_convert_rule_real_splunk_backend(rules_dir):
    # sigma-cli is actually installed (backend dependency): real
    # integration test rather than mocked, no VirtualBox/network involved.
    output = sigma_service.convert_rule("windows/test_rule.yml", "splunk")
    assert "whoami" in output


def test_convert_rule_real_lucene_backend(rules_dir):
    output = sigma_service.convert_rule("windows/test_rule.yml", "lucene")
    assert "whoami" in output


def test_convert_rule_real_kusto_backend(rules_dir):
    # process_creation is mapped to a table (DeviceProcessEvents) by the
    # microsoft_xdr pipeline, so this exercises the "pipeline succeeds"
    # path rather than the --without-pipeline fallback.
    output = sigma_service.convert_rule("windows/test_rule.yml", "kusto")
    assert "DeviceProcessEvents" in output
    assert "whoami" in output


def test_convert_rule_kusto_falls_back_without_pipeline(rules_dir):
    # A category the microsoft_xdr pipeline has no table mapping for
    # (e.g. proxy) errors with "Unable to determine table name" — must
    # fall back to --without-pipeline instead of raising.
    proxy_rule = rules_dir / "windows" / "proxy_rule.yml"
    proxy_rule.write_text(
        "title: Proxy Test Rule\nstatus: test\nlogsource:\n"
        "    category: proxy\ndetection:\n"
        "    selection:\n        c-useragent: 'curl*'\n"
        "    condition: selection\nlevel: medium\n"
    )
    output = sigma_service.convert_rule("windows/proxy_rule.yml", "kusto")
    assert "curl" in output


def test_browse_folder_root(rules_dir):
    result = sigma_service.browse_folder("")
    assert result == {"folders": ["windows"], "files": [], "rule_count": 1}


def test_browse_folder_subfolder(rules_dir):
    result = sigma_service.browse_folder("windows")
    assert result == {"folders": [], "files": ["test_rule.yml"], "rule_count": 1}


def test_stats_counts_total_and_by_category(rules_dir):
    (rules_dir / "linux").mkdir()
    (rules_dir / "linux" / "another.yml").write_text(TEST_RULE)
    result = sigma_service.stats()
    assert result["total"] == 2
    assert {"folder": "linux", "count": 1} in result["by_category"]
    assert {"folder": "windows", "count": 1} in result["by_category"]


def test_stats_empty_when_rules_dir_missing():
    settings_mock = MagicMock()
    settings_mock.sigma_rules_dir = "/tmp/does-not-exist-at-all"
    with patch("app.services.sigma_service.get_settings", return_value=settings_mock):
        assert sigma_service.stats() == {"total": 0, "by_category": []}


def test_browse_folder_missing_raises(rules_dir):
    with pytest.raises(SigmaServiceError):
        sigma_service.browse_folder("does-not-exist")


def test_search_rules_finds_match(rules_dir):
    matches = sigma_service.search_rules("whoami")
    assert matches == ["windows/test_rule.yml"]


def test_search_rules_no_match(rules_dir):
    assert sigma_service.search_rules("nonexistent-keyword-xyz") == []


def test_get_rule_content(rules_dir):
    content = sigma_service.get_rule_content("windows/test_rule.yml")
    assert "Test Rule" in content


def test_update_rules_propagates_clone_failure():
    settings_mock = MagicMock()
    settings_mock.sigma_rules_dir = "/tmp/does-not-matter"
    with patch("app.services.sigma_service.get_settings", return_value=settings_mock):
        with patch("app.services.sigma_service.subprocess.run") as mock_run:
            mock_run.return_value = MagicMock(returncode=1, stderr="network unreachable")
            with pytest.raises(SigmaServiceError, match="git clone failed"):
                sigma_service.update_rules()


def test_update_rules_builds_expected_git_commands(tmp_path):
    dest = tmp_path / "sigma_rules"
    settings_mock = MagicMock()
    settings_mock.sigma_rules_dir = str(dest)

    def fake_run(command, **kwargs):
        if command[:2] == ["git", "clone"]:
            clone_dir = command[-1]
            (pytest_tmp_rules := __import__("pathlib").Path(clone_dir) / "rules").mkdir(parents=True)
            (pytest_tmp_rules / "dummy.yml").write_text("title: dummy\n")
            return MagicMock(returncode=0, stderr="")
        return MagicMock(returncode=0, stderr="")

    with patch("app.services.sigma_service.get_settings", return_value=settings_mock):
        with patch("app.services.sigma_service.subprocess.run", side_effect=fake_run):
            count = sigma_service.update_rules()

    assert count == 1
    assert (dest / "dummy.yml").exists()


TAGGED_RULE = """title: Tagged
tags:
    - attack.execution
    - attack.t1059.001
    - attack.T1059.001
    - attack.t1218
logsource:
    product: windows
detection:
    selection:
        Image|endswith: '\\\\powershell.exe'
    condition: selection
"""


def test_technique_rule_counts_counts_each_rule_once_per_technique(rules_dir):
    (rules_dir / "windows" / "tagged_a.yml").write_text(TAGGED_RULE)
    (rules_dir / "windows" / "tagged_b.yml").write_text(TAGGED_RULE.replace("attack.t1218", ""))
    counts = sigma_service.technique_rule_counts()
    assert counts == {"T1059.001": 2, "T1218": 1}


def test_technique_rule_counts_is_cached_until_cleared(rules_dir):
    (rules_dir / "windows" / "tagged.yml").write_text(TAGGED_RULE)
    first = sigma_service.technique_rule_counts()
    (rules_dir / "windows" / "tagged_2.yml").write_text(TAGGED_RULE)
    assert sigma_service.technique_rule_counts() == first
    sigma_service._technique_counts_cache.clear()
    assert sigma_service.technique_rule_counts()["T1059.001"] == 2
