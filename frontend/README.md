# PurpleLab — frontend

React (Vite + TypeScript) + Tailwind + shadcn/ui. Consumes the FastAPI API
(`../backend`) via `src/lib/apiClient.ts` (JWT in `Authorization: Bearer`,
no cookie).

## Dev

```bash
npm install
npm run dev        # http://localhost:5173, API defaults to http://127.0.0.1:8000 (see .env)
```

## Build / container

```bash
npm run build       # static dist/
docker compose up -d --build frontend   # nginx serves the build + proxies /api to backend:8000
```

In the container, nginx (`nginx.conf`) serves the build and proxies
anything starting with `/api/` to `backend:8000` on the Docker network
(prefix stripped before relaying) — the browser only ever calls a single
origin (`VITE_API_BASE_URL=/api` at build time). This dedicated prefix
avoids any collision between a React route and a backend router with the
same name (e.g. `/sigma` the page vs `/sigma` the router — without it, a
refresh on a React page landed on the backend's JSON response instead of
the SPA). The published port is bound to the server's LAN IP
(`192.168.142.132:8080`), not `0.0.0.0`, to stay on the private network
(see the comment in `docker-compose.yml`).

## Pages

Every page from the legacy PHP app has been migrated to React (no `.php`
file left in the repo):

| React route | Legacy page |
|---|---|
| `/` | `index.php` (dashboard) |
| *(sidebar link, opens `/hunting/` in a new tab — not a React route)* | Hunting: OpenSearch Dashboards, rebranded and proxied by nginx |
| `/health` | `health.php` |
| `/custom-payloads` | `custom_payloads.php` |
| `/malware` | `malware.php` |
| `/sharing` | `sharing.php` |
| `/sigma` | `sigma.php` |
| `/mitre` | `mittre.php` |
| `/rule-lifecycle` | `rule_lifecycle.php` |
| `/admin` | `admin.php` (Token Generation and LDAP sections not carried over) |
