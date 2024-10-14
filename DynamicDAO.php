<?php
class DynamicDAO
{
    use BaseDAOTrait;

    public function __construct($className) {
        $this->entityClazz = Clazz::get($className);
        $this->init();
    }
    public static function getInstance($className) {
        return new DynamicDAO($className);
    }

    protected function bindEntity(): Clazz
    {
        return $this->entityClazz;
    }
}
