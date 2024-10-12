<?php
class DynamicDAO extends BaseDAO
{
    public function __construct($className) {
        $this->entityClazz = Clazz::get($className);
        parent::__construct();
    }
    public static function getInstance($className) {
        return new DynamicDAO($className);
    }

    protected function bindEntity(): Clazz
    {
        return $this->entityClazz;
    }
}
