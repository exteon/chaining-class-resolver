<?php

    namespace Exteon\Loader\ChainingClassResolver\DataStructure;

    class WeavedClass
    {
        protected string $class;
        protected ?string $source;
        protected bool $isAbstract;
        protected bool $isFinal;
        protected bool $isTrait;
        protected bool $isInterface;
        protected bool $isClass;

        /** @var string[] */
        protected array $extends;

        /** @var string[] */
        protected array $canonicalTraits;

        /** @var string[] */
        protected array $canonicalInterfaces;

        /** @var string[] */
        protected array $canonicalExtends;

        /**
         * WeavedClass constructor.
         * @param string $class
         * @param string|null $source
         * @param bool $isAbstract
         * @param bool $isFinal
         * @param bool $isTrait
         * @param bool $isInterface
         * @param bool $isClass
         * @param string[] $canonicalTraits
         * @param string[] $canonicalInterfaces
         * @param array $canonicalExtends
         */
        function __construct(
            string $class,
            ?string $source,
            bool $isAbstract,
            bool $isFinal,
            bool $isTrait,
            bool $isInterface,
            bool $isClass,
            array $canonicalTraits,
            array $canonicalInterfaces,
            array $canonicalExtends
        ) {
            $this->class = $class;
            $this->source = $source;
            $this->isAbstract = $isAbstract;
            $this->isFinal = $isFinal;
            $this->isTrait = $isTrait;
            $this->isInterface = $isInterface;
            $this->isClass = $isClass;
            $this->canonicalTraits = $canonicalTraits;
            $this->canonicalInterfaces = $canonicalInterfaces;
            $this->canonicalExtends = $canonicalExtends;
        }

        /**
         * @return string
         */
        public function getClass(): string
        {
            return $this->class;
        }

        /**
         * @return string|null
         */
        public function getSource(): ?string
        {
            return $this->source;
        }

        /**
         * @return bool
         */
        public function isAbstract(): bool
        {
            return $this->isAbstract;
        }

        /**
         * @return bool
         */
        public function isFinal(): bool
        {
            return $this->isFinal;
        }

        /**
         * @return bool
         */
        public function isTrait(): bool
        {
            return $this->isTrait;
        }

        /**
         * @return bool
         */
        public function isInterface(): bool
        {
            return $this->isInterface;
        }

        /**
         * @return bool
         */
        public function isClass(): bool
        {
            return $this->isClass;
        }

        /**
         * @return string[]
         */
        public function getExtends(): array
        {
            return $this->extends;
        }

        /**
         * @return string[]
         */
        public function getCanonicalTraits(): array
        {
            return $this->canonicalTraits;
        }

        /**
         * @return string[]
         */
        public function getCanonicalInterfaces(): array
        {
            return $this->canonicalInterfaces;
        }

        /**
         * @return string[]
         */
        public function getCanonicalExtends(): array
        {
            return $this->canonicalExtends;
        }
    }