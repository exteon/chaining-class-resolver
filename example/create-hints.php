<?php

    use Exteon\FileHelper;

    require_once(__DIR__ . '/../vendor/autoload.php');
    require_once(__DIR__ . '/setup.inc.php');

    $hintDir = __DIR__ . '/dev/hints';
    FileHelper::rmDir($hintDir);
    FileHelper::preparePath($hintDir);
    $loader->dumpHintClasses($hintDir);