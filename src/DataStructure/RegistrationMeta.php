<?php

    namespace Exteon\Loader\ChainingClassResolver\DataStructure;

    class RegistrationMeta
    {
        private string $chainedClass;
        private bool $isIntermediate;
        private string $moduleName;

        /**
         * RegistrationMeta constructor.
         * @param string $moduleName
         * @param string $chainedClass
         * @param bool $isIntermediate
         */
        public function __construct(
            string $moduleName,
            string $chainedClass,
            bool $isIntermediate
        ) {
            $this->moduleName = $moduleName;
            $this->chainedClass = $chainedClass;
            $this->isIntermediate = $isIntermediate;
        }

        /**
         * @return string
         */
        public function getChainedClass(): string
        {
            return $this->chainedClass;
        }

        /**
         * @return bool
         */
        public function isIntermediate(): bool
        {
            return $this->isIntermediate;
        }

        /**
         * @return string
         */
        public function getModuleName(): string
        {
            return $this->moduleName;
        }
    }