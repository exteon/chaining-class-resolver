<?php

namespace Test\Exteon\Loader\ChainingClassResolver\Props\Module1;

class Foo
{
    public function whoami(): string
    {
        return 'Module1';
    }
}