<?php

namespace Test\Exteon\Loader\ChainingClassResolver\Props\Module2;

trait Trait2
{
    use Trait3;

    public function whoami(): string
    {
        return parent::whoami() . '+Trait2';
    }
}