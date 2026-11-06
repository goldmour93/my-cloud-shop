"""Dependency-free catalogue HTTP client and recommendation logic."""
import json
import re
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlsplit
from urllib.request import Request, urlopen

GENRES = frozenset(("Fantasy", "Science Fiction"))
COVER_RE = re.compile(
    r"\Ahttps://covers\.openlibrary\.org/b/isbn/[0-9]{13}-[SML]\.jpg(?:\?default=false)?\Z"
)
PRICE_RE = re.compile(r"\A[0-9]{1,7}(?:\.[0-9]{1,2})?\Z")


class ConfigError(Exception):
    """Azure application configuration is absent or invalid."""


class CatalogueError(Exception):
    """The PHP catalogue cannot be fetched or parsed safely."""


def fetch_books(api_url: str, genre: str, timeout: int = 5) -> list:
    """Fetch the public PHP JSON endpoint with a short timeout and a size cap.

    BOOKS_API_URL is set by the Function App administrator, never by a user.
    Only HTTPS is allowed, except loopback HTTP used during local development.
    """
    if genre not in GENRES:
        raise ValueError("Unknown genre")
    parsed = urlsplit(api_url.strip())
    local_http = parsed.scheme == "http" and parsed.hostname in ("localhost", "127.0.0.1")
    if not parsed.hostname or not (parsed.scheme == "https" or local_http):
        raise ConfigError("BOOKS_API_URL must be a full HTTPS URL")
    if parsed.username or parsed.password or parsed.fragment:
        raise ConfigError("BOOKS_API_URL must not contain credentials or a fragment")
    if not parsed.path.endswith("/api/books.php"):
        raise ConfigError("BOOKS_API_URL must point to /api/books.php")

    query_separator = "&" if parsed.query else "?"
    target = api_url.strip() + query_separator + urlencode({"genre": genre})
    request = Request(
        target,
        headers={
            "Accept": "application/json",
            "User-Agent": "StarboundBooks-Recommendations/1.0",
        },
        method="GET",
    )
    try:
        with urlopen(request, timeout=timeout) as response:
            if getattr(response, "status", 200) != 200:
                raise CatalogueError("The book API returned a non-success HTTP status")
            raw = response.read(262145)
    except (HTTPError, URLError, TimeoutError, OSError) as exc:
        raise CatalogueError("HTTP request to the book API failed") from exc

    if len(raw) > 262144:
        raise CatalogueError("Book API response exceeds the permitted size")
    try:
        payload = json.loads(raw)
    except (ValueError, UnicodeDecodeError) as exc:
        raise CatalogueError("Book API returned invalid JSON") from exc
    if not isinstance(payload, dict) or not isinstance(payload.get("books"), list):
        raise CatalogueError("Book API returned an unexpected JSON format")
    if len(payload["books"]) > 100:
        raise CatalogueError("Book API returned too many records")
    return payload["books"]


def recommend_books(books: list, genre: str, limit: int = 3) -> list[dict]:
    """Pick up to limit distinct, in-stock books matching the requested genre.

    This is a transparent, deterministic stock-based rule, NOT an ML model
    or a personalised recommendation engine.
    """
    if genre not in GENRES or not isinstance(limit, int) or not 1 <= limit <= 6:
        raise ValueError("Invalid recommendation parameters")

    selection = []
    seen = set()
    for book in books:
        if not isinstance(book, dict) or book.get("genre") != genre:
            continue
        sku = str(book.get("sku", "")).strip()[:64]
        title = str(book.get("title", "")).strip()[:120]
        author = str(book.get("author", "")).strip()[:120]
        if not sku or not title or not author or sku in seen:
            continue
        try:
            stock = int(book.get("stock", 0))
        except (ValueError, TypeError, OverflowError):
            continue
        if not 0 < stock <= 1000000:
            continue
        price = str(book.get("price_gbp", ""))
        if not PRICE_RE.fullmatch(price):
            continue
        raw_cover = str(book.get("cover_url") or "")
        cover = raw_cover if COVER_RE.fullmatch(raw_cover) else ""

        selection.append({
            "sku": sku,
            "title": title,
            "author": author,
            "genre": genre,
            "price_gbp": price,
            "stock": stock,
            "cover_url": cover,
        })
        seen.add(sku)

    selection.sort(key=lambda book: (-book["stock"], book["title"].casefold()))
    return selection[:limit]
