<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ORM;

use Doctrine\DBAL\Types\Type,
    Doctrine\DBAL\Cache\QueryCacheProfile,
    Doctrine\ORM\Query\QueryException,
    Doctrine\ORM\Internal\Hydration\CacheHydrator;

/**
 * ORM 查询的基础契约 。查询和本地查询的基类
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  Konsta Vesterinen <kvesteri@cc.hut.fi>
 */
abstract class AbstractQuery
{
    /* 混合模式常数 */
    /**
     * 混合一个对象映射，这是默认的表现。
     */
    const HYDRATE_OBJECT = 1;
    /**
     * 混合一个数组映射。
     */
    const HYDRATE_ARRAY = 2;
    /**
     * 使用标量值混合一个平面，矩形的结果集
     */
    const HYDRATE_SCALAR = 3;
    /**
     * 混合一个简单的标量值。
     */
    const HYDRATE_SINGLE_SCALAR = 4;

    /**
     * 十分简单的对象混合 (优化表现).
     */
    const HYDRATE_SIMPLEOBJECT = 5;

    /**
     * @var array 映射此查询的参数.
     */
    protected $_params = array();

    /**
     * @var array 映射此查询的参数类型.
     */
    protected $_paramTypes = array();

    /**
     * @var ResultSetMapping 用户指定使用的结果集映射
     */
    protected $_resultSetMapping;

    /**
     * @var \Doctrine\ORM\EntityManager 此查询对象使用的实体管理器。
     */
    protected $_em;

    /**
     * @var array 查询提示映射.
     */
    protected $_hints = array();

    /**
     * @var integer 混合模式。
     */
    protected $_hydrationMode = self::HYDRATE_OBJECT;

    /**
     * @param \Doctrine\DBAL\Cache\QueryCacheProfile
     */
    protected $_queryCacheProfile;

    /**
     * @var boolean 描述结果缓存是否过期的布尔值。
     */
    protected $_expireResultCache = false;

    /**
     * @param \Doctrine\DBAL\Cache\QueryCacheProfile
     */
    protected $_hydrationCacheProfile;

    /**
     * 初始化一个起源于<tt>AbstractQuery</tt>类的新实例
     *
     * @param \Doctrine\ORM\EntityManager $entityManager
     */
    public function __construct(EntityManager $em)
    {
        $this->_em = $em;
    }

    /**
     * 获取此查询实例相关联的实体管理器
     *
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return $this->_em;
    }

    /**
     * 使用查询对象释放资源。
     *
     * 重置参数，参数类型和查询提示
     *
     * @return void 空
     */
    public function free()
    {
        $this->_params = array();
        $this->_paramTypes = array();
        $this->_hints = array();
    }

    /**
     * 获取所有定义的参数。
     *
     * @return array 已定义的查询参数。
     */
    public function getParameters()
    {
        return $this->_params;
    }

    /**
     * 获取所有定义的参数类型。
     *
     * @return array 已定义的查询参数类型。
     */
    public function getParameterTypes()
    {
        return $this->_paramTypes;
    }

    /**
     * 得到一个查询参数。
     *
     * @param mixed $key 绑定参数的键(索引或者名字)。
     * @return mixed 绑定参数的值。
     */
    public function getParameter($key)
    {
        if (isset($this->_params[$key])) {
            return $this->_params[$key];
        }

        return null;
    }

    /**
     * 得到一个查询参数类型。
     *
     * @param mixed $key 绑定参数的键(索引或者名字)。
     * @return mixed 绑定参数的参数类型。
     */
    public function getParameterType($key)
    {
        if (isset($this->_paramTypes[$key])) {
            return $this->_paramTypes[$key];
        }

        return null;
    }

    /**
     * 得到和查询对象一致SQL查询语句。
     * 返回的SQL句法依赖于当此方法被调用时被使用的查询对象的连接驱动。
     *
     * @return string SQL查询
     */
    abstract public function getSQL();

    /**
     * 一个查询参数的集合
     *
     * @param string|integer $key 参数的位置或者名字。
     * @param mixed $value 参数的值。
     * @param string $type 参数的类型. 如果指定了, 此类型给出的值将通过类型转换。 
     *                      如果是字符串型或者数字型通常不需要转换。
     * @return \Doctrine\ORM\AbstractQuery 这个查询的实例。
     */
    public function setParameter($key, $value, $type = null)
    {
        $key = trim($key, ':');

        $value = $this->processParameterValue($value);
        if ($type === null) {
            $type = Query\ParameterTypeInferer::inferType($value);
        }

        $this->_paramTypes[$key] = $type;
        $this->_params[$key] = $value;

        return $this;
    }

    /**
     * 处理一个私有参数值
     *
     * @param mixed $value
     * @return array
     */
    private function processParameterValue($value)
    {
        switch (true) {
            case is_array($value):
                for ($i = 0, $l = count($value); $i < $l; $i++) {
                    $paramValue = $this->processParameterValue($value[$i]);
                    $value[$i] = is_array($paramValue) ? $paramValue[key($paramValue)] : $paramValue;
                }

                return $value;

            case is_object($value) && $this->_em->getMetadataFactory()->hasMetadataFor(get_class($value)):
                return $this->convertObjectParameterToScalarValue($value);

            default:
                return $value;
        }
    }

    protected function convertObjectParameterToScalarValue($value)
    {
        $class = $this->_em->getClassMetadata(get_class($value));

        if ($class->isIdentifierComposite) {
            throw new \InvalidArgumentException("Binding an entity with a composite primary key to a query is not supported. You should split the parameter into the explicit fields and bind them seperately.");
        }

        if ($this->_em->getUnitOfWork()->getEntityState($value) === UnitOfWork::STATE_MANAGED) {
            $values = $this->_em->getUnitOfWork()->getEntityIdentifier($value);
        } else {
            $values = $class->getIdentifierValues($value);
        }

        $value = $values[$class->getSingleIdentifierFieldName()];
        if (!$value) {
            throw new \InvalidArgumentException("Binding entities to query parameters only allowed for entities that have an identifier.");
        }

        return $value;
    }

    /**
     * 设置一个查询参数的集合。
     *
     * @param array $params
     * @param array $types
     * @return \Doctrine\ORM\AbstractQuery 这个查询的实例。
     */
    public function setParameters(array $params, array $types = array())
    {
        foreach ($params as $key => $value) {
            $this->setParameter($key, $value, isset($types[$key]) ? $types[$key] : null);
        }

        return $this;
    }

    /**
     * 设置应该用于混合的结果集映射。
     *
     * @param ResultSetMapping $rsm
     * @return \Doctrine\ORM\AbstractQuery
     */
    public function setResultSetMapping(Query\ResultSetMapping $rsm)
    {
        $this->_resultSetMapping = $rsm;

        return $this;
    }

    /**
     * 给混合缓存设置一个缓存档案。
     *
     * 如果在QueryCacheProfile中没有设置结果缓存驱动，这个配置默认的结果缓存驱动将被使用。
     *
     * 重点: 混合缓存不是从缓存中获取的工作单元的注册实体。
     * 不要使用结果缓存实体去请求，这样会刷新实体管理器。如果你想用一些缓存工作单元注册的表单，
     * 你应该使用
     * {@see AbstractQuery::setResultCacheProfile()}.
     *
     * @example
     * $lifetime = 100;
     * $resultKey = "abc";
     * $query->setHydrationCacheProfile(new QueryCacheProfile());
     * $query->setHydrationCacheProfile(new QueryCacheProfile($lifetime, $resultKey));
     *
     * @param \Doctrine\DBAL\Cache\QueryCacheProfile $profile
     * @return \Doctrine\ORM\AbstractQuery
     */
    public function setHydrationCacheProfile(QueryCacheProfile $profile = null)
    {
        if ( ! $profile->getResultCacheDriver()) {
            $resultCacheDriver = $this->_em->getConfiguration()->getHydrationCacheImpl();
            $profile = $profile->setResultCacheDriver($resultCacheDriver);
        }

        $this->_hydrationCacheProfile = $profile;

        return $this;
    }

    /**
     * @return \Doctrine\DBAL\Cache\QueryCacheProfile
     */
    public function getHydrationCacheProfile()
    {
        return $this->_hydrationCacheProfile;
    }

    /**
     * 给结果缓存设置一个缓存档案
     *
     * 如果在QueryCacheProfile里没有设置结果缓存驱动，配置中默认的
     * 结果缓存驱动将被使用。
     *
     * @param \Doctrine\DBAL\Cache\QueryCacheProfile $profile
     * @return \Doctrine\ORM\AbstractQuery
     */
    public function setResultCacheProfile(QueryCacheProfile $profile = null)
    {
        if ( ! $profile->getResultCacheDriver()) {
            $resultCacheDriver = $this->_em->getConfiguration()->getResultCacheImpl();
            $profile = $profile->setResultCacheDriver($resultCacheDriver);
        }

        $this->_queryCacheProfile = $profile;

        return $this;
    }

    /**
     * 定义一个缓存驱动用于缓存结果集或者隐式启动缓存
     *
     * @param \Doctrine\Common\Cache\Cache $driver Cache driver
     * @return \Doctrine\ORM\AbstractQuery
     */
    public function setResultCacheDriver($resultCacheDriver = null)
    {
        if ($resultCacheDriver !== null && ! ($resultCacheDriver instanceof \Doctrine\Common\Cache\Cache)) {
            throw ORMException::invalidResultCacheDriver();
        }

        $this->_queryCacheProfile = $this->_queryCacheProfile
            ? $this->_queryCacheProfile->setResultCacheDriver($resultCacheDriver)
            : new QueryCacheProfile(0, null, $resultCacheDriver);

        return $this;
    }

    /**
     * 返回一个缓存驱动给缓存结果集
     *
     * @deprecated
     * @return \Doctrine\Common\Cache\Cache Cache driver
     */
    public function getResultCacheDriver()
    {
        if ($this->_queryCacheProfile && $this->_queryCacheProfile->getResultCacheDriver()) {
            return $this->_queryCacheProfile->getResultCacheDriver();
        }

        return $this->_em->getConfiguration()->getResultCacheImpl();
    }

    /**
     * 设置是否缓存这个查询的结果，如果是，缓存时长和用于缓存条目的ID。
     *
     * @param boolean $bool
     * @param integer $lifetime
     * @param string $resultCacheId
     * @return \Doctrine\ORM\AbstractQuery 这个查询的实例。
     */
    public function useResultCache($bool, $lifetime = null, $resultCacheId = null)
    {
        if ($bool) {
            $this->setResultCacheLifetime($lifetime);
            $this->setResultCacheId($resultCacheId);

            return $this;
        }

        $this->_queryCacheProfile = null;

        return $this;
    }

    /**
     * 定义结果缓存在过期前活动的时长。
     *
     * @param integer $lifetime 缓存条目有效的时长。
     * @return \Doctrine\ORM\AbstractQuery 本次查询的实例。
     */
    public function setResultCacheLifetime($lifetime)
    {
        $lifetime = ($lifetime !== null) ? (int) $lifetime : 0;

        $this->_queryCacheProfile = $this->_queryCacheProfile
            ? $this->_queryCacheProfile->setLifetime($lifetime)
            : new QueryCacheProfile($lifetime, null, $this->_em->getConfiguration()->getResultCacheImpl());

        return $this;
    }

    /**
     * 获取结果集缓存的生存时间。
     *
     * @deprecated
     * @return integer
     */
    public function getResultCacheLifetime()
    {
        return $this->_queryCacheProfile ? $this->_queryCacheProfile->getLifetime() : 0;
    }

    /**
     * 定义结果缓存是否激活。
     *
     * @param boolean $expire 是否强迫结果集缓存过期。
     * @return \Doctrine\ORM\AbstractQuery 本次查询的实例。
     */
    public function expireResultCache($expire = true)
    {
        $this->_expireResultCache = $expire;

        return $this;
    }

    /**
     * 获得结果集缓存是否激活的状态。
     *
     * @return boolean
     */
    public function getExpireResultCache()
    {
        return $this->_expireResultCache;
    }

    /**
     * @return QueryCacheProfile
     */
    public function getQueryCacheProfile()
    {
        return $this->_queryCacheProfile;
    }

    /**
     * Change the default fetch mode of an association for this query.
     *
     * $fetchMode can be one of ClassMetadata::FETCH_EAGER or ClassMetadata::FETCH_LAZY
     *
     * @param  string $class
     * @param  string $assocName
     * @param  int $fetchMode
     * @return AbstractQuery
     */
    public function setFetchMode($class, $assocName, $fetchMode)
    {
        if ($fetchMode !== Mapping\ClassMetadata::FETCH_EAGER) {
            $fetchMode = Mapping\ClassMetadata::FETCH_LAZY;
        }

        $this->_hints['fetchMode'][$class][$assocName] = $fetchMode;

        return $this;
    }

    /**
     * Defines the processing mode to be used during hydration / result set transformation.
     *
     * @param integer $hydrationMode Doctrine processing mode to be used during hydration process.
     *                               One of the Query::HYDRATE_* constants.
     * @return \Doctrine\ORM\AbstractQuery This query instance.
     */
    public function setHydrationMode($hydrationMode)
    {
        $this->_hydrationMode = $hydrationMode;

        return $this;
    }

    /**
     * Gets the hydration mode currently used by the query.
     *
     * @return integer
     */
    public function getHydrationMode()
    {
        return $this->_hydrationMode;
    }

    /**
     * Gets the list of results for the query.
     *
     * Alias for execute(array(), $hydrationMode = HYDRATE_OBJECT).
     *
     * @return array
     */
    public function getResult($hydrationMode = self::HYDRATE_OBJECT)
    {
        return $this->execute(array(), $hydrationMode);
    }

    /**
     * Gets the array of results for the query.
     *
     * Alias for execute(array(), HYDRATE_ARRAY).
     *
     * @return array
     */
    public function getArrayResult()
    {
        return $this->execute(array(), self::HYDRATE_ARRAY);
    }

    /**
     * Gets the scalar results for the query.
     *
     * Alias for execute(array(), HYDRATE_SCALAR).
     *
     * @return array
     */
    public function getScalarResult()
    {
        return $this->execute(array(), self::HYDRATE_SCALAR);
    }

    /**
     * Get exactly one result or null.
     *
     * @throws NonUniqueResultException
     * @param int $hydrationMode
     * @return mixed
     */
    public function getOneOrNullResult($hydrationMode = null)
    {
        $result = $this->execute(array(), $hydrationMode);

        if ($this->_hydrationMode !== self::HYDRATE_SINGLE_SCALAR && ! $result) {
            return null;
        }

        if ( ! is_array($result)) {
            return $result;
        }

        if (count($result) > 1) {
            throw new NonUniqueResultException;
        }

        return array_shift($result);
    }

    /**
     * Gets the single result of the query.
     *
     * Enforces the presence as well as the uniqueness of the result.
     *
     * If the result is not unique, a NonUniqueResultException is thrown.
     * If there is no result, a NoResultException is thrown.
     *
     * @param integer $hydrationMode
     * @return mixed
     * @throws NonUniqueResultException If the query result is not unique.
     * @throws NoResultException If the query returned no result.
     */
    public function getSingleResult($hydrationMode = null)
    {
        $result = $this->execute(array(), $hydrationMode);

        if ($this->_hydrationMode !== self::HYDRATE_SINGLE_SCALAR && ! $result) {
            throw new NoResultException;
        }

        if ( ! is_array($result)) {
            return $result;
        }

        if (count($result) > 1) {
            throw new NonUniqueResultException;
        }

        return array_shift($result);
    }

    /**
     * Gets the single scalar result of the query.
     *
     * Alias for getSingleResult(HYDRATE_SINGLE_SCALAR).
     *
     * @return mixed
     * @throws QueryException If the query result is not unique.
     */
    public function getSingleScalarResult()
    {
        return $this->getSingleResult(self::HYDRATE_SINGLE_SCALAR);
    }

    /**
     * Sets a query hint. If the hint name is not recognized, it is silently ignored.
     *
     * @param string $name The name of the hint.
     * @param mixed $value The value of the hint.
     * @return \Doctrine\ORM\AbstractQuery
     */
    public function setHint($name, $value)
    {
        $this->_hints[$name] = $value;

        return $this;
    }

    /**
     * Gets the value of a query hint. If the hint name is not recognized, FALSE is returned.
     *
     * @param string $name The name of the hint.
     * @return mixed The value of the hint or FALSE, if the hint name is not recognized.
     */
    public function getHint($name)
    {
        return isset($this->_hints[$name]) ? $this->_hints[$name] : false;
    }

    /**
     * Return the key value map of query hints that are currently set.
     *
     * @return array
     */
    public function getHints()
    {
        return $this->_hints;
    }

    /**
     * Executes the query and returns an IterableResult that can be used to incrementally
     * iterate over the result.
     *
     * @param array $params The query parameters.
     * @param integer $hydrationMode The hydration mode to use.
     * @return \Doctrine\ORM\Internal\Hydration\IterableResult
     */
    public function iterate(array $params = array(), $hydrationMode = null)
    {
        if ($hydrationMode !== null) {
            $this->setHydrationMode($hydrationMode);
        }

        if ($params) {
            $this->setParameters($params);
        }

        $stmt = $this->_doExecute();

        return $this->_em->newHydrator($this->_hydrationMode)->iterate(
            $stmt, $this->_resultSetMapping, $this->_hints
        );
    }

    /**
     * Executes the query.
     *
     * @param array $params Any additional query parameters.
     * @param integer $hydrationMode Processing mode to be used during the hydration process.
     * @return mixed
     */
    public function execute($params = array(), $hydrationMode = null)
    {
        if ($hydrationMode !== null) {
            $this->setHydrationMode($hydrationMode);
        }

        if ($params) {
            $this->setParameters($params);
        }

        $setCacheEntry = function() {};

        if ($this->_hydrationCacheProfile !== null) {
            list($cacheKey, $realCacheKey) = $this->getHydrationCacheId();

            $queryCacheProfile = $this->getHydrationCacheProfile();
            $cache             = $queryCacheProfile->getResultCacheDriver();
            $result            = $cache->fetch($cacheKey);

            if (isset($result[$realCacheKey])) {
                return $result[$realCacheKey];
            }

            if ( ! $result) {
                $result = array();
            }

            $setCacheEntry = function($data) use ($cache, $result, $cacheKey, $realCacheKey, $queryCacheProfile) {
                $result[$realCacheKey] = $data;
                $cache->save($cacheKey, $result, $queryCacheProfile->getLifetime());
            };
        }

        $stmt = $this->_doExecute();

        if (is_numeric($stmt)) {
            $setCacheEntry($stmt);

            return $stmt;
        }

        $data = $this->_em->getHydrator($this->_hydrationMode)->hydrateAll(
            $stmt, $this->_resultSetMapping, $this->_hints
        );

        $setCacheEntry($data);

        return $data;
    }

    /**
     * Get the result cache id to use to store the result set cache entry.
     * Will return the configured id if it exists otherwise a hash will be
     * automatically generated for you.
     *
     * @return array ($key, $hash)
     */
    protected function getHydrationCacheId()
    {
        $params = $this->getParameters();

        foreach ($params AS $key => $value) {
            $params[$key] = $this->processParameterValue($value);
        }

        $sql                    = $this->getSQL();
        $queryCacheProfile      = $this->getHydrationCacheProfile();
        $hints                  = $this->getHints();
        $hints['hydrationMode'] = $this->getHydrationMode();
        ksort($hints);

        return $queryCacheProfile->generateCacheKeys($sql, $params, $hints);
    }

    /**
     * Set the result cache id to use to store the result set cache entry.
     * If this is not explicitely set by the developer then a hash is automatically
     * generated for you.
     *
     * @param string $id
     * @return \Doctrine\ORM\AbstractQuery This query instance.
     */
    public function setResultCacheId($id)
    {
        $this->_queryCacheProfile = $this->_queryCacheProfile
            ? $this->_queryCacheProfile->setCacheKey($id)
            : new QueryCacheProfile(0, $id, $this->_em->getConfiguration()->getResultCacheImpl());

        return $this;
    }

    /**
     * Get the result cache id to use to store the result set cache entry if set.
     *
     * @deprecated
     * @return string
     */
    public function getResultCacheId()
    {
        return $this->_queryCacheProfile ? $this->_queryCacheProfile->getCacheKey() : null;
    }

    /**
     * Executes the query and returns a the resulting Statement object.
     *
     * @return \Doctrine\DBAL\Driver\Statement The executed database statement that holds the results.
     */
    abstract protected function _doExecute();

    /**
     * Cleanup Query resource when clone is called.
     *
     * @return void
     */
    public function __clone()
    {
        $this->_params = array();
        $this->_paramTypes = array();
        $this->_hints = array();
    }
}
