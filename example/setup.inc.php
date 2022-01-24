<?php

    // Setup modules

    use Exteon\Loader\ChainingClassResolver\Module;
    use Exteon\Loader\ChainingClassResolver\ClassFileResolver\PSR4ClassFileResolver;

    // Set up the chaining resolver

    use Exteon\Loader\ChainingClassResolver\ChainingClassResolver;

    $chainingClassResolver = new ChainingClassResolver(
        [
            new Module(
                'Code base',
                [new PSR4ClassFileResolver(__DIR__ . '/base', 'Code\\Base')]
            ),
            new Module(
                'Plugin 1',
                [new PSR4ClassFileResolver(__DIR__ . '/plugins/plugin1', 'Plugin1')]
            ),
            new Module(
                'Plugin 2',
                [new PSR4ClassFileResolver(__DIR__ . '/plugins/plugin2', 'Plugin2')]
            ),
            new Module(
                'Plugin 3',
                [new PSR4ClassFileResolver(__DIR__ . '/plugins/plugin3', 'Plugin3')]
            )
        ],
        'Target'
    );

    // Set up the loader

    use Exteon\Loader\MappingClassLoader\MappingClassLoader;
    use Exteon\Loader\MappingClassLoader\StreamWrapLoader;

    $loader = new MappingClassLoader(
        [],
        [
            $chainingClassResolver
        ],
        null,
        new StreamWrapLoader([
            'enableMapping' => true
        ])
    );
    $loader->register();
