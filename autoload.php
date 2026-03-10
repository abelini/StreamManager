<?php
/**
 * autoload.php — Autoloader PSR-4 sin Composer
 *
 * Namespace raíz: Stream\  →  src/
 * PHP 8.4: first-class callable syntax en spl_autoload_register
 */

declare(strict_types=1);

/** @param non-empty-string $class */
$streamAutoloader = static function (string $class): void {
    $prefix = 'Stream\\';
    $base   = __DIR__ . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
};

// PHP 8.1+: first-class callable syntax
spl_autoload_register($streamAutoloader, prepend: true);
