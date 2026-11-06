# Starbound Books — Python Azure Function

This folder is a **separate Azure Functions application**. It is not a replacement for the PHP bookstore.

- The existing workshop's **SimpleHello** is kept.
- The new **BookRecommendations** function requests public book JSON from the PHP App Service, picks 3 in-stock books of the selected genre, and responds with JSON. This is rule-based, not personalised AI.

## HTTP request flow

```text
Browser → PHP homepage JS → PHP recommendations proxy
                       → Python Function GET /api/recommendations?genre=Fantasy
                       → PHP /api/books.php?genre=Fantasy
                       → Azure SQL dbo.products
                       ← JSON recommendations
```

The browser only makes same-origin requests to PHP. The PHP proxy calls the Python Function over HTTPS. CORS configuration on the Function App is not necessary.

## Included files

```text
python-function/
    function_app.py            SimpleHello and BookRecommendations Azure routes
    recommendations_core.py    pure-Python HTTP client and book selection
    requirements.txt           azure-functions dependency
    host.json                  Azure Functions runtime configuration
    local.settings.example.json  template for local development
    .funcignore                excludes local credentials/tests from publishing
    .gitignore                 prevents local credentials from being committed
    tests/test_recommendations.py
```

## 1. Find your correct PHP URL

In Azure Portal, open **App Services → firstwebpage-cs → Overview → Default domain**. Note the full host; modern Azure App Services may use a unique suffix.

Confirm this URL opens and displays JSON:

```
https://<PHP-APP-DEFAULT-DOMAIN>/api/books.php?genre=Fantasy
```

It must return JSON with a `books` array. We do **not** need SQL credentials in the Python app.

## 2. Configure Azure Function App

Open **Function Apps → python-function-cs → Settings → Environment variables**. Add:

```text
Name: BOOKS_API_URL
Value: https://<PHP-APP-DEFAULT-DOMAIN>/api/books.php
```

Do not include `?genre` in the setting and do not substitute the Python Function App's URL here.

**Save** the setting and restart if Azure requests it.

## 3. Deploy Python code using Azure Functions Core Tools (recommended)

On your Windows PC, download the `python-function` folder and open it in VS Code. It must contain `host.json` and `function_app.py` directly in the folder. Do not accidentally create `python-function/python-function/...` without navigating to the inner one.

Install the supported **Azure Functions Core Tools v4** from [Microsoft's instructions](https://learn.microsoft.com/en-us/azure/azure-functions/functions-run-local) if needed. **Do not** use `pip install azure-functions-core-tools`; it is not a Python pip package.

Open the VS Code PowerShell terminal **inside the folder containing host.json**:

```powershell
func --version
python --version
python -m pip install -r requirements.txt
```

Your earlier workstation used Python 3.11; this code supports the Function App's configured supported Python version. The Python interpreter used in VS Code must contain the `azure-functions` package.

To test locally, **copy** `local.settings.example.json` to `local.settings.json` and replace the `BOOKS_API_URL` placeholder with your actual PHP App Service URL:

```powershell
Copy-Item .\local.settings.example.json .\local.settings.json
notepad .\local.settings.json
func start
```

Then test in your browser:

```
http://localhost:7071/api/simplehello?name=Callum
http://localhost:7071/api/recommendations?genre=Fantasy
http://localhost:7071/api/recommendations?genre=Science%20Fiction&limit=2
```

Stop the local host with Ctrl+C. The `local.settings.json` file must stay local; don't commit it or include it in the deployment.

When the local test works, publish to the **existing** Function App:

```powershell
func azure functionapp publish python-function-cs
```

This uses Azure Functions Core Tools with a Python remote build and can prompt you to sign in to Azure. If the publish command fails due to an Azure account or plan restriction, check the Function App's runtime stack, hosting plan and deployment logs. [Microsoft recommends remote builds for Windows-to-Linux Python deployment](https://learn.microsoft.com/en-us/azure/azure-functions/python-build-options).

**Important:** Publishing replaces the Function App's deployed project, so the new `function_app.py` explicitly includes **both** `SimpleHello` and `BookRecommendations`. Don't publish only `recommendations_core.py`.

## Alternative: ZIP deployment

If you use the workshop's Azure Portal ZIP method, ZIP **the contents of this folder**, *not* the enclosing folder, keeping `host.json` at the ZIP root. Core Tools or VS Code publishing is preferable because it arranges remote dependency installation. A bare ZIP upload may succeed without installing `azure-functions` unless remote build is correctly configured for your hosting plan.

PowerShell from inside `python-function/`:

```powershell
Compress-Archive -Path .\function_app.py,.\recommendations_core.py,.\requirements.txt,.\host.json -DestinationPath .\python-deployment.zip -Force
tar -tf .\python-deployment.zip
```

Check ZIP contents show all four files directly at root. On Linux Consumption, use a remote build/publish workflow supported by your hosting plan rather than assuming the manual ZIP upload installs Python dependencies.

## 4. Verify the cloud function

Open **Function Apps → python-function-cs → Overview → Default domain** and then **Functions**; both `SimpleHello` and `BookRecommendations` should appear.

Visit:

```
https://<FUNCTION-APP-DEFAULT-DOMAIN>/api/recommendations?genre=Fantasy
```

Expected JSON format:

```json
{
  "genre": "Fantasy",
  "count": 3,
  "recommendations": [
    {
      "sku": "9781250318541",
      "title": "Mistborn: The Final Empire",
      "author": "Brandon Sanderson",
      "genre": "Fantasy",
      "price_gbp": "11.99",
      "stock": 25,
      "cover_url": "https://covers.openlibrary.org/b/isbn/9781250318541-M.jpg?default=false"
    }
  ],
  "source": "Starbound Books PHP catalogue",
  "strategy": "Available books ordered by current simulated stock; not personalised"
}
```

This is **illustrative**; the actual titles/order/stock depend on your database state.

If no matching active stock exists, the function returns an empty `recommendations` list.

## 5. Link the PHP bookstore

In **App Services → firstwebpage-cs → Settings → Environment variables**, add:

```text
Name: RECOMMENDATIONS_FUNCTION_URL
Value: https://<FUNCTION-APP-DEFAULT-DOMAIN>/api/recommendations
```

Save/restart. This setting is the **Python Function address**, not the PHP book API address. Use the exact **Default domain** with `.azurewebsites.net`; the PHP proxy only permits Azure's standard HTTPS hostname and this endpoint path. Don't add `?genre` to the setting.

Merge the companion GitHub PR to deploy the PHP changes. Open the bookstore homepage and try the **Recommended for you** genre selector. The JS fetches `api/recommendations-proxy.php?genre=Fantasy` from the *same* PHP origin. The PHP proxy calls the Python Function, which calls the book API and ultimately Azure SQL.

## Troubleshooting

| Symptom | Likely next check |
| --- | --- |
| `func` command not recognised | Install Azure Functions Core Tools v4 using Microsoft installer/winget |
| `No module named azure` | Run `python -m pip install -r requirements.txt` in the interpreter used by your project |
| Cloud endpoint is DNS NXDOMAIN | Copy the Function App's exact Default domain from Azure Portal |
| Cloud endpoint returns 404 | Ensure both functions are listed and the project was published at the correct root |
| Cloud recommendations return 503 | Confirm `BOOKS_API_URL` is configured in the Function App |
| Cloud recommendations return 502 | Test the PHP /api/books.php URL, Azure network access, and Function App logs |
| Homepage recommendations show 'not configured' | Add `RECOMMENDATIONS_FUNCTION_URL` on PHP App Service, not Function App |
| Homepage suggestions temporarily unavailable | Inspect `/api/recommendations-proxy.php?genre=Fantasy` and the direct Function endpoint |
| Old books/no covers | The Python endpoint reflects what's currently in Azure SQL; verify book API output |

## Test offline and avoid secrets

From the repository root:

```powershell
python -m unittest discover -s python-function/tests -v
```

Unit tests mock HTTP requests and never contact Azure or real SQL. Only the book API's public product fields flow through the recommendations endpoint. Keep database passwords in your existing PHP Azure App Service settings; no SQL password or Function key is required in this anonymous, read-only classroom demo. For a production application, add authentication and abuse prevention before offering such endpoints publicly.
