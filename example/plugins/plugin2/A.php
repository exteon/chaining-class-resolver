<?php

    namespace Plugin2;

    class A extends \Code\Base\A
    {
        public function whoami(): array
        {
            return array_merge(parent::whoami(), ['Plugin2\\A']);
        }
    }