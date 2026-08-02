# Task Plan: Compile ThinkPHP Project with TPC into Standalone CLI Binary

## Goal
Compile ThinkPHP 8 project (`C:\git\php\typephp-think`) into a standalone CLI binary using TPC (TypePHP Compiler), with a patched RunServer.php that provides a pure-PHP HTTP server for the embed SAPI.

## Current Phase
Phase 10 (Complete - Audit Ignored Files & Patch to Compile)

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

### Phase 9: Add New Compile Directories + Fix Controller Route Crash
- [x] Add flysystem, flysystem-local, polyfill-mbstring, var-dumper to project.yml sources
- [x] Patch TPC incompatibilities (interface constants, throw patterns, traits, switch/if, variadic refs)
- [x] Ignore unfixable files (bootstrap, Caster/, Command/, Dumper/, VarCloner, traits)
- [x] Root-cause `class '' is undefined` 500 error (TPC if/else static-call codegen bug in Str::studly)
- [x] Fix: inline str_replace/ucwords studly equivalent in Controller.php patch
- [x] Remove all debug probes from patches
- [x] Final build: 350 files, EXIT_CODE=0
- [x] All routes 200 (/think, /, /hello/world, JSON)
- [x] Benchmark: 275.99 req/s, 100% success (500 req, c=10)
- **Status:** complete

### Phase 10: Audit Ignored Files & Patch to Compile
- [x] Audit 43-item ignore list; classify clean/patchable/unpatcheable
- [x] Patch trace Html/Console, flysystem-local LocalFilesystemAdapter, FinfoMimeTypeDetector, 8 Caster files, CliDumper/HtmlDumper, think-dumper Dumper/ServerDumper
- [x] Bisect TPC internal crash (dumpEllipsis) + switch fall-through (dumpKey) via AST stubbing
- [x] Add 3 Caster ignores (AmqpCaster/PgSqlCaster/RedisCaster — unresolvable ext constants)
- [x] Build: 417 files, EXIT_CODE=0, 11.9MB
- [x] Fix trace panel regression (include-scope: $GLOBALS bridge for page_trace.tpl)
- [x] All routes 200 with trace panel rendering
- [x] Final probe-free rebuild (11,897,344 bytes, 16:02:09; probe verified removed)
- [x] Benchmark 417-file build: 274.16 req/s, 100% success (no regression vs 350-file build)
- **Status:** complete

## Key Questions
1. TPC can only compile `main()` function code — all framework code runs at runtime via embed SAPI
2. PHPX `attr()` doesn't trigger `__get()` — must use explicit `make()` calls
3. Embed SAPI PHP_SAPI='embed' (not 'cli') — affects pathinfo resolution in ThinkPHP
4. TPC codegen bug: static method calls in if/else branches may use uninitialized class tmp vars → `class '' is undefined` — avoid `Str::staticMethod()` in if/else, inline instead

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
| Crash: `Attempt to read property 'trait' on null` | 3 | Interface `public const` unsupported → moved constants to classes |
| Build timeout 15 min (aggregate+link never reached) | 2 | 25-min timeout + manual link (C:\temp\link_only.bat) |
| `class '' is undefined` (500 on /hello/world, server crash) | 4 | TPC if/else static-call bug: `Str::studly()` in else branch used uninitialized tmp class var → inlined studly |
| `Unsupported statement: Stmt_If` (top-level if in dump.php) | 1 | Ignored file |
| `.obj` size > 4GB / LNK errors | 1 | `/bigobj` cxx-flag + correct .rsp generation filtering build\php\typephp-think + phpx-misc |
| `Cannot re-assign typed object $dumper` (HtmlDumper↔CliDumper) | 2 | Rewrote think-dumper createHandler with per-branch closures |
| `Declaration of dump() must be compatible with interface` | 1 | Added `: ?string` to ServerDumper::dump() |
| C2679 `static_int_ref += php::Variant` (cut += expr) | 3 | Split into separate `+=`/`-=` with literal operands (ReflectionCaster/SymfonyCaster/XmlReaderCaster) |
| C2026 string literal too big (27KB Sfdump JS nowdoc) | 2 | Split into 2 concatenated nowdocs (closing marker indent = min content indent) |
| TPC internal crash `Invalid callback getObjectPropertyTypeCheckDisplayName` | 3 | dumpEllipsis `$this->line .= $cut` (int→string prop) → `(string)$cut` |
| `switch case must end with return/break/exit/throw` (dumpKey) | 2 | Rewrote switch as if/elseif chain |
| `Undefined variable $trace in page_trace.tpl` (all HTML 500) | 2 | include doesn't pass method locals → `$GLOBALS['tp_page_trace']` bridge |
| Private method/property override/shadowing false positives | 1 | private→protected (getSourceLink, displayOptions) |
| Extension constants (AMQP_*/PGSQL_*/Redis::*) unresolvable | 1 | Ignored AmqpCaster/PgSqlCaster/RedisCaster (dead code, ext not loaded) |

## Files Modified
| File | Purpose |
|------|---------|
| `C:\git\php\typephp-think\main.php` | Entry point with `main()` function |
| `C:\git\php\typephp-think\project.yml` | TPC compilation config (sources + ignore lists) |
| `C:\git\php\typephp-think\build.bat` | Build script (vcvarsall + tpc) |
| `C:\git\php\typephp-think\vendor\topthink\framework\src\think\console\command\RunServer.php` | Patched HTTP server |
| `C:\git\php\typephp-think\patches\topthink\framework\src\think\console\command\RunServer.php` | Patch source for Composer |
| `C:\git\source\tpc_v1095_windows_x86_64\php.ini` | Added pdo_mysql + mysqli extensions |
| `C:\git\php\typephp-think\patches\topthink\framework\src\think\route\dispatch\Controller.php` | `Str::studly()` → inline (class '' fix), `make('config')` bypass |
| `C:\git\php\typephp-think\patches\topthink\framework\src\think\initializer\Error.php` | Inline assignment-in-condition fix |
| `C:\git\php\typephp-think\patches\league\flysystem\src\*` | Interface constants, throw patterns, trait removal |
| `C:\git\php\typephp-think\patches\symfony\polyfill-mbstring\Mbstring.php` | Switch/if, variadic ref, array_walk_recursive fixes |
| `C:\git\php\typephp-think\patches\symfony\var-dumper\*` | Fall-throughs, top-level require removal |
| `C:\git\php\typephp-think\patches\symfony\var-dumper\Caster\{ClassStub,DateCaster,ExceptionCaster,ReflectionCaster,SocketCaster,SymfonyCaster,XmlReaderCaster}.php` | Type-union renames, __CLASS__ literal, out-params, cut += splits |
| `C:\git\php\typephp-think\patches\symfony\var-dumper\Dumper\{CliDumper,HtmlDumper}.php` | goto→flag, switch→if/elseif, heredoc split, protected overrides |
| `C:\git\php\typephp-think\patches\topthink\think-dumper\src\{Dumper,ServerDumper}.php` | createHandler closures, :?string |
| `C:\git\php\typephp-think\patches\topthink\think-trace\src\{Html,Console}.php` + `tpl\page_trace.tpl` | switch break, var_export, $GLOBALS trace bridge |
| `C:\git\php\typephp-think\patches\league\flysystem-local\LocalFilesystemAdapter.php` | 17× throw split, generator restructure, $mode rename |
| `C:\git\php\typephp-think\patch.php` | Copies patches/ → vendor/ (overwrite only) |

## Compiled Binary
- **Location:** `C:\git\php\typephp-think\build\myapp.exe` (11,896,832 bytes, 417-file build, 2026/8/2 15:25:05)
- **Runtime DLLs:** `C:\git\source\tpc_v1095_windows_x86_64\` (php8ts.dll, phpx.dll, ext/*.dll)
- **Commands:** `myapp.exe run`, `myapp.exe list`, `myapp.exe version`, `myapp.exe info`
