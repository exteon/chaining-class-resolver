<?php

    namespace Exteon\Loader\ChainingClassResolver;

    use ErrorException;
    use Exception;
    use Exteon\FileHelper;
    use Exteon\Loader\ChainingClassResolver\DataStructure\NSSpec;
    use Exteon\Loader\ChainingClassResolver\DataStructure\ProcessedClassSpec;
    use Exteon\Loader\ChainingClassResolver\DataStructure\RegistrationMeta;
    use Exteon\Loader\ChainingClassResolver\DataStructure\TargetNSSpec;
    use PhpParser\Node;
    use PhpParser\Node\Name;
    use PhpParser\Node\Name\FullyQualified;
    use PhpParser\Node\Stmt\Class_;
    use PhpParser\Node\Stmt\Interface_;
    use PhpParser\Node\Stmt\Trait_;
    use PhpParser\Node\Stmt\TraitUse;
    use PhpParser\NodeAbstract;
    use PhpParser\NodeVisitorAbstract;
    use Exteon\ClassNameHelper;

    class ASTTransformerVisitor extends NodeVisitorAbstract
    {
        protected const INTERMEDIATE_CLASS_SUFFIX = 'ccr_inter';

        /** @var NSSpec */
        protected $classSpec;

        /** @var NSSpec */
        protected $previousClassSpec;

        /** @var string|null */
        protected $filePath;

        /**@var ChainingClassResolver */
        protected $resolver;

        /** @var Class_|Interface_|Trait_ */
        protected $classNode;

        /** @var TraitUse[] */
        protected $traits = [];

        /** @var string */
        protected $source;

        /** @var string */
        protected $initialSource;

        /** @var array */
        protected $positionCoresp = [];

        /** @var bool */
        protected $isAbstract = false;

        /** @var bool */
        protected $isFinal = false;

        /**@var bool */
        protected $isTrait = false;

        /**@var bool */
        protected $isClass = false;

        /** @var bool */
        protected $isInterface = false;

        /** @var string */
        protected $moduleName;

        /** @var bool */
        protected $doTraitExtend;

        /** @var string[] */
        protected $canonicalTraits = [];

        /** @var string[] */
        protected $canonicalInterfaces = [];

        /**  @var string[] */
        protected $canonicalExtends = [];

        /**
         * ChainingVisitor constructor.
         * @param $source
         * @param TargetNSSpec $classSpec
         * @param TargetNSSpec|null $previousClassSpec
         * @param string|null $filePath
         * @param ChainingClassResolver $resolver
         * @param string $moduleName
         */
        public function __construct(
            $source,
            TargetNSSpec $classSpec,
            ?TargetNSSpec $previousClassSpec,
            ?string $filePath,
            ChainingClassResolver $resolver,
            string $moduleName
        ) {
            $this->source = $source;
            $this->initialSource = $source;
            $this->classSpec = $classSpec;
            $this->previousClassSpec = $previousClassSpec;
            $this->filePath = $filePath;
            $this->resolver = $resolver;
            $this->moduleName = $moduleName;
        }

        /**
         * @param Node $node
         * @throws ErrorException
         */
        public function enterNode(Node $node): void
        {
            if (
                $node instanceof Class_ ||
                $node instanceof Interface_ ||
                $node instanceof Trait_
            ) {
                /** @var Class_ $node */
                $classNs = $node->namespacedName->__toString();
                if ($classNs !== $this->classSpec->getFullClass()) {
                    throw new ErrorException(
                        "Defined class $node->name -> $classNs is not the expected {$this->classSpec->getFullClass()}" .
                        $this->fileErr(
                            $node
                        )
                    );
                }
                $this->classNode = $node;
            }
            if (
                $node instanceof TraitUse &&
                $this->classNode instanceof Trait_
            ) {
                $this->doTraitExtend = true;
            }
        }

        /**
         * @param Node $node
         * @return void|null
         * @throws ErrorException
         * @throws Exception
         */
        public function leaveNode(Node $node): void
        {
            if ($node instanceof Interface_) {
                /** @var Interface_ $node */
                if ($node->extends) {
                    foreach ($node->extends as $extend) {
                        $replacement = $this->processClassName(
                            $extend,
                            true,
                            true
                        );
                        $extend = ClassNameHelper::addNsLeading(
                            $replacement->getThis()
                        );
                        $this->canonicalExtends[] = $extend;
                    }
                }
                $this->addAfter(
                    $node,
                    (
                        " \\" . ChainedClassMeta::class .
                        "::get(" .
                        var_export($this->classSpec->getFullClass(), true) .
                        ")->" .
                        "setRegistrationMeta(" .
                        "new \\" . RegistrationMeta::class . "(" .
                        var_export($this->moduleName, true) . "," .
                        var_export(
                            $this->classSpec->getFullTargetClass(),
                            true
                        ) . "," .
                        var_export(false, true) .
                        ")" .
                        ");"
                    )
                );
                $this->isInterface = true;
            }
            if ($node instanceof Class_) {
                /** @var Class_ $node */
                if ($node->isFinal()) {
                    $this->isFinal = true;
                    $this->removeClassQualifier($node, 'final');
                }
                if ($node->isAbstract()) {
                    $this->isAbstract = true;
                }
                if ($node->extends) {
                    $replacement = $this->processClassName(
                        $node->extends,
                        true,
                        true
                    );
                    $extend = ClassNameHelper::addNsLeading(
                        $replacement->getThis()
                    );
                    $parent = $replacement->getParent();
                    $this->canonicalExtends[] = $extend;
                } else {
                    $extend = null;
                    $parent = null;
                }
                if ($this->traits) {
                    $className = $node->name->__toString();
                    $seq = 0;
                    $fragments = [];
                    foreach ($this->traits as $trait) {
                        foreach ($trait->traits as $traitName) {
                            $seq++;
                            [
                                'traitNs' => $traitNs,
                                'intermediate' => $intermediate
                            ] = $this->addTrait(
                                $traitName,
                                $className,
                                $seq,
                                $node
                            );
                            $fragment = "abstract class {$intermediate}";
                            if ($extend) {
                                $fragment .= " extends " . $extend;
                            }
                            $fragment .= " { ";
                            $fragment .=
                                "use " .
                                ClassNameHelper::addNsLeading(
                                    $traitNs
                                ) .
                                "; ";
                            $fragment .= " }; ";
                            $extend = $intermediate;
                            $fragments[] = $fragment;
                            $this->canonicalTraits[] =
                                ClassNameHelper::addNsLeading(
                                    $traitNs
                                );
                        }
                    }
                    $block = "";
                    $block .= implode('', $fragments);
                    $this->addBefore($node, $block);
                    if ($node->extends) {
                        $this->replace($node->extends, $extend . ' ');
                    } else {
                        $this->replace(
                            $node->name,
                            $node->name . ' extends ' . $extend . ' '
                        );
                    }
                }
                $consts = ' ';
                if ($parent !== null) {
                    $consts .=
                        "const " .
                        ChainedClassMeta::CLASS_CONST_PARENT .
                        "='" .
                        addcslashes(
                            $parent,
                            "'\\"
                        ) . "'; ";
                }
                if ($this->filePath) {
                    $consts .=
                        "const " .
                        ChainedClassMeta::CLASS_CONST_FILE .
                        "='" .
                        addcslashes(
                            $this->filePath,
                            "'\\"
                        ) . "'; ";
                    $consts .=
                        "const " .
                        ChainedClassMeta::CLASS_CONST_DIRECTORY .
                        "='" .
                        addcslashes(
                            FileHelper::getAscendPath($this->filePath),
                            "'\\"
                        ) . "'; ";
                }
                $this->addInBlock($node, $consts);
                $this->addAfter(
                    $node,
                    (
                        " \\" . ChainedClassMeta::class .
                        "::get(" .
                        var_export($this->classSpec->getFullClass(), true) .
                        ")->" .
                        "setRegistrationMeta(" .
                        "new \\" . RegistrationMeta::class . "(" .
                        var_export($this->moduleName, true) . "," .
                        var_export(
                            $this->classSpec->getFullTargetClass(),
                            true
                        ) . "," .
                        var_export(false, true) .
                        ")" .
                        ");"
                    )
                );
                $this->traits = [];
                $this->isClass = true;
                foreach ($node->implements as $implement) {
                    $replacement = $this->processClassName(
                        $implement,
                        false
                    );
                    $name = ClassNameHelper::addNsLeading(
                        $replacement->getThis()
                    );
                    $this->canonicalExtends[] = $name;
                }
            }
            if ($node instanceof Trait_) {
                if ($this->traits) {
                    foreach ($this->traits as $trait) {
                        foreach ($trait->traits as $traitName) {
                            $replacement = $this->processClassName(
                                $traitName,
                                false,
                                true
                            );
                            $name = ClassNameHelper::addNsLeading(
                                $replacement->getThis()
                            );
                            $this->canonicalTraits[] = $name;
                        }
                    }
                }
                $this->addAfter(
                    $node,
                    (
                        " \\" . ChainedClassMeta::class .
                        "::get(" .
                        var_export($this->classSpec->getFullClass(), true) .
                        ")->" .
                        "setRegistrationMeta(" .
                        "new \\" . RegistrationMeta::class . "(" .
                        var_export($this->moduleName, true) . "," .
                        var_export(
                            $this->classSpec->getFullTargetClass(),
                            true
                        ) . "," .
                        var_export(false, true) .
                        ")" .
                        ");"

                    )
                );
                $this->isTrait = true;
            }
            if (
                $node instanceof TraitUse
            ) {
                $this->traits[] = $node;
                if ($this->classNode instanceof Class_) {
                    $this->replace($node, '');
                }
                $this->doTraitExtend = false;
            }
            if (
                $node instanceof Name ||
                $node instanceof FullyQualified
            ) {
                $this->processClassName($node, true, $this->doTraitExtend);
            }
            if (
                $node instanceof Node\Scalar\MagicConst\File &&
                $this->filePath
            ) {
                $this->replace(
                    $node,
                    "self::" . ChainedClassMeta::CLASS_CONST_FILE
                );
            }
            if (
                $node instanceof Node\Scalar\MagicConst\Dir &&
                $this->filePath
            ) {
                $this->replace($node, ChainedClassMeta::CLASS_CONST_DIRECTORY);
            }
            if (
                $node instanceof Interface_ ||
                $node instanceof Trait_ ||
                $node instanceof Class_
            ) {
                $this->classNode = null;
            }
        }


        /**
         * @param int $pos
         * @param false $append
         * @return int|null
         */
        protected function translatePos(int $pos, $append = false): ?int
        {
            $delta = 0;
            $nextDelta = 0;
            $nextPos = null;
            foreach ($this->positionCoresp as $pcPos => $pcDelta) {
                if (
                    $pcPos > $pos ||
                    (
                        !$append &&
                        $pcPos == $pos
                    )
                ) {
                    $nextDelta = $delta + $pcDelta;
                    $nextPos = $pcPos;
                    break;
                }
                $delta += $pcDelta;
            }
            $p = $pos + $delta;
            if (
                !$append &&
                $nextPos !== null
            ) {
                $np = $nextPos + $nextDelta;
                if ($np <= $p) {
                    return null;
                }
            }
            return $p;
        }

        /**
         * @param Name $name
         * @param bool $doReplace
         * @param false $extendMode
         * @return ProcessedClassSpec {
         *      parent: null|string,
         *      this: null|string
         * }
         * @throws ErrorException
         * @throws Exception
         */
        protected function processClassName(
            Name $name,
            $doReplace = true,
            $extendMode = false
        ): ?ProcessedClassSpec {
            $n = $name->__toString();
            if (
                $n == 'parent' ||
                $n == 'self' ||
                $n == 'static'
            ) {
                return null;
            }
            $parent = null;
            $_this = null;
            if ($name instanceof FullyQualified) {
                $ns = $n;
            } elseif ($name->getAttribute('resolvedName')) {
                $ns = $name->getAttribute('resolvedName')->__toString();
            } else {
                return null;
            }
            $resolved = $this->resolver->getTargetClass($ns);
            if ($resolved) {
                $replace = null;
                if ($extendMode) {
                    if (
                        $resolved === $this->classSpec->getFullTargetClass()
                    ) {
                        if (!$this->previousClassSpec) {
                            throw new ErrorException(
                                "Cannot resolve previous extended $name -> $ns" .
                                $this->fileErr(
                                    $name
                                ) .
                                " : no previous spec for {$this->classSpec->getFullTargetClass()}"
                            );
                        }
                        $replace = $this->previousClassSpec->getFullClass();
                    } else {
                        $parent = $resolved;
                    }
                }
                if ($replace === null) {
                    $replace = $resolved;
                }
                if ($doReplace) {
                    $this->replace(
                        $name,
                        ClassNameHelper::addNsLeading($replace)
                    );
                }
                $_this = $replace;
            } else {
                $_this = $ns;
            }
            return new ProcessedClassSpec($_this, $parent);
        }

        /**
         * @param int $start
         * @param int $end
         * @param string $with
         * @param false $append
         * @param bool $preserveNl
         * @throws ErrorException
         * @throws Exception
         */
        protected function spliceSource(
            int $start,
            int $end,
            string $with,
            $append = false,
            $preserveNl = true
        ): void {
            if (
                $start < 0 ||
                $end < 0 ||
                $start > $end
            ) {
                throw new ErrorException("Invalid operation");
            }
            $realStart = $this->translatePos($start, $append);
            $realEnd = $this->translatePos($end, true);
            if ($realStart === null) {
                return;
            }
            if ($preserveNl) {
                $toDelete = substr(
                    $this->source,
                    $realStart,
                    $realEnd - $realStart
                );
                $dnlCount = substr_count($toDelete, "\n");
                $nlCount = substr_count($with, "\n");
                if ($nlCount > $dnlCount) {
                    throw new Exception("Cannot preserve line numbers");
                }
                $with .= str_repeat("\n", $dnlCount - $nlCount);
            }
            $this->source = substr($this->source, 0, $realStart) . $with .
                substr(
                    $this->source,
                    $realEnd
                );
            $len = strlen($with);
            $deletedLen = $realEnd - $realStart;
            $delta = $len - $deletedLen;
            if ($append) {
                $this->positionCoresp[$start] = ($this->positionCoresp[$start]
                        ?? 0) + $len;
                $this->positionCoresp[$end] = ($this->positionCoresp[$end] ??
                        0) - $deletedLen;
            } else {
                $this->positionCoresp[$end] = ($this->positionCoresp[$end] ??
                        0) + $delta;
            }
            ksort($this->positionCoresp);
        }

        /**
         * @param NodeAbstract $node
         * @param string $with
         * @throws ErrorException
         */
        protected function replace(NodeAbstract $node, string $with): void
        {
            $this->spliceSource(
                $node->getStartFilePos(),
                $node->getEndFilePos() + 1,
                $with
            );
        }

        /**
         * @param NodeAbstract $node
         * @param string $with
         * @throws ErrorException
         */
        protected function addBefore(NodeAbstract $node, string $with): void
        {
            $this->spliceSource(
                $node->getStartFilePos(),
                $node->getStartFilePos(),
                $with,
                true
            );
        }

        /**
         * @param NodeAbstract $node
         * @param string $with
         * @throws ErrorException
         */
        protected function addAfter(NodeAbstract $node, string $with): void
        {
            $this->spliceSource(
                $node->getEndFilePos() + 1,
                $node->getEndFilePos() + 1,
                $with,
                true
            );
        }

        /**
         * @param Node $node
         * @return int|null
         */
        protected function findBlockStartPos(Node $node): ?int
        {
            $pos = $node->getStartFilePos();
            if (
                !property_exists($node, 'stmts') ||
                !$node->stmts
            ) {
                $posInner = $node->getEndFilePos();
            } else {
                $posInner = reset($node->stmts)->getStartFilePos();
            }
            for ($i = $posInner; $i >= $pos; $i--) {
                if ($this->initialSource[$i] === '{') {
                    break;
                }
            }
            if ($i === $pos) {
                return null;
            }
            return $i + 1;
        }

        /**
         * @param Node $node
         * @param $with
         * @throws ErrorException
         */
        protected function addInBlock(Node $node, $with): void
        {
            $pos = $this->findBlockStartPos($node);
            if ($pos === null) {
                throw new ErrorException("Cannot find block start");
            }
            $this->spliceSource($pos, $pos, $with, true);
        }

        /**
         * @param Class_ $node
         * @param $qualifier
         * @throws ErrorException
         */
        protected function removeClassQualifier(Class_ $node, $qualifier): void
        {
            $pos = $node->getStartFilePos();
            $matches = [];
            preg_match(
                '`(?:(.*?)\\s)?class\\s`s',
                $this->initialSource,
                $matches,
                null,
                $pos
            );
            $qualifierStr = $matches[1];
            $qualifiers = preg_split('`\\s+`s', $qualifierStr);
            $qp = array_search($qualifier, $qualifiers);
            if ($qp !== false) {
                unset($qualifiers[$qp]);
                $newQualifierstr = implode(' ', $qualifiers);
                $this->spliceSource(
                    $pos,
                    $pos + strlen($qualifierStr),
                    $newQualifierstr
                );
            }
        }

        /**
         * @param Node|null $node
         * @return string
         */
        protected function fileErr(Node $node = null): string
        {
            $result = '';
            if ($this->filePath) {
                $result .= " in {$this->filePath}";
            }
            if ($node) {
                $result .= " on line {$node->getLine()}";
            }
            return $result;
        }

        /**
         * @return WeavedClass
         */
        public function getWeavedClass(): WeavedClass
        {
            return new WeavedClass(
                $this->classSpec->getFullClass(),
                (
                (
                    $this->source !== $this->initialSource ||
                    !$this->filePath
                ) ?
                    $this->source :
                    null
                ),
                $this->isAbstract,
                $this->isFinal,
                $this->isTrait,
                $this->isInterface,
                $this->isClass,
                $this->canonicalTraits,
                $this->canonicalInterfaces,
                $this->canonicalExtends
            );
        }

        /**
         * @param Name $traitName
         * @param string $className
         * @param int $seq
         * @param NodeAbstract $node
         * @return array {
         *      traitNs: string,
         *      intermediate: string
         * }
         * @throws ErrorException
         */
        protected function addTrait(
            Name $traitName,
            string $className,
            int $seq,
            NodeAbstract $node
        ): array {
            $replacement = $this->processClassName(
                $traitName,
                false
            );
            $traitNs = $replacement->getThis();
            $intermediate = "{$className}_" .
                self::INTERMEDIATE_CLASS_SUFFIX .
                "_trait_{$seq}";
            $intermediateFullClass = ClassNameHelper::joinNs(
                $this->classSpec->getFullNs(),
                $intermediate
            );
            $this->addAfter(
                $node,
                (
                    " \\" . ChainedClassMeta::class .
                    "::get(" . var_export($intermediateFullClass, true) .
                    ")->" .
                    "setRegistrationMeta(" .
                    "new \\" . RegistrationMeta::class .
                    "(" .
                    var_export(
                        $this->moduleName,
                        true
                    ) . "," .
                    var_export(
                        $this->classSpec->getFullTargetClass(),
                        true
                    ) . "," .
                    var_export(true, true) .
                    ")" .
                    ");"
                )
            );
            return [
                'traitNs' => $traitNs,
                'intermediate' => $intermediate
            ];
        }
    }