import io
import json
import os
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))
from recommendations_core import ConfigError, CatalogueError, fetch_books, recommend_books


BOOKS = [
    {"sku": "9781", "title": "Mistborn", "author": "Brandon Sanderson", "genre": "Fantasy",
     "price_gbp": "11.99", "stock": 8,
     "cover_url": "https://covers.openlibrary.org/b/isbn/9781250318541-M.jpg?default=false"},
    {"sku": "9782", "title": "The Hobbit", "author": "J. R. R. Tolkien", "genre": "Fantasy",
     "price_gbp": "10.50", "stock": 20, "cover_url": "javascript:bad()"},
    {"sku": "9783", "title": "Dune", "author": "Frank Herbert", "genre": "Science Fiction",
     "price_gbp": "12.99", "stock": 25, "cover_url": ""},
    {"sku": "9784", "title": "Sold-out fantasy", "author": "A. Writer", "genre": "Fantasy",
     "price_gbp": "9.99", "stock": 0, "cover_url": ""},
]


class DummyResponse:
    status = 200

    def __init__(self, data):
        self.stream = io.BytesIO(data)

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc, tb):
        return False

    def read(self, size):
        return self.stream.read(size)


class RecommendationTests(unittest.TestCase):
    def test_genre_stock_sort_and_limit(self):
        result = recommend_books(BOOKS, "Fantasy", 2)
        self.assertEqual([b["title"] for b in result], ["The Hobbit", "Mistborn"])
        self.assertEqual(result[0]["cover_url"], "")
        self.assertTrue(result[1]["cover_url"].startswith("https://covers.openlibrary.org/"))

    def test_wrong_genre_and_sold_out_excluded(self):
        result = recommend_books(BOOKS, "Science Fiction")
        self.assertEqual([b["title"] for b in result], ["Dune"])

    def test_invalid_parameters(self):
        with self.assertRaises(ValueError):
            recommend_books(BOOKS, "Horror")
        with self.assertRaises(ValueError):
            recommend_books(BOOKS, "Fantasy", 7)

    def test_duplicate_sku_excluded(self):
        result = recommend_books([BOOKS[0], BOOKS[0]], "Fantasy")
        self.assertEqual(len(result), 1)

    def test_fetch_from_php_api(self):
        payload = json.dumps({"books": BOOKS}).encode("utf-8")
        with patch("recommendations_core.urlopen", return_value=DummyResponse(payload)) as mock:
            result = fetch_books("https://firstwebpage.example/api/books.php", "Fantasy")
        self.assertEqual(len(result), len(BOOKS))
        self.assertIn("genre=Fantasy", mock.call_args.args[0].full_url)

    def test_reject_untrusted_api_url(self):
        with self.assertRaises(ConfigError):
            fetch_books("http://untrusted.example/api/books.php", "Fantasy")

    def test_reject_invalid_json(self):
        with patch("recommendations_core.urlopen", return_value=DummyResponse(b"not json")):
            with self.assertRaises(CatalogueError):
                fetch_books("https://example.org/api/books.php", "Fantasy")

    def test_reject_oversized_response(self):
        with patch("recommendations_core.urlopen", return_value=DummyResponse(b"x" * 262145)):
            with self.assertRaises(CatalogueError):
                fetch_books("https://example.org/api/books.php", "Fantasy")


if __name__ == "__main__":
    unittest.main()
