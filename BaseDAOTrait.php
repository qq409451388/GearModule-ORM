<?php
trait BaseDAOTrait
{
    /**
     * @var Clazz $entityClazz
     */
    protected $entityClazz;
    protected $table;
    protected $database;

    /**
     * @var bool 是否存在分表策略
     */
    private $hasSplit;
    private $splitCnt;
    private $splitColumn;
    private $splitModel;

    private function init() {
        $this->entityClazz = $this->bindEntity();
        $this->hasSplit = false;
        DBC::assertTrue($this->entityClazz->isSubClassOf(AbstractDO::class),
            "[DAO] create Fail!",0, GearShutDownException::class);

        /**
         * @var AnnoationElement $annoItem
         */
        $annoItem = AnnoationRule::searchCertainlyRelationshipAnnoation($this->entityClazz->getName(), EntityBind::class);
        DBC::assertNotEmpty($annoItem, "[DAO] the Class {$this->entityClazz->getName()} must bind Anno ".EntityBind::class);
        if ($annoItem instanceof AnnoationElement) {
            /**
             * @var EntityBind $anno
             */
            $anno = $annoItem->getValue();

            $this->table = $anno->getTable();
            $this->database = $anno->getDb();
        } else {
            /**
             * @var AnnoationElement $annoItem
             */
            $annoItem = AnnoationRule::searchCertainlyRelationshipAnnoation($this->entityClazz->getName(), EntityBindSplit::class);
            DBC::assertTrue($annoItem instanceof AnnoationElement, "[DAO] create Fail!", 0, GearShutDownException::class);
            /**
             * @var EntityBindSplit $anno
             */
            $anno = $annoItem->getValue();
            $this->table = $anno->getTable();
            $this->database = $anno->getDb();
            $this->splitCnt = $anno->getSplit();
            $this->splitColumn = $anno->getSplitColumn();
            $this->splitModel = $anno->getSplitModel();
            $this->hasSplit = true;
        }
    }

    /**
     * @param $whereSql
     * @param $params
     * @return AbstractDO|null
     * @throws ReflectionException|Exception
     */
    public function findOne($whereSql, $params){
        $sql = "select * from `{$this->getTable($params[$this->splitColumn]??null)}` {$whereSql} limit 1";
        $res = DB::get($this->database)->queryOne($sql, $params);
        if (empty($res)) {
            return null;
        }
        $className = $this->entityClazz->getName();
        return EzObjectUtils::create($res, $className);
    }

    private function get($entityName, $id, $useCache) {
        if (!$useCache) {
            return null;
        }
        /**
         * @var EzLocalCache $localCache
         */
        $localCache = CacheFactory::getInstance(CacheFactory::TYPE_MEM);
        if (null == $localCache) {
            return null;
        }
        $ormLocalCacheSpace = $localCache->getSource(OrmConst::KEY_LOCALCACHE_ORM);
        if (empty($ormLocalCacheSpace) || empty($ormLocalCacheSpace[$entityName])) {
            return null;
        }
        return $ormLocalCacheSpace[$entityName][$id]??null;
    }

    private function put(AbstractDO $entity) {
        $entityName = get_class($entity);
        /**
         * @var EzLocalCache $localCache
         */
        $localCache = CacheFactory::getInstance(CacheFactory::TYPE_MEM);
        if (null == $localCache) {
            return null;
        }
        $ormLocalCacheSpace = $localCache->getSource(OrmConst::KEY_LOCALCACHE_ORM);
        if (empty($ormLocalCacheSpace)) {
            $localCache->putSource(OrmConst::KEY_LOCALCACHE_ORM, ["$entityName" => []]);
            return null;
        }
        if (empty($ormLocalCacheSpace[$entityName])) {
            $ormLocalCacheSpace[$entityName] = [];
            return null;
        }
        $ormLocalCacheSpace[$entityName][$entity->id] = $entity;
    }

    /**
     * @param $id
     * @return AbstractDO
     * @throws ReflectionException
     */
    public function findById($id, bool $useCache = true) {
        $cached = $this->get($this->entityClazz->getName(), $id, $useCache);
        if (!is_null($cached)) {
            return $cached;
        }
        $params = [":id" => $id];
        if ($this->hasSplit) {
            DBC::assertEquals("id", $this->splitColumn,
                "[DAO] this table has split strategy and split column is not 'id'.", 0, GearRunTimeException::class);
            $params["id"] = $id;
        }
        $data = $this->findOne("where id = :id", $params);
        if(empty($data)){
            return null;
        }
        $this->put($data);
        return $data;
    }

    private function getSql4Split($whereSql, $params, $column) {
        $tableList = [];
        foreach ($params[$column] as $id) {
            $tmpTableName = $this->getTable($id);
            if (!isset($tableList[$tmpTableName])) {
                $tableList[$tmpTableName] = [];
            }
            $tableList[$tmpTableName][] = $id;
        }
        $sql = [];
        foreach ($tableList as $tableName => $ids) {
            $tmpParams = $params;
            $tmpParams[$column] = $ids;
            $sql[] = DB::get($this->database)->getSql("select * from $tableName $whereSql", $tmpParams);
        }
        $sql = implode(SqlPatternChunk::EOL, $sql);
        return $sql;
    }

    private function findList4SplitCertainly($appendSql, $params, $column) {
        $sql = $this->getSql4Split($appendSql, $params, $column);
        $res = DB::get($this->database)->query($sql, [], SqlOptions::new()->isChunk(true));
        $className = $this->entityClazz->getName();
        foreach ($res as &$item) {
            $item = EzObjectUtils::create($item, $className);
        }
        return $res;
    }

    private function findList4Split($appendSql, $params) {
        // 来源是 findByIds
        if (isset($params[':'.$this->splitColumn])) {
            return $this->findList4SplitCertainly($appendSql, $params, ":$this->splitColumn");
        } elseif(isset($params[":".$this->splitColumn."List"])){
            return $this->findList4SplitCertainly($appendSql, $params, ":{$this->splitColumn}List");
        } elseif(isset($params[':'.$this->splitColumn."s"])) {
            return $this->findList4SplitCertainly($appendSql, $params, ":{$this->splitColumn}s");
        } else {
            $appendSql = "select * from $this->table ".strtolower($appendSql);
            preg_match('/^(\s*select\s+(?P<select>.*))\s+from\s+(?P<from>[^\s]+)?(\s+where\s+(?P<where>.*?))?(\s+group\s+by\s+(?P<groupby>[\w,\s]+?))?(?:\s+having\s+(?P<having>.*?))?(?:\s+order\s+by\s+(?P<orderby>[\w`,\s]+)?)?(?:\s+limit\s+(?P<offset>\d+\s?),(?P<limit>\s?\d+?))?\s*$/i', $appendSql, $matches);
            $whereSql = "where {$matches['where']}";
            $splitSql = [];
            for ($i=0;$i<$this->splitCnt;$i++) {
                $tmpTable = sprintf($this->table, $i);
                $tmpParams = $params;
                $splitSql[] = "(".DB::get($this->database)->getSql("select * from $tmpTable $whereSql ", $tmpParams).")";
            }
            $splitSql = implode(" union all ", $splitSql);
            $sql = "select * from ($splitSql) tmp $appendSql";
            $res = DB::get($this->database)->query($sql, $params);
            $className = $this->entityClazz->getName();
            foreach ($res as &$item) {
                $item = EzObjectUtils::create($item, $className);
            }
            return $res;
        }
    }

    /**
     * @param $whereSql
     * @param $params
     * @return array<AbstractDO>
     * @throws ReflectionException
     */
    public function findList($whereSql, $params) {
        if ($this->hasSplit) {
            return $this->findList4Split($whereSql, $params);
        } else {
            $sql = "select * from `{$this->getTable()}` {$whereSql}";
            $res = DB::get($this->database)->query($sql, $params);
            if (empty($res)) {
                return [];
            }
            $className = $this->entityClazz->getName();
            foreach ($res as &$item) {
                $item = EzObjectUtils::create($item, $className);
            }
            return $res;
        }
    }

    /**
     * @param $ids
     * @return array<AbstractDO>
     * @throws ReflectionException
     */
    public function findByIds($ids, bool $useCache = true) {
        $res = [];
        $idsNoCache = [];
        foreach ($ids as $id) {
            $data = $this->get($this->entityClazz->getName(), $id, $useCache);
            if(is_null($data)){
                $idsNoCache[] = $id;
            } else {
                $res[] = $data;
            }
        }

        $resNoCache = [];
        if (!empty($idsNoCache)) {
            $resNoCache = $this->findList("where id in (:idList)", [":idList" => $idsNoCache]);
        }
        /**
         * @var AbstractDO $item
         */
        foreach ($resNoCache as $item) {
            $this->put($item);
        }
        $res = array_merge($res, $resNoCache);
        array_multisort($res, array_column($res, "id"), SORT_ASC);
        return $res;
    }

    public function save(AbstractDO $domain) {
        $refClass = new EzReflectionClass($domain);
        $annoItme = $refClass->getAnnoation(Clazz::get(IdGenerator::class));
        if ($annoItme instanceof AnnoationElement) {
            /**
             * @var EzIdClient $idClient
             */
            $idClient = BeanFinder::get()->pull($annoItme->value);
            $domain->id = $idClient->nextId();

            $domainArr = $domain->toArray();
        }
        if ($domain instanceof BaseDO) {
            $domain->ver = 1;
            $date = new EzDate();
            $domain->createTime = $date;
            $domain->updateTime = $date;
            $domainArr = $domain->toArray();
            unset($domainArr["id"]);
        }
        $splitColumn = $this->splitColumn;
        return DB::get($this->database)->save($this->getTable($domain->$splitColumn ?? null), $domainArr);
    }

    public function update(AbstractDO $domain) {
        if (is_null($domain->id)) {
            return false;
        }
        $splitColumn = $this->splitColumn;
        if ($domain instanceof BaseDO) {
            $ver = $domain->ver;
            $domain->ver++;
            $domain->updateTime = new EzDate();
            $updateRes = DB::get($this->database)->update($this->getTable($domain->$splitColumn ?? null), $domain->toArray(), "id", "ver = $ver");
        } else {
            $updateRes = DB::get($this->database)->update($this->getTable($domain->$splitColumn ?? null), $domain->toArray(), "id");
        }
        /**
         * @var EzLocalCache $localCache
         */
        $localCache = CacheFactory::getInstance(CacheFactory::TYPE_MEM);
        $localCache->del($this->entityClazz->getName().$domain->id);
        return $updateRes;
    }

    private function getTable($splitValue = null) {
        if ($this->hasSplit) {
            DBC::assertNonNull($splitValue, "[DAO] getTableFail! this table has split strategy, but no splitvalue input!");
            if ("mod" == $this->splitModel) {
                return sprintf($this->table, $splitValue%$this->splitCnt);
            }
            DBC::assertTrue(true, "[DAO] getTableFail! this table has split strategy, but no splitmodel input!");
        }
        return $this->table;
    }

    public function count($whereSql, $params):int {
        if ($this->hasSplit) {
            Logger::warn("未实现此方法");
            return 0;
        } else {
            $sql = "select count(1) as cnt from `{$this->getTable()}` {$whereSql}";
            return DB::get($this->database)->queryValue($sql, $params, "cnt");
        }
    }

    /**
     * @param $sql
     * @param $params
     * @return array<AbstractDO>
     * @throws ReflectionException
     */
    public function findListRaw($sql, $params) {
        if ($this->hasSplit) {
            Logger::warn("未实现此方法");
            return [];
        } else {
            $res = DB::get($this->database)->query($sql, $params);
            if (empty($res)) {
                return [];
            }
            $className = $this->entityClazz->getName();
            foreach ($res as &$item) {
                $item = EzObjectUtils::create($item, $className);
            }
            return $res;
        }
    }

    public function batchSave($entities) {

    }

    public function batchUpdate($entities) {
        if (empty($entities)) {
            return true;
        }
        $splitColumn = $this->splitColumn;
        $waitUpdateList = [];
        $oldVerMap = [];
        foreach ($entities as $domain) {
            $tableName = $this->getTable($domain->$splitColumn ?? null);
            if ($domain instanceof BaseDO) {
                $ver = $domain->ver;
                $oldVerMap[$domain->calcSummary()] = $ver;
                $domain->ver++;
                $domain->updateTime = new EzDate();
                $waitUpdateList[$tableName][] = $domain;
                $updateRes = DB::get($this->database)->update($this->getTable($domain->$splitColumn ?? null), $domain->toArray(), "id", "ver = $ver");
            } else {
                $waitUpdateList[$tableName][] = $domain;
                //$updateRes = DB::get($this->database)->update($this->getTable($domain->$splitColumn ?? null), $domain->toArray(), "id");
            }
        }
        if (empty($oldVerMap)) {
            foreach ($waitUpdateList as $tableName => $waitUpdateListGroup) {
                $updateRes = DB::get($this->database)->updateList($tableName, $waitUpdateListGroup, "id");
            }
        } else {
            foreach ($waitUpdateList as $tableName => $waitUpdateListGroup) {
                $ver = $oldVerMap[$tableName];
                $updateRes = DB::get($this->database)->updateList($tableName, $waitUpdateListGroup, "id", "ver = $ver");
            }
        }


        /**
         * @var EzLocalCache $localCache
         */
        $localCache = CacheFactory::getInstance(CacheFactory::TYPE_MEM);
        $localCache->del($this->entityClazz->getName().$domain->id);
        return $updateRes;
    }

    public function batchDeleteByIds($entityIds) {

    }
}
