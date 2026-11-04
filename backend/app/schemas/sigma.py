from pydantic import BaseModel


class BrowseOut(BaseModel):
    folders: list[str]
    files: list[str]
    rule_count: int


class SearchOut(BaseModel):
    matches: list[str]


class RuleContentOut(BaseModel):
    path: str
    content: str


class ConvertRequest(BaseModel):
    rule_path: str
    plugin: str


class ConvertOut(BaseModel):
    output: str


class UpdateOut(BaseModel):
    rule_count: int


class CategoryCount(BaseModel):
    folder: str
    count: int


class StatsOut(BaseModel):
    total: int
    by_category: list[CategoryCount]
