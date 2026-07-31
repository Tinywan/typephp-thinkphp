# Findings: TPC + ThinkPHP Compilation

## TPC Architecture
- TPC v0.4.3 compiles PHP to C++ via PHPX, then links with MSVC cl.exe
- Generated C++ uses `php::eval("\n\nmain();", "main.php")` to invoke the PHP main() at runtime
- Runtime uses PHP embed SAPI (php8ts.dll + phpx.dll)
- All execution code MUST be inside a `main()` function — no stray code allowed

## TPC Cannot Compile
- Top-level `return` statements (config files)
- Stray function calls (route definitions)
- Classes with unresolvable parent classes (framework inheritance chains)
- Only self-contained code or code with known dependency chains

## PHPX Object.attr() vs PHP __get()
PHPX's `Object.attr(name, create)` uses `zend_read_property()` which bypasses PHP's `__get()` magic method. This breaks ThinkPHP Container which uses `__get()` for dynamic service resolution.

**Fix:** Always use explicit `$app->make('service')` instead of `$app->service`.

## Embed SAPI Differences
- `PHP_SAPI` = `'embed'` (not `'cli'`)
- `str_contains(PHP_SAPI, 'cli')` returns false — affects ThinkPHP's pathinfo resolution
- PATH_INFO must be set explicitly in `$_SERVER`
- `SCRIPT_NAME` must be `/index.php`, `PHP_SELF` must be the full URI path
- Request object must be created fresh per HTTP request (caches pathinfo)

## Patched RunServer.php Design
- Detects `PHP_SAPI === 'embed'` to choose embed mode
- Uses `stream_socket_server()` for pure-PHP HTTP server
- Parses HTTP request line, headers, body manually
- Sets `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE` superglobals
- Routes requests through `$this->app->http->run($request)`
- Static file serving with MIME type mapping
- Falls back to original `passthru()` for CLI SAPI

## Benchmark Results (oha, 500 requests, concurrency 10)

### APP_DEBUG=true
| Mode | Req/s | Avg Latency | Speedup |
|------|-------|-------------|---------|
| PHP CLI | 29.4 | 337ms | baseline |
| Embed | 157.9 | 63ms | 5.4x |

### APP_DEBUG=false
| Mode | Req/s | Avg Latency | Speedup |
|------|-------|-------------|---------|
| PHP CLI | 34.1 | 290ms | baseline |
| Embed | 315.3 | 31.5ms | 9.2x |
