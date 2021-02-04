<?php

    namespace Exteon\Loader\ChainingClassResolver\DataStructure;

    class ProcessedClassSpec
    {

        /**
         * @var string
         */
        protected $_this;

        /**
         * @var string|null
         */
        protected $parent;

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

        /**
         * @return string
         */
        public function getParent(): ?string
        {
            return $this->parent;
        }
    }