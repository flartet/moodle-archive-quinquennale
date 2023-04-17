<?php

class archive_fileutils {

    public static $UMASK_DIRECTORIES = 0775;
    public static $UMASK_FILES = 0664;

    public static function ensureDirectory($dir) {
        if (!is_dir($dir)) {
            return mkdir($dir, self::$UMASK_DIRECTORIES, true);
        }
        return true;
    }

    public static function writeFile($dir, $filename, $contents, $log=false) {
        self::ensureDirectory($dir);
        if ($log) echo "\tÉcriture de (".$dir.DIRECTORY_SEPARATOR.$filename.")\n";
        $retval = file_put_contents($dir.DIRECTORY_SEPARATOR.$filename, $contents);
        if ($retval !== false) chmod($dir.DIRECTORY_SEPARATOR.$filename, self::$UMASK_FILES);
        return $retval;
    }

    public static function getExtension($filename) {
        $matches = [];
        if (preg_match('/^.*\.([a-zA-Z0-9]+)$/', $filename, $matches) > 0) {
            return $matches[1];
        }
        return '';
    }

}