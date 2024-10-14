<?php

abstract class AbstractDO implements EzDataObject, EzIgnoreUnknow
{
    /**
     * @Alias("id")
     */
    public $id;

    private $summary;

    private $markDeleted;

    public function __construct() {
        /**
         * @var EzLocalCache $localCache
         */
        $localCache = CacheFactory::getInstance(CacheFactory::TYPE_MEM);
        $map = $localCache->getSourceMap(OrmConst::KEY_LOCALCACHE_ORM_NEW);
        if (empty($map)) {
            $map[get_class($this)] = [];
        }
        $map[get_class($this)][] = $this;

        $localCache->putSource(OrmConst::KEY_LOCALCACHE_ORM_NEW, $map);
    }

    public function toArray(){
        $ezReflectionClass = new EzReflectionClass($this);
        $annoList = $ezReflectionClass->getPropertyAnnotationList(Clazz::get(Alias::class));
        $array = get_object_vars($this);
        foreach ($array as $k => $item) {
            if ($item instanceof EzSerializeDataObject) {
                $array[$k] = Clazz::get(get_class($item))->getSerializer()->serialize($item);
            }
            if (isset($annoList[$k])) {
                $annoItem = $annoList[$k];
                $array[$annoItem->value] = $array[$k];
                if ($k !== $annoItem->value) {
                    unset($array[$k]);
                }
            }
        }
        return $array;
    }

    /**
     * @return mixed
     */
    public function getSummary()
    {
        return $this->summary;
    }

    // todo
    public function calcSummary() {
        return "";
    }

    /**
     * @return bool
     */
    public function deleted()
    {
        return $this->markDeleted;
    }

    /**
     * @param bool $markDeleted
     */
    public function markDeleted(): void
    {
        $this->markDeleted = true;
    }
}
