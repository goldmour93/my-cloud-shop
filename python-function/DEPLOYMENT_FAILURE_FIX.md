# Deployment failure fix — Azure Python version mismatch

The original GitHub Actions deployment logged:
- Successful GitHub OIDC login to Azure.
- Flex Consumption plan and Azure remote build using **Python 3.15**.
- `azure-functions==1.26.0` failed to install because that SDK only supports Python 3.10–3.12.

The `python-function/requirements.txt` file now selects:
- `azure-functions==1.26.0` for Python versions before 3.13.
- `azure-functions==2.4.0` for Python 3.13 and later (supports Python 3.15).

This avoids changing your existing Azure resources or GitHub identity. GitHub
Actions also installs the package and imports the Function App using Python 3.15
**before attempting the Azure deployment**, so future version issues are caught
earlier. Local Python 3.11 remains supported.

**After merging this fix:** GitHub → Actions →
"Deploy Starbound Python Function (Azure Portal)" → Run workflow → main.
The old failed run cannot be repaired by pressing "Re-run jobs", since it would
run against the old commit. Start a new run after merging.

After a successful deploy:
1. In Azure Function App `python-function-cs`, open **Functions** and confirm
   `BookRecommendations` and `SimpleHello` exist.
2. Verify Function App setting
   `BOOKS_API_URL=https://<actual-php-default-domain>/api/books.php`.
3. Visit `https://<actual-function-default-domain>/api/recommendations?genre=Fantasy`.
4. Verify PHP App Service setting
   `RECOMMENDATIONS_FUNCTION_URL=https://<actual-function-default-domain>/api/recommendations`
   and refresh homepage.

**Note:** Python 3.15 was still listed as Preview in Microsoft's Azure Functions
language support chart on 13 November 2026. For longer-term coursework stability,
consider moving this Flex Consumption Function App to a GA runtime such as
Python 3.12 or 3.14 *only if needed and after confirming the Azure Portal supports
the change*. Don't change Python versions or resources just to repair the original
packaging problem.

References:
- https://pypi.org/project/azure-functions/1.26.0/
- https://pypi.org/project/azure-functions/2.4.0/
- https://learn.microsoft.com/en-us/azure/azure-functions/functions-versions
