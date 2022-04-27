<?php

    namespace Test\Exteon\Loader\ChainingClassResolver\Props\Multi;

    use Test\Exteon\Loader\ChainingClassResolver\Props\Module1\Foo;

    class Chain2 {
        public function getFoo(): Foo {
            return new Foo();
        }
    }