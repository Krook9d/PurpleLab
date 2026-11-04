from app.db import models  # noqa: F401  (registers all the models on Base.metadata)
from app.db.session import Base


def test_all_tables_create_on_sqlite(db_session):
    engine = db_session.get_bind()
    table_names = set(Base.metadata.tables.keys())
    assert table_names == {
        "users",
        "contents",
        "custom_payloads",
        "atomic_tests",
        "mitre_techniques",
        "rule_lifecycle_payloads",
        "rule_lifecycle_rules",
        "rule_payload_map",
        "integration_configs",
        "alienvault_dashboard_cache",
        "execution_history",
    }
    # Base.metadata.create_all has already run in the db_session fixture;
    # we just verify that each table actually exists on the engine side.
    from sqlalchemy import inspect

    inspector = inspect(engine)
    for name in table_names:
        assert inspector.has_table(name)


def test_user_content_foreign_key(db_session):
    from app.db.models import Content, User

    user = User(
        first_name="Ada",
        last_name="Lovelace",
        email="ada@example.com",
        analyst_level="n1",
        avatar="/avatar.png",
        password="hashed",
    )
    db_session.add(user)
    db_session.flush()

    content = Content(author_id=user.id, content="hello")
    db_session.add(content)
    db_session.commit()

    assert content.id is not None
    assert content.author_id == user.id


def test_rule_payload_map_foreign_key_to_payload(db_session):
    from app.db.models import RuleLifecyclePayload, RulePayloadMap

    payload = RuleLifecyclePayload(name="test-payload", code="whoami")
    db_session.add(payload)
    db_session.flush()

    mapping = RulePayloadMap(rule_id="splunk-alert-1", payload_id=payload.id)
    db_session.add(mapping)
    db_session.commit()

    assert mapping.id is not None
