def clean_error_message(exc: BaseException) -> str:
    """User-facing text for an exception, without httpx's "For more
    information check: https://developer.mozilla.org/..." suffix that
    HTTPStatusError.__str__ appends to every network error — noise once it
    reaches a toast in the frontend (VM agent calls, connector requests,
    MalwareBazaar lookups, ...). httpx puts that suffix on its own line, so
    keeping only the first line is enough regardless of exception type.
    """
    return str(exc).split("\n", 1)[0]
