<?php

    namespace Exteon\Loader\ChainingClassResolver\DataStructure;

    use Exteon\ClassNameHelper;

    class NSSpec
    {
        protected ?string $baseNs;
        protected string $ns;
        protected ?string $class;

        /**
         * NsSpec constructor.
         * @param string|null $baseNs
         * @param string $ns
         * @param string|null $class
         */
        public function __construct(
            ?string $baseNs,
            string $ns,
            ?string $class = null
        ) {
            $this->baseNs = $baseNs;
            $this->ns = $ns;
            $this->class = $class;
        }

        /**
         * @return string
         */
        public function getFullNs(): string
        {
            return ClassNameHelper::joinNs($this->baseNs, $this->ns);
        }

        /**
         * @return string
         */
        public function getFullClass(): string
        {
            return ClassNameHelper::joinNs($this->baseNs, $this->ns,
                $this->class);
        }

        /**
         * @return string|null
         */
        public function getBaseNs(): ?string
        {
            return $this->baseNs;
        }

        /**
         * @return string
         */
        public function getNs(): string
        {
            return $this->ns;
        }

        /**
         * @return string
         */
        public function getClass(): string
        {
            return $this->class;
        }
    }