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

## Final Build with All Packages (350 files, APP_DEBUG=true)
- Routes: `/think`, `/`, `/hello/world` all HTTP 200; JSON variant returns `"hello,world"` (13 B)
- Benchmark (500 req, c=10): **275.99 req/s**, 100% success rate, avg 35.9ms
- `myapp.exe`: 10,309,632 bytes (10.3MB)
- Server stable under load — no more single-request crash

## Session 7 Build (417 files, 11.9MB)
- Audited the 43-item ignore list; patched ~20 files to compile; only 24 remain ignored (runtime-loaded plain PHP, traits, ext-constant casters, duplicate FQCN)
- All routes 200 with trace panel rendering: `/think` 200 (14464 B), `/` 200 (14543), `/hello/world` 200 (14562), JSON 200 (13)
- Benchmark pending for this build

## TPC Incompatibilities Discovered (Session 5+)
| Construct | Symptom | Fix |
|-----------|---------|-----|
| `trait` keyword | `Unsupported statement` | Ignore file |
| `var_export()` | Compile error | Replace with string interpolation |
| `call_user_func_array()` | Compile error | Rewrite with direct call / foreach |
| `extract()` | Compile error | Comment out / rewrite |
| Top-level `return`/`if` (bootstrap files) | Compile error | Ignore file |
| Interface `public const` | Crash: `Attempt to read property 'trait' on null` | Move constants to class, replace refs with `self::` |
| `throw Static::method()` | Compile error (static return type) | `$e = Static::method(); throw $e;` |
| `throw` inside generator | Not supported | Remove try/catch, restructure |
| `?? throw` pattern | Not supported | Explicit null check + throw |
| `elseif` with `throw` | Not supported | Separate `if` blocks |
| Variable type reassignment (same var, diff types) | Compile error | Rename to unique vars (`$eSep`, `$eMount`, ...) |
| `&...$args` variadic reference | Not supported | `array &$vars = []` |
| `continue 2` in switch | Not supported | Restructure |
| `self::CONST` in constructor default | Not accessible | Move to constructor body |
| `foreach ($item as $obj->prop => $obj->prop2)` | Bad C++ (trailing return type) | Use temp vars |
| `array_walk_recursive` closure with `&$value` | Not supported | Rewrite with foreach |
| Class extending non-compiled class | Compile error | Compile parent or ignore |
| `match` expressions | **SUPPORTED** | — |
| Conditional function definitions (`if (!function_exists)`) | **SUPPORTED** | — |
| `include` in compiled method | Locals NOT visible in included file scope | `$GLOBALS['key'] = $var;` bridge before include (trace tpl fix) |
| Single string literal > ~16KB | MSVC C2026 | Split PHP heredoc/nowdoc into 2 concatenated parts |
| `obj->intProp += <Variant expr>` | C2679 (static-int-ref wrapper) | Split into separate `+=`/`-=` with literal operands |
| Class implementing interface method | Must declare compatible return type | Add explicit `: ?string` etc. |
| Private method/property overridden in subclass | False positive "cannot override/conflicts" | Change to `protected` (getSourceLink, displayOptions) |
| PHP flexible heredoc closing marker indent | Parse error if deeper than min content indent | Indent `EOHTML` to min content indentation |
| `$this->strProp .= $int` | TPC internal crash (Invalid callback) | `$this->strProp .= (string) $int;` |
| Extension constants (AMQP_*, PGSQL_*, Redis::*) | `Constant X cannot be found` | Ignore file (dead code, ext not loaded) |
| `goto` + label | TPC internal crash | Replace with boolean flag + `if (!$early)` wrapper |
| Typed static property assigned different values | Suspected crash | Untype the static property |

## CRITICAL: `class '' is undefined` Root Cause (TPC codegen bug)
**Symptom:** Controller routes (`/hello/world`) → 500 error `class '' is undefined`; server stops accepting connections. Closure routes (`/think`) work fine.

**Root cause:** TPC codegen bug for static method calls inside `if/else` branches. In `Controller::parseDispatch()`:
```php
if (str_contains($controller, '.')) {
    $controller = Str::studly(substr($controller, $pos + 1));  // tmp_var_8 assigned here
} else {
    $controller = Str::studly($controller);  // ← tmp_var_8 NEVER assigned!
}
```
Generated C++ for the else branch: `controller = php_think__helper__str__studly(tmp_var_8, controller);` — `tmp_var_8` (the class object) is only assigned in the if branch (`Z_PTR_P(tmp_var_8.ptr()) = php_get_class(19, ...)`). Uninitialized `tmp_var_8` → `php_get_called_class()` returns `""` (phpx typephp_main.cc:30-37) → `php::getStaticProperty("", 'studlyCache')` → `getClassEntrySafe("")` → throws `class '' is undefined` (phpx.h:1650).

**Fix:** Replace `Str::studly($x)` with inline equivalent:
```php
$controller = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $controller)));
```

**Debugging method that worked:** instrument patches with `file_put_contents('C:\\temp\\tpc_error.log', ..., FILE_APPEND)` probes between every statement (INIT ENTER → PD ENTER → PD POP → PD AFTER LAYER → PD AFTER STUDLY → ...) to bisect the exact failing line, then inspect generated `.cc` code. Note: trace/error handler instrumentation (Error.php EXCEPTION/FATAL, Http.php HTTPRUN CATCH) did NOT fire even though the 500 page rendered — the error bypassed all registered handlers.

## Build/Test Commands (working)
- Apply patches: `php -r "require 'C:\git\php\typephp-think\patch.php';"` (copies patches/ → vendor/)
- Build: `cmd /c C:\temp\tpc_build_v4.bat > C:\temp\tpc_full_log.txt 2>&1` (~15-25 min full build; incremental only recompiles changed files + extension-myapp.cc + link)
- Test: `cmd /c C:\temp\test_all_routes.bat` (starts server, tests all routes, kills server)
- Benchmark: `cmd /c C:\temp\bench_embed.bat` (500 req c=10)
- Batch gotchas: `%` must be `%%` in .bat; use `start /b cmd /c "..."` not `start /b exe.exe`; PowerShell PATH changes silently break subsequent commands in same call

## Debugging Helper: AST Stub Bisection
For TPC internal crashes / specific-method failures in a large file:
`C:\temp\bisect_clidumper2.php` (uses TPC's own php-parser at `C:\git\source\tpc_v1095_windows_x86_64\vendor\autoload.php`):
`php bisect_clidumper2.php <file> <keepStart> <keepEnd>` — stubs (empties) every ClassMethod outside [keepStart,keepEnd], prints file back. Backup the original file first, restore between runs.

## Ignored Files Audit Result (Session 7) — What Remains Ignored & Why
| File(s) | Reason |
|---------|--------|
| app/{provider,middleware,service,event}.php, config/, route/, extend/, view/ | Top-level `return [...]` / top-level calls; runtime-loaded via require (works interpreted) |
| 4× think-*/helper.php + framework/helper.php | Top-level `if (!function_exists)` guards + dynamic `new $x()`; loaded via composer autoload_files |
| framework lang/zh-cn.php, think-trace config.php | Top-level return arrays |
| view/driver/Php.php | `extract()` ×2 + `eval()` (template engine core) |
| think-validate Validate.php | 9× call_user_func_array + dynamic `new $rule[0]()` |
| flysystem CalculateChecksumFromStream/ProxyArrayAccessToProperties | traits (logic inlined into Filesystem/FileAttributes patches) |
| mbstring bootstrap*.php + Resources | Top-level return/require + `&...$vars` variadic ref |
| var-dumper Test/VarDumperTestTrait.php | trait + PHPUnit deps (test-only) |
| var-dumper Caster/{AmqpCaster,PgSqlCaster,RedisCaster}.php | Extension constants unresolvable (AMQP_*/PGSQL_*/Redis::*) — dead code |
| var-dumper Cloner/VarCloner.php | 4× `continue 2` + pervasive $stub/$a type reassignment |
| var-dumper Command/ | Extends Symfony Console Command (not compiled) |
| var-dumper Resources/functions/dump.php | Top-level if guards; composer autoload file |
| framework route/dispatch/Dispatch.php | Byte-identical duplicate FQCN `think\route\Dispatch` |
