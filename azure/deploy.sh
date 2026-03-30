#!/bin/bash
#
# Prime CRM Laravel — Azure App Service Deployment
# ==================================================
# Run from VS Code terminal: bash laravel-backend/azure/deploy.sh
#
# Deploys:
#   1. Azure Resource Group
#   2. Azure SQL Server + Database
#   3. Azure App Service (PHP 8.3) for Laravel API
#   4. Azure Static Web App for React frontend
#   5. Custom domain crmprime.online
#

set -e

# ══════════════════════════════════════════════════════════════
# CONFIGURATION
# ══════════════════════════════════════════════════════════════
RESOURCE_GROUP="primecrm-rg"
LOCATION="eastus"

SQL_SERVER="primecrmdbserver"
SQL_DB="primecrmdb"
SQL_ADMIN="primeadmin"
SQL_PASS='$Credit123'

APP_NAME="primecrm-app"
APP_PLAN="primecrm-plan"

STATIC_APP="primecrm-web"
CUSTOM_DOMAIN="crmprime.online"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PROJECT_DIR="$(cd "$LARAVEL_DIR/.." && pwd)"

echo ""
echo "  ╔═══════════════════════════════════════════════╗"
echo "  ║   Prime CRM — Laravel Azure Deployment        ║"
echo "  ╚═══════════════════════════════════════════════╝"
echo ""

# ── [1] Prerequisites ─────────────────────────────────────────
echo "── [1/10] Checking prerequisites ─────────────────────"

MISSING=0
for cmd in az php composer; do
    if ! command -v $cmd &>/dev/null; then
        echo "  ✗ $cmd not found"
        MISSING=1
    fi
done

if [ "$MISSING" -eq 1 ]; then
    echo ""
    echo "  Install missing tools:"
    echo "    Azure CLI:  winget install -e --id Microsoft.AzureCLI"
    echo "    PHP 8.3:    https://windows.php.net/download (thread safe)"
    echo "    Composer:   https://getcomposer.org/download/"
    exit 1
fi

if ! az account show &>/dev/null 2>&1; then
    echo "  Logging in to Azure..."
    az login
fi
echo "  ✓ Azure: $(az account show --query name -o tsv)"
echo "  ✓ PHP: $(php -v | head -1)"
echo ""

# ── [2] Resource Group ────────────────────────────────────────
echo "── [2/10] Resource Group ─────────────────────────────"
az group create --name "$RESOURCE_GROUP" --location "$LOCATION" --output none 2>/dev/null
echo "  ✓ $RESOURCE_GROUP ready"
echo ""

# ── [3] SQL Server ────────────────────────────────────────────
echo "── [3/10] Azure SQL Server ───────────────────────────"
az sql server show --name "$SQL_SERVER" --resource-group "$RESOURCE_GROUP" &>/dev/null 2>&1 || \
    az sql server create --name "$SQL_SERVER" --resource-group "$RESOURCE_GROUP" \
        --location "$LOCATION" --admin-user "$SQL_ADMIN" --admin-password "$SQL_PASS" --output none

az sql server firewall-rule create --resource-group "$RESOURCE_GROUP" --server "$SQL_SERVER" \
    --name "AllowAzureServices" --start-ip-address 0.0.0.0 --end-ip-address 0.0.0.0 --output none 2>/dev/null || true

MY_IP=$(curl -s https://api.ipify.org 2>/dev/null)
[ -n "$MY_IP" ] && az sql server firewall-rule create --resource-group "$RESOURCE_GROUP" --server "$SQL_SERVER" \
    --name "ClientIP" --start-ip-address "$MY_IP" --end-ip-address "$MY_IP" --output none 2>/dev/null || true

echo "  ✓ SQL Server ready"
echo ""

# ── [4] SQL Database ──────────────────────────────────────────
echo "── [4/10] SQL Database ───────────────────────────────"
az sql db show --name "$SQL_DB" --server "$SQL_SERVER" --resource-group "$RESOURCE_GROUP" &>/dev/null 2>&1 || \
    az sql db create --resource-group "$RESOURCE_GROUP" --server "$SQL_SERVER" \
        --name "$SQL_DB" --service-objective Basic --output none

echo "  ✓ $SQL_DB ready"
echo ""

# ── [5] Install Laravel dependencies ─────────────────────────
echo "── [5/10] Laravel dependencies ───────────────────────"
cd "$LARAVEL_DIR"
composer install --no-dev --optimize-autoloader --no-interaction
echo "  ✓ Composer packages installed"
echo ""

# ── [6] App Service Plan + Web App ───────────────────────────
echo "── [6/10] Azure App Service (PHP 8.3) ────────────────"

az appservice plan show --name "$APP_PLAN" --resource-group "$RESOURCE_GROUP" &>/dev/null 2>&1 || \
    az appservice plan create --name "$APP_PLAN" --resource-group "$RESOURCE_GROUP" \
        --location "$LOCATION" --sku B1 --is-linux --output none

az webapp show --name "$APP_NAME" --resource-group "$RESOURCE_GROUP" &>/dev/null 2>&1 || \
    az webapp create --name "$APP_NAME" --resource-group "$RESOURCE_GROUP" \
        --plan "$APP_PLAN" --runtime "PHP|8.3" --output none

# Set document root to public/
az webapp config set --name "$APP_NAME" --resource-group "$RESOURCE_GROUP" \
    --linux-fx-version "PHP|8.3" --output none 2>/dev/null || true

# Startup command
az webapp config set --name "$APP_NAME" --resource-group "$RESOURCE_GROUP" \
    --startup-file "php artisan serve --host=0.0.0.0 --port=8080" --output none 2>/dev/null || true

echo "  ✓ App Service created: $APP_NAME"
echo ""

# ── [7] Configure environment ─────────────────────────────────
echo "── [7/10] Environment variables ──────────────────────"

APP_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")

az webapp config appsettings set --name "$APP_NAME" --resource-group "$RESOURCE_GROUP" --settings \
    "APP_NAME=Prime CRM" \
    "APP_ENV=production" \
    "APP_KEY=$APP_KEY" \
    "APP_DEBUG=false" \
    "APP_URL=https://$CUSTOM_DOMAIN" \
    "DB_CONNECTION=sqlsrv" \
    "DB_HOST=$SQL_SERVER.database.windows.net" \
    "DB_PORT=1433" \
    "DB_DATABASE=$SQL_DB" \
    "DB_USERNAME=$SQL_ADMIN" \
    "DB_PASSWORD=$SQL_PASS" \
    "SESSION_DRIVER=database" \
    "CACHE_STORE=file" \
    "LOG_CHANNEL=stderr" \
    --output none

# CORS
az webapp cors add --name "$APP_NAME" --resource-group "$RESOURCE_GROUP" \
    --allowed-origins "https://$CUSTOM_DOMAIN" "https://www.$CUSTOM_DOMAIN" "http://localhost:5173" \
    2>/dev/null || true

echo "  ✓ Environment configured"
echo ""

# ── [8] Deploy Laravel to App Service ─────────────────────────
echo "── [8/10] Deploying Laravel backend ──────────────────"

cd "$LARAVEL_DIR"

# Create deployment zip (exclude dev files)
zip -r /tmp/laravel-deploy.zip . \
    -x ".git/*" "node_modules/*" "tests/*" ".env" "storage/logs/*" \
    "storage/framework/cache/data/*" "storage/framework/sessions/*" "storage/framework/views/*"

az webapp deploy --name "$APP_NAME" --resource-group "$RESOURCE_GROUP" \
    --src-path /tmp/laravel-deploy.zip --type zip --output none

rm -f /tmp/laravel-deploy.zip

echo "  ✓ Laravel deployed to https://$APP_NAME.azurewebsites.net"
echo ""

# ── [9] Run migrations and seed ───────────────────────────────
echo "── [9/10] Database migrations ────────────────────────"

# Run migrations via SSH
az webapp ssh --name "$APP_NAME" --resource-group "$RESOURCE_GROUP" \
    --command "cd /home/site/wwwroot && php artisan migrate --force && php artisan db:seed --force" \
    2>/dev/null || {
    echo "  ⚠ Could not run migrations via SSH. Run manually:"
    echo "    az webapp ssh --name $APP_NAME --resource-group $RESOURCE_GROUP"
    echo "    cd /home/site/wwwroot"
    echo "    php artisan migrate --force"
    echo "    php artisan db:seed --force"
}
echo ""

# ── [10] Frontend deployment ──────────────────────────────────
echo "── [10/10] React frontend ────────────────────────────"

cd "$PROJECT_DIR"
npm install --silent
npm run build

az staticwebapp show --name "$STATIC_APP" --resource-group "$RESOURCE_GROUP" &>/dev/null 2>&1 || \
    az staticwebapp create --name "$STATIC_APP" --resource-group "$RESOURCE_GROUP" \
        --location "eastus2" --sku Free --output none

DEPLOY_TOKEN=$(az staticwebapp secrets list --name "$STATIC_APP" --resource-group "$RESOURCE_GROUP" \
    --query "properties.apiKey" -o tsv 2>/dev/null)

if [ -n "$DEPLOY_TOKEN" ]; then
    npx -y @azure/static-web-apps-cli deploy "$PROJECT_DIR/dist" \
        --deployment-token "$DEPLOY_TOKEN" --env production 2>/dev/null
    echo "  ✓ Frontend deployed"
else
    echo "  ⚠ Deploy frontend manually via VS Code Azure sidebar"
fi

# ── Results ───────────────────────────────────────────────────
SWA_HOSTNAME=$(az staticwebapp show --name "$STATIC_APP" --resource-group "$RESOURCE_GROUP" \
    --query "defaultHostname" -o tsv 2>/dev/null || echo "$STATIC_APP.azurestaticapps.net")

VERIFICATION_ID=$(az staticwebapp show --name "$STATIC_APP" --resource-group "$RESOURCE_GROUP" \
    --query "customDomainVerificationId" -o tsv 2>/dev/null || echo "(check Azure Portal)")

echo ""
echo "  ╔═══════════════════════════════════════════════════════╗"
echo "  ║              DEPLOYMENT COMPLETE                       ║"
echo "  ╚═══════════════════════════════════════════════════════╝"
echo ""
echo "  ┌───────────────────────────────────────────────────────┐"
echo "  │ RESOURCES                                             │"
echo "  ├───────────────────────────────────────────────────────┤"
echo "  │ SQL Server:  $SQL_SERVER.database.windows.net         │"
echo "  │ Database:    $SQL_DB                                  │"
echo "  │ Laravel API: https://$APP_NAME.azurewebsites.net      │"
echo "  │ Frontend:    https://$SWA_HOSTNAME                    │"
echo "  └───────────────────────────────────────────────────────┘"
echo ""
echo "  ┌───────────────────────────────────────────────────────┐"
echo "  │ GODADDY DNS RECORDS                                   │"
echo "  │                                                       │"
echo "  │ Go to: https://dcc.godaddy.com/domains                │"
echo "  │ Click: crmprime.online → DNS                          │"
echo "  │                                                       │"
echo "  │ Type    Name        Value                             │"
echo "  │ ─────   ─────────   ───────────────────────────────── │"
echo "  │ CNAME   www         $SWA_HOSTNAME                     │"
echo "  │ TXT     asuid       $VERIFICATION_ID                  │"
echo "  │ TXT     asuid.www   $VERIFICATION_ID                  │"
echo "  │                                                       │"
echo "  │ Forwarding: crmprime.online → www.crmprime.online     │"
echo "  └───────────────────────────────────────────────────────┘"
echo ""
echo "  Then register custom domain:"
echo "    az staticwebapp hostname set --name $STATIC_APP \\"
echo "      --resource-group $RESOURCE_GROUP --hostname www.$CUSTOM_DOMAIN"
echo ""
echo "  ┌───────────────────────────────────────────────────────┐"
echo "  │ TEST                                                  │"
echo "  │                                                       │"
echo "  │ Frontend: https://www.$CUSTOM_DOMAIN                  │"
echo "  │ API:      https://$APP_NAME.azurewebsites.net/api/health│"
echo "  │ Payroll:  .../api/payroll?action=get_settings         │"
echo "  │                                                       │"
echo "  │ Login:    dchen / 12345678                            │"
echo "  │ Admin:    primeadmin / prime2026                      │"
echo "  └───────────────────────────────────────────────────────┘"
echo ""
echo "  Cost: ~\$18/month (B1 App Service \$13 + SQL Basic \$5)"
echo "  Free for 30 days with Azure trial (\$200 credit)"
echo ""
