from app.core.security import hash_password
from app.db.models.user import User


def make_user(db_session, email="ada@example.com", first_name="Ada", last_name="Lovelace", role="analyst"):
    user = User(
        first_name=first_name,
        last_name=last_name,
        email=email,
        analyst_level="n1",
        avatar="/avatar.png",
        password=hash_password("CorrectHorseBatteryStaple"),
        role=role,
    )
    db_session.add(user)
    db_session.commit()
    db_session.refresh(user)
    return user


def login(client, email):
    resp = client.post("/auth/login", json={"email": email, "password": "CorrectHorseBatteryStaple"})
    return {"Authorization": f"Bearer {resp.json()['access_token']}"}


def test_routes_require_auth(client):
    assert client.get("/sharing").status_code == 401
    assert client.post("/sharing", json={"content": "x"}).status_code == 401
    assert client.delete("/sharing/1").status_code == 401


def test_create_post_persists_title_description_language(client, db_session):
    # fixes the data-loss bug of the old sharing.php.
    user = make_user(db_session)
    headers = login(client, user.email)

    resp = client.post(
        "/sharing",
        json={
            "content": "print('hello')",
            "title": "My snippet",
            "description": "A quick test",
            "language": "python",
        },
        headers=headers,
    )
    assert resp.status_code == 200
    body = resp.json()
    assert body["title"] == "My snippet"
    assert body["description"] == "A quick test"
    assert body["language"] == "python"
    assert body["created_at"] is not None


def test_list_shows_posts_from_all_users(client, db_session):
    ada = make_user(db_session, email="ada@example.com")
    bob = make_user(db_session, email="bob@example.com", first_name="Bob", last_name="Smith")
    ada_headers = login(client, ada.email)
    bob_headers = login(client, bob.email)

    client.post("/sharing", json={"content": "ada post"}, headers=ada_headers)
    client.post("/sharing", json={"content": "bob post"}, headers=bob_headers)

    resp = client.get("/sharing", headers=ada_headers)
    contents = {p["content"] for p in resp.json()}
    assert contents == {"ada post", "bob post"}


def test_delete_own_post_succeeds(client, db_session):
    user = make_user(db_session)
    headers = login(client, user.email)
    created = client.post("/sharing", json={"content": "mine"}, headers=headers)
    post_id = created.json()["id"]

    resp = client.delete(f"/sharing/{post_id}", headers=headers)
    assert resp.status_code == 200
    assert client.get("/sharing", headers=headers).json() == []


def test_delete_other_users_post_rejected_403(client, db_session):
    # fixes the IDOR in the old sharing.php ($canDelete = true; hardcoded).
    ada = make_user(db_session, email="ada@example.com")
    bob = make_user(db_session, email="bob@example.com", first_name="Bob", last_name="Smith")
    ada_headers = login(client, ada.email)
    bob_headers = login(client, bob.email)

    created = client.post("/sharing", json={"content": "ada's post"}, headers=ada_headers)
    post_id = created.json()["id"]

    resp = client.delete(f"/sharing/{post_id}", headers=bob_headers)
    assert resp.status_code == 403

    still_there = client.get("/sharing", headers=ada_headers)
    assert len(still_there.json()) == 1


def test_admin_can_delete_any_post(client, db_session):
    ada = make_user(db_session, email="ada@example.com")
    admin = make_user(db_session, email="admin@example.com", first_name="Admin", last_name="User", role="admin")
    ada_headers = login(client, ada.email)
    admin_headers = login(client, admin.email)

    created = client.post("/sharing", json={"content": "ada's post"}, headers=ada_headers)
    post_id = created.json()["id"]

    resp = client.delete(f"/sharing/{post_id}", headers=admin_headers)
    assert resp.status_code == 200
    assert client.get("/sharing", headers=ada_headers).json() == []


def test_delete_nonexistent_post_404(client, db_session):
    user = make_user(db_session)
    headers = login(client, user.email)
    resp = client.delete("/sharing/9999", headers=headers)
    assert resp.status_code == 404
