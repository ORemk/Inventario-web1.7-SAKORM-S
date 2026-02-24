$urls = @(
    'http://127.0.0.1:8000/index.php',
    'http://127.0.0.1:8000/login.html',
    'http://127.0.0.1:8000/admin/ai_review.php',
    'http://127.0.0.1:8000/api/login.php',
    'http://127.0.0.1:8000/productos.php',
    'http://127.0.0.1:8000/ventas.php',
    'http://127.0.0.1:8000/VERIFICAR_SETUP.php'
)
foreach ($u in $urls) {
    try {
        $r = Invoke-WebRequest -Uri $u -Method Head -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop
        Write-Host "$u -> $($r.StatusCode)"
    } catch {
        Write-Host "$u -> ERROR: $($_.Exception.Message)"
    }
}