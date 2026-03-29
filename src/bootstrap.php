<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'ScadenzeFatture\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativePath = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relativePath) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

