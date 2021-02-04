<?php

    namespace Exteon\Loader\ChainingClassResolver;

    use Exteon\Loader\ChainingClassResolver\DataStructure\ClassFileSpec;

    interface IClassFileResolver
    {
        public function resolveClass(string $class): ?ClassFileSpec;

        public function resolveRelativeClass(string $relativeClass): ?ClassFileSpec;
    }