<?php
/**
 * First-run setup (MySQL):
 *  1. creates the tables from database/schema.sql if they don't exist;
 *  2. if ../usvesta-data.sql (next to usvesta-config.php, outside the web root) exists and the
 *     store is still empty, imports it once and renames it to usvesta-data.sql.imported.
 */
final class Migrator
{
    public static function ensure(): void
    {
        $flag = BASE_PATH . '/storage/cache/schema_ok_' . md5((string)json_encode(config('db')));
        $dataFile = dirname(BASE_PATH) . '/usvesta-data.sql';
        if (!is_file($dataFile) && is_file(BASE_PATH . '/usvesta-data.sql')) $dataFile = BASE_PATH . '/usvesta-data.sql';
        if (is_file($flag) && !is_file($dataFile)) return;

        $pdo = DB::pdo();
        if (DB::$driver === 'mysql') {
            $exists = (bool)DB::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'cloth_app_product'");
            if (!$exists) {
                foreach (self::split(file_get_contents(BASE_PATH . '/database/schema.sql')) as $stmt) $pdo->exec($stmt);
                log_error('Migrator: schema created');
            }
            if (is_file($dataFile) && (int)DB::scalar('SELECT COUNT(*) FROM `cloth_app_product`') === 0) {
                $lock = BASE_PATH . '/storage/cache/import.lock';
                $fh = fopen($lock, 'c');
                if ($fh && flock($fh, LOCK_EX | LOCK_NB)) {
                    @set_time_limit(300);
                    foreach (self::split(file_get_contents($dataFile)) as $stmt) $pdo->exec($stmt);
                    @rename($dataFile, $dataFile . '.imported');
                    log_error('Migrator: data imported from usvesta-data.sql');
                    flock($fh, LOCK_UN);
                }
            } elseif (is_file($dataFile)) {
                @rename($dataFile, $dataFile . '.skipped');   // store already has data – never overwrite it
            }
        }
        @file_put_contents($flag, gmdate('c'));
    }

    /** Split on ";" at line end (string literals in our dumps never contain raw newlines). */
    public static function split(string $sql): array
    {
        $out = [];
        foreach (preg_split('/;\s*\n/', $sql) as $s) {
            $s = trim(preg_replace('/^--.*$/m', '', $s));
            if ($s !== '') $out[] = $s;
        }
        return $out;
    }
}
