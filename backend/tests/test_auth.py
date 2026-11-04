from app.core.deps import require_admin
from app.core.security import hash_password
from app.db.models.user import User

PHP_HASH = "$2y$10$XJdH1V3nf9TFiOLMgNnj3uCUa.mawleaZNfWxCDd2NChz97f11aqK"


def make_user(db_session, *, email="ada@example.com", password_hash=None, role="analyst"):
    user = User(
        first_name="Ada",
        last_name="Lovelace",
        email=email,
        analyst_level="n1",
        avatar="/avatar.png",
        password=password_hash or hash_password("CorrectHorseBatteryStaple"),
        role=role,
    )
    db_session.add(user)
    db_session.commit()
    return user


def test_login_success_with_real_php_hash(client, db_session):
    make_user(db_session, password_hash=PHP_HASH)

    response = client.post("/auth/login", json={"email": "ada@example.com", "password": "CorrectHorseBatteryStaple"})

    assert response.status_code == 200
    body = response.json()
    assert body["token_type"] == "bearer"
    assert body["user"]["email"] == "ada@example.com"
    assert body["user"]["role"] == "analyst"
    assert body["access_token"]


def test_login_wrong_password(client, db_session):
    make_user(db_session)

    response = client.post("/auth/login", json={"email": "ada@example.com", "password": "wrong"})

    assert response.status_code == 401


def test_login_unknown_email(client, db_session):
    response = client.post("/auth/login", json={"email": "nobody@example.com", "password": "whatever"})

    assert response.status_code == 401


def test_me_requires_token(client, db_session):
    response = client.get("/auth/me")

    assert response.status_code == 401


def test_me_with_valid_token(client, db_session):
    make_user(db_session)
    login = client.post("/auth/login", json={"email": "ada@example.com", "password": "CorrectHorseBatteryStaple"})
    token = login.json()["access_token"]

    response = client.get("/auth/me", headers={"Authorization": f"Bearer {token}"})

    assert response.status_code == 200
    assert response.json()["email"] == "ada@example.com"


def test_me_rejects_garbage_token(client, db_session):
    response = client.get("/auth/me", headers={"Authorization": "Bearer not-a-real-token"})

    assert response.status_code == 401


def test_refresh_issues_new_token(client, db_session):
    make_user(db_session)
    login = client.post("/auth/login", json={"email": "ada@example.com", "password": "CorrectHorseBatteryStaple"})
    token = login.json()["access_token"]

    response = client.post("/auth/refresh", headers={"Authorization": f"Bearer {token}"})

    assert response.status_code == 200
    assert response.json()["access_token"]


def test_require_admin_rejects_analyst(db_session):
    from fastapi import HTTPException

    user = make_user(db_session, role="analyst")
    try:
        require_admin(user)
        assert False, "should have raised"
    except HTTPException as exc:
        assert exc.status_code == 403


def test_require_admin_allows_admin(db_session):
    user = make_user(db_session, email="admin@local.com", role="admin")
    assert require_admin(user) is user
