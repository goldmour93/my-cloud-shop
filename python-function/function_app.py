"""HTTP-triggered Azure Functions for Starbound Books.

This app preserves the original workshop /api/simplehello endpoint and adds
GET /api/recommendations, which fetches read-only catalogue JSON from PHP.
"""
import json
import logging
import os

import azure.functions as func

from recommendations_core import CatalogueError, ConfigError, fetch_books, recommend_books

app = func.FunctionApp()


@app.function_name(name="SimpleHello")
@app.route(route="simplehello", methods=["GET", "POST"], auth_level=func.AuthLevel.ANONYMOUS)
def simple_hello(req: func.HttpRequest) -> func.HttpResponse:
    """The original workshop example, retained so the existing URL still works."""
    logging.info("SimpleHello HTTP request received")
    name = req.params.get("name", "World")
    if req.method == "POST":
        try:
            body = req.get_json()
            if isinstance(body, dict):
                name = body.get("name", name)
        except ValueError:
            pass
    if not isinstance(name, str) or not name.strip():
        name = "World"
    return func.HttpResponse(
        f"Hello, {name.strip()[:100]}! This Python function executed successfully.",
        status_code=200,
        mimetype="text/plain",
    )


def json_response(payload: dict, status: int) -> func.HttpResponse:
    return func.HttpResponse(
        json.dumps(payload, ensure_ascii=False),
        status_code=status,
        mimetype="application/json",
        headers={"Cache-Control": "no-store", "X-Content-Type-Options": "nosniff"},
    )


@app.function_name(name="BookRecommendations")
@app.route(route="recommendations", methods=["GET"], auth_level=func.AuthLevel.ANONYMOUS)
def recommendations(req: func.HttpRequest) -> func.HttpResponse:
    """Recommend available books using data from the public PHP shop API."""
    genre = req.params.get("genre", "Fantasy")
    if genre not in ("Fantasy", "Science Fiction"):
        return json_response({"error": "genre must be Fantasy or Science Fiction"}, 400)

    raw_limit = req.params.get("limit", "3")
    if not raw_limit.isdecimal() or not 1 <= int(raw_limit) <= 6:
        return json_response({"error": "limit must be an integer between 1 and 6"}, 400)
    limit = int(raw_limit)

    api_url = os.environ.get("BOOKS_API_URL", "")
    try:
        books = fetch_books(api_url, genre)
        selection = recommend_books(books, genre, limit)
    except ConfigError:
        logging.error("BOOKS_API_URL is missing or invalid")
        return json_response({"error": "The book API has not been configured in Azure."}, 503)
    except CatalogueError as exc:
        logging.warning("Book catalogue upstream request failed: %s", exc)
        return json_response({"error": "The book catalogue is temporarily unavailable."}, 502)

    logging.info("Returned %d recommendations for %s", len(selection), genre)
    return json_response({
        "genre": genre,
        "count": len(selection),
        "recommendations": selection,
        "source": "Starbound Books PHP catalogue",
        "strategy": "Available books ordered by current simulated stock; not personalised",
    }, 200)
