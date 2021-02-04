<?php

    namespace Exteon\Loader\ChainingClassResolver;

    use Exception;
    use Exteon\Loader\ChainingClassResolver\DataStructure\ClassFileSpec;
    use Exteon\Loader\MappingClassLoader\IClassScanner;

    class Module implements IModule, IClassScanner
    {
        /**
         * @var string
         */
        protected $name;

        /**
         * @var IClassFileResolver[]
         */
        protected $classFileResolvers;

        /**
         * Module constructor.
         * @param string $name
         * @param IClassFileResolver[] $classFileResolvers
         */
        public function __construct(
            string $name,
            array $classFileResolvers
        ) {
            $this->name = $name;
            $this->classFileResolvers = $classFileResolvers;
        }

        /**
         * @return string
         */
        public function getName(): string
        {
            return $this->name;
        }

        /**
         * @param string $classNs
         * @return ClassFileSpec|null
         * @throws Exception
         */
        public function resolveClass(string $classNs): ?ClassFileSpec
        {
            $classFileSpec = null;
            foreach ($this->classFileResolvers as $fileResolver) {
                $resolved = $fileResolver->resolveClass($classNs);
                if ($resolved) {
                    if ($classFileSpec) {
                        throw new Exception('Ambiguous resolution of class');
                    }
                    $classFileSpec = $resolved;
                }
            }
            return $classFileSpec;
        }

        /**
         * @param string $relativeClass
         * @return ClassFileSpec|null
         * @throws Exception
         */
        public function resolveRelativeClass(string $relativeClass): ?ClassFileSpec
        {
            $classFileSpec = null;
            foreach ($this->classFileResolvers as $fileResolver) {
                $resolved = $fileResolver->resolveRelativeClass($relativeClass);
                if ($resolved) {
                    if ($classFileSpec) {
                        throw new Exception('Ambiguous resolution of class');
                    }
                    $classFileSpec = $resolved;
                }
            }
            return $classFileSpec;
        }

        public function scanClasses(): array
        {
            $classes = [];
            foreach ($this->classFileResolvers as $fileResolver) {
                if ($fileResolver instanceof IClassScanner) {
                    $classes = array_merge(
                        $classes,
                        array_flip($fileResolver->scanClasses())
                    );
                }
            }
            return array_keys($classes);
        }
    }