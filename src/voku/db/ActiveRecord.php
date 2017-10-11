<?php

namespace voku\db;

use Arrayy\Arrayy;
use voku\db\exceptions\ActiveRecordException;
use voku\db\exceptions\FetchingException;

/**
 * A simple implement of active record via Arrayy.
 *
 * @method $this select(string $dbProperty)
 * @method $this eq(string $dbProperty, string | null $value = null)
 * @method $this from(string $table)
 * @method $this where(string $where)
 * @method $this having(string $having)
 * @method $this limit(int $start, int | null $end = null)
 *
 * @method $this equal(string $dbProperty, string $value)
 * @method $this notEqual(string $dbProperty, string $value)
 * @method $this ne(string $dbProperty, string $value)
 * @method $this greaterThan(string $dbProperty, int $value)
 * @method $this gt(string $dbProperty, int $value)
 * @method $this lessThan(string $dbProperty, int $value)
 * @method $this lt(string $dbProperty, int $value)
 * @method $this greaterThanOrEqual(string $dbProperty, int $value)
 * @method $this ge(string $dbProperty, int $value)
 * @method $this gte(string $dbProperty, int $value)
 * @method $this lessThanOrEqual(string $dbProperty, int $value)
 * @method $this le(string $dbProperty, int $value)
 * @method $this lte(string $dbProperty, int $value)
 * @method $this between(string $dbProperty, array $value)
 * @method $this like(string $dbProperty, string $value)
 * @method $this in(string $dbProperty, array $value)
 * @method $this notIn(string $dbProperty, array $value)
 * @method $this isnull(string $dbProperty)
 * @method $this isNotNull(string $dbProperty)
 * @method $this notNull(string $dbProperty)
 */
abstract class ActiveRecord extends Arrayy
{
  /**
   * @var DB static
   */
  protected static $db;

  /**
   * @var array <p>Mapping the function name and the operator, to build Expressions in WHERE condition.</p>
   *
   * call the function like this:
   * <pre>
   *   $user->isNotNull()->eq('id', 1);
   * </pre>
   *
   * the result in SQL:
   * <pre>
   *   WHERE user.id IS NOT NULL AND user.id = :ph1
   * </pre>
   */
  protected static $operators = array(
      'equal'              => '=',
      'eq'                 => '=',
      'notequal'           => '<>',
      'ne'                 => '<>',
      'greaterthan'        => '>',
      'gt'                 => '>',
      'lessthan'           => '<',
      'lt'                 => '<',
      'greaterthanorequal' => '>=',
      'ge'                 => '>=',
      'gte'                => '>=',
      'lessthanorequal'    => '<=',
      'le'                 => '<=',
      'lte'                => '<=',
      'between'            => 'BETWEEN',
      'like'               => 'LIKE',
      'in'                 => 'IN',
      'notin'              => 'NOT IN',
      'isnull'             => 'IS NULL',
      'isnotnull'          => 'IS NOT NULL',
      'notnull'            => 'IS NOT NULL',
  );

  /**
   * @var array <p>Part of the SQL, mapping the function name and the operator to build SQL Part.</p>
   *
   * <br />
   *
   * call the function like this:
   * <pre>
   *      $user->orderBy('id DESC', 'name ASC')->limit(2, 1);
   * </pre>
   *
   * the result in SQL:
   * <pre>
   *      ORDER BY id DESC, name ASC LIMIT 2,1
   * </pre>
   */
  protected $sqlParts = array(
      'select' => 'SELECT',
      'from'   => 'FROM',
      'set'    => 'SET',
      'where'  => 'WHERE',
      'group'  => 'GROUP BY',
      'having' => 'HAVING',
      'order'  => 'ORDER BY',
      'limit'  => 'LIMIT',
      'top'    => 'TOP',
  );

  /**
   * @var array <p>The default sql expressions values.</p>
   */
  protected $defaultSqlExpressions = array(
      'expressions' => array(),
      'wrap'        => false,
      'select'      => null,
      'insert'      => null,
      'update'      => null,
      'set'         => null,
      'delete'      => 'DELETE ',
      'join'        => null,
      'from'        => null,
      'values'      => null,
      'where'       => null,
      'having'      => null,
      'limit'       => null,
      'order'       => null,
      'group'       => null,
  );

  /**
   * @var array <p>Stored the Expressions of the SQL.</p>
   */
  protected $sqlExpressions = array();

  /**
   * @var string <p>The table name in database.</p>
   */
  protected $table;

  /**
   * @var string  <p>The primary key of this ActiveRecord, just support single primary key.</p>
   */
  protected $primaryKeyName = 'id';

  /**
   * @var array <p>Stored the dirty data of this object, when call "insert" or "update" function, will write this data
   *      into database.</p>
   */
  protected $dirty = array();

  /**
   * @var bool
   */
  protected static $new_data_are_dirty = true;

  /**
   * @var array <p>Stored the params will bind to SQL when call DB->query().</p>
   */
  protected $params = array();

  /**
   * @var ActiveRecordExpressions[] <p>Stored the configure of the relation, or target of the relation.</p>
   */
  protected $relations = array();

  /**
   * @var int <p>The count of bind params, using this count and const "PREFIX" (:ph) to generate place holder in SQL.</p>
   */
  private static $count = 0;

  const BELONGS_TO = 'belongs_to';
  const HAS_MANY   = 'has_many';
  const HAS_ONE    = 'has_one';

  const PREFIX = ':active_record';

  /**
   * @return array
   */
  public function getParams()
  {
    return $this->params;
  }

  /**
   * @return string
   */
  public function getPrimaryKeyName()
  {
    return $this->primaryKeyName;
  }

  /**
   * @return mixed|null
   */
  public function getPrimaryKey()
  {
    $id = $this->{$this->primaryKeyName};
    if ($id) {
      return $id;
    }

    return null;
  }

  /**
   * @param mixed $primaryKey
   * @param bool  $dirty
   *
   * @return $this
   */
  public function setPrimaryKey($primaryKey, $dirty = true)
  {
    if ($dirty === true) {
      $this->dirty[$this->primaryKeyName] = $primaryKey;
    } else {
      $this->array[$this->primaryKeyName] = $primaryKey;
    }

    return $this;
  }

  /**
   * @return string
   */
  public function getTable()
  {
    return $this->table;
  }

  /**
   * Function to reset the $params and $sqlExpressions.
   *
   * @return $this
   */
  public function reset()
  {
    $this->params = array();
    $this->sqlExpressions = array();

    return $this;
  }

  /**
   * Reset the dirty data.
   *
   * @return $this
   */
  public function resetDirty()
  {
    $this->dirty = array();

    return $this;
  }

  /**
   * set the DB connection.
   *
   * @param DB $db
   */
  public static function setDb($db)
  {
    self::$db = $db;
  }

  /**
   * Function to find one record and assign in to current object.
   *
   * @param mixed $id <p>
   *                  If call this function using this param, we will find the record by using this id.
   *                  If not set, just find the first record in database.
   *                  </p>
   *
   * @return false|$this <p>
   *                     If we could find the record, assign in to current object and return it,
   *                     otherwise return "false".
   *                     </p>
   */
  public function fetch($id = null)
  {
    if ($id) {
      $this->reset()->eq($this->primaryKeyName, $id);
    }

    return self::query(
        $this->limit(1)->_buildSql(
            array(
                'select',
                'from',
                'join',
                'where',
                'group',
                'having',
                'order',
                'limit',
            )
        ),
        $this->params,
        $this->reset(),
        true
    );
  }

  /**
   * @param string $query
   *
   * @return $this[]
   */
  public function fetchManyByQuery($query)
  {
    $list = $this->fetchByQuery($query);

    if (!$list || empty($list)) {
      return array();
    }

    return $list;
  }

  /**
   * @param string $query
   *
   * @return $this|null
   */
  public function fetchOneByQuery($query)
  {
    $list = $this->fetchByQuery($query);

    if (!$list || empty($list)) {
      return null;
    }

    if (is_array($list) && count($list) > 0) {
      $this->array = $list[0]->getArray();
    } else {
      $this->array = $list->getArray();
    }

    return $this;
  }

  /**
   * @param mixed $id
   *
   * @return $this
   *
   * @throws FetchingException <p>Will be thrown, if we can not find the id.</p>
   */
  public function fetchById($id)
  {
    $obj = $this->fetchByIdIfExists($id);
    if ($obj === null) {
      throw new FetchingException("No row with primary key '$id' in table '$this->table'.");
    }

    return $obj;
  }

  /**
   * @param mixed $id
   *
   * @return $this|null
   */
  public function fetchByIdIfExists($id)
  {
    $list = $this->fetch($id);

    if (!$list || $list->isEmpty()) {
      return null;
    }

    return $list;
  }

  /**
   * @param array $ids
   *
   * @return $this[]
   */
  public function fetchByIds(array $ids)
  {
    if (empty($ids)) {
      return array();
    }

    $list = $this->fetchAll($ids);
    if (is_array($list) && count($list) > 0) {
      return $list;
    }

    return array();
  }

  /**
   * @param string $query
   *
   * @return $this[]|$this
   */
  public function fetchByQuery($query)
  {
    $list = self::query(
        $query,
        $this->params,
        $this->reset()
    );

    if (is_array($list)) {
      if (count($list) === 0) {
        return array();
      }

      return $list;
    }

    $this->array = $list->getArray();

    return $this;
  }

  /**
   * @param array $ids
   *
   * @return $this[]
   */
  public function fetchByIdsPrimaryKeyAsArrayIndex(array $ids)
  {
    $result = $this->fetchAll($ids);

    $resultNew = array();
    foreach ($result as $item) {
      $resultNew[$item->getPrimaryKey()] = $item;
    }

    return $resultNew;
  }

  /**
   * Function to find all records in database.
   *
   * @param array|null $ids <p>
   *                        If call this function using this param, we will find the record by using this id's.
   *                        If not set, just find all records in database.
   *                        </p>
   *
   * @return $this[]
   */
  public function fetchAll(array $ids = null)
  {
    if ($ids) {
      $this->reset()->in($this->primaryKeyName, $ids);
    }

    return self::query(
        $this->_buildSql(
            array(
                'select',
                'from',
                'join',
                'where',
                'groupBy',
                'having',
                'orderBy',
                'limit',
            )
        ),
        $this->params,
        $this->reset()
    );
  }

  /**
   * Function to delete current record in database.
   *
   * @return bool
   */
  public function delete()
  {
    $return = self::execute(
        $this->eq($this->primaryKeyName, $this->{$this->primaryKeyName})->_buildSql(
            array(
                'delete',
                'from',
                'where',
            )
        ),
        $this->params
    );

    if ($return !== false) {
      return true;
    }

    return false;
  }

  /**
   * @param string $primaryKeyName
   *
   * @return $this
   */
  public function setPrimaryKeyName($primaryKeyName)
  {
    $this->primaryKeyName = $primaryKeyName;

    return $this;
  }

  /**
   * @param string $table
   */
  public function setTable($table)
  {
    $this->table = $table;
  }

  /**
   * Function to build update SQL, and update current record in database, just write the dirty data into database.
   *
   * @return bool|int <p>
   *                  If update was successful, it will return the affected rows as int,
   *                  otherwise it will return false or true (if there are no dirty data).
   *                  </p>
   */
  public function update()
  {
    if (count($this->dirty) == 0) {
      return true;
    }

    foreach ($this->dirty as $field => $value) {
      $this->addCondition($field, '=', $value, ',', 'set');
    }

    $result = self::execute(
        $this->eq($this->primaryKeyName, $this->{$this->primaryKeyName})->_buildSql(
            array(
                'update',
                'set',
                'where',
            )
        ),
        $this->params
    );
    if ($result !== false) {
      $this->resetDirty();
      $this->reset();

      return $result;
    }

    return false;
  }

  /**
   * @return $this
   */
  public static function fetchEmpty()
  {
    $class = get_called_class();
    return new $class;
  }

  /**
   * Function to build insert SQL, and insert current record into database.
   *
   * @return bool|int <p>
   *                  If insert was successful, it will return the new id,
   *                  otherwise it will return false or true (if there are no dirty data).
   *                  </p>
   */
  public function insert()
  {
    if (!self::$db instanceof DB) {
      self::$db = DB::getInstance();
    }

    if (count($this->dirty) === 0) {
      return true;
    }

    $value = $this->_filterParam($this->dirty);
    $this->insert = new ActiveRecordExpressions(
        array(
            'operator' => 'INSERT INTO ' . $this->table,
            'target'   => new ActiveRecordExpressionsWrap(array('target' => array_keys($this->dirty))),
        )
    );
    $this->values = new ActiveRecordExpressions(
        array(
            'operator' => 'VALUES',
            'target'   => new ActiveRecordExpressionsWrap(array('target' => $value)),
        )
    );

    $result = self::execute($this->_buildSql(array('insert', 'values')), $this->params);
    if ($result !== false) {
      $this->{$this->primaryKeyName} = $result;

      $this->resetDirty();
      $this->reset();

      return $result;
    }

    return false;
  }

  /**
   * Helper function to copy an existing active record (and insert it into the database).
   *
   * @param bool $insert
   *
   * @return $this
   */
  public function copy($insert = true)
  {
    $new = clone $this;

    if ($insert) {
      $new->setPrimaryKey(null);
      $id = $new->insert();
      $new->setPrimaryKey($id);
    }

    return $new;
  }

  /**
   * Helper function to exec sql.
   *
   * @param string $sql   <p>The SQL need to be execute.</p>
   * @param array  $param <p>The param will be bind to the sql statement.</p>
   *
   * @return bool|int|Result              <p>
   *                                      "Result" by "<b>SELECT</b>"-queries<br />
   *                                      "int" (insert_id) by "<b>INSERT / REPLACE</b>"-queries<br />
   *                                      "int" (affected_rows) by "<b>UPDATE / DELETE</b>"-queries<br />
   *                                      "true" by e.g. "DROP"-queries<br />
   *                                      "false" on error
   *                                      </p>
   */
  public static function execute($sql, array $param = array())
  {
    if (!self::$db instanceof DB) {
      self::$db = DB::getInstance();
    }

    return self::$db->query($sql, $param);
  }

  /**
   * Helper function to query one record by sql and params.
   *
   * @param string            $sql    <p>
   *                                  The SQL query to find the record.
   *                                  </p>
   * @param array             $param  <p>
   *                                  The param will be bind to the $sql query.
   *                                  </p>
   * @param ActiveRecord|null $obj    <p>
   *                                  The object, if find record in database, we will assign the attributes into
   *                                  this object.
   *                                  </p>
   * @param bool              $single <p>
   *                                  If set to true, we will find record and fetch in current object, otherwise
   *                                  will find all records.
   *                                  </p>
   *
   * @return bool|$this|array
   */
  public static function query($sql, array $param = array(), ActiveRecord $obj = null, $single = false)
  {
    $result = self::execute($sql, $param);

    if ($result === false) {
      return false;
    }

    $useObject = is_object($obj);
    if ($useObject === true) {
      $called_class = $obj;
    } else {
      $called_class = get_called_class();
    }

    self::setNewDataAreDirty(false);

    if ($single) {
      $return = $result->fetchObject($called_class, null, true);
    } else {
      $return = $result->fetchAllObject($called_class, null);
    }

    self::setNewDataAreDirty(true);

    return $return;
  }

  /**
   * Helper function to get relation of this object.
   * There was three types of relations: {BELONGS_TO, HAS_ONE, HAS_MANY}
   *
   * @param string $name <p>The name of the relation (the array key from the definition).</p>
   *
   * @return mixed
   *
   * @throws ActiveRecordException <p>If the relation can't be found .</p>
   */
  protected function &getRelation($name)
  {
    $relation = $this->relations[$name];
    if (
        $relation instanceof self
        ||
        (
            is_array($relation)
            &&
            $relation[0] instanceof self
        )
    ) {
      return $relation;
    }

    /* @var $obj ActiveRecord */
    $obj = new $relation[1];

    $this->relations[$name] = $obj;
    if (isset($relation[3]) && is_array($relation[3])) {
      foreach ((array)$relation[3] as $func => $args) {
        call_user_func_array(array($obj, $func), (array)$args);
      }
    }

    $backref = isset($relation[4]) ? $relation[4] : '';
    $relationInstanceOfSelf = ($relation instanceof self);
    if (
        $relationInstanceOfSelf === false
        &&
        self::HAS_ONE == $relation[0]
    ) {

      $this->relations[$name] = $obj->eq($relation[2], $this->{$this->primaryKeyName})->fetch();

      if ($backref) {
        $this->relations[$name] && $backref && $obj->__set($backref, $this);
      }

    } elseif (
        is_array($relation)
        &&
        self::HAS_MANY == $relation[0]
    ) {

      $this->relations[$name] = $obj->eq($relation[2], $this->{$this->primaryKeyName})->fetchAll();
      if ($backref) {
        foreach ($this->relations[$name] as $o) {
          $o->__set($backref, $this);
        }
      }

    } elseif (
        $relationInstanceOfSelf === false
        &&
        self::BELONGS_TO == $relation[0]
    ) {

      $this->relations[$name] = $obj->eq($obj->primaryKeyName, $this->{$relation[2]})->fetch();

      if ($backref) {
        $this->relations[$name] && $backref && $obj->__set($backref, $this);
      }

    } else {
      throw new ActiveRecordException("Relation $name not found.");
    }

    return $this->relations[$name];
  }

  /**
   * Helper function to build SQL with sql parts.
   *
   * @param string       $n <p>The SQL part will be build.</p>
   * @param int          $i <p>The index of $n in $sql array.</p>
   * @param ActiveRecord $o <p>The reference to $this.</p>
   */
  private function _buildSqlCallback(&$n, $i, $o)
  {
    if (
        'select' === $n
        &&
        null === $o->$n
    ) {

      $n = strtoupper($n) . ' ' . $o->table . '.*';

    } elseif (
        (
            'update' === $n
            ||
            'from' === $n
        )
        &&
        null === $o->$n
    ) {

      $n = strtoupper($n) . ' ' . $o->table;

    } elseif ('delete' === $n) {

      $n = strtoupper($n) . ' ';

    } else {

      $n = (null !== $o->$n) ? $o->$n . ' ' : '';

    }
  }

  /**
   * Helper function to build SQL with sql parts.
   *
   * @param array $sqls <p>The SQL part will be build.</p>
   *
   * @return string
   */
  protected function _buildSql($sqls = array())
  {
    array_walk($sqls, array($this, '_buildSqlCallback'), $this);

    // DEBUG
    //echo 'SQL: ', implode(' ', $sqls), "\n", 'PARAMS: ', implode(', ', $this->params), "\n";

    return implode(' ', $sqls);
  }

  /**
   * Magic function to make calls witch in function mapping stored in $operators and $sqlPart.
   * also can call function of DB object.
   *
   * @param string $name <p>The name of the function.</p>
   * @param array  $args <p>The arguments of the function.</p>
   *
   * @return $this|mixed <p>Return the result of callback or the current object to make chain method calls.</p>
   *
   * @throws ActiveRecordException
   */
  public function __call($name, $args)
  {
    if (!self::$db instanceof DB) {
      self::$db = DB::getInstance();
    }

    $nameTmp = strtolower($name);

    if (array_key_exists($nameTmp, self::$operators)) {

      $this->addCondition(
          $args[0],
          self::$operators[$nameTmp],
          isset($args[1]) ? $args[1] : null,
          (is_string(end($args)) && 'or' === strtolower(end($args))) ? 'OR' : 'AND'
      );

    } elseif (array_key_exists($nameTmp = str_replace('by', '', $nameTmp), $this->sqlParts)) {

      $this->$name = new ActiveRecordExpressions(
          array(
              'operator' => $this->sqlParts[$nameTmp],
              'target'   => implode(', ', $args),
          )
      );

    } elseif (is_callable($callback = array(self::$db, $name))) {

      return call_user_func_array($callback, $args);

    } else {

      throw new ActiveRecordException("Method $name not exist.");

    }

    return $this;
  }

  /**
   * Make wrap when build the SQL expressions of WHERE.
   *
   * @param string $op <p>If given, this param will build one "ActiveRecordExpressionsWrap" and include the stored expressions add into WHERE,
   *                   otherwise it will stored the expressions into an array.</p>
   *
   * @return $this
   */
  public function wrap($op = null)
  {
    if (1 === func_num_args()) {
      $this->wrap = false;
      if (is_array($this->expressions) && count($this->expressions) > 0) {
        $this->_addCondition(
            new ActiveRecordExpressionsWrap(
                array(
                    'delimiter' => ' ',
                    'target'    => $this->expressions,
                )
            ), 'or' === strtolower($op) ? 'OR' : 'AND'
        );
      }
      $this->expressions = array();
    } else {
      $this->wrap = true;
    }

    return $this;
  }

  /**
   * Helper function to build place holder when make SQL expressions.
   *
   * @param mixed $value <p>The value will be bind to SQL, just store it in $this->params.</p>
   *
   * @return mixed $value
   */
  protected function _filterParam($value)
  {
    if (is_array($value)) {
      foreach ($value as $key => $val) {
        $this->params[$value[$key] = self::PREFIX . ++self::$count] = $val;
      }
    } elseif (is_string($value)) {
      $this->params[$ph = self::PREFIX . ++self::$count] = $value;
      $value = $ph;
    }

    return $value;
  }

  /**
   * Helper function to add condition into WHERE.
   *
   * @param string $field <p>The field name, the source of Expressions</p>
   * @param string $operator
   * @param mixed  $value <p>The target of the Expressions.</p>
   * @param string $op    <p>The operator to concat this Expressions into WHERE or SET statement.</p>
   * @param string $name  <p>The Expression will contact to.</p>
   */
  public function addCondition($field, $operator, $value, $op = 'AND', $name = 'where')
  {
    $value = $this->_filterParam($value);
    $exp = new ActiveRecordExpressions(
        array(
            'source'   => ('where' == $name ? $this->table . '.' : '') . $field,
            'operator' => $operator,
            'target'   => is_array($value)
                ? new ActiveRecordExpressionsWrap(
                    'between' === strtolower($operator)
                        ? array('target' => $value, 'start' => ' ', 'end' => ' ', 'delimiter' => ' AND ')
                        : array('target' => $value)
                ) : $value,
        )
    );
    if ($exp) {
      if (!$this->wrap) {
        $this->_addCondition($exp, $op, $name);
      } else {
        $this->_addExpression($exp, $op);
      }
    }
  }

  /**
   * Helper function to add condition into JOIN.
   *
   * @param string $table <p>The join table name.</p>
   * @param string $on    <p>The condition of ON.</p>
   * @param string $type  <p>The join type, like "LEFT", "INNER", "OUTER".</p>
   *
   * @return $this
   */
  public function join($table, $on, $type = 'LEFT')
  {
    $this->join = new ActiveRecordExpressions(
        array(
            'source'   => $this->join ?: '',
            'operator' => $type . ' JOIN',
            'target'   => new ActiveRecordExpressions(
                array('source' => $table, 'operator' => 'ON', 'target' => $on)
            ),
        )
    );

    return $this;
  }

  /**
   * helper function to make wrapper. Stored the expression in to array.
   *
   * @param ActiveRecordExpressions $exp      <p>The expression will be stored.</p>
   * @param string                  $operator <p>The operator to concat this Expressions into WHERE statement.</p>
   */
  protected function _addExpression($exp, $operator)
  {
    if (
        !is_array($this->expressions)
        ||
        count($this->expressions) === 0
    ) {
      $this->expressions = array($exp);
    } else {
      $this->expressions[] = new ActiveRecordExpressions(array('operator' => $operator, 'target' => $exp));
    }
  }

  /**
   * helper function to add condition into WHERE.
   *
   * @param ActiveRecordExpressions $exp      <p>The expression will be concat into WHERE or SET statement.</p>
   * @param string                  $operator <p>The operator to concat this Expressions into WHERE or SET statement.</p>
   * @param string                  $name     <p>The Expression will contact to.</p>
   */
  protected function _addCondition($exp, $operator, $name = 'where')
  {
    if (!$this->$name) {
      $this->$name = new ActiveRecordExpressions(array('operator' => strtoupper($name), 'target' => $exp));
    } else {
      $this->$name->target = new ActiveRecordExpressions(
          array(
              'source'   => $this->$name->target,
              'operator' => $operator,
              'target'   => $exp,
          )
      );
    }
  }

  /**
   * @return array
   */
  public function getDirty()
  {
    return $this->dirty;
  }

  /**
   * @return bool
   */
  public static function isNewDataAreDirty()
  {
    return self::$new_data_are_dirty;
  }

  /**
   * @param bool $bool
   */
  public static function setNewDataAreDirty($bool)
  {
    self::$new_data_are_dirty = (bool)$bool;
  }

  /**
   * Magic function to SET values of the current object.
   *
   * @param mixed $var
   * @param mixed $val
   */
  public function __set($var, $val)
  {
    if (
        array_key_exists($var, $this->sqlExpressions)
        ||
        array_key_exists($var, $this->defaultSqlExpressions)
    ) {

      $this->sqlExpressions[$var] = $val;

    } elseif (
        array_key_exists($var, $this->relations)
        &&
        $val instanceof self
    ) {

      $this->relations[$var] = $val;

    } else {

      $this->array[$var] = $val;

      if (self::$new_data_are_dirty === true) {
        $this->dirty[$var] = $val;
      }

    }
  }

  /**
   * Magic function to UNSET values of the current object.
   *
   * @param mixed $var
   */
  public function __unset($var)
  {
    if (array_key_exists($var, $this->sqlExpressions)) {
      unset($this->sqlExpressions[$var]);
    }

    if (isset($this->array[$var])) {
      unset($this->array[$var]);
    }

    if (isset($this->dirty[$var])) {
      unset($this->dirty[$var]);
    }
  }

  /**
   * Helper function for "GROUP BY".
   *
   * @param array $args
   *
   * @return $this
   */
  public function groupBy($args)
  {
    $this->__call('groupBy', func_get_args());

    return $this;
  }

  /**
   * Helper function for "ORDER BY".
   *
   * @param $args ...
   *
   * @return $this
   */
  public function orderBy($args)
  {
    $this->__call('orderBy', func_get_args());

    return $this;
  }

  /**
   * Magic function to GET the values of current object.
   *
   * @param $var
   *
   * @return mixed
   */
  public function &__get($var)
  {
    if (array_key_exists($var, $this->sqlExpressions)) {
      return $this->sqlExpressions[$var];
    }

    if (array_key_exists($var, $this->relations)) {
      return $this->getRelation($var);
    }

    if (isset($this->dirty[$var])) {
      return $this->dirty[$var];
    }

    return parent::__get($var);
  }
}
