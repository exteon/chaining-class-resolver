<?php

    namespace Test\Exteon\Loader\ChainingClassResolver\Props\Module3;

    trait Trait2
    {
        use \Test\Exteon\Loader\ChainingClassResolver\Props\Module2\Trait2;
    }