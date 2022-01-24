<?php

    namespace Exteon\Loader\ChainingClassResolver\DataStructure;

    class TargetClassFileSpec {
        protected TargetNSSpec $classSpec;
        protected string $path;
        protected string $moduleName;

        public function __construct(
            TargetNSSpec $classSpec,
            string $path,
            string $moduleName
        ) {
            $this->classSpec = $classSpec;
            $this->path = $path;
            $this->moduleName = $moduleName;
        }

        /**
         * @return TargetNSSpec
         */
        public function getClassSpec(): TargetNSSpec
        {
            return $this->classSpec;
        }

        /**
         * @return string
         */
        public function getPath(): string
        {
            return $this->path;
        }

        /**
         * @return string
         */
        public function getModuleName(): string
        {
            return $this->moduleName;
        }
    }