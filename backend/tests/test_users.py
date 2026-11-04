from app.core.security import hash_password
from app.db.models.user import User


def make_user(db_session, email="ada@example.com"):
    user = User(
        first_name="Ada",
        last_name="Lovelace",
        email=email,
        analyst_level="n1",
        avatar="/avatar.png",
        password=hash_password("CorrectHorseBatteryStaple"),
        role="analyst",
    )
    db_session.add(user)
    db_session.commit()
    db_session.refresh(user)
    return user


def login(client, email):
    resp = client.post("/auth/login", json={"email": email, "password": "CorrectHorseBatteryStaple"})
    return {"Authorization": f"Bearer {resp.json()['access_token']}"}


def test_count_requires_auth(client):
    assert client.get("/users/count").status_code == 401


def test_count_reflects_total_users(client, db_session):
    headers = login(client, make_user(db_session, email="ada@example.com").email)
    make_user(db_session, email="bob@example.com")
    make_user(db_session, email="carl@example.com")

    resp = client.get("/users/count", headers=headers)
    assert resp.status_code == 200
    assert resp.json() == {"count": 3}
