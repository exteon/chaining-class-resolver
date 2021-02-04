<?php

    namespace Exteon\Loader\ChainingClassResolver;

    use Exception;
    use Exteon\Loader\ChainingClassResolver\DataStructure\ClassFileSpec;

    interface IModule
    {
        /**
         * @return string
         */
        public function getName(): string;

        /**
         * @param string $classNs
         * @return ClassFileSpec|null
         * @throws Exception
         */
        public function resolveClass(string $classNs): ?ClassFileSpec;

        /**
         * @param string $relativeClass
         * @return ClassFileSpec|null
         * @throws Exception
         */
        public function resolveRelativeClass(
            string $relativeClass
        ): ?ClassFileSpec;
    }