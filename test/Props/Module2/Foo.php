<?php

namespace Test\Exteon\Loader\ChainingClassResolver\Props\Module2;

class Foo extends \Test\Exteon\Loader\ChainingClassResolver\Props\Module1\Foo
{
    use Trait1, Trait2;

    public function whoami(): string
    {
        return parent::whoami() . '+Module2';
    }
}