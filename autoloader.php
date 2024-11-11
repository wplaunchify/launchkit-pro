<?php

/**
 * Autoload plugin classes from the `plugin` directory only, without namespace.
 */

spl_autoload_register(function ($class) {
    // Convert class name to filename format
    $filename = $class . '.php';

    // Define path for class files in the `plugin` directory
    $file_path = __DIR__ . '/plugin/' . $filename;

    // Load the class file if it exists
    if (file_exists($file_path)) {
        require_once $file_path;
    }
});