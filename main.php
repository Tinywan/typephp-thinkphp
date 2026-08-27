<?php
use think\App;

function main() :void
{
    $autoload = getcwd() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!file_exists($autoload)) {
        $autoload = __DIR__ . '/vendor/autoload.php';
    }
    require $autoload;
    global $argv;
    $cmd = $argv[1] ?? '';
    if ($cmd === 'info') {
        echo "PHP_BINARY: " . PHP_BINARY . "\n";
        echo "PHP_VERSION: " . PHP_VERSION . "\n";
        echo "PHP_SAPI: " . PHP_SAPI . "\n";
        echo "__DIR__: " . __DIR__ . "\n";
        echo "php_ini_loaded_file: " . (php_ini_loaded_file() ?: '(none)') . "\n";
        echo "php_ini_scanned_files: " . (php_ini_scanned_files() ?: '(none)') . "\n";
        echo "extension_dir: " . ini_get('extension_dir') . "\n";
        echo "get_loaded_extensions: " . implode(', ', get_loaded_extensions()) . "\n";
        echo "extension_loaded(pdo): " . (extension_loaded('pdo') ? 'yes' : 'no') . "\n";
        echo "extension_loaded(pdo_mysql): " . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";
        echo "output_buffering: " . ini_get('output_buffering') . "\n";
        echo "implicit_flush: " . ini_get('implicit_flush') . "\n";
        echo "STDOUT defined: " . (defined('STDOUT') ? 'yes' : 'no') . "\n";
        echo "is_resource(STDOUT): " . (defined('STDOUT') && is_resource(STDOUT) ? 'yes' : 'no') . "\n";
        $h = @fopen('php://stdout', 'w');
        echo "fopen php://stdout: " . (is_resource($h) ? 'resource' : 'FAILED') . "\n";
        if (is_resource($h)) {
            $r = @fwrite($h, "FWRITE-STDOUT-OK\n");
            echo "fwrite php://stdout res: " . var_export($r, true) . "\n";
            fflush($h);
            fclose($h);
        }
        $h2 = @fopen('php://output', 'w');
        echo "fopen php://output: " . (is_resource($h2) ? 'resource' : 'FAILED') . "\n";
        if (is_resource($h2)) {
            $r2 = @fwrite($h2, "FWRITE-OUTPUT-OK\n");
            echo "fwrite php://output res: " . var_export($r2, true) . "\n";
            fclose($h2);
        }
        if (defined('STDOUT')) {
            $r3 = @fwrite(STDOUT, "FWRITE-STDOUT-CONST-OK\n");
            echo "fwrite STDOUT const res: " . var_export($r3, true) . "\n";
            fflush(STDOUT);
        }
        echo "argv: " . var_export($_SERVER['argv'] ?? '(not set)', true) . "\n";
        $ansicon = getenv('ANSICON');
        echo "getenv(ANSICON): " . var_export($ansicon, true) . "\n";
        echo "DIRECTORY_SEPARATOR: " . DIRECTORY_SEPARATOR . "\n";
        $out = new \think\console\Output();
        echo "output class: " . get_class($out) . "\n";
        $out->writeln("OUTPUT-WRITELN-OK");
        echo "after writeln\n";
        $d = new \think\console\output\driver\Console($out);
        echo "driver class: " . get_class($d) . "\n";
        $d->write("DRIVER-WRITE-OK\n", true);
        echo "after driver write\n";
        try {
            $d->renderException(new \RuntimeException('TEST-EXCEPTION-12345'));
            echo "after renderException\n";
        } catch (\Throwable $te) {
            echo "renderException threw: " . get_class($te) . ": " . $te->getMessage() . "\n";
        }
        try {
            $app = new \think\App();
            echo "new App OK\n";
            $console = new \think\Console($app);
            echo "new Console OK\n";
            $cmd = $console->find('version');
            echo "find(version): " . get_class($cmd) . "\n";
            // HTTP 请求链路测试（复用同一 App 实例，模拟 RunServer 的请求构造）
            $_SERVER = array_change_key_case([
                'REQUEST_METHOD'    => 'GET',
                'REQUEST_URI'       => '/',
                'QUERY_STRING'      => '',
                'SERVER_NAME'       => 'localhost',
                'SERVER_PORT'       => 8000,
                'SERVER_PROTOCOL'   => 'HTTP/1.1',
                'SCRIPT_FILENAME'   => $app->getRootPath() . 'public/index.php',
                'SCRIPT_NAME'       => '/index.php',
                'PHP_SELF'          => '/',
                'PATH_INFO'         => '',
                'REQUEST_TIME'      => time(),
                'REMOTE_ADDR'       => '127.0.0.1',
            ], CASE_UPPER);
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];
            $_REQUEST = [];
            $_FILES = [];
            $req = $app->make('request', [], true);
            $req->server = $_SERVER;
            $req->header = [];
            $req->get    = $_GET;
            $req->post   = $_POST;
            $req->put    = $_POST;
            $req->request = $_REQUEST;
            $req->cookie = $_COOKIE;
            $req->file   = $_FILES;
            $req->input  = '';
            $app->instance('request', $req);
            $http = $app->make('http');
            $resp = $http->run($req);
            echo "HTTP STATUS: " . $resp->getCode() . "\n";
            $content = (string) $resp->getContent();
            $content = str_replace(["\r", "\n"], ' ', $content);
            echo "HTTP BODY: " . substr($content, 0, 300) . "\n";
        } catch (\Throwable $te) {
            echo "console setup threw: " . get_class($te) . ": " . $te->getMessage() . "\n";
            echo "trace: " . str_replace("\n", " | ", $te->getTraceAsString()) . "\n";
        }
        return;
    }

    try {
        $app = new App();
        $app->make('console')->run();
    } catch (\Throwable $te) {
        echo "CONSOLE FATAL: " . get_class($te) . ": " . $te->getMessage() . "\n";
        echo $te->getTraceAsString() . "\n";
        exit(1);
    }
}
