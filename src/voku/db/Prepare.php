<?php

namespace voku\db;

use voku\db\exceptions\DBGoneAwayException;
use voku\db\exceptions\QueryException;
use voku\helper\Bootup;

/**
 * Prepare: This class can handle the prepare-statement from the "DB"-class.
 *
 * @package   voku\db
 */
final class Prepare extends \mysqli_stmt
{

  /**
   * @var string $_sql - the unchanged query string provided to the constructor
   */
  private $_sql;

  /**
   * @var string $_sql_with_bound_parameters - the query string with bound parameters interpolated
   */
  private $_sql_with_bound_parameters;

  /**
   * @var bool
   */
  private $_use_bound_parameters_interpolated = false;

  /**
   * @var array $_boundParams - array of arrays containing values that have been bound to the query as parameters
   */
  private $_boundParams = array();

  /**
   * @var DB
   */
  private $_db;

  /**
   * @var Debug
   */
  private $_debug;

  /**
   * Prepare constructor.
   *
   * @param DB     $db
   * @param string $query
   */
  public function __construct(DB $db, $query)
  {
    $this->_db = $db;
    $this->_debug = $db->getDebugger();

    parent::__construct($db->getLink(), $query);

    $this->prepare($query);
  }

  /**
   * Prepare destructor.
   */
  public function __destruct()
  {
    $this->close();
  }

  /**
   * Combines the values stored in $this->boundParams into one array suitable for pushing as the input arguments to
   * parent::bind_param when used with call_user_func_array
   *
   * @return array
   */
  private function _buildArguments()
  {
    $arguments = array();
    $arguments[0] = '';

    foreach ($this->_boundParams as $param) {
      $arguments[0] .= $param['type'];
      $arguments[] =& $param['value'];
    }

    return $arguments;
  }

  /**
   * Escapes the supplied value.
   *
   * @param array $param
   *
   * @return array 0 => "$value" escaped<br />
   *               1 => "$valueForSqlWithBoundParameters" for insertion into the interpolated query string
   */
  private function _prepareValue(&$param)
  {
    $type = $param['type']; // 'i', 'b', 's', 'd'
    $value = $param['value'];

    /** @noinspection ReferenceMismatchInspection */
    $value = $this->_db->escape($value);

    if ($type === 's') {
      $valueForSqlWithBoundParameters = "'" . $value . "'";
    } elseif ($type === 'i') {
      $valueForSqlWithBoundParameters = (int)$value;
    } elseif ($type === 'd') {
      $valueForSqlWithBoundParameters = (double)$value;
    } else {
      $valueForSqlWithBoundParameters = $value;
    }

    return array($value, $valueForSqlWithBoundParameters);
  }

  /**
   * @return int
   */
  public function affected_rows()
  {
    return $this->affected_rows;
  }

  /**
   * This is a wrapper for "bind_param" what binds variables to a prepared statement as parameters. If you use this
   * wrapper, you can debug your query with e.g. "$this->get_sql_with_bound_parameters()".
   *
   * @param string $types <strong>i<strong> corresponding variable has type integer<br />
   *                      <strong>d</strong> corresponding variable has type double<br />
   *                      <strong>s</strong> corresponding variable has type string<br />
   *                      <strong>b</strong> corresponding variable is a blob and will be sent in packets
   *
   * INFO: We have to explicitly declare all parameters as references, otherwise it does not seem possible to pass them
   * on without losing the reference property.
   *
   * @param mixed  $v1
   * @param mixed  $v2
   * @param mixed  $v3
   * @param mixed  $v4
   * @param mixed  $v5
   * @param mixed  $v6
   * @param mixed  $v7
   * @param mixed  $v8
   * @param mixed  $v9
   * @param mixed  $v10
   * @param mixed  $v11
   * @param mixed  $v12
   * @param mixed  $v13
   * @param mixed  $v14
   * @param mixed  $v15
   * @param mixed  $v16
   * @param mixed  $v17
   * @param mixed  $v18
   * @param mixed  $v19
   * @param mixed  $v20
   * @param mixed  $v21
   * @param mixed  $v22
   * @param mixed  $v23
   * @param mixed  $v24
   * @param mixed  $v25
   * @param mixed  $v26
   * @param mixed  $v27
   * @param mixed  $v28
   * @param mixed  $v29
   * @param mixed  $v30
   * @param mixed  $v31
   * @param mixed  $v32
   * @param mixed  $v33
   * @param mixed  $v34
   * @param mixed  $v35
   *
   * @return mixed
   */
  public function bind_param_debug($types, &$v1 = null, &$v2 = null, &$v3 = null, &$v4 = null, &$v5 = null, &$v6 = null, &$v7 = null, &$v8 = null, &$v9 = null, &$v10 = null, &$v11 = null, &$v12 = null, &$v13 = null, &$v14 = null, &$v15 = null, &$v16 = null, &$v17 = null, &$v18 = null, &$v19 = null, &$v20 = null, &$v21 = null, &$v22 = null, &$v23 = null, &$v24 = null, &$v25 = null, &$v26 = null, &$v27 = null, &$v28 = null, &$v29 = null, &$v30 = null, &$v31 = null, &$v32 = null, &$v33 = null, &$v34 = null, &$v35 = null)
  {
    $this->_use_bound_parameters_interpolated = true;

    // debug_backtrace returns arguments by reference, see comments at http://php.net/manual/de/function.func-get-args.php
    if (Bootup::is_php('5.4')) {
      $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 1);
    } else {
      $trace = debug_backtrace();
    }

    $args =& $trace[0]['args'];
    $types = str_split($types);

    $args_count = count($args) - 1;
    $types_count = count($types);

    if ($args_count !== $types_count) {
      trigger_error('Number of variables doesn\'t match number of parameters in prepared statement', E_WARNING);

      return false;
    }

    $arg = 1;
    foreach ($types as $typeInner) {
      $val =& $args[$arg];
      $this->_boundParams[] = array(
          'type'  => $typeInner,
          'value' => &$val,
      );
      $arg++;
    }

    return true;
  }

  /**
   * @inheritdoc
   *
   * @return bool
   */
  public function execute_raw()
  {
    return parent::execute();
  }

  /**
   * Executes a prepared Query
   *
   * @link http://php.net/manual/en/mysqli-stmt.execute.php
   *
   * @return bool|int|Result   "Result" by "<b>SELECT</b>"-queries<br />
   *                           "int" (insert_id) by "<b>INSERT / REPLACE</b>"-queries<br />
   *                           "int" (affected_rows) by "<b>UPDATE / DELETE</b>"-queries<br />
   *                           "true" by e.g. "DROP"-queries<br />
   *                           "false" on error
   */
  public function execute()
  {
    if ($this->_use_bound_parameters_interpolated === true) {
      $this->interpolateQuery();
      call_user_func_array(array('parent', 'bind_param'), $this->_buildArguments());
    }

    $query_start_time = microtime(true);
    $result = parent::execute();
    $query_duration = microtime(true) - $query_start_time;

    if ($result === true) {

      // "INSERT" || "REPLACE"
      if (preg_match('/^\s*"?(INSERT|REPLACE)\s+/i', $this->_sql)) {
        $insert_id = (int)$this->insert_id;
        $this->_debug->logQuery($this->_sql_with_bound_parameters, $query_duration, $insert_id);

        return $insert_id;
      }

      // "UPDATE" || "DELETE"
      if (preg_match('/^\s*"?(UPDATE|DELETE)\s+/i', $this->_sql)) {
        $affected_rows = (int)$this->affected_rows;
        $this->_debug->logQuery($this->_sql_with_bound_parameters, $query_duration, $affected_rows);

        return $affected_rows;
      }

      // "SELECT"
      if (preg_match('/^\s*"?(SELECT)\s+/i', $this->_sql)) {
        $result = $this->get_result();
        $num_rows = (int)$result->num_rows;
        $this->_debug->logQuery($this->_sql_with_bound_parameters, $query_duration, $num_rows);

        return new Result($this->_sql_with_bound_parameters, $result);
      }

      // log the ? query
      $this->_debug->logQuery($this->_sql_with_bound_parameters, $query_duration, 0);

      return true;
    }

    // log the error query
    $this->_debug->logQuery($this->_sql_with_bound_parameters, $query_duration, 0, true);

    return $this->queryErrorHandling($this->error, $this->_sql_with_bound_parameters);
  }

  /**
   * Prepare an SQL statement for execution
   *
   * @link  http://php.net/manual/en/mysqli-stmt.prepare.php
   *
   * @param string $query <p>
   *                      The query, as a string. It must consist of a single SQL statement.
   *                      </p>
   *                      <p>
   *                      You can include one or more parameter markers in the SQL statement by
   *                      embedding question mark (?) characters at the
   *                      appropriate positions.
   *                      </p>
   *                      <p>
   *                      You should not add a terminating semicolon or \g
   *                      to the statement.
   *                      </p>
   *                      <p>
   *                      The markers are legal only in certain places in SQL statements.
   *                      For example, they are allowed in the VALUES() list of an INSERT statement
   *                      (to specify column values for a row), or in a comparison with a column in
   *                      a WHERE clause to specify a comparison value.
   *                      </p>
   *                      <p>
   *                      However, they are not allowed for identifiers (such as table or column names),
   *                      in the select list that names the columns to be returned by a SELECT statement),
   *                      or to specify both operands of a binary operator such as the =
   *                      equal sign. The latter restriction is necessary because it would be impossible
   *                      to determine the parameter type. In general, parameters are legal only in Data
   *                      Manipulation Language (DML) statements, and not in Data Definition Language
   *                      (DDL) statements.
   *                      </p>
   *
   * @return bool false on error
   * @since 5.0
   */
  public function prepare($query)
  {
    $this->_sql = $query;
    $this->_sql_with_bound_parameters = $query;

    if (!$this->_db->isReady()) {
      return false;
    }

    if (!$query || $query === '') {
      $this->_debug->displayError('Can not prepare an empty query.', false);

      return false;
    }

    $bool = parent::prepare($query);

    if ($bool === false) {
      $this->_debug->displayError('Can not prepare query: ' . $query . ' | ' . $this->error, false);
    }

    return $bool;
  }

  /**
   * Ger the bound parameters from sql-query as array, if you use the "$this->bind_param_debug()" method.
   *
   * @return array
   */
  public function get_bound_params()
  {
    return $this->_boundParams;
  }

  /**
   * @return string
   */
  public function get_sql()
  {
    return $this->_sql;
  }

  /**
   * Get the sql-query with bound parameters, if you use the "$this->bind_param_debug()" method.
   *
   * @return string
   */
  public function get_sql_with_bound_parameters()
  {
    return $this->_sql_with_bound_parameters;
  }

  /**
   * @return int
   */
  public function insert_id()
  {
    return $this->insert_id;
  }

  /**
   * Copies $this->_sql then replaces bound markers with associated values ($this->_sql is not modified
   * but the resulting query string is assigned to $this->sql_bound_parameters)
   *
   * @return string $testQuery - interpolated db query string
   */
  private function interpolateQuery()
  {
    $testQuery = $this->_sql;
    if ($this->_boundParams) {
      foreach ($this->_boundParams as &$param) {
        $values = $this->_prepareValue($param);

        // set new values
        $param['value'] = $values[0];
        // we need to replace the question mark "?" here
        $values[1] = str_replace('?', '###simple_mysqli__prepare_question_mark###', $values[1]);
        // build the query (only for debugging)
        $testQuery = preg_replace("/\?/", $values[1], $testQuery, 1);
      }
      unset($param);
      $testQuery = str_replace('###simple_mysqli__prepare_question_mark###', '?', $testQuery);
    }
    $this->_sql_with_bound_parameters = $testQuery;

    return $testQuery;
  }

  /**
   * Error-handling for the sql-query.
   *
   * @param string $errorMsg
   * @param string $sql
   *
   * @throws QueryException
   * @throws DBGoneAwayException
   *
   * @return bool
   */
  private function queryErrorHandling($errorMsg, $sql)
  {
    if ($errorMsg === 'DB server has gone away' || $errorMsg === 'MySQL server has gone away') {
      static $RECONNECT_COUNTER;

      // exit if we have more then 3 "DB server has gone away"-errors
      if ($RECONNECT_COUNTER > 3) {
        $this->_debug->mailToAdmin('DB-Fatal-Error', $errorMsg . ":\n<br />" . $sql, 5);
        throw new DBGoneAwayException($errorMsg);
      }

      $this->_debug->mailToAdmin('DB-Error', $errorMsg . ":\n<br />" . $sql);

      // reconnect
      $RECONNECT_COUNTER++;
      $this->_db->reconnect(true);

      // re-run the current query
      return $this->execute();
    }

    $this->_debug->mailToAdmin('SQL-Error', $errorMsg . ":\n<br />" . $sql);

    // this query returned an error, we must display it (only for dev) !!!
    $this->_debug->displayError($errorMsg . ' | ' . $sql);

    return false;
  }

}
