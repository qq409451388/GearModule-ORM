<?php
class UnitOfWorkStarter implements EzStarter
{

    public function init()
    {
        $uw = new UnitOfWork();
    }

    public function start()
    {
        // TODO: Implement start() method.
    }
}
