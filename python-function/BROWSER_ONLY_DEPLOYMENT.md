# Browser-only Python Azure Function deployment

Use this guide on a university computer where software installation is blocked.
The project is already on GitHub. You need only the GitHub website and Azure Portal.
Do not try to run the func command on your university PC.

## Important: two cloud apps

- PHP App Service: firstwebpage-cs, deployed by the existing PHP GitHub Action.
- Python Function App: python-function-cs, deployed by the separate action
  named **Deploy Starbound Python Function**.
- Python source folder: python-function. The deploy workflow sets this correctly.

## 1. Find the hosting plan

In Azure Portal (https://portal.azure.com/), open the existing Function App
**python-function-cs**. Look in Overview or Properties for Hosting plan.

- **Flex Consumption**: in GitHub repository Settings > Secrets and variables >
  Actions > Variables, add repository variable
  AZURE_FUNCTIONS_PLAN with value flexconsumption.
- **Consumption**, **Elastic Premium** or **Dedicated/App Service Plan**:
  no plan variable is required.

The action uses remote Python dependency builds appropriate for each plan.

## 2. Connect GitHub in Azure Portal (requires Azure role permissions)

From Function App **python-function-cs**, choose Deployment > Deployment Center.

- Select Continuous Deployment (CI/CD) and Source: GitHub.
- Authorize your GitHub account if prompted.
- Organization/account: goldmour93
- Repository: my-cloud-shop
- Branch: main
- Provider: GitHub Actions.
- Workflow: **Use available workflow**, choose
  .github/workflows/deploy-python-function.yml
- Authentication: **User-assigned identity / OIDC** (recommended). Choose the
  new identity suggested by Azure, if available.
- Preview the workflow: app-name must be python-function-cs and package must
  be python-function. Then Save.

Azure's Deployment Center normally configures the managed identity,
federated credential, and AZURE_CLIENT_ID / AZURE_TENANT_ID /
AZURE_SUBSCRIPTION_ID repository secrets for you.

If your university subscription prevents creating identities or assigning
Website Contributor role, ask your university Azure administrator for
permission to configure GitHub OIDC deployment. Do not expose your Azure
credentials or a publishing profile in chat or commit it to GitHub.

Microsoft's Azure instructions:
https://learn.microsoft.com/en-us/azure/azure-functions/functions-how-to-github-actions?pivots=method-portal&tabs=linux%2Cpython

## 3. Add the public PHP API URL to Python's Azure settings

In Azure Portal, open firstwebpage-cs > Overview > Default domain.
Copy its actual hostname, including any suffix. Verify by opening:

https://YOUR-PHP-DEFAULT-DOMAIN/api/books.php?genre=Fantasy

A successful response contains a JSON 'books' array.

Now go to Function App python-function-cs > Settings > Environment variables,
create a new app setting:

Name: BOOKS_API_URL
Value: https://YOUR-PHP-DEFAULT-DOMAIN/api/books.php

Do not add ?genre to the environment setting. Save and restart if prompted.

## 4. Run GitHub Actions manually (no downloads)

After merging the workflow PR, open the repository on GitHub, select
Actions > Deploy Starbound Python Function > Run workflow.
Select branch main and confirm Run workflow.

Open the new run. Confirm 'Sign in to Azure using GitHub OIDC' and the relevant
'Publish to ...' step succeeded. Tests run before deployment.

GitHub builds the app on cloud runners. Nothing is installed on your PC.

## 5. Confirm the function works

Azure Portal > python-function-cs > Functions should show:
- SimpleHello
- BookRecommendations

Get the real default domain from Function App Overview.
Open in your browser:

https://YOUR-FUNCTION-DEFAULT-DOMAIN/api/recommendations?genre=Fantasy

Expect a JSON object with a recommendations array. Also try:

https://YOUR-FUNCTION-DEFAULT-DOMAIN/api/recommendations?genre=Science%20Fiction

404: Check that the Function App shows BookRecommendations and that you're
using the correct Default domain.
503: Check BOOKS_API_URL is saved.
502: Check the PHP /api/books.php URL and Function App logs.

## 6. Connect the PHP website

Azure Portal > App Service firstwebpage-cs > Settings > Environment variables:
Name: RECOMMENDATIONS_FUNCTION_URL
Value: https://YOUR-FUNCTION-DEFAULT-DOMAIN/api/recommendations

Save/restart the PHP App Service, then refresh the bookstore homepage.
The Recommended for you section should load Fantasy and Science Fiction
suggestions. Your cart/checkout still function if recommendations are offline.

No Azure SQL migration is required. For the assessment, capture the successful
GitHub Action, the Function App's invocation logs, the Python endpoint JSON
and the homepage recommendations.

## Troubleshooting identity setup

If the workflow fails during OIDC login with missing AZURE_CLIENT_ID,
AZURE_TENANT_ID, or AZURE_SUBSCRIPTION_ID, Deployment Center did not finish
linking the workflow, or the connection is tied to a different repository.
Revisit step 2; do not put fake IDs into the workflow file.
