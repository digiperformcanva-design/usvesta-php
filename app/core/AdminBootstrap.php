<?php
/**
 * One-time admin password reset for the PHP migration.
 * Applies ONLY while the "admin" account still has its legacy Django (pbkdf2) hash,
 * so once the password has been changed or used, this never runs again.
 */
final class AdminBootstrap
{
    private const NEW_HASH = 'phpbcrypt$$2y$10$jeIZOloZcBtIWTr0wpcureN9xNbOB72WKIxcQKv4i2vcDKoGw9Tga';

    public static function run(): void
    {
        $flag = BASE_PATH . '/storage/cache/admin_bootstrap_done';
        if (is_file($flag)) return;
        try {
            $n = DB::query("UPDATE `cloth_app_customuser` SET `password` = ?, `is_active` = 1, `is_staff` = 1, `is_superuser` = 1
                            WHERE `username` = 'admin' AND `password` LIKE 'pbkdf2_sha256$%'", [self::NEW_HASH])->rowCount();
            if ($n > 0) {
                foreach (glob(BASE_PATH . '/storage/cache/login_*.json') ?: [] as $f) @unlink($f);
                log_error('AdminBootstrap: admin password reset');
            }
            @file_put_contents($flag, gmdate('c'));
        } catch (Throwable $e) {
            log_error('AdminBootstrap: ' . $e->getMessage());
        }
    }
}
