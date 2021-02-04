<?php

    namespace Exteon\Loader\ChainingClassResolver\ClassFileResolver;

    use Exception;
    use Exteon\ClassNameHelper;
    use Exteon\FileHelper;
    use Exteon\Loader\ChainingClassResolver\DataStructure\ClassFileSpec;
    use Exteon\Loader\ChainingClassResolver\DataStructure\NSSpec;
    use Exteon\Loader\ChainingClassResolver\IClassFileResolver;
    use Exteon\Loader\MappingClassLoader\IClassScanner;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    class PSR4ClassFileResolver implements IClassFileResolver, IClassScanner
    {
        const PHP_FILE_SUFFIX = '.php';

        /** @var string */
        protected $baseNs;

        /** @var string */
        private $basePath;

        public function __construct(string $basePath, string $baseNs)
        {
            $this->basePath = $basePath;
            $this->baseNs = $baseNs;
        }

        public function scanClasses(): array
        {
            $classes = [];
            $directoryIterator = new RecursiveDirectoryIterator(
                $this->basePath
            );
            $iterator = new RecursiveIteratorIterator($directoryIterator);
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    /**
                     * PHP black magic here
                     * @noinspection PhpUndefinedMethodInspection
                     */
                    $fn = $iterator->getSubPathname();
                    $pathFrags = explode('/', $fn);
                    $match = null;
                    preg_match(
                        '`(.*)\\.php$`',
                        array_pop($pathFrags),
                        $match
                    );
                    $className = $match[1];
                    array_push($pathFrags, $className);
                    $classes[ClassNameHelper::joinNs(...$pathFrags)] = null;
                }
            }
            return array_map(
                function ($c) {
                    return ClassNameHelper::joinNs($this->baseNs, $c);
                },
                array_keys($classes)
            );
        }

        /**
         * @param string $class
         * @return ClassFileSpec|null
         * @throws Exception
         */
        public function resolveClass(
            string $class
        ): ?ClassFileSpec {
            if (!ClassNameHelper::isNsPrefix($this->baseNs, $class)) {
                return null;
            }
            $classRelativeNsName = ClassNameHelper::stripNsPrefix(
                $this->baseNs,
                $class
            );
            $nsSpec = $this->getNSSpecFromRelativeClass($classRelativeNsName);

            return $this->resolveFromNsSpec($nsSpec);
        }

        /**
         * @param string $relativeClass
         * @return ClassFileSpec|null
         * @throws Exception
         */
        public function resolveRelativeClass(
            string $relativeClass
        ): ?ClassFileSpec {
            return $this->resolveFromNsSpec(
                $this->getNSSpecFromRelativeClass($relativeClass)
            );
        }

        /**
         * @param NSSpec $nsSpec
         * @return ClassFileSpec|null
         */
        protected function resolveFromNsSpec(
            NSSpec $nsSpec
        ): ?ClassFileSpec {
            $fileName = $nsSpec->getClass() . self::PHP_FILE_SUFFIX;
            $path = $this->basePath;
            foreach (
                ClassNameHelper::nsToFragments(
                    $nsSpec->getNs()
                ) as $fragment
            ) {
                $path = FileHelper::getDescendPath($path, $fragment);
            }
            $path = FileHelper::getDescendPath($path, $fileName);
            if (file_exists($path)) {
                return new ClassFileSpec(
                    $nsSpec,
                    $path
                );
            }
            return null;
        }

        /**
         * @param string $classRelativeNsName
         * @return NSSpec
         */
        protected function getNSSpecFromRelativeClass(
            string $classRelativeNsName
        ): NSSpec {
            [
                'class' => $className,
                'ns' => $classRelativeNs
            ] = ClassNameHelper::toNsClass($classRelativeNsName);
            return new NSSpec(
                $this->baseNs,
                $classRelativeNs,
                $className
            );
        }
    }