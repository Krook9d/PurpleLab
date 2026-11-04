FROM python:3.12-slim

WORKDIR /app

# git : requis par app/services/sigma_service.py (update_rules, git clone
# --sparse de SigmaHQ/sigma — jamais shell=True, toujours en liste
# d'arguments).
RUN apt-get update && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
# All sigma-cli conversion backends that actually work — sigma-cli
# installs with no backend by default. Every plugin here was verified
# (in an isolated container, one at a time) to install cleanly AND
# produce real conversion output. Deliberately excluded despite the
# official plugin registry marking them "Compatible: yes":
#   - cortexxdr: installing it corrupts the sigma-cli process itself
#     (ImportError on every subsequent `sigma` invocation, even for
#     unrelated backends) — a broken package pin, not a version check
#     the registry actually verified against.
#   - clickhouse: `pip install pysigma-backend-clickhouse` itself fails.
#   - panther: only converts through a vendor-specific pipeline
#     (`sigma list pipelines panther`) that isn't installed/wired up here.
#   - qradar/ibm-qradar-aql: registry itself marks these incompatible
#     with the installed pySigma version (confirmed broken already, see
#     the qradar removal from the frontend plugin list).
RUN sigma plugin install splunk \
    && sigma plugin install elasticsearch \
    && sigma plugin install kusto \
    && sigma plugin install carbonblack \
    && sigma plugin install sentinelone \
    && sigma plugin install sentinelone-pq \
    && sigma plugin install opensearch \
    && sigma plugin install crowdstrike \
    && sigma plugin install sumologic \
    && sigma plugin install loki \
    && sigma plugin install logpoint \
    && sigma plugin install secops \
    && sigma plugin install quickwit \
    && sigma plugin install sqlite \
    && sigma plugin install netwitness \
    && sigma plugin install signals \
    && sigma plugin install golangexpr

COPY app ./app
COPY alembic.ini .

EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
