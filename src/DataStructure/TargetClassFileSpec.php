<?php

    namespace Exteon\Loader\ChainingClassResolver\DataStructure;

    class TargetClassFileSpec {
        /** @var NSSpec */
        protected $classSpec;

        /** @var string */
        protected $path;

        /** @var string */
        protected $moduleName;

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