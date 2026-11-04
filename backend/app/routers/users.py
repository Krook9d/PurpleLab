from fastapi import APIRouter, Depends
from sqlalchemy import func
from sqlalchemy.orm import Session

from app.core.deps import get_current_user
from app.db.models.user import User
from app.db.session import get_db
from app.schemas.users import UserCount

router = APIRouter(prefix="/users", tags=["users"], dependencies=[Depends(get_current_user)])


@router.get("/count", response_model=UserCount)
def count(db: Session = Depends(get_db)) -> UserCount:
    # Replaces getActiveUsersCount.php: this is a total account count
    # (SELECT COUNT(*) FROM users), not real session tracking despite
    # the "Active Users" label in the legacy UI — behavior kept as-is,
    # no new activity tracking to invent here.
    total = db.query(func.count(User.id)).scalar()
    return UserCount(count=total or 0)
