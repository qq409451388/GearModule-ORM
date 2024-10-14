<?php
class UnitOfWork implements EzComponent
{
    public $entityManager;

    public function __construct() {
        $this->entityManager = BeanFinder::get()->fetch(EzLocalCache::class);
    }

    public function commit() {
    }
}
