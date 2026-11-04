from unittest.mock import MagicMock, patch

import pandas as pd

from app.db.models.mitre_technique import MitreTechnique
from app.services import mitre_service


def _fake_techniques_df():
    return pd.DataFrame(
        [
            {
                "ID": "T1566",
                "STIX ID": "attack-pattern--abc",
                "name": "Phishing",
                "description": "desc",
                "url": "https://attack.mitre.org/techniques/T1566",
                "created": "02 March 2020",
                "last modified": "12 May 2026",
                "domain": "enterprise-attack",
                "version": "2.7",
                "tactics": "Initial Access",
                "platforms": "Windows",
                "is sub-technique": False,
                "sub-technique of": float("nan"),
                "contributors": float("nan"),
                "supports remote": float("nan"),
                "impact type": float("nan"),
                "relationship citations": float("nan"),
            }
        ]
    )


def test_refresh_creates_new_technique(db_session):
    with patch("app.services.mitre_service.attack_to_excel.get_stix_data", return_value=MagicMock()), patch(
        "app.services.mitre_service.stixToDf.techniquesToDf",
        return_value={"techniques": _fake_techniques_df()},
    ):
        result = mitre_service.refresh(db_session)

    assert result == {"created": 1, "updated": 0, "total": 1}
    row = db_session.get(MitreTechnique, "T1566")
    assert row.name == "Phishing"
    assert row.sub_technique_of is None


def test_refresh_updates_without_wiping_fields_it_cannot_provide(db_session):
    existing = MitreTechnique(id="T1566", name="Old Name", detection="manually curated detection guidance")
    db_session.add(existing)
    db_session.commit()

    with patch("app.services.mitre_service.attack_to_excel.get_stix_data", return_value=MagicMock()), patch(
        "app.services.mitre_service.stixToDf.techniquesToDf",
        return_value={"techniques": _fake_techniques_df()},
    ):
        result = mitre_service.refresh(db_session)

    assert result == {"created": 0, "updated": 1, "total": 1}
    row = db_session.get(MitreTechnique, "T1566")
    assert row.name == "Phishing"  # field provided by the refresh: updated
    assert row.detection == "manually curated detection guidance"  # field not provided: preserved


def test_atomic_tests_by_tactic_groups_and_counts(db_session):
    from app.db.models.atomic_test import AtomicTest

    db_session.add_all(
        [
            AtomicTest(technique_id="T1566", tactic="Initial Access"),
            AtomicTest(technique_id="T1598", tactic="Initial Access"),
            AtomicTest(technique_id="T1059", tactic="Execution"),
        ]
    )
    db_session.commit()

    result = mitre_service.atomic_tests_by_tactic(db_session)
    assert {"tactic": "Initial Access", "count": 2} in result
    assert {"tactic": "Execution", "count": 1} in result


def test_stats_counts_mitre_techniques_not_atomic_test_coverage(db_session):
    from app.db.models.atomic_test import AtomicTest

    db_session.add_all(
        [
            MitreTechnique(id="T1566", name="Phishing"),
            MitreTechnique(id="T1059", name="Command and Scripting Interpreter"),
            MitreTechnique(id="T1082", name="System Information Discovery"),
        ]
    )
    # Only one technique has an atomic test — technique_count must still
    # reflect the 3 known MITRE techniques, not this narrower coverage.
    db_session.add(AtomicTest(technique_id="T1082", tactic="Discovery"))
    db_session.commit()

    assert mitre_service.stats(db_session) == {"technique_count": 3}


def test_search_matches_id_or_name(db_session):
    db_session.add_all(
        [
            MitreTechnique(id="T1566", name="Phishing"),
            MitreTechnique(id="T1059", name="Command and Scripting Interpreter"),
        ]
    )
    db_session.commit()

    assert [t.id for t in mitre_service.search_techniques(db_session, "phish")] == ["T1566"]
    assert [t.id for t in mitre_service.search_techniques(db_session, "T1059")] == ["T1059"]
