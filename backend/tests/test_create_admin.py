from app.create_admin import create_or_update_user
from app.core.security import verify_password
from app.db.models.user import User


def test_create_admin_creates_new_user(db_session):
    user, created = create_or_update_user(db_session, "admin@local.com", "s3cret-pw")

    assert created is True
    assert user.email == "admin@local.com"
    assert user.role == "admin"
    assert verify_password("s3cret-pw", user.password)


def test_create_admin_resets_existing_user_password_and_role(db_session):
    create_or_update_user(db_session, "ada@example.com", "old-password", role="analyst")

    user, created = create_or_update_user(db_session, "ada@example.com", "new-password", role="admin")

    assert created is False
    assert user.role == "admin"
    assert verify_password("new-password", user.password)
    assert db_session.query(User).filter(User.email == "ada@example.com").count() == 1
