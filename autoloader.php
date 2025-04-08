<?php

/*
 * Autoload function registration:
 * Registers a function to automatically load classes when they are not already included.
 * This code converts class names with namespaces into file paths to automatically load them.
 * @url: https://zaemis.blogspot.com/2012/05/writing-minimal-psr-0-autoloader.html
 * @author: Timothy Boronczyk https://zaemis.blogspot.com/
 */
spl_autoload_register(function ($classname) {

    // Removes the leading backslash (\) if it exists.
    // This is useful if the class is called with an absolute name (e.g., "\Namespace\ClassName").
    $classname = ltrim($classname, "\\");

    /*
     * Uses regular expressions to separate the namespace part and the class name.
     * - The pattern '/^(.+)?([^\\\\]+)$/U' captures everything before the class name (if any) and the class name itself.
     * - The captured parts are stored in the `$match` array.
     */
    preg_match('/^(.+)?([^\\\\]+)$/U', $classname, $match);

    // Replaces backslashes (\) in the namespace with slashes (/) to create file paths.
    // It also replaces underscores (_) with slashes to ensure compatibility with file paths.
    $classname = str_replace("\\", "/", $match[1])
        . str_replace(["\\", "_"], "/", $match[2])
        . ".php"; // Appends the ".php" extension at the end.

    /*
     * Finally, includes the corresponding file that contains the class.
     * It looks in the "src" directory and builds the final path using `DIRECTORY_SEPARATOR` for better compatibility across operating systems.
     */
    include_once "src" . DIRECTORY_SEPARATOR . $classname;
});