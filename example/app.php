<?php
    require_once(__DIR__ . '/../vendor/autoload.php');
    require_once(__DIR__.'/setup.inc.php');

    // Use the classes chained to Target\

    use Target\A;
    use Target\B;

    $a = new A();
    $b = new B();

    var_dump($a->whoami());
    var_dump($b->whoami());
