$ErrorActionPreference = "Stop"

docker compose up -d --build
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

try {
    docker compose exec -T app php tests/smoke_test.php
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}
finally {
    docker compose down
}
