"""One-shot account creation/reset — replaces the old two-step README
instructions (hash a password, then hand-write a psql INSERT with the
result pasted in) with a single command:

    docker compose exec backend python -m app.create_admin admin@local.com

Prompts for the password (getpass, not a CLI arg) by default so it never
lands in shell history or `docker compose exec` process listings.
"""

import argparse
import getpass
import sys

from sqlalchemy.orm import Session

from app.core.security import hash_password
from app.db.models.user import User
from app.db.session import SessionLocal


def create_or_update_user(
    db: Session,
    email: str,
    password: str,
    *,
    first_name: str = "Admin",
    last_name: str = "User",
    role: str = "admin",
    analyst_level: str = "n3",
) -> tuple[User, bool]:
    """Returns (user, created) — created=False means an existing account's
    password/role were reset instead."""
    existing = db.query(User).filter(User.email == email).first()
    if existing is not None:
        existing.password = hash_password(password)
        existing.role = role
        db.commit()
        return existing, False

    user = User(
        first_name=first_name,
        last_name=last_name,
        email=email,
        analyst_level=analyst_level,
        avatar="",
        password=hash_password(password),
        role=role,
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    return user, True


def main() -> None:
    parser = argparse.ArgumentParser(description="Create or reset a PurpleLab user account.")
    parser.add_argument("email")
    parser.add_argument("--first-name", default="Admin")
    parser.add_argument("--last-name", default="User")
    parser.add_argument("--role", choices=["admin", "analyst"], default="admin")
    parser.add_argument("--analyst-level", default="n3")
    parser.add_argument(
        "--password",
        help="Plaintext password. Omit to be prompted (recommended: avoids shell history/process list exposure).",
    )
    args = parser.parse_args()

    password = args.password or getpass.getpass("Password: ")
    if not password:
        print("A non-empty password is required.", file=sys.stderr)
        raise SystemExit(1)

    db = SessionLocal()
    try:
        user, created = create_or_update_user(
            db,
            args.email,
            password,
            first_name=args.first_name,
            last_name=args.last_name,
            role=args.role,
            analyst_level=args.analyst_level,
        )
        verb = "Created" if created else "Updated"
        print(f"{verb} user {user.email} (role={user.role}).")
    finally:
        db.close()


if __name__ == "__main__":
    main()
