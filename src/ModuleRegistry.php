<?php

    namespace Exteon\Loader\ChainingClassResolver;

    abstract class ModuleRegistry
    {

        /** @var Module[] */
        protected static $moduleChain = [];

        /**
         * @param Module $module
         */
        public static function registerModule(Module $module): void
        {
            self::$moduleChain[] = $module;
        }

        /**
         * @return Module[]
         */
        public static function getModuleChain(): array
        {
            return self::$moduleChain;
        }

        /**
         * @param $name
         * @return false|int
         */
        public static function getModuleIndex($name)
        {
            foreach (self::$moduleChain as $key => $value) {
                if ($value->getName() == $name) {
                    return count(self::$moduleChain) - $key - 1;
                }
            }
            return false;
        }

        /**
         * @param $name
         * @return bool
         */
        public static function hasModule($name): bool
        {
            foreach (self::$moduleChain as $module) {
                if ($module->getName() == $name) {
                    return true;
                }
            }
            return false;
        }

        /**
         * @param $name
         * @return Module|null
         */
        public static function getModule($name): ?Module
        {
            foreach (self::$moduleChain as $module) {
                if ($module->getName() == $name) {
                    return $module;
                }
            }
            return null;
        }
    }