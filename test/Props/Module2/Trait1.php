<?php

namespace Test\Exteon\Loader\ChainingClassResolver\Props\Module2;

trait Trait1
{
    public function whoami(): string
    {
        return parent::whoami() . '+Trait1';
    }
}