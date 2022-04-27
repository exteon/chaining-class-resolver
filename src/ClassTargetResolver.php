<?php

    namespace Exteon\Loader\ChainingClassResolver;

    use Exception;

    interface ClassTargetResolver
    {
        /**
         * @param string $class
         * @return string|null
         * @throws Exception
         */
        public function getTargetClass(string $class): ?string;
    }