<?php

/* ====================================================================== config */

function config(?string $key = null, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require BASE_PATH . '/app/config.php';
    }
    if ($key === null) return $cfg;
    $v = $cfg;
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $default;
        $v = $v[$k];
    }
    return $v;
}

/* ====================================================================== time */

function app_tz(): DateTimeZone { static $tz; return $tz ??= new DateTimeZone(config('timezone', 'Asia/Kolkata')); }
function to_local(DateTimeInterface $d): DateTimeImmutable { return DateTimeImmutable::createFromInterface($d)->setTimezone(app_tz()); }
function now_local(): DateTimeImmutable { return new DateTimeImmutable('now', app_tz()); }

/* ====================================================================== misc */

function to_plain(mixed $v): mixed
{
    if ($v instanceof Undefined) return null;
    if ($v instanceof SafeString || $v instanceof Decimal || $v instanceof FieldFile) return (string)$v;
    if ($v instanceof DateTimeInterface) return $v->format(DATE_ATOM);
    if ($v instanceof Model) return $v->toArray();
    if ($v instanceof QuerySet) return array_map('to_plain', $v->get());
    if (is_array($v)) return array_map('to_plain', $v);
    return $v;
}

function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function url_for(string $name): string { return '/' . ltrim($name, '/'); }

function absolute_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? parse_url(config('site_url', 'http://localhost'), PHP_URL_HOST);
    return ($https ? 'https' : 'http') . '://' . $host . '/' . ltrim($path, '/');
}

function log_error(string $msg): void
{
    @file_put_contents(BASE_PATH . '/storage/logs/app-' . gmdate('Y-m-d') . '.log', '[' . gmdate('c') . '] ' . $msg . "\n", FILE_APPEND);
}

/** Run work after the response has been sent (replacement for Django's background threads). */
function after_response(?callable $fn = null): array
{
    static $queue = [];
    if ($fn) $queue[] = $fn;
    return $queue;
}

function run_after_response(): void
{
    $queue = after_response();
    if (!$queue) return;
    ignore_user_abort(true);
    @set_time_limit(600);
    ini_set('display_errors', '0');
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
    else { while (ob_get_level() > 0) @ob_end_flush(); flush(); }
    foreach ($queue as $fn) {
        try { $fn(); } catch (Throwable $e) { log_error('after_response: ' . $e->getMessage() . "\n" . $e->getTraceAsString()); }
    }
}

/* ====================================================================== session / csrf / messages */

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (PHP_SAPI === 'cli' && !defined('TEST_HTTP')) { $_SESSION ??= []; return; }
    $dir = BASE_PATH . '/storage/sessions';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    session_name('sessionid');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params(['lifetime' => 1209600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
    ini_set('session.gc_maxlifetime', '1209600');
    session_start();
}

function session_get(string $k, mixed $d = null): mixed { return $_SESSION[$k] ?? $d; }
function session_set(string $k, mixed $v): void { $_SESSION[$k] = $v; }

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    $t = $_SESSION['_csrf'];
    if (!headers_sent() && ($_COOKIE['csrftoken'] ?? '') !== $t && PHP_SAPI !== 'cli') {
        setcookie('csrftoken', $t, ['expires' => time() + 31449600, 'path' => '/', 'samesite' => 'Lax']);
        $_COOKIE['csrftoken'] = $t;
    }
    return $t;
}

function csrf_valid(Request $r): bool
{
    $expected = $_SESSION['_csrf'] ?? '';
    if ($expected === '') return false;
    $given = $r->POST['csrfmiddlewaretoken'] ?? $r->header('X-CSRFToken') ?? $r->header('X-CSRF-Token') ?? '';
    if (is_array($given)) $given = (string)reset($given);
    return hash_equals($expected, (string)$given);
}

final class Message
{
    public function __construct(public string $level, public string $message) {}
    public function tags(): string { return $this->level === 'error' ? 'danger' : $this->level; }
    public function level_tag(): string { return $this->tags(); }
    public function __toString(): string { return $this->message; }
}

final class Messages
{
    public static function add(string $level, string $msg): void { $_SESSION['_messages'][] = [$level, $msg]; }
    /** consumed on iteration like Django's storage */
    public static function consume(): array
    {
        $m = $_SESSION['_messages'] ?? [];
        unset($_SESSION['_messages']);
        return array_map(fn($x) => new Message($x[0], $x[1]), $m);
    }
}

final class LazyMessages implements IteratorAggregate, Countable
{
    private ?array $items = null;
    private function load(): array { return $this->items ??= Messages::consume(); }
    public function getIterator(): ArrayIterator { return new ArrayIterator($this->load()); }
    public function count(): int { return count($_SESSION['_messages'] ?? []) + count($this->items ?? []); }
}

function msg_success(string $m): void { Messages::add('success', $m); }
function msg_error(string $m): void { Messages::add('error', $m); }
function msg_info(string $m): void { Messages::add('info', $m); }

/* ====================================================================== auth */

final class Passwords
{
    public static function check(string $raw, string $encoded): bool
    {
        if ($encoded === '' || $encoded[0] === '!') return false;
        if (str_starts_with($encoded, 'pbkdf2_sha256$')) {
            $p = explode('$', $encoded, 4);
            if (count($p) !== 4) return false;
            [, $iter, $salt, $hash] = $p;
            $calc = base64_encode(hash_pbkdf2('sha256', $raw, $salt, (int)$iter, 32, true));
            return hash_equals($hash, $calc);
        }
        if (str_starts_with($encoded, 'phpbcrypt$')) return password_verify($raw, substr($encoded, 10));
        if (str_starts_with($encoded, 'bcrypt_sha256$')) return password_verify(hash('sha256', $raw), substr($encoded, 14));
        return false;
    }
    public static function make(string $raw): string { return 'phpbcrypt$' . password_hash($raw, PASSWORD_BCRYPT); }
}

final class Auth
{
    private static CustomUser|AnonymousUser|null $user = null;

    public static function user(): CustomUser|AnonymousUser
    {
        if (self::$user) return self::$user;
        $id = $_SESSION['_auth_user_id'] ?? null;
        $u = $id ? CustomUser::find($id) : null;
        if ($u && !$u->is_active) $u = null;
        return self::$user = $u ?: new AnonymousUser();
    }

    public static function login(CustomUser $u): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        $_SESSION['_auth_user_id'] = $u->pk();
        unset($_SESSION['_csrf']);
        $u->attrs['last_login'] = now_utc_str();
        CustomUser::updateWhere(['id' => $u->pk()], ['last_login' => now_utc_str()]);
        self::$user = $u;
    }

    public static function logout(): void
    {
        // Django flushes the whole session on logout
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        self::$user = new AnonymousUser();
    }

    public static function check(): bool { return self::user() instanceof CustomUser; }
    public static function reset(): void { self::$user = null; }
}

/* ====================================================================== uploads */

final class UploadedFile
{
    public function __construct(public string $name, public string $tmp, public int $size, public string $type, public int $error) {}
    public function ok(): bool { return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tmp) || ($this->error === UPLOAD_ERR_OK && defined('TEST_HTTP') && is_file($this->tmp)); }
    public function __toString(): string { return $this->name; }
}

final class Storage
{
    /** Django get_valid_filename */
    public static function validName(string $name): string
    {
        $name = str_replace(' ', '_', trim(basename(str_replace('\\', '/', $name))));
        $name = preg_replace('/[^-\w.]/u', '', $name);
        return $name === '' || $name === '.' || $name === '..' ? 'file' : $name;
    }

    /**
     * Save an upload under media/<uploadTo>/ and return the stored relative name.
     * $opt = ['max' => px, 'quality' => int] converts png/jpg to WebP like the Django signals did.
     */
    public static function save(UploadedFile $f, string $uploadTo, ?array $opt = null): ?string
    {
        if (!$f->ok()) return null;
        $uploadTo = trim($uploadTo, '/');
        $dir = BASE_PATH . '/media/' . $uploadTo;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = self::validName($f->name);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        // Never store executable files
        if (in_array($ext, ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'pht', 'htaccess', 'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp'], true)) {
            $name .= '.txt';
            $ext = 'txt';
        }

        if ($opt !== null && in_array($ext, ['png', 'jpg', 'jpeg'], true) && function_exists('imagewebp')) {
            $webp = self::toWebp($f->tmp, $ext, $opt['max'] ?? null, $opt['quality'] ?? 90);
            if ($webp !== null) {
                $base = pathinfo($name, PATHINFO_FILENAME) . '.webp';
                $final = self::available($dir, $base);
                file_put_contents($dir . '/' . $final, $webp);
                return $uploadTo . '/' . $final;
            }
        }
        $final = self::available($dir, $name);
        $dest = $dir . '/' . $final;
        if (!@move_uploaded_file($f->tmp, $dest)) { if (!@copy($f->tmp, $dest)) return null; }
        @chmod($dest, 0644);
        return $uploadTo . '/' . $final;
    }

    public static function putContents(string $bytes, string $uploadTo, string $name, ?array $opt = null): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $bytes);
        $f = new UploadedFile($name, $tmp, strlen($bytes), '', UPLOAD_ERR_OK);
        $uploadTo = trim($uploadTo, '/');
        $dir = BASE_PATH . '/media/' . $uploadTo;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($opt !== null && in_array($ext, ['png', 'jpg', 'jpeg'], true) && ($webp = self::toWebp($tmp, $ext, $opt['max'] ?? null, $opt['quality'] ?? 90)) !== null) {
            $final = self::available($dir, pathinfo(self::validName($name), PATHINFO_FILENAME) . '.webp');
            file_put_contents($dir . '/' . $final, $webp);
        } else {
            $final = self::available($dir, self::validName($name));
            copy($tmp, $dir . '/' . $final);
        }
        @unlink($tmp);
        return $uploadTo . '/' . $final;
    }

    private static function available(string $dir, string $name): string
    {
        if (!file_exists($dir . '/' . $name)) return $name;
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        do {
            $cand = $base . '_' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 7) . ($ext !== '' ? '.' . $ext : '');
        } while (file_exists($dir . '/' . $cand));
        return $cand;
    }

    private static function toWebp(string $path, string $ext, ?int $max, int $quality): ?string
    {
        try {
            $img = match ($ext) { 'png' => @imagecreatefrompng($path), default => @imagecreatefromjpeg($path) };
            if (!$img) return null;
            if ($ext !== 'png' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($path);
                $o = $exif['Orientation'] ?? 1;
                if ($o == 3) $img = imagerotate($img, 180, 0);
                elseif ($o == 6) $img = imagerotate($img, -90, 0);
                elseif ($o == 8) $img = imagerotate($img, 90, 0);
            }
            $w = imagesx($img); $h = imagesy($img);
            if ($max && max($w, $h) > $max) {
                $scale = $max / max($w, $h);
                $nw = (int)($w * $scale); $nh = (int)($h * $scale);
                $dst = imagecreatetruecolor($nw, $nh);
                imagealphablending($dst, false); imagesavealpha($dst, true);
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $dst;
            } elseif (!imageistruecolor($img)) {
                imagepalettetotruecolor($img);
            }
            imagesavealpha($img, true);
            ob_start();
            imagewebp($img, null, $quality);
            $out = ob_get_clean();
            imagedestroy($img);
            return $out ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}

/** Upload presets mirroring the Django pre_save signals. */
const IMG_PRODUCT = ['max' => 1200, 'quality' => 82];
const IMG_CMS = ['max' => 1920, 'quality' => 82];
const IMG_BANNER = ['max' => 1920, 'quality' => 94];
const IMG_BANNER_PHONE = ['max' => 1080, 'quality' => 92];
const IMG_DEFAULT = ['max' => null, 'quality' => 90];

/* ====================================================================== http client */

final class Http
{
    public static function request(string $method, string $url, array $headers = [], string|array|null $body = null, int $timeout = 10): array
    {
        $ch = curl_init($url);
        $h = [];
        foreach ($headers as $k => $v) $h[] = "$k: $v";
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout), CURLOPT_HTTPHEADER => $h, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) throw new RuntimeException('HTTP error: ' . $err);
        return ['status' => $status, 'body' => $resp, 'json' => json_decode($resp, true)];
    }
    public static function postJson(string $url, array $data, array $headers = [], int $timeout = 10): array
    {
        return self::request('POST', $url, $headers + ['Content-Type' => 'application/json'], json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $timeout);
    }
    public static function postForm(string $url, array $data, array $headers = [], int $timeout = 10): array
    {
        return self::request('POST', $url, $headers + ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query($data), $timeout);
    }
    public static function get(string $url, array $headers = [], int $timeout = 10): array { return self::request('GET', $url, $headers, null, $timeout); }
}

/* ====================================================================== mail (SMTP) */

final class Mailer
{
    public static function send(string $subject, string $body, string $to): bool
    {
        $c = config('mail');
        if (empty($c['host']) || empty($c['user'])) {
            // fall back to PHP mail() (works on Hostinger with a domain mailbox)
            $from = $c['from'] ?? ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            return @mail($to, $subject, $body, "From: $from\r\nContent-Type: text/plain; charset=UTF-8");
        }
        try {
            return self::smtp($c, $subject, $body, $to);
        } catch (Throwable $e) {
            log_error('SMTP: ' . $e->getMessage());
            return false;
        }
    }

    private static function smtp(array $c, string $subject, string $body, string $to): bool
    {
        $secure = $c['encryption'] ?? 'tls';
        $host = ($secure === 'ssl' ? 'ssl://' : '') . $c['host'];
        $fp = stream_socket_client($host . ':' . ($c['port'] ?? 587), $errno, $errstr, 15);
        if (!$fp) throw new RuntimeException("connect: $errstr");
        stream_set_timeout($fp, 15);
        $read = function () use ($fp) {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) { $data .= $line; if (isset($line[3]) && $line[3] === ' ') break; }
            return $data;
        };
        $cmd = function (string $s, array $ok) use ($fp, $read) {
            fwrite($fp, $s . "\r\n");
            $r = $read();
            if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new RuntimeException("SMTP '$s' => $r");
            return $r;
        };
        $read();
        $ehlo = 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $cmd($ehlo, [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('TLS failed');
            $cmd($ehlo, [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['user']), [334]);
        $cmd(base64_encode($c['pass']), [235]);
        $from = $c['from'] ?? $c['user'];
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $headers = "From: $from\r\nTo: $to\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\nDate: " . date('r') . "\r\n";
        $msg = str_replace(["\r\n", "\n"], ["\n", "\r\n"], $body);
        $msg = preg_replace('/^\./m', '..', $msg);
        $cmd($headers . "\r\n" . $msg . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }
}
