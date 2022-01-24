<?php

    namespace Exteon\Loader\ChainingClassResolver;

    use Exteon\ClassMeta;
    use Exteon\Loader\ChainingClassResolver\DataStructure\RegistrationMeta;
    use InvalidArgumentException;
    use JetBrains\PhpStorm\Pure;
    use ReflectionException;
    use SplObjectStorage;

    class ChainedClassMeta
    {
        public const CLASS_CONST_FILE = 'CCR_FILE';
        public const CLASS_CONST_DIRECTORY = 'CCR_DIR';
        public const CLASS_CONST_PARENT = 'CCR_PARENT_CLASS';

        /** @var array<string,static> */
        protected static array $instances;

        /**  @var string */
        protected string $className;

        /**  @var ClassMeta */
        protected ClassMeta $classMeta;

        protected RegistrationMeta $registrationMeta;
        protected bool $isChainTraitsInit = false;

        /** @var static[]|null */
        protected ?array $chainTraits;

        protected bool $isChainInterfacesInit;

        /** @var static[]|null */
        protected ?array $chainInterfaces;

        /** @var array<class-string,bool> */
        protected array $hasChainTrait;

        protected function __construct(string $className)
        {
            $this->className = $className;
            $this->classMeta = ClassMeta::get($className);
        }

        /**
         * @param RegistrationMeta $registrationMeta
         */
        public function setRegistrationMeta(RegistrationMeta $registrationMeta)
        {
            $this->registrationMeta = $registrationMeta;
        }

        /**
         * @return string
         */
        public function getClassName(): string
        {
            return $this->className;
        }

        public function isChained(): bool
        {
            return (bool)$this->getChainedClass();
        }

        /**
         * @return static|null
         */
        public function getChainParent(): ?self
        {
            $constName = $this->getClassName() . "::" .
                self::CLASS_CONST_PARENT;
            if (defined($constName)) {
                return static::get(constant($constName));
            }
            return null;
        }

        /**
         * @return static|null
         */
        public function getChainedClass(): ?self
        {
            if (isset($this->registrationMeta)) {
                return static::get($this->registrationMeta->getChainedClass());
            }
            return null;
        }

        /**
         * @return string|null
         */
        #[Pure]
        public function getModuleName(): ?string
        {
            if (isset($this->registrationMeta)) {
                return $this->registrationMeta->getModuleName();
            }
            return null;
        }

        /**
         * @return ClassMeta
         */
        public function getClassMeta(): ClassMeta
        {
            return $this->classMeta;
        }

        /**
         * @throws ReflectionException
         */
        protected function initChainTraits(): void
        {
            if ($this->isChainTraitsInit) {
                return;
            }
            if ($this->getClassMeta()->isInterface()) {
                $chainTraits = null;
                $hasChainTrait = null;
            } else {
                $current = $this;
                $traitsFlipped = new SplObjectStorage();
                $chain = $this->getChainedClass() ?? $this;
                do {
                    foreach (
                        array_map(
                            function ($trait) {
                                return static::get($trait->getClassName());
                            },
                            $current->getClassMeta()->getTraits()
                        )
                        as $trait
                    ) {
                        if ($trait->getChainedClass() !== $chain) {
                            $traitsFlipped[$trait] = null;
                        }
                        foreach($trait->getChainTraits() as $chainTrait){
                            $traitsFlipped[$chainTrait] = null;
                        }
                    }
                    $current = $current->getClassMeta()->getParent() ?
                        static::get(
                            $current->getClassMeta()->getParent()->getClassName()
                        )
                        :
                        null;
                } while (
                    $current &&
                    $current->getChainedClass() === $chain
                );
                $chainTraits = [];
                $hasChainTrait = [];
                /** @var static $trait */
                foreach ($traitsFlipped as $trait) {
                    $chainTraits[] = $trait;
                    $hasChainTrait[$trait->getClassName()] = null;
                }
            }
            $this->chainTraits = $chainTraits;
            $this->hasChainTrait = $hasChainTrait;
            $this->isChainTraitsInit = true;
        }

        /**
         * @return static[]|null
         * @throws ReflectionException
         */
        public function getChainTraits(): ?array
        {
            $this->initChainTraits();
            return $this->chainTraits;
        }

        /**
         * @param $trait
         * @return bool
         * @throws ReflectionException
         */
        public function hasChainTrait($trait): bool
        {
            $invalidArgument = false;
            $traitName = null;
            if ($trait instanceof self) {
                if (!$trait->getClassMeta()->isTrait()) {
                    $invalidArgument = true;
                } else {
                    $traitName = $trait->getClassName();
                }
            } elseif($trait instanceof ClassMeta){
                if (!$trait->isTrait()) {
                    $invalidArgument = true;
                } else {
                    $traitName = $trait->getClassName();
                }
            } elseif (is_string($trait)) {
                $traitName = $trait;
            } else {
                $invalidArgument = true;
            }
            if ($invalidArgument) {
                throw new InvalidArgumentException(
                    'Trait-type ClassMeta object or string expected'
                );
            }
            $this->initChainTraits();
            return array_key_exists($traitName, $this->hasChainTrait);

        }

        /**
         * @throws ReflectionException
         */
        protected function initChainInterfaces(): void
        {
            if ($this->isChainInterfacesInit) {
                return;
            }
            if ($this->getClassMeta()->isTrait()) {
                $this->chainInterfaces = null;
            } else {
                $parentInterfaces = $this->getChainParent() ?
                    array_map(
                        function ($interface) {
                            return static::get($interface->getClassName());
                        },
                        $this->getChainParent()->getClassMeta()->getInterfaces()
                    )
                    :
                    [];
                $parentInterfacesFlipped = new SplObjectStorage();
                foreach ($parentInterfaces as $parentInterface) {
                    $parentInterfacesFlipped[$parentInterface] = null;
                }
                $interfaces = array_map(
                    function ($interface) {
                        return static::get($interface->getClassName());
                    },
                    $this->getClassMeta()->getInterfaces()
                );
                $interfaceAncestryFlipped = new SplObjectStorage();
                foreach ($interfaces as $interface) {
                    foreach (
                        array_map(
                            function ($interface) {
                                return static::get($interface->getClassName());
                            },
                            $interface->getClassMeta()->getInterfaces()
                        )
                        as $interfaceAncestor
                    ) {
                        $interfaceAncestryFlipped[$interfaceAncestor] = null;
                    }
                }

                $chain = $this->getChainedClass() ?? $this;
                $this->chainInterfaces = array_filter(
                    $interfaces,
                    function ($interface) use (
                        $parentInterfacesFlipped,
                        $interfaceAncestryFlipped,
                        $chain
                    ) {
                        return (
                            !$parentInterfacesFlipped->offsetExists(
                                $interface
                            ) &&
                            !$interfaceAncestryFlipped->offsetExists(
                                $interface
                            ) &&
                            $interface->getChainedClass() !== $chain
                        );
                    }
                );
            }
            $this->isChainInterfacesInit = true;
        }

        /**
         * @return static[]|null
         * @throws ReflectionException
         */
        public function getChainInterfaces(): ?array
        {
            $head = $this->getChainedClass() ?? $this;
            $head->initChainInterfaces();
            return $head->chainInterfaces;
        }

        /**
         * @param string $className
         * @return static
         */
        public static function get(string $className): self
        {
            if (isset(self::$instances[$className])) {
                return self::$instances[$className];
            }
            $what = static::class;
            $instance = new $what($className);
            self::$instances[$className] = $instance;
            return $instance;
        }
    }