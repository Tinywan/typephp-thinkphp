# Progress: TPC ThinkPHP Compilation

## Session 1: Initial Compilation & Debug
- Created build.bat with vcvarsall.bat + tpc.exe
- Fixed project.yml (source -> sources, config)
- Fixed main.php (moved require inside main() function)
- Resolved compilation errors: return stmts, stray code, parent class chain
- Successfully compiled myapp.exe (~50KB)
- Discovered PHPX attr() bypasses __get() magic method
- Fixed with `$app->make('console')->run()`
- Verified: myapp.exe version, list, run all work

## Session 2: RunServer.php Patch
- Created pure-PHP socket HTTP server for embed SAPI
- Implemented request parsing, superglobal setup, static file serving
- Created patches/ directory for Composer patch system
- Tested HTTP 200 with ThinkPHP welcome page

## Session 3: Routing Fix
- User reported route/app.php not working
- Root cause: PATH_INFO not set, SCRIPT_NAME == PHP_SELF
- Fixed: set SCRIPT_NAME=/index.php, PHP_SELF=URI path, compute PATH_INFO
- Fixed: force new Request per request (pathinfo caching issue)
- All URL formats verified: /think, /index.php/index/hello, ?s=index/hello

## Session 4: PDO + Benchmark
- User reported "could not find driver" for MySQL
- Added php_pdo_mysql.dll + php_mysqli.dll to php.ini
- User confirmed php.ini placement works
- Ran oha benchmarks:
  - APP_DEBUG=true: 5.4x speedup (29.4 -> 157.9 req/s)
  - APP_DEBUG=false: 9.2x speedup (34.1 -> 315.3 req/s)
- Created planning files (task_plan.md, findings.md, progress.md)

## Key Metrics
- Compiled binary: 50KB (myapp.exe)
- Speedup: 5.4x (debug) to 9.2x (production)
- All ThinkPHP features work: routing, controllers, config loading, middleware
- Patch preserves CLI mode compatibility for A/B comparison
