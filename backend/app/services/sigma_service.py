import re
import shutil
import subprocess
import tempfile
from pathlib import Path

from app.core.config import get_settings

ALLOWED_PLUGINS = {
    "splunk",
    "lucene",
    "kusto",
    "carbon_black",
    "sentinel_one",
    "sentinel_one_pq",
    "opensearch_lucene",
    "log_scale",
    "sumologic_cse",
    "loki",
    "logpoint",
    "secops",
    "quickwit",
    "sqlite",
    "net_witness",
    "signals",
    "golang_expr",
}
SIGMA_REPO_URL = "https://github.com/SigmaHQ/sigma.git"

# kusto (Sentinel/Defender KQL) needs a pipeline to resolve a rule's log
# source into a real table + field names (e.g. DeviceProcessEvents) —
# without one it still converts, but as a generic boolean expression with
# no table and Sigma's own field names, which isn't runnable KQL as-is.
# microsoft_xdr only maps a subset of Sigma categories to a table though,
# so a rule outside that subset (e.g. proxy/web logs) fails with "Unable
# to determine table name" — convert_rule() falls back to
# --without-pipeline for those rather than erroring out entirely.
PLUGIN_PIPELINES = {"kusto": "microsoft_xdr"}


class SigmaServiceError(Exception):
    pass


def _rules_root() -> Path:
    return Path(get_settings().sigma_rules_dir).resolve()


def _resolve_rule_path(relative_path: str) -> Path:
    """Resolves a rule path relative to the Sigma rules folder, rejecting
    any attempt to escape that folder (path traversal) — fixes the
    unvalidated `os.path.join` of the old Web/app.py:convert_sigma."""
    root = _rules_root()
    candidate = (root / relative_path).resolve()
    if candidate != root and root not in candidate.parents:
        raise SigmaServiceError("invalid rule path")
    return candidate


def convert_rule(relative_path: str, plugin: str) -> str:
    if plugin not in ALLOWED_PLUGINS:
        raise SigmaServiceError(f"unsupported plugin: {plugin}")
    rule_path = _resolve_rule_path(relative_path)
    if not rule_path.is_file():
        raise SigmaServiceError("rule not found")

    # Argument list, never shell=True, never sudo (sigma-cli touches
    # neither VirtualBox nor anything privileged — fixes the double
    # sudo+shell=True of the old Downloaded/Sigma/rules/sigma.py).
    pipeline = PLUGIN_PIPELINES.get(plugin)
    if pipeline:
        command = ["sigma", "convert", "-t", plugin, "-p", pipeline, str(rule_path)]
    else:
        command = ["sigma", "convert", "-t", plugin, "--without-pipeline", str(rule_path)]
    result = subprocess.run(command, capture_output=True, text=True, timeout=30)
    if result.returncode != 0 and pipeline:
        command = ["sigma", "convert", "-t", plugin, "--without-pipeline", str(rule_path)]
        result = subprocess.run(command, capture_output=True, text=True, timeout=30)
    if result.returncode != 0:
        raise SigmaServiceError(result.stderr.strip() or "sigma convert failed")
    return result.stdout.strip()


# ATT&CK technique tags in Sigma rules: attack.t1059 / attack.t1059.001.
_TECHNIQUE_TAG = re.compile(r"\battack\.(t\d{4}(?:\.\d{3})?)\b", re.IGNORECASE)
# Per rules-directory cache: scanning ~3k files costs a few hundred ms and the
# rules only change through update_rules(), which clears it.
_technique_counts_cache: dict[str, dict[str, int]] = {}


def technique_rule_counts() -> dict[str, int]:
    """Number of Sigma rules tagged with each ATT&CK technique ID
    (upper-case, e.g. "T1059.001"), for the MITRE matrix Sigma coverage."""
    root = _rules_root()
    key = str(root)
    if key in _technique_counts_cache:
        return _technique_counts_cache[key]
    counts: dict[str, int] = {}
    if not root.is_dir():
        return counts
    for path in root.rglob("*.yml"):
        try:
            content = path.read_text(errors="ignore")
        except OSError:
            continue
        for technique_id in {match.upper() for match in _TECHNIQUE_TAG.findall(content)}:
            counts[technique_id] = counts.get(technique_id, 0) + 1
    # An empty result is not cached: the rules may still be cloning (init
    # container) on a fresh install.
    if counts:
        _technique_counts_cache[key] = counts
    return counts


def update_rules() -> int:
    """Reproduces scripts/sigma_database_update.py while fixing the
    absolute path bug (wrote to /Downloaded/Sigma/rules instead of the
    actually configured path) and by actually propagating errors (the
    original swallowed them with an except Exception: print(), never a
    raise — Web/app.py/update_sigma_rules therefore always received a
    success, even on a real failure). Returns the number of .yml files
    retrieved."""
    dest = _rules_root()
    with tempfile.TemporaryDirectory() as clone_dir:
        clone_result = subprocess.run(
            [
                "git", "clone", "--depth", "1", "--filter=blob:none",
                "--sparse", SIGMA_REPO_URL, clone_dir,
            ],
            capture_output=True, text=True, timeout=120,
        )
        if clone_result.returncode != 0:
            raise SigmaServiceError(f"git clone failed: {clone_result.stderr.strip()}")

        sparse_result = subprocess.run(
            ["git", "-C", clone_dir, "sparse-checkout", "set", "rules"],
            capture_output=True, text=True, timeout=30,
        )
        if sparse_result.returncode != 0:
            raise SigmaServiceError(f"sparse-checkout failed: {sparse_result.stderr.strip()}")

        cloned_rules = Path(clone_dir) / "rules"
        if not cloned_rules.is_dir():
            raise SigmaServiceError("rules directory not found in clone")

        dest.mkdir(parents=True, exist_ok=True)
        # dest can be a Docker mount point (bind-mount): it cannot be
        # removed itself (rmtree would fail with "Device or resource
        # busy"), only emptied of its content, then the new rules moved
        # into it one by one.
        for child in dest.iterdir():
            if child.is_dir():
                shutil.rmtree(child)
            else:
                child.unlink()
        for item in cloned_rules.iterdir():
            shutil.move(str(item), str(dest / item.name))

    _technique_counts_cache.clear()
    return sum(1 for _ in dest.rglob("*.yml"))


def browse_folder(folder: str = "") -> dict:
    target = _resolve_rule_path(folder) if folder else _rules_root()
    if not target.is_dir():
        raise SigmaServiceError("folder not found")
    subfolders = sorted(p.name for p in target.iterdir() if p.is_dir())
    files = sorted(p.name for p in target.iterdir() if p.is_file() and p.suffix == ".yml")
    # Recursive count under the browsed folder (not just its immediate
    # files) so the frontend can show a rule count that updates as you
    # navigate deeper, the same way stats() does for the whole tree.
    rule_count = sum(1 for _ in target.rglob("*.yml"))
    return {"folders": subfolders, "files": files, "rule_count": rule_count}


def search_rules(query: str) -> list[str]:
    root = _rules_root()
    if not root.is_dir():
        return []
    matches = []
    for path in root.rglob("*.yml"):
        try:
            content = path.read_text(errors="ignore")
        except OSError:
            continue
        if query.lower() in content.lower():
            matches.append(str(path.relative_to(root)))
    return matches


def stats() -> dict:
    """Replaces getSigmaData.php: recursive total count + count per
    top-level category, for the index.php dashboard."""
    root = _rules_root()
    if not root.is_dir():
        return {"total": 0, "by_category": []}
    total = sum(1 for _ in root.rglob("*.yml"))
    by_category = [
        {"folder": child.name, "count": sum(1 for _ in child.rglob("*.yml"))}
        for child in sorted(root.iterdir())
        if child.is_dir()
    ]
    return {"total": total, "by_category": by_category}


def get_rule_content(relative_path: str) -> str:
    rule_path = _resolve_rule_path(relative_path)
    if not rule_path.is_file():
        raise SigmaServiceError("rule not found")
    return rule_path.read_text(errors="ignore")
