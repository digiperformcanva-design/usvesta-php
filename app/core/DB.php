<?php

final class DB
{
    private static ?PDO $pdo = null;
    public static string $driver = 'mysql';
    public static int $queries = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        $cfg = config('db');
        self::$driver = $cfg['driver'] ?? 'mysql';
        if (self::$driver === 'sqlite') {
            $pdo = new PDO('sqlite:' . $cfg['path']);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'] ?? '127.0.0.1', $cfg['port'] ?? 3306, $cfg['name']);
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                // UTC like Django (USE_TZ) and lenient mode like the original SQLite database
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+00:00', SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'",
                PDO::ATTR_EMULATE_PREPARES => true,
            ]);
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        return self::$pdo = $pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        self::$queries++;
        $st = self::pdo()->prepare($sql);
        foreach (array_values($params) as $i => $p) {
            $type = is_int($p) ? PDO::PARAM_INT : (is_bool($p) ? PDO::PARAM_INT : ($p === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
            $st->bindValue($i + 1, is_bool($p) ? (int)$p : $p, $type);
        }
        $st->execute();
        return $st;
    }

    public static function all(string $sql, array $p = []): array { return self::query($sql, $p)->fetchAll(); }
    public static function one(string $sql, array $p = []): ?array { $r = self::query($sql, $p)->fetch(); return $r ?: null; }
    public static function scalar(string $sql, array $p = []): mixed { $r = self::query($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
    public static function lastId(): string { return self::pdo()->lastInsertId(); }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try { $r = $fn(); $pdo->commit(); return $r; }
        catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public static function q(string $ident): string { return '`' . str_replace('`', '', $ident) . '`'; }
}
