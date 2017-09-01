<?php

namespace voku\db;

use Arrayy\Arrayy;

/**
 * Simple implement of active record in PHP.<br />
 * Using magic function to implement more smarty functions.<br />
 * Can using chain method calls, to build concise and compactness program.<br />
 *
 * @method $this select(string $stuff1, string | null $stuff2 = null)
 * @method $this eq(string $stuff1, string | null $stuff2 = null)
 * @method $this from(string $table)
 * @method $this where()
 * @method $this group()
 * @method $this having()
 * @method $this order()
 * @method $this limit(int $start, int | null $end = null)
 *
 * @method $this equal(string $stuff1, string $stuff2)
 * @method $this notequal(string $stuff1, string $stuff2)
 * @method $this ne(string $stuff1, string $stuff2)
 * @method $this greaterthan(string $stuff1, int $stuff2)
 * @method $this gt(string $stuff1, int $stuff2)
 * @method $this lessthan(string $stuff1, int $stuff2)
 * @method $this lt(string $stuff1, int $stuff2)
 * @method $this greaterthanorequal(string $stuff1, int $stuff2)
 * @method $this ge(string $stuff1, int $stuff2)
 * @method $this gte(string $stuff1, int $stuff2)
 * @method $this lessthanorequal(string $stuff1, int $stuff2)
 * @method $this le(string $stuff1, int $stuff2)
 * @method $this lte(string $stuff1, int $stuff2)
 * @method $this between(string $stuff1, array $stuff2)
 * @method $this like(string $stuff1, string $stuff2)
 * @method $this in(string $stuff1, array $stuff2)
 * @method $this notin(string $stuff1, array $stuff2)
 * @method $this isnull(string $stuff1)
 * @method $this isnotnull(string $stuff1)
 * @method $this notnull(string $stuff1)
 */
abstract class ActiveRecord extends Arrayy
{
  /**
   * @var DB static property to connect database.
   */
  public static $db;

  /**
   * @var array maping the function name and the operator, to build Expressions in WHERE condition.
   * <pre>user can call it like this:
   *      $user->isnotnull()->eq('id', 1);
   * will create Expressions can explain to SQL:
   *      WHERE user.id IS NOT NULL AND user.id = :ph1</pre>
   */
  public static $operators = array(
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
   * @var array Part of SQL, maping the function name and the operator to build SQL Part.
   * <pre>call function like this:
   *      $user->order('id desc', 'name asc')->limit(2,1);
   *  can explain to SQL:
   *      ORDER BY id desc, name asc limit 2,1</pre>
   */
  public static $sqlParts = array(
      'select'  => 'SELECT',
      'from'    => 'FROM',
      'set'     => 'SET',
      'where'   => 'WHERE',
      'group'   => 'GROUP BY',
      'groupby' => 'GROUP BY',
      'having'  => 'HAVING',
      'order'   => 'ORDER BY',
      'orderby' => 'ORDER BY',
      'limit'   => 'limit',
      'top'     => 'TOP',
  );

  /**
   * @var array Static property to stored the default Sql Expressions values.
   */
  public static $defaultSqlExpressions = array(
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
   * @var array Stored the Expressions of the SQL.
   */
  protected $sqlExpressions = array();

  /**
   * @var string  The table name in database.
   */
  public $table;

  /**
   * @var string  The primary key of this ActiveRecord, just suport single primary key.
   */
  public $primaryKey = 'id';

  /**
   * @var array Stored the drity data of this object, when call "insert" or "update" function, will write this data
   *      into database.
   */
  public $dirty = array();

  /**
   * @var array Stored the params will bind to SQL when call PDOStatement::execute(),
   */
  public $params = array();

  /**
   * @var Arrayy[] Stored the configure of the relation, or target of the relation.
   */
  public $relations = array();

  /**
   * @var int The count of bind params, using this count and const "PREFIX" (:ph) to generate place holder in SQL.
   */
  public static $count = 0;

  const BELONGS_TO = 'belongs_to';
  const HAS_MANY   = 'has_many';
  const HAS_ONE    = 'has_one';

  const PREFIX = ':ph';

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
   * function to SET or RESET the dirty data.
   *
   * @param array $dirty The dirty data will be set, or empty array to reset the dirty data.
   *
   * @return $this
   */
  public function dirty(array $dirty = array())
  {
    $this->array = array_merge($this->array, $this->dirty = $dirty);

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
   * function to find one record and assign in to current object.
   *
   * @param int $id If call this function using this param, will find record by using this id. If not set, just find
   *                the first record in database.
   *
   * @return bool|ActiveRecord if find record, assign in to current object and return it, other wise return "false".
   */
  public function fetch($id = null)
  {
    if ($id) {
      $this->reset()->eq($this->primaryKey, $id);
    }

    return self::_query(
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
   * Function to find all records in database.
   *
   * @return array return array of ActiveRecord
   */
  public function fetchAll()
  {
    return self::_query(
        $this->_buildSql(
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
    return self::execute(
        $this->eq($this->primaryKey, $this->{$this->primaryKey})->_buildSql(
            array(
                'delete',
                'from',
                'where',
            )
        ),
        $this->params
    );
  }

  /**
   * Function to build update SQL, and update current record in database, just write the dirty data into database.
   *
   * @return bool|ActiveRecord if update success return current object, other wise return false.
   */
  public function update()
  {
    if (count($this->dirty) == 0) {
      return true;
    }

    foreach ($this->dirty as $field => $value) {
      $this->addCondition($field, '=', $value, ',', 'set');
    }

    if (self::execute(
        $this->eq($this->primaryKey, $this->{$this->primaryKey})->_buildSql(
            array(
                'update',
                'set',
                'where',
            )
        ),
        $this->params
    )) {
      return $this->dirty()->reset();
    }

    return false;
  }

  /**
   * Function to build insert SQL, and insert current record into database.
   *
   * @return bool|ActiveRecord if insert success return current object, other wise return false.
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
    $this->insert = new Expressions(
        array(
            'operator' => 'INSERT INTO ' . $this->table,
            'target'   => new WrapExpressions(array('target' => array_keys($this->dirty))),
        )
    );
    $this->values = new Expressions(
        array(
            'operator' => 'VALUES',
            'target'   => new WrapExpressions(array('target' => $value)),
        )
    );

    $result = self::execute($this->_buildSql(array('insert', 'values')), $this->params);
    if ($result) {
      $this->{$this->primaryKey} = $result;

      return $this->dirty()->reset();
    }

    return false;
  }

  /**
   * Helper function to exec sql.
   *
   * @param string $sql   The SQL need to be execute.
   * @param array  $param The param will be bind to the sql statement.
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
   * @param string       $sql    The SQL to find record.
   * @param array        $param  The param will be bind to PDOStatement.
   * @param ActiveRecord $obj    The object, if find record in database, will assign the attributes in to this object.
   * @param bool         $single If set to true, will find record and fetch in current object, otherwise will find all
   *                             records.
   *
   * @return bool|ActiveRecord|array
   */
  public static function _query($sql, array $param = array(), $obj = null, $single = false)
  {
    $result = self::execute($sql, $param);

    if (!$result) {
      return false;
    }

    if ($obj && class_exists($obj)) {
      $called_class = $obj;
    } else {
      $called_class = get_called_class();
    }

    if ($single) {
      return $result->fetchObject($called_class);
    }

    return $result->fetchAllObject($called_class);
  }

  /**
   * Helper function to get relation of this object.
   * There was three types of relations: {BELONGS_TO, HAS_ONE, HAS_MANY}
   *
   * @param string $name The name of the relation, the array key when defind the relation.
   *
   * @return mixed
   *
   * @throws \Exception
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
    if (
        (!$relation instanceof self)
        &&
        self::HAS_ONE == $relation[0]
    ) {

      $this->relations[$name] = $obj->eq($relation[2], $this->{$this->primaryKey})->fetch();

      if ($backref) {
        $this->relations[$name] && $backref && $obj->__set($backref, $this);
      }

    } elseif (
        is_array($relation)
        &&
        self::HAS_MANY == $relation[0]
    ) {

      $this->relations[$name] = $obj->eq($relation[2], $this->{$this->primaryKey})->fetchAll();
      if ($backref) {
        foreach ($this->relations[$name] as $o) {
          $o->__set($backref, $this);
        }
      }

    } elseif (
        (!$relation instanceof self)
        &&
        self::BELONGS_TO == $relation[0]
    ) {

      $this->relations[$name] = $obj->eq($obj->primaryKey, $this->{$relation[2]})->fetch();

      if ($backref) {
        $this->relations[$name] && $backref && $obj->__set($backref, $this);
      }

    } else {
      throw new \Exception("Relation $name not found.");
    }

    return $this->relations[$name];
  }

  /**
   * Helper function to build SQL with sql parts.
   *
   * @param string       $n The SQL part will be build.
   * @param int          $i The index of $n in $sql array.
   * @param ActiveRecord $o The reference to $this
   */
  private function _buildSqlCallback(&$n, $i, $o)
  {
    if ('select' === $n && null == $o->$n) {
      $n = strtoupper($n) . ' ' . $o->table . '.*';
    } elseif (('update' === $n || 'from' === $n) && null == $o->$n) {
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
   * @param array $sqls The SQL part will be build.
   *
   * @return string
   */
  protected function _buildSql($sqls = array())
  {
    array_walk($sqls, array($this, '_buildSqlCallback'), $this);

    // DEBUG
    echo 'SQL: ', implode(' ', $sqls), "\n", "PARAMS: ", implode(', ', $this->params), "\n";

    return implode(' ', $sqls);
  }

  /**
   * Magic function to make calls witch in function mapping stored in $operators and $sqlPart.
   * also can call function of PDO object.
   *
   * @param string $name function name
   * @param array  $args The arguments of the function.
   *
   * @return $this|mixed Return the result of callback or the current object to make chain method calls.
   *
   * @throws \Exception
   */
  public function __call($name, $args)
  {
    if (!self::$db instanceof DB) {
      self::$db = DB::getInstance();
    }

    if (array_key_exists($name = strtolower($name), self::$operators)) {

      $this->addCondition($args[0], self::$operators[$name], isset($args[1]) ? $args[1] : null, (is_string(end($args)) && 'or' === strtolower(end($args))) ? 'OR' : 'AND');

    } else if (array_key_exists($name = str_replace('by', '', $name), self::$sqlParts)) {

      $this->$name = new Expressions(array('operator' => self::$sqlParts[$name], 'target' => implode(', ', $args)));

    } else if (is_callable($callback = array(self::$db, $name))) {

      return call_user_func_array($callback, $args);

    } else {

      throw new \Exception("Method $name not exist.");

    }

    return $this;
  }

  /**
   * Make wrap when build the SQL expressions of WHERE.
   *
   * @param string $op If give this param will build one WrapExpressions include the stored expressions add into WHERE.
   *                   otherwise wil stored the expressions into array.
   *
   * @return $this
   */
  public function wrap($op = null)
  {
    if (1 === func_num_args()) {
      $this->wrap = false;
      if (is_array($this->expressions) && count($this->expressions) > 0) {
        $this->_addCondition(
            new WrapExpressions(
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
   * @param mixed $value The value will bind to SQL, just store it in $this->params.
   *
   * @return mixed $value
   */
  protected function _filterParam($value)
  {
    if (is_array($value)) {
      foreach ($value as $key => $val) {
        $this->params[$value[$key] = self::PREFIX . ++self::$count] = $val;
      }
    } else if (is_string($value)) {
      $this->params[$ph = self::PREFIX . ++self::$count] = $value;
      $value = $ph;
    }

    return $value;
  }

  /**
   * Helper function to add condition into WHERE.
   * create the SQL Expressions.
   *
   * @param string $field The field name, the source of Expressions
   * @param string $operator
   * @param mixed  $value The target of the Expressions
   * @param string $op    The operator to concat this Expressions into WHERE or SET statement.
   * @param string $name  The Expression will contact to.
   */
  public function addCondition($field, $operator, $value, $op = 'AND', $name = 'where')
  {
    $value = $this->_filterParam($value);
    $exp = new Expressions(
        array(
            'source'   => ('where' == $name ? $this->table . '.' : '') . $field,
            'operator' => $operator,
            'target'   => is_array($value)
                ? new WrapExpressions(
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
   * helper function to add condition into JOIN.
   * create the SQL Expressions.
   *
   * @param string $table The join table name
   * @param string $on    The condition of ON
   * @param string $type  The join type, like "LEFT", "INNER", "OUTER"
   *
   * @return $this
   */
  public function join($table, $on, $type = 'LEFT')
  {
    $this->join = new Expressions(
        array(
            'source'   => $this->join ?: '',
            'operator' => $type . ' JOIN',
            'target'   => new Expressions(
                array('source' => $table, 'operator' => 'ON', 'target' => $on)
            ),
        )
    );

    return $this;
  }

  /**
   * helper function to make wrapper. Stored the expression in to array.
   *
   * @param Expressions $exp      The expression will be stored.
   * @param string      $operator The operator to concat this Expressions into WHERE statment.
   */
  protected function _addExpression($exp, $operator)
  {
    if (!is_array($this->expressions) || count($this->expressions) == 0) {
      $this->expressions = array($exp);
    } else {
      $this->expressions[] = new Expressions(array('operator' => $operator, 'target' => $exp));
    }
  }

  /**
   * helper function to add condition into WHERE.
   *
   * @param Expressions $exp      The expression will be concat into WHERE or SET statment.
   * @param string      $operator the operator to concat this Expressions into WHERE or SET statment.
   * @param string      $name     The Expression will contact to.
   */
  protected function _addCondition($exp, $operator, $name = 'where')
  {
    if (!$this->$name) {
      $this->$name = new Expressions(array('operator' => strtoupper($name), 'target' => $exp));
    } else {
      $this->$name->target = new Expressions(
          array(
              'source'   => $this->$name->target,
              'operator' => $operator,
              'target'   => $exp,
          )
      );
    }
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
        array_key_exists($var, self::$defaultSqlExpressions)
    ) {

      $this->sqlExpressions[$var] = $val;

    } else if (
        array_key_exists($var, $this->relations)
        &&
        $val instanceof self
    ) {

      $this->relations[$var] = $val;

    } else {

      $this->dirty[$var] = $this->array[$var] = $val;

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
