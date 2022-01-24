<?php

    namespace Exteon\Loader\ChainingClassResolver\DataStructure;

    class ProcessedClassSpec
    {
        private string $_this;
        private ?string $parent;

        /**
         * ClassReplacementSpec constructor.
         * @param string $_this
         * @param string|null $parent
         */
        public function __construct(string $_this, ?string $parent)
        {
            $this->_this = $_this;
            $this->parent = $parent;
        }

        /**
         * @return string
         */
        public function getThis(): string
        {
            return $this->_this;
        }

        public function getParent(): ?string
        {
            return $this->parent;
        }
    }