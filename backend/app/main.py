from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.core.config import get_settings
from app.routers import (
    alienvault,
    auth,
    connectors,
    custom_payloads,
    health,
    malware,
    mitre,
    mitre_ai,
    sharing,
    sigma,
    users,
    vm,
)

app = FastAPI(title="PurpleLab API")

settings = get_settings()
if settings.cors_origins_list:
    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.cors_origins_list,
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )

app.include_router(health.router)
app.include_router(auth.router)
app.include_router(vm.router)
app.include_router(malware.router)
app.include_router(sigma.router)
app.include_router(custom_payloads.router)
app.include_router(sharing.router)
app.include_router(connectors.router)
app.include_router(mitre.router)
app.include_router(alienvault.router)
app.include_router(mitre_ai.router)
app.include_router(users.router)
