<?php
class UnitOfWork implements EzBean
{
    /**
     * @Resource("EzLocalCache")
     * @var EzLocalCache $entityManager
     */
    public $entityManager;

    public function __destruct() {
        Logger::info("UnitOfWork committing transaction...");
        $this->commit();
    }

    public function commit() {
        $saveOrUpdateList = [];
        $dbIns = null;
        try {
            $ormLocalCacheSpace = $this->entityManager->getSource(OrmConst::KEY_LOCALCACHE_ORM);
            $ormLocalCacheSpace = empty($ormLocalCacheSpace) ? [] : $ormLocalCacheSpace;
            $ormLocalCacheSpace2 = $this->entityManager->getSource(OrmConst::KEY_LOCALCACHE_ORM_NEW);
            $ormLocalCacheSpace2 = empty($ormLocalCacheSpace2) ? [] : $ormLocalCacheSpace2;
            /**
             * @var array<AbstractDO> $entityList
             */
            foreach ($ormLocalCacheSpace as $entityName => $entityList) {
                foreach ($entityList as $entity) {
                    if ($entity->deleted()) {
                        $saveOrUpdateList[$entityName]['delete'][] = $entity;
                    } else if ($entity->calcSummary() != $entity->getSummary()) {
                        $saveOrUpdateList[$entityName]['update'][] = $entity;
                    }
                }
            }

            /**
             * @var array<AbstractDO> $entityList
             */
            foreach ($ormLocalCacheSpace2 as $entityName => $entityList) {
                foreach ($entityList as $entity) {
                    $saveOrUpdateList[$entityName]['save'][] = $entity;
                }
            }
            $dbIns = DB::get(Config::get("application.datasource.mysql.database"));
            $dbIns->startTransaction();
            foreach ($saveOrUpdateList as $entityName => $entityGroupList) {
                $dynamicDao = DynamicDAO::getInstance($entityName);
                $deleteGroup = $entityGroupList['delete']??[];
                $saveGroup = $entityGroupList['save']??[];
                $updateGroup = $entityGroupList['update']??[];
                $dynamicDao->batchSave($saveGroup);
                $dynamicDao->batchUpdate($updateGroup);
                $dynamicDao->batchDeleteByIds(array_column($deleteGroup, 'id'));
            }
            $dbIns->commit();
            Logger::info("UnitOfWork commit transaction succeed!");
        } catch (\Exception | Error $e) {
            if ($dbIns instanceof IDbSe) {
                $dbIns->rollBack();
            }
        }
    }
}
