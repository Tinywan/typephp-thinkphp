# Task Plan: Compile ThinkPHP Project with TPC into Standalone CLI Binary

## Goal
Compile ThinkPHP 8 project (`C:\git\php\typephp-think`) into a standalone CLI binary using TPC (TypePHP Compiler), with a patched RunServer.php that provides a pure-PHP HTTP server for the embed SAPI.

## Current Phase
Phase 8 (Complete - Benchmark Done)

## Phases

### Phase 1: TPC Environment Setup
- [x] Locate TPC binary and understand build requirements
- [x] Create build.bat with vcvarsall.bat + tpc.exe invocation
- **Status:** complete

### Phase 2: Fix Compilation Errors
- [x] Fix `return` statements in config files (moved config/ to ignore)
- [x] Fix stray code in route/app.php (moved route/ to ignore)
- [x] Fix parent class chain issues (moved app/ to ignore, only compile main.php)
- [x] Fix project.yml: `source` -> `sources` key name
- **Status:** complete

### Phase 3: Fix Runtime Issues
- [x] PHPX `Object.attr()` bypasses `__get()` magic method
- [x] Solution: use `$app->make('console')->run()` instead of `$app->console->run()`
- **Status:** complete

### Phase 4: Create Patched RunServer.php
- [x] Detect embed SAPI (`PHP_SAPI === 'embed'`)
- [x] Implement pure-PHP socket HTTP server for embed mode
- [x] Preserve original `passthru()` for CLI mode
- [x] Static file serving with MIME mapping
- [x] Set `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE` superglobals
- [x] Create patches/ directory copy for Composer patch system
- **Status:** complete

### Phase 5: Fix PATH_INFO / Routing
- [x] Set `SCRIPT_NAME` = `/index.php`, `PHP_SELF` = URI path
- [x] Compute correct `PATH_INFO` (strip `/index.php` prefix)
- [x] Force new Request instance per request (`make('request', [], true)`)
- [x] Verify route matching works (`/think`, `/hello/:name`)
- [x] Verify controller dispatch works (`/index.php/index/hello`)
- [x] Verify compat mode works (`?s=index/hello`)
- **Status:** complete

### Phase 6: Fix PDO MySQL Driver
- [x] Add `php_pdo_mysql.dll` and `php_mysqli.dll` to php.ini
- [x] User confirmed php.ini placement works
- **Status:** complete

### Phase 7: Benchmark with oha
- [x] Benchmark CLI mode (`php think run`)
- [x] Benchmark embed mode (`myapp.exe run`)
- [x] APP_DEBUG=true: CLI 29.4 req/s vs Embed 157.9 req/s (5.4x)
- [x] APP_DEBUG=false: CLI 34.1 req/s vs Embed 315.3 req/s (9.2x)
- **Status:** complete

### Phase 8: Document & Finalize
- [x] Create planning files (task_plan.md, findings.md, progress.md)
- **Status:** complete

## Key Questions
1. TPC can only compile `main()` function code — all framework code runs at runtime via embed SAPI
2. PHPX `attr()` doesn't trigger `__get()` — must use explicit `make()` calls
3. Embed SAPI PHP_SAPI='embed' (not 'cli') — affects pathinfo resolution in ThinkPHP

## Decisions Made
| Decision | Rationale |
|----------|-----------|
| Only compile main.php | TPC cannot handle framework class hierarchies, config `return` stmts, or route definitions |
| Pure-PHP socket server | PHP built-in server needs `passthru()` + `PHP_BINARY` which don't work in embed SAPI |
| Patch via Composer system | `patches/` dir + `patch.php` overrides vendor files non-destructively |
| `make('request', [], true)` | Request object caches pathinfo; must create fresh per HTTP request |
| Set PATH_INFO explicitly | embed SAPI PHP_SAPI='embed' skips the `str_contains(PHP_SAPI, 'cli')` fallback |

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| Unsupported statement: Stmt_Return | 1 | Moved config/ from sources to ignore |
| Stray code: Route::get() | 1 | Moved route/ from sources to ignore |
| Non-existent parent class | 1 | Moved app/ from sources to ignore |
| `$app->console` null at runtime | 1 | Changed to `$app->make('console')->run()` |
| build dir missing | 1 | Created build/ directory manually |
| All URLs return welcome page (no routing) | 1 | Set PATH_INFO + SCRIPT_NAME + PHP_SELF correctly |
| Pathinfo computed as empty | 2 | Force new Request via `make('request', [], true)` |
| PDO: could not find driver | 1 | Added php_pdo_mysql.dll to php.ini |

## Files Modified
| File | Purpose |
|------|---------|
| `C:\git\php\typephp-think\main.php` | Entry point with `main()` function |
| `C:\git\php\typephp-think\project.yml` | TPC compilation config |
| `C:\git\php\typephp-think\build.bat` | Build script (vcvarsall + tpc) |
| `C:\git\php\typephp-think\vendor\topthink\framework\src\think\console\command\RunServer.php` | Patched HTTP server |
| `C:\git\php\typephp-think\patches\topthink\framework\src\think\console\command\RunServer.php` | Patch source for Composer |
| `C:\git\source\tpc_v1095_windows_x86_64\php.ini` | Added pdo_mysql + mysqli extensions |

## Compiled Binary
- **Location:** `C:\git\php\typephp-think\build\myapp.exe` (~50KB)
- **Runtime DLLs:** `C:\git\source\tpc_v1095_windows_x86_64\` (php8ts.dll, phpx.dll, ext/*.dll)
- **Commands:** `myapp.exe run`, `myapp.exe list`, `myapp.exe version`, `myapp.exe info`
