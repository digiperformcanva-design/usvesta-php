<?php

final class Request
{
    public string $method;
    public string $path;
    public array $GET;
    public array $POST;
    public array $FILES = [];
    public array $META;
    public string $body;
    private ?array $jsonCache = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        $this->GET = $_GET;
        $this->POST = $_POST;
        $this->META = $_SERVER;
        $this->body = (string)@file_get_contents('php://input');
        // Django keeps repeated keys in urlencoded bodies (getlist); PHP keeps only the last – re-parse.
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($this->method === 'POST' && str_starts_with($ct, 'application/x-www-form-urlencoded') && $this->body !== '') {
            $this->POST = self::parseQs($this->body);
        }
        if (!empty($_SERVER['QUERY_STRING'])) $this->GET = self::parseQs($_SERVER['QUERY_STRING']);
        $this->FILES = self::normalizeFiles($_FILES);
    }

    /** parse query string keeping repeated keys as lists (key => last value; key[] => list) */
    public static function parseQs(string $qs): array
    {
        $out = [];
        $multi = [];
        foreach (explode('&', $qs) as $pair) {
            if ($pair === '') continue;
            $kv = explode('=', $pair, 2);
            $k = urldecode($kv[0]);
            $v = urldecode($kv[1] ?? '');
            if (str_ends_with($k, '[]')) { $base = substr($k, 0, -2); $out[$base][] = $v; $multi[$base][] = $v; continue; }
            $out[$k] = $v;
            $multi[$k][] = $v;
        }
        $out['__multi__'] = $multi;
        return $out;
    }

    private static function normalizeFiles(array $files): array
    {
        $out = [];
        foreach ($files as $key => $f) {
            if (is_array($f['name'])) {
                $list = [];
                foreach ($f['name'] as $i => $n) {
                    if (is_array($n)) continue;
                    if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                    $list[] = new UploadedFile($n, $f['tmp_name'][$i], (int)$f['size'][$i], (string)$f['type'][$i], (int)$f['error'][$i]);
                }
                $out[$key] = $list;
            } else {
                if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $out[$key] = new UploadedFile($f['name'], $f['tmp_name'], (int)$f['size'], (string)$f['type'], (int)$f['error']);
            }
        }
        return $out;
    }

    /* ---- POST / GET access like Django QueryDict */
    public function post(string $k, mixed $d = null): mixed
    {
        $k = preg_replace('/\[\]$/', '', $k);
        if (!array_key_exists($k, $this->POST)) return $d;
        $v = $this->POST[$k];
        return is_array($v) ? (count($v) ? end($v) : $d) : $v;
    }
    public function has(string $k): bool { return array_key_exists(preg_replace('/\[\]$/', '', $k), $this->POST); }
    public function get(string $k, mixed $d = null): mixed
    {
        if (!array_key_exists($k, $this->GET) || $k === '__multi__') return $d;
        $v = $this->GET[$k];
        return is_array($v) ? (count($v) ? end($v) : $d) : $v;
    }
    public function getlist(string $k): array
    {
        $base = preg_replace('/\[\]$/', '', $k);
        $multi = $this->POST['__multi__'] ?? null;
        if ($multi !== null) return $multi[$base] ?? $multi[$k] ?? [];
        $v = $this->POST[$base] ?? null;
        if ($v === null) return [];
        return is_array($v) ? array_values($v) : [$v];
    }
    public function file(string $k): ?UploadedFile
    {
        $base = preg_replace('/\[\]$/', '', $k);
        $f = $this->FILES[$k] ?? $this->FILES[$base] ?? null;
        if (is_array($f)) return $f ? end($f) : null;
        return $f;
    }
    /** @return UploadedFile[] */
    public function files(string $k): array
    {
        $base = preg_replace('/\[\]$/', '', $k);
        $f = $this->FILES[$k] ?? $this->FILES[$base] ?? null;
        if ($f === null) return [];
        return is_array($f) ? $f : [$f];
    }
    public function hasFile(string $k): bool { return $this->file($k) !== null; }

    public function json(): array
    {
        if ($this->jsonCache !== null) return $this->jsonCache;
        $d = json_decode($this->body, true);
        if (!is_array($d)) throw new JsonException('Invalid JSON');
        return $this->jsonCache = $d;
    }

    public function header(string $name): ?string
    {
        $k = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->META[$k] ?? null;
    }

    public function referer(string $default = '/'): string { return $this->META['HTTP_REFERER'] ?? $default; }
    public function build_absolute_uri(?string $loc = null): string
    {
        if ($loc === null) return absolute_url(ltrim($_SERVER['REQUEST_URI'] ?? '/', '/'));
        if (preg_match('#^https?://#', $loc)) return $loc;
        if (!str_starts_with($loc, '/')) {
            $dir = rtrim(dirname($this->path . 'x'), '/');
            $loc = $dir . '/' . $loc;
        }
        return absolute_url(ltrim($loc, '/'));
    }
    public function get_full_path(): string { return $_SERVER['REQUEST_URI'] ?? $this->path; }
    public function ip(): string { return $this->META['HTTP_X_FORWARDED_FOR'] ?? $this->META['REMOTE_ADDR'] ?? ''; }

    public function tplGet(string $k): mixed
    {
        return match ($k) {
            'user' => Auth::user(),
            'path' => $this->path,
            'GET' => array_diff_key($this->GET, ['__multi__' => 1]),
            'POST' => array_diff_key($this->POST, ['__multi__' => 1]),
            'method' => $this->method,
            'META' => $this->META,
            'session' => $_SESSION ?? [],
            'get_full_path' => $this->get_full_path(),
            'build_absolute_uri' => $this->build_absolute_uri(),
            'COOKIES' => $_COOKIE,
            'headers' => array_change_key_case(function_exists('getallheaders') ? (getallheaders() ?: []) : [], CASE_LOWER),
            default => Undefined::get(),
        };
    }
    public function __get($k) { return $k === 'user' ? Auth::user() : (($v = $this->tplGet($k)) instanceof Undefined ? null : $v); }
}

/* ======================================================================= responses */

class Response
{
    public array $headers = [];
    public array $cookies = [];
    public function __construct(public string $content = '', public int $status = 200, string $contentType = 'text/html; charset=utf-8')
    {
        $this->headers['Content-Type'] = $contentType;
    }
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) header("$k: $v");
        }
        echo $this->content;
    }
}

final class JsonResponse extends Response
{
    public function __construct(mixed $data, int $status = 200)
    {
        parent::__construct(json_encode(to_plain($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), $status, 'application/json');
    }
}

final class RedirectResponse extends Response
{
    public function __construct(string $url, int $status = 302)
    {
        parent::__construct('', $status);
        $this->headers['Location'] = $url;
    }
}

final class Http404 extends Exception {}

function redirect(string $to): RedirectResponse { return new RedirectResponse($to); }
function json(mixed $d, int $status = 200): JsonResponse { return new JsonResponse($d, $status); }
function http(string $content, int $status = 200, string $type = 'text/html; charset=utf-8'): Response { return new Response($content, $status, $type); }
function redirect_back(Request $r): RedirectResponse { return redirect($r->referer('/')); }

/* ======================================================================= rendering */

function engine(): Engine
{
    static $e;
    return $e ??= new Engine(BASE_PATH . '/templates');
}

function render(Request $req, string $template, array $data = [], int $status = 200): Response
{
    $ctx = array_merge(context_processors($req), $data);
    return new Response(engine()->render($template, $ctx), $status);
}

function context_processors(Request $req): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $base = [
        'request' => $req,
        'user' => Auth::user(),
        'messages' => new LazyMessages(),
        'csrf_token' => new SafeString(csrf_token()),
        'perms' => [],
        'DEFAULT_MESSAGE_LEVELS' => ['DEBUG' => 10, 'INFO' => 20, 'SUCCESS' => 25, 'WARNING' => 30, 'ERROR' => 40],
    ];
    return $cache = array_merge($base, single_templates_data($req), single_templates_data_for_add_to_cart($req));
}

/* ======================================================================= routing */

final class Router
{
    /** @var array<int, array{0:string,1:callable,2:array}> */
    private array $routes = [];

    public function add(string $pattern, callable|array $handler, array $opts = []): void
    {
        $this->routes[] = [self::compile($pattern), $handler, $opts];
    }

    /** Django path() syntax -> regex */
    private static function compile(string $p): string
    {
        if (str_starts_with($p, '^')) return '#' . $p . '#u';
        $parts = preg_split('/(<(?:\w+:)?\w+>)/', $p, -1, PREG_SPLIT_DELIM_CAPTURE);
        $re = '';
        foreach ($parts as $part) {
            if (preg_match('/^<(?:(\w+):)?(\w+)>$/', $part, $m)) {
                $rx = match ($m[1] ?: 'str') {
                    'int' => '[0-9]+', 'slug' => '[-a-zA-Z0-9_]+', 'path' => '.+',
                    'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}', default => '[^/]+',
                };
                $re .= '(?P<' . $m[2] . '>' . $rx . ')';
            } else {
                $re .= preg_quote($part, '#');
            }
        }
        return '#^/' . $re . '$#u';
    }

    public function match(string $path): ?array
    {
        foreach ($this->routes as [$re, $h, $opts]) {
            if (preg_match($re, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$h, $params, $opts];
            }
        }
        return null;
    }

    public function dispatch(Request $req): Response
    {
        $hit = $this->match($req->path);
        if (!$hit && !str_ends_with($req->path, '/') && $this->match($req->path . '/')) {
            // Django APPEND_SLASH
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            return new RedirectResponse($req->path . '/' . ($qs !== '' ? '?' . $qs : ''), 301);
        }
        if (!$hit) throw new Http404();
        [$h, $params, $opts] = $hit;

        if (!empty($opts['login']) && !Auth::check()) {
            $loginUrl = is_string($opts['login']) ? $opts['login'] : config('login_url', '/login-register');
            return redirect($loginUrl . '?next=' . rawurlencode($req->get_full_path()));
        }
        if ($req->method === 'POST' && empty($opts['csrf_exempt']) && !csrf_valid($req)) {
            return render($req, '403_csrf.html', [], 403);
        }
        if (is_array($h) && count($h) === 2) return $h($req, ...array_values($params));
        if (is_array($h) && is_string($h[0])) {
            $obj = new $h[0]();
            $m = strtolower($req->method);
            if (!method_exists($obj, $m)) return http('Method Not Allowed', 405);
            return $obj->$m($req, ...array_values($params));
        }
        return $h($req, ...array_values($params));
    }
}
