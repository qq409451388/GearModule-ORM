<?php

/**
 * degign for Object ? extends BaseDO
 */
class EntityBind extends RuntimeAnnotation
{
    protected $table;
    protected $db;

    public function getTable() {
        return $this->table;
    }

    public function getDb() {
        return $this->db;
    }

    public function constTarget()
    {
        return AnnoElementType::TYPE_CLASS;
    }

    public function constStruct()
    {
        return AnnoValueTypeEnum::TYPE_RELATION;
    }
}
