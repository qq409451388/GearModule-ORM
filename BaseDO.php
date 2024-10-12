<?php

abstract class BaseDO extends AbstractDO
{
    /**
     * @ColumnAlias("id")
     */
    public $id;

    /**
     * @ColumnAlias("ver")
     */
    public $ver;

    /**
     * @var EzDate $createTime
     * @ColumnAlias("create_time")
     */
    public $createTime;

    /**
     * @var EzDate $updateTime
     * @ColumnAlias("update_time")
     */
    public $updateTime;

    public function __construct() {
        parent::__construct();
        $this->ver = 1;
        $this->createTime = $this->updateTime;
    }

    public function toString()
    {
        return EzObjectUtils::toString($this);
    }

    public function toJson() {
        return EzCodecUtils::encodeJson($this);
    }

    public function format(&$data) {
        return $this;
    }
}
