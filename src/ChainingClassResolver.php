<?php

    namespace Exteon\Loader\ChainingClassResolver;

    use Exception;
    use Exteon\Loader\ChainingClassResolver\DataStructure\TargetClassFileSpec;
    use Exteon\Loader\ChainingClassResolver\DataStructure\TargetNSSpec;
    use Exteon\Loader\ChainingClassResolver\DataStructure\WeavedClass;
    use Exteon\Loader\MappingClassLoader\ClassResolver;
    use Exteon\Loader\MappingClassLoader\ClassScanner;
    use Exteon\Loader\MappingClassLoader\Data\LoadAction;
    use PhpParser\Lexer;
    use PhpParser\NodeTraverser;
    use PhpParser\NodeVisitor\NameResolver;
    use PhpParser\ParserFactory;
    use Exteon\ClassNameHelper;

    class ChainingClassResolver implements ClassResolver, ClassScanner, ClassTargetResolver
    {
        private string $targetNs;

        /** @var IModule[] */
        private array $modules;

        /** @var ClassTargetResolver[] */
        private array $classTargetResolvers;

        /**
         * ChainingClassResolver constructor.
         * @param string $targetNs
         * @param IModule[] $modules
         */
        public function __construct(
            array $modules,
            string $targetNs = ''
        ) {
            $this->targetNs = $targetNs;
            $this->modules = $modules;
            $this->classTargetResolvers = [$this];
        }

        /**
         * @param string $class
         * @return LoadAction[]
         * @throws Exception
         */
        public function resolveClass(string $class): array
        {
            $class = ClassNameHelper::trimNsLeading($class);
            $chain = $this->getChain($class);
            if (!empty($chain)) {
                $toLoad = [];
                $previousClassSpec = null;
                $weavedClass = null;
                $extends = [];
                $traits = [];
                $implements = [];
                foreach ($chain as $chainFileSpec) {
                    $weavedClass = $this->getWeavedClass(
                        file_get_contents($chainFileSpec->getPath()),
                        $chainFileSpec->getClassSpec(),
                        $previousClassSpec,
                        $chainFileSpec->getPath(),
                        $chainFileSpec->getModuleName()
                    );
                    $loadAction = new LoadAction(
                        $weavedClass->getClass(),
                        $chainFileSpec->getPath(),
                        $weavedClass->getSource()
                    );
                    $toLoad[] = $loadAction;
                    $previousClassSpec = $chainFileSpec->getClassSpec();
                    $extends = array_merge(
                        $extends,
                        $weavedClass->getCanonicalExtends()
                    );
                    $traits = array_merge(
                        $traits,
                        $weavedClass->getCanonicalTraits()
                    );
                    $implements = array_merge(
                        $implements,
                        $weavedClass->getCanonicalInterfaces()
                    );
                }
                $classDef = $this->getAggregateCode(
                    $previousClassSpec,
                    $weavedClass,
                    $extends,
                    $traits,
                    $implements
                );
                $classDef = "<?php\n$classDef";
                $hintCode = $this->getHintCode(
                    $previousClassSpec,
                    $weavedClass,
                    $extends,
                    $traits,
                    $implements
                );
                $hintCode = "<?php\n$hintCode";
                $loadAction = new LoadAction(
                    $previousClassSpec->getFullTargetClass(),
                    null,
                    $classDef,
                    $hintCode
                );
                $toLoad[] = $loadAction;
                return $toLoad;
            }
            return [];
        }

        /**
         * @param $source
         * @param TargetNSSpec $classSpec
         * @param TargetNSSpec|null $previousClassSpec
         * @param string $filePath
         * @param string $moduleName
         * @return WeavedClass
         */
        private function getWeavedClass(
            $source,
            TargetNSSpec $classSpec,
            ?TargetNSSpec $previousClassSpec,
            string $filePath,
            string $moduleName
        ): WeavedClass {
            if (
                extension_loaded('web3tracer') &&
                function_exists('web3tracer_tag')
            ) {
                web3tracer_tag('weave: ' . $filePath);
            }
            $lexer = new Lexer\Emulative(
                [
                    'usedAttributes' => [
                        'startFilePos',
                        'endFilePos',
                        'startLine',
                        'endLine'
                    ]
                ]
            );
            $parser = (new ParserFactory)->create(
                ParserFactory::ONLY_PHP7,
                $lexer
            );
            $ast = $parser->parse($source);
            $traverser = new NodeTraverser();
            $nameResolver = new NameResolver(
                null,
                [
                    'replaceNodes' => false
                ]
            );
            $traverser->addVisitor($nameResolver);
            $visitor = new ASTTransformerVisitor(
                $source,
                $classSpec,
                $previousClassSpec,
                $filePath,
                $this->classTargetResolvers,
                $moduleName
            );
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);
            return $visitor->getWeavedClass();
        }

        /**
         * @return string[]
         * @throws Exception
         */
        public function scanClasses(): array
        {
            $classes = [];
            foreach ($this->modules as $module) {
                if ($module instanceof ClassScanner) {
                    $moduleClasses = $module->scanClasses();
                    foreach ($moduleClasses as $class) {
                        $classFileSpec = $module->resolveClass($class);
                        $targetClassName = ClassNameHelper::joinNs(
                            $this->targetNs,
                            $classFileSpec->getClassSpec()->getNs(),
                            $classFileSpec->getClassSpec()->getClass()
                        );
                        $classes[$targetClassName] = null;
                    }
                }
            }
            return array_keys($classes);
        }

        /**
         * @param TargetNSSpec $previousClassSpec
         * @param WeavedClass $weavedClass
         * @param string[] $extends
         * @param string[] $traits
         * @param string[] $implements
         * @return string
         */
        private function getAggregateCode(
            TargetNSSpec $previousClassSpec,
            WeavedClass $weavedClass,
            array $extends,
            array $traits,
            array $implements
        ): string {
            $classDef = "    namespace " . $previousClassSpec->getFullTargetNs() .
                " {\n";
            if (
                $weavedClass->isClass() ||
                $weavedClass->isInterface()
            ) {
                if (
                    $extends ||
                    $implements
                ) {
                    $classDef .= "        /**\n";
                    foreach ($extends as $extend) {
                        $classDef .= "         * @mixin $extend\n";
                    }
                    foreach ($implements as $implement) {
                        $classDef .= "         * @implements $implement\n";
                    }
                    $classDef .= "         */\n";
                }
                $classDef .= "        ";
                if ($weavedClass->isAbstract()) {
                    $classDef .= 'abstract ';
                }
                if ($weavedClass->isFinal()) {
                    $classDef .= 'final ';
                }
                if ($weavedClass->isClass()) {
                    $classDef .= 'class ';
                } else {
                    $classDef .= 'interface ';
                }
                $classDef .= $previousClassSpec->getClass();
                $classDef .= ' extends ';
                $classDef .=
                    '\\' .
                    $previousClassSpec->getFullClass();
                if ($traits) {
                    $classDef .= "\n";
                    $classDef .= "        {\n";
                    $classDef .= "            /**\n";
                    foreach ($traits as $trait) {
                        $classDef .= "             * @use $trait\n";
                    }
                    $classDef .= "             */\n";
                    $classDef .= "        }\n";
                } else {
                    $classDef .= " {}\n";
                }
            } elseif ($weavedClass->isTrait()) {
                $classDef .= "        trait ";
                $classDef .= $previousClassSpec->getClass();
                $classDef .= "\n";
                $classDef .= "        {\n";
                if ($traits) {
                    $classDef .= "            /**\n";
                    foreach ($traits as $trait) {
                        $classDef .= "             * @use $trait\n";
                    }
                    $classDef .= "             */\n";
                }
                $classDef .=
                    "            use " .
                    "\\" .
                    $previousClassSpec->getFullClass() .
                    ";\n";
                $classDef .= "        }\n";
            }
            $classDef .= "    }\n";
            return $classDef;
        }

        /**
         * @param TargetNSSpec $previousClassSpec
         * @param WeavedClass $weavedClass
         * @param string[] $extends
         * @param string[] $traits
         * @param string[] $implements
         * @return string
         */
        private function getHintCode(
            TargetNSSpec $previousClassSpec,
            WeavedClass $weavedClass,
            array $extends,
            array $traits,
            array $implements
        ): string {
            return $this->getAggregateCode(
                $previousClassSpec,
                $weavedClass,
                $extends,
                $traits,
                $implements
            );
        }

        /**
         * Gets a chain of paths that make up a chaining class definition.
         * Returns paths in the order of modules (base first)
         *
         * @param string $class
         * @return TargetClassFileSpec[]
         * @throws Exception
         */
        private function getChain(string $class): array
        {
            $targetClassSpec = $this->resolveToTargetClassNsSpec($class);
            if (!$targetClassSpec) {
                return [];
            }
            $relativeClass = ClassNameHelper::joinNs(
                $targetClassSpec->getNs(),
                $targetClassSpec->getClass()
            );
            $chain = [];
            foreach ($this->modules as $module) {
                $classFileSpec = $module->resolveRelativeClass($relativeClass);
                if ($classFileSpec) {
                    $chain[] = new TargetClassFileSpec(
                        new TargetNSSpec(
                            $classFileSpec->getClassSpec()->getBaseNs(),
                            $this->targetNs,
                            $classFileSpec->getClassSpec()->getNs(),
                            $classFileSpec->getClassSpec()->getClass()
                        ),
                        $classFileSpec->getPath(),
                        $module->getName()
                    );
                }
            }
            return $chain;
        }

        /**
         * @param string $class
         * @return string|null
         * @throws Exception
         */
        public function getTargetClass(string $class): ?string
        {
            foreach ($this->modules as $module) {
                $resolved = $module->resolveClass($class);
                if ($resolved) {
                    return ClassNameHelper::joinNs(
                        $this->targetNs,
                        $resolved->getClassSpec()->getNs(),
                        $resolved->getClassSpec()->getClass()
                    );
                }
            }
            if (ClassNameHelper::isNsPrefix($this->targetNs, $class)) {
                $relativeClass = ClassNameHelper::stripNsPrefix($this->targetNs, $class);
                foreach ($this->modules as $module) {
                    $resolved = $module->resolveRelativeClass($relativeClass);
                    if ($resolved) {
                        return ClassNameHelper::joinNs(
                            $this->targetNs,
                            $resolved->getClassSpec()->getNs(),
                            $resolved->getClassSpec()->getClass()
                        );
                    }
                }
            }
            return null;
        }

        /**
         * @param string $class
         * @return TargetNSSpec|null
         * @throws Exception
         */
        private function resolveToTargetClassNsSpec(string $class
        ): ?TargetNSSpec {
            foreach ($this->modules as $module) {
                $classFileSpec = $module->resolveClass($class);
                if ($classFileSpec) {
                    return new TargetNSSpec(
                        $classFileSpec->getClassSpec()->getBaseNs(),
                        $this->targetNs,
                        $classFileSpec->getClassSpec()->getNs(),
                        $classFileSpec->getClassSpec()->getClass()
                    );
                }
            }
            if (ClassNameHelper::isNsPrefix($this->targetNs, $class)) {
                $relativeClass = ClassNameHelper::stripNsPrefix(
                    $this->targetNs,
                    $class
                );
                ['ns' => $ns, 'class' => $class] = ClassNameHelper::toNsClass(
                    $relativeClass
                );

                return new TargetNSSpec(null, $this->targetNs, $ns, $class);
            }
            return null;
        }

        /**
         * When using multiple chaining resolvers, or other class manipulation methods, one may need to set external
         * class target resolvers to modify classes in generated files.
         *
         * @param ClassTargetResolver[] $resolvers
         * @return void
         */
        public function setClassTargetResolvers(array $resolvers): void
        {
            $this->classTargetResolvers = [
                $this,
                ...array_filter(
                    $resolvers,
                    fn(ClassTargetResolver $resolver): bool => $resolver !== $this
                )
            ];
        }
    }