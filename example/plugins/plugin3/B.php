<?php

    namespace Plugin3;

    class B extends \Plugin2\B
    {
        public function whoami(): array
        {
            return array_merge(parent::whoami(), ['Plugin3\\B']);
        }
    }