<?php

abstract class BaseDAO implements EzBean
{
    use BaseDAOTrait;

    public function __construct() {
        $this->init();
    }

    abstract protected function bindEntity():Clazz;

}
