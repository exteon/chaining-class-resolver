<?php

    namespace Test\Exteon\Loader\ChainingClassResolver\Props\Module3;

    class Foo extends
        \Test\Exteon\Loader\ChainingClassResolver\Props\Module1\Foo
    {
        public function whoami(): string
        {
            return parent::whoami() . '+Module3';
        }
    }