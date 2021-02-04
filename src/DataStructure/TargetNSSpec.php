<?php

    namespace Exteon\Loader\ChainingClassResolver\DataStructure;

    use Exteon\ClassNameHelper;

    class TargetNSSpec
    {
        /** @var string|null */
        protected $baseNs;

        /** @var string */
        protected $ns;

        /** @var string|null */
        protected $class;

        /** @var string */
        protected $targetNs;

        public function __construct(
            ?string $baseNs,
            string $targetNs,
            string $ns,
            ?string $class = null
        ) {
            $this->baseNs = $baseNs;
            $this->targetNs = $targetNs;
            $this->ns = $ns;
            $this->class = $class;
        }

        /**
         * @return string
         */
        public function getFullTargetClass(): string
        {
            return ClassNameHelper::joinNs(
                $this->targetNs,
                $this->ns,
                $this->class
            );
        }

        /**
         * @return string
         */
        public function getTargetNs(): string
        {
            return $this->targetNs;
        }

        /**
         * @return string
         */
        public function getFullTargetNs(): string
        {
            return ClassNameHelper::joinNs($this->targetNs, $this->ns);
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