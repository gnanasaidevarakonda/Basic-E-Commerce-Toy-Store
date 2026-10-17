<?php

namespace App\Core;

class Autoloader
{
    // Register the custom PSR-4 autoloader with PHP

    public static function register(): void
    {
        spl_autoload_register(function (string $class): void {
            // Our project namespace prefix
            $prefix = 'App\\';

            //Base folder where 'App\' maps to on disk (the src/ directory)
            $baseDir = dirname(__DIR__) . '/';

            // Check if the class uses our 'App\' namespace
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                // If not, don't handle it
                return;
            }

            // Get the relative class name(eg. "Models\Product")
            $relativeClass = substr($class, $len);

            //Replace backslashes with directory slashes and add.php
            //e.g. "Models/Product.php"
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            //If the file exists, load it into memory
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
