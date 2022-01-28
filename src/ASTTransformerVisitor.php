<?php

    namespace Exteon\Loader\ChainingClassResolver;

    use ErrorException;
    use Exception;
    use Exteon\FileHelper;
    use Exteon\Loader\ChainingClassResolver\DataStructure\ProcessedClassSpec;
    use Exteon\Loader\ChainingClassResolver\DataStructure\RegistrationMeta;
    use Exteon\Loader\ChainingClassResolver\DataStructure\TargetNSSpec;
    use Exteon\Loader\ChainingClassResolver\DataStructure\WeavedClass;
    use JetBrains\PhpStorm\ArrayShape;
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
        private const INTERMEDIATE_CLASS_SUFFIX = 'ccr_inter';

        private TargetNSSpec $classSpec;
        private ?TargetNSSpec $previousClassSpec;
        private ?string $filePath;
        private ChainingClassResolver $resolver;
        private Class_|Interface_|Trait_|null $classNode;

        /** @var TraitUse[] */
        private array $traits = [];

        private string $source;
        private string $initialSource;

        /** @var array<int,int> */
        private array $positionCoresp = [];

        private bool $isAbstract = false;
        private bool $isFinal = false;
        private bool $isTrait = false;
        private bool $isClass = false;
        private bool $isInterface = false;
        private string $moduleName;
        private bool $doTraitExtend = false;

        /** @var string[] */
        private array $canonicalTraits = [];

        /** @var string[] */
        private array $canonicalInterfaces = [];

        /**  @var string[] */
        private array $canonicalExtends = [];

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
                            $fragment = "abstract class $intermediate";
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
                    $block = implode('', $fragments);
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
                    $this->canonicalInterfaces[] = $name;
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
            if ( $node instanceof Name ) {
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
                $this->replace($node, 'self::'.ChainedClassMeta::CLASS_CONST_DIRECTORY);
            }
            if (
                $node instanceof Interface_ ||
                $node instanceof Trait_ ||
                $node instanceof Class_
            ) {
                $this->classNode = null;
            }
        }


        private function translatePos(int $pos, bool $append = false): ?int
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
         * @throws ErrorException
         * @throws Exception
         */
        private function processClassName(
            Name $name,
            bool $doReplace = true,
            bool $extendMode = false
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
         * @throws ErrorException
         * @throws Exception
         */
        private function spliceSource(
            int $start,
            int $end,
            string $with,
            bool $append = false,
            bool $preserveNl = true
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
        private function replace(NodeAbstract $node, string $with): void
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
        private function addBefore(NodeAbstract $node, string $with): void
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
        private function addAfter(NodeAbstract $node, string $with): void
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
        private function findBlockStartPos(Node $node): ?int
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
        private function addInBlock(Node $node, $with): void
        {
            $pos = $this->findBlockStartPos($node);
            if ($pos === null) {
                throw new ErrorException("Cannot find block start");
            }
            $this->spliceSource($pos, $pos, $with, true);
        }

        /**
         * @throws ErrorException
         */
        private function removeClassQualifier(Class_ $node, string $qualifier): void
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
        private function fileErr(Node $node = null): string
        {
            $result = '';
            if ($this->filePath) {
                $result .= " in $this->filePath";
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
         * @throws ErrorException
         */
        #[ArrayShape(['traitNs' => "string", 'intermediate' => "string"])]
        private function addTrait(
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
                "_trait_$seq";
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