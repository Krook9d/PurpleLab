from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.core.deps import get_current_user
from app.db.models.content import Content
from app.db.models.user import User
from app.db.session import get_db
from app.schemas.sharing import PostCreate, PostOut

router = APIRouter(prefix="/sharing", tags=["sharing"], dependencies=[Depends(get_current_user)])


def _to_out(post: Content, author: User) -> PostOut:
    return PostOut(
        id=post.id,
        content=post.content,
        title=post.title,
        description=post.description,
        language=post.language,
        author_id=post.author_id,
        author_first_name=author.first_name,
        author_last_name=author.last_name,
        created_at=post.created_at,
    )


@router.get("", response_model=list[PostOut])
def list_posts(db: Session = Depends(get_db)) -> list[PostOut]:
    rows = db.execute(
        select(Content, User).join(User, Content.author_id == User.id).order_by(Content.created_at.desc())
    ).all()
    return [_to_out(post, author) for post, author in rows]


@router.post("", response_model=PostOut)
def create_post(
    body: PostCreate, db: Session = Depends(get_db), user: User = Depends(get_current_user)
) -> PostOut:
    post = Content(
        content=body.content,
        title=body.title,
        description=body.description,
        language=body.language,
        author_id=user.id,
    )
    db.add(post)
    db.commit()
    db.refresh(post)
    return _to_out(post, user)


@router.delete("/{post_id}")
def delete_post(post_id: int, db: Session = Depends(get_db), user: User = Depends(get_current_user)) -> dict:
    post = db.get(Content, post_id)
    if post is None:
        raise HTTPException(status_code=404, detail="post not found")
    # Fixes the IDOR in the old sharing.php: $canDelete = true; hardcoded,
    # no real check despite the conditional display of the button on the
    # UI side. Here the check is done server-side, not only at the
    # presentation layer.
    if post.author_id != user.id and user.role != "admin":
        raise HTTPException(status_code=403, detail="not the author of this post")
    db.delete(post)
    db.commit()
    return {"message": "deleted"}
