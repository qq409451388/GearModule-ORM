<?php

abstract class BaseDO extends AbstractDO
{

    /**
     * @Alias("ver")
     */
    public $ver;

    /**
     * @var EzDate $createTime
     * @Alias("create_time")
     */
    public $createTime;

    /**
     * @var EzDate $updateTime
     * @Alias("update_time")
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
