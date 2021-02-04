<?php

    namespace Plugin1;

    class A extends \Code\Base\A
    {
        public function whoami(): array
        {
            return array_merge(parent::whoami(), ['Plugin1\\A']);
        }
    }