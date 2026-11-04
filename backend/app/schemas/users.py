from pydantic import BaseModel


class UserCount(BaseModel):
    count: int
