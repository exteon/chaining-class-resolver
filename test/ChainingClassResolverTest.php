<?php

    namespace Test\Exteon\Loader\ChainingClassResolver;

    use Exception;
    use Exteon\ClassMeta;
    use Exteon\Loader\ChainingClassResolver\ChainedClassMeta;
    use Exteon\Loader\ChainingClassResolver\ChainingClassResolver;
    use Exteon\Loader\ChainingClassResolver\ClassFileResolver\PSR4ClassFileResolver;
    use Exteon\Loader\ChainingClassResolver\Module;
    use Exteon\Loader\ChainingClassResolver\ModuleRegistry;
    use Exteon\Loader\MappingClassLoader\MappingClassLoader;
    use Exteon\Loader\MappingClassLoader\StreamWrapLoader;
    use PHPUnit\Framework\TestCase;

    /**
     * @runTestsInSeparateProcesses
     */
    class ChainingClassResolverTest extends TestCase
    {
        /**
         * @throws Exception
         */
        public function testClassLoading()
        {
            ModuleRegistry::registerModule(
                new Module(
                    'Module1',
                    [
                        new PSR4ClassFileResolver(
                            'test/Props/Module1',
                            'Test\\Exteon\\Loader\\ChainingClassResolver\\Props\\Module1'
                        )
                    ]
                )
            );
            ModuleRegistry::registerModule(
                new Module(
                    'Module2',
                    [
                        new PSR4ClassFileResolver(
                            'test/Props/Module2',
                            'Test\\Exteon\\Loader\\ChainingClassResolver\\Props\\Module2'
                        )
                    ]
                )
            );
            ModuleRegistry::registerModule(
                new Module(
                    'Module3',
                    [
                        new PSR4ClassFileResolver(
                            'test/Props/Module3',
                            'Test\\Exteon\\Loader\\ChainingClassResolver\\Props\\Module3'
                        )
                    ]
                )
            );
            $resolver = new ChainingClassResolver(
                'Test\\Exteon\\Loader\\ChainingClassResolver\\Props\\Target',
                ModuleRegistry::class
            );
            $loader = new MappingClassLoader(
                [],
                [$resolver],
                null,
                new StreamWrapLoader([])
            );
            $loader->register();

            $foo = new \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Foo(
            );
            self::assertTrue(
                is_a(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Module2\Foo::class,
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Module1\Foo::class,
                    true
                )
            );
            self::assertTrue(
                is_a(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Module2\Foo::class,
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Module1\Foo::class,
                    true
                )
            );
            self::assertTrue(
                is_a(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Module3\Foo::class,
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Module2\Foo::class,
                    true
                )
            );
            self::assertTrue(
                is_a(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Foo::class,
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Module3\Foo::class,
                    true
                )
            );
            self::assertEquals(
                'Module1+Trait1+Trait2+Module2+Module3',
                $foo->whoami()
            );
            self::assertEquals(
                \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Foo::class,
                ChainedClassMeta::get(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Module1\Foo::class
                )->getChainedClass()->getClassName()
            );
            self::assertEquals(
                \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Foo::class,
                ChainedClassMeta::get(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Bar2::class
                )->getChainParent()->getClassName()
            );
            self::assertEquals(
                \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Foo::class,
                ChainedClassMeta::get(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Bar::class
                )->getChainParent()->getClassName()
            );
            self::assertEquals(
                \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Foo::class,
                ChainedClassMeta::get(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Bar2::class
                )->getChainParent()->getClassName()
            );

            /*
             * Test that chain traits contain traits included upwards in the
             * hierarchy
             */
            {
                $chainTraits = ChainedClassMeta::get(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Trait2::class
                )->getChainTraits();
                $has = false;
                foreach ($chainTraits as $chainTrait) {
                    if (
                        $chainTrait->getClassName() ===
                        \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Trait3::class
                    ) {
                        $has = true;
                        break;
                    }
                }
                self::assertTrue($has);
                self::assertTrue(
                    ChainedClassMeta::get(
                        \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Trait2::class
                    )->hasChainTrait(
                        \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Trait3::class
                    )
                );
            }

            /*
             * Test that chained traits are hidden
             * hierarchy
             */
            {
                $chainTraits = ChainedClassMeta::get(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Trait2::class
                )->getChainTraits();
                $has = false;
                foreach ($chainTraits as $chainTrait) {
                    if (
                        $chainTrait->getClassName() ===
                        Test\Exteon\Loader\ChainingClassResolver\Props\Module3\Trait2::class ||
                        $chainTrait->getClassName() ===
                        Test\Exteon\Loader\ChainingClassResolver\Props\Module2\Trait2::class ||
                        $chainTrait->getClassName() ===
                        Test\Exteon\Loader\ChainingClassResolver\Props\Module2\Trait3::class
                    ) {
                        $has = true;
                        break;
                    }
                }
                self::assertFalse($has);
                $chainedClassMeta = ChainedClassMeta::get(
                    \Test\Exteon\Loader\ChainingClassResolver\Props\Target\Trait2::class
                );
                self::assertFalse(
                    $chainedClassMeta->hasChainTrait(
                        Test\Exteon\Loader\ChainingClassResolver\Props\Module3\Trait2::class
                    )
                );
                self::assertFalse(
                    $chainedClassMeta->hasChainTrait(
                        Test\Exteon\Loader\ChainingClassResolver\Props\Module2\Trait2::class
                    )
                );
                self::assertFalse(
                    $chainedClassMeta->hasChainTrait(
                        Test\Exteon\Loader\ChainingClassResolver\Props\Module2\Trait3::class
                    )
                );
            }
        }
    }
