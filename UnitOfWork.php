<?php
class UnitOfWork implements EzBean
{
    /**
     * @Resource("EzLocalCache")
     * @var EzLocalCache $entityManager
     */
    public $entityManager;

    public function commit() {
        $saveOrUpdateList = [];
        try {
            $ormLocalCacheSpace = $this->entityManager->getSource(OrmConst::KEY_LOCALCACHE_ORM);
            $ormLocalCacheSpace2 = $this->entityManager->getSource(OrmConst::KEY_LOCALCACHE_ORM_NEW);
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
            $dbIns = DB::get(Config::get("application.datasource.database"));
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
        } catch (\Exception $e) {
            $dbIns->rollBack();
        }
    }
}
