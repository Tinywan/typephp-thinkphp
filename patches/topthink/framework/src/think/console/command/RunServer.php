<?php
declare (strict_types = 1);

namespace think\console\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class RunServer extends Command
{
    public function configure()
    {
        $this->setName('run')
            ->addOption(
                'host',
                'H',
                Option::VALUE_OPTIONAL,
                'The host to server the application on',
                '0.0.0.0'
            )
            ->addOption(
                'port',
                'p',
                Option::VALUE_OPTIONAL,
                'The port to server the application on',
                8000
            )
            ->addOption(
                'root',
                'r',
                Option::VALUE_OPTIONAL,
                'The document root of the application',
                ''
            )
            ->setDescription('PHP Built-in Server for ThinkPHP');
    }

    public function execute(Input $input, Output $output)
    {
        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');
        $root = $input->getOption('root');
        if (empty($root)) {
            $root = $this->app->getRootPath() . 'public';
        }

        if (PHP_SAPI === 'embed') {
            $this->runEmbedServer($host, $port, $root, $output);
        } else {
            $this->runBuiltinServer($host, $port, $root, $output);
        }
    }

    protected function runBuiltinServer(string $host, int $port, string $root, Output $output): void
    {
        $command = sprintf(
            '"%s" -S %s:%d -t %s %s',
            PHP_BINARY,
            $host,
            $port,
            escapeshellarg($root),
            escapeshellarg($root . DIRECTORY_SEPARATOR . 'router.php')
        );

        $output->writeln(sprintf('ThinkPHP Development server is started On <http://%s:%s/>', $host, $port));
        $output->writeln(sprintf('You can exit with <info>`CTRL-C`</info>'));
        $output->writeln(sprintf('Document root is: %s', $root));
        passthru($command);
    }

    protected function runEmbedServer(string $host, int $port, string $root, Output $output): void
    {
        $output->writeln(sprintf('ThinkPHP Development server (embed) started On <http://%s:%s/>', $host, $port));
        $output->writeln('You can exit with <info>`CTRL-C`</info>');
        $output->writeln(sprintf('Document root is: %s', $root));

        $addr = $host . ':' . $port;
        $server = @stream_socket_server("tcp://{$addr}", $errno, $errstr);
        if (!$server) {
            $output->writeln("<error>Failed to bind: {$errstr} ({$errno})</error>");
            return;
        }

        stream_set_blocking($server, false);

        $routerFile = $root . DIRECTORY_SEPARATOR . 'router.php';
        $indexFile  = $root . DIRECTORY_SEPARATOR . 'index.php';

        while (true) {
            $conn = @stream_socket_accept($server, 0);
            if ($conn === false) {
                usleep(5000);
                continue;
            }

            stream_set_timeout($conn, 5);
            $raw = '';
            while (true) {
                $chunk = fread($conn, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $raw .= $chunk;
                if (str_contains($raw, "\r\n\r\n")) {
                    break;
                }
            }

            if ($raw === '') {
                fclose($conn);
                continue;
            }

            $lines   = explode("\r\n", $raw);
            $requestLine = $lines[0];
            $parts   = explode(' ', $requestLine, 3);
            $method  = $parts[0] ?? 'GET';
            $uri     = $parts[1] ?? '/';
            $version = $parts[2] ?? 'HTTP/1.1';

            $headers = [];
            $bodyOffset = 0;
            for ($i = 1, $count = count($lines); $i < $count; $i++) {
                if ($lines[$i] === '') {
                    $bodyOffset = $i + 1;
                    break;
                }
                $pos = strpos($lines[$i], ':');
                if ($pos !== false) {
                    $key   = trim(substr($lines[$i], 0, $pos));
                    $value = trim(substr($lines[$i], $pos + 1));
                    $headers[$key] = $value;
                }
            }

            $bodyLines = array_slice($lines, $bodyOffset);
            $body      = implode("\r\n", $bodyLines);

            $contentLength = (int)($headers['Content-Length'] ?? $headers['content-length'] ?? 0);
            if ($contentLength > strlen($body)) {
                $more = fread($conn, $contentLength - strlen($body));
                if ($more !== false) {
                    $body .= $more;
                }
            }

            $parsedUrl = parse_url('http://host' . $uri);
            $pathInfo  = $parsedUrl['path'] ?? '/';
            $queryString = $parsedUrl['query'] ?? '';

            $scriptName = $pathInfo;
            $staticFile = $root . str_replace('/', DIRECTORY_SEPARATOR, $pathInfo);

            $response = null;
            if ($method === 'GET' && is_file($staticFile) && basename($staticFile) !== 'router.php') {
                $ext = pathinfo($staticFile, PATHINFO_EXTENSION);
                $mimeMap = [
                    'html' => 'text/html', 'htm'  => 'text/html', 'css'  => 'text/css',
                    'js'   => 'application/javascript', 'json' => 'application/json',
                    'png'  => 'image/png', 'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
                    'gif'  => 'image/gif', 'svg'  => 'image/svg+xml', 'ico'  => 'image/x-icon',
                    'woff' => 'font/woff', 'woff2'=> 'font/woff2', 'ttf'  => 'font/ttf',
                    'xml'  => 'text/xml', 'txt'  => 'text/plain',
                ];
                $mime = $mimeMap[$ext] ?? 'application/octet-stream';
                $content = file_get_contents($staticFile);
                $this->sendResponse($conn, 200, $mime, $content);
            } else {
                $this->processRequest($conn, $method, $uri, $pathInfo, $queryString, $headers, $body, $root, $scriptName);
            }

            fclose($conn);
        }
    }

    protected function processRequest($conn, string $method, string $uri, string $pathInfo, string $queryString, array $headers, string $body, string $root, string $scriptName): void
    {
        $entryScript = '/index.php';
        $scriptFile  = $root . DIRECTORY_SEPARATOR . 'index.php';

        if (str_starts_with($pathInfo, $entryScript)) {
            $pathAfterScript = substr($pathInfo, strlen($entryScript));
            $pathInfoValue   = $pathAfterScript !== false ? $pathAfterScript : '';
        } else {
            $pathInfoValue = $pathInfo === '/' ? '' : $pathInfo;
        }

        $_SERVER = [];
        $_SERVER['REQUEST_METHOD']    = $method;
        $_SERVER['REQUEST_URI']       = $uri;
        $_SERVER['QUERY_STRING']      = $queryString;
        $_SERVER['SERVER_NAME']       = 'localhost';
        $_SERVER['SERVER_PORT']       = 8000;
        $_SERVER['SERVER_PROTOCOL']   = 'HTTP/1.1';
        $_SERVER['DOCUMENT_ROOT']     = $root;
        $_SERVER['SCRIPT_FILENAME']   = $scriptFile;
        $_SERVER['SCRIPT_NAME']       = $entryScript;
        $_SERVER['PHP_SELF']          = $pathInfo;
        $_SERVER['PATH_INFO']         = $pathInfoValue;
        $_SERVER['REQUEST_TIME']      = time();
        $_SERVER['GATEWAY_INTERFACE'] = 'CGI/1.1';

        $remoteAddr = '';
        if (isset($conn)) {
            $name = stream_socket_get_name($conn, true);
            if ($name !== false) {
                $remoteAddr = $name;
            }
        }
        $_SERVER['REMOTE_ADDR'] = $remoteAddr ?: '127.0.0.1';

        foreach ($headers as $key => $value) {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $_SERVER[$serverKey] = $value;
        }

        if (isset($headers['Content-Type']) || isset($headers['content-type'])) {
            $ct = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
            $_SERVER['CONTENT_TYPE'] = $ct;
        }
        if (isset($headers['Content-Length']) || isset($headers['content-length'])) {
            $_SERVER['CONTENT_LENGTH'] = $headers['Content-Length'] ?? $headers['content-length'] ?? 0;
        }

        $_GET = [];
        if ($queryString !== '') {
            parse_str($queryString, $_GET);
        }

        $_POST   = [];
        $_COOKIE = [];
        $_REQUEST = [];
        $_FILES  = [];

        if (isset($headers['Cookie']) || isset($headers['cookie'])) {
            $cookieStr = $headers['Cookie'] ?? $headers['cookie'] ?? '';
            parse_str(str_replace('; ', '&', $cookieStr), $_COOKIE);
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            if (str_contains($contentType, 'application/x-www-form-urlencoded') && $body !== '') {
                parse_str($body, $_POST);
            } elseif (str_contains($contentType, 'application/json') && $body !== '') {
                $_POST = json_decode($body, true) ?? [];
            }
        }
        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);

        $this->app->initialize();

        $request = $this->app->make('request', [], true);
        $request->server = array_change_key_case($_SERVER, CASE_UPPER);
        $request->header = array_change_key_case($headers);
        $request->get    = $_GET;
        $request->post   = $_POST;
        $request->put    = $_POST;
        $request->request = $_REQUEST;
        $request->cookie = $_COOKIE;
        $request->file   = $_FILES;
        $request->input  = $body;

        if (!empty($queryString)) {
            $request->query = $_GET;
        }

        $this->app->instance('request', $request);

        $http = $this->app->make('http');
        $response = $http->run($request);

        $statusCode = 200;
        $respHeaders = [];
        $respBody = '';

        if (method_exists($response, 'getCode')) {
            $statusCode = $response->getCode();
        }
        if (method_exists($response, 'getHeader')) {
            $respHeaders['Content-Type'] = $response->getHeader('Content-Type') ?: 'text/html';
        }
        $respBody = (string)$response->getContent();

        $this->sendResponse($conn, $statusCode, $respHeaders['Content-Type'] ?? 'text/html', $respBody, $respHeaders);
    }

    protected function sendResponse($conn, int $statusCode, string $contentType, string $body, array $extraHeaders = []): void
    {
        $statusTexts = [
            200 => 'OK', 301 => 'Moved Permanently', 302 => 'Found',
            304 => 'Not Modified', 400 => 'Bad Request', 401 => 'Unauthorized',
            403 => 'Forbidden', 404 => 'Not Found', 500 => 'Internal Server Error',
        ];
        $statusText = $statusTexts[$statusCode] ?? 'Unknown';

        $header  = "HTTP/1.1 {$statusCode} {$statusText}\r\n";
        $header .= "Content-Type: {$contentType}\r\n";
        $header .= "Content-Length: " . strlen($body) . "\r\n";
        $header .= "Connection: close\r\n";

        foreach ($extraHeaders as $key => $value) {
            if ($key !== 'Content-Type') {
                $header .= "{$key}: {$value}\r\n";
            }
        }

        $header .= "\r\n";
        fwrite($conn, $header . $body);
    }
}
