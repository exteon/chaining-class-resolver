<?php

    namespace Exteon\Loader\ChainingClassResolver\DataStructure;

    class ClassFileSpec
    {
        protected NSSpec $classSpec;
        protected string $path;

        /**
         * ChainFileSpec constructor.
         * @param NSSpec $classSpec
         * @param string $path
         */
        public function __construct(
            NSSpec $classSpec,
            string $path
        ) {
            $this->classSpec = $classSpec;
            $this->path = $path;
        }

        /**
         * @return NSSpec
         */
        public function getClassSpec(): NSSpec
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
    }