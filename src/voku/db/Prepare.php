<?php

namespace voku\db;

/**
 * Prepare: this handles the prepare-statement from "DB"-Class
 *
 * @package   voku\db
 */
final class Prepare extends \mysqli_stmt
{

  /**
   * @var string
   */
  private $_sql;

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
   * Executes a prepared Query
   *
   * @link  http://php.net/manual/en/mysqli-stmt.execute.php
   * @return bool true on success or false on failure.
   * @since 5.0
   */
  public function execute()
  {
    $query_start_time = microtime(true);
    $bool = parent::execute();
    $query_duration = microtime(true) - $query_start_time;

    $this->_debug->logQuery($this->_sql, $query_duration, $this->num_rows);

    if ($bool === false) {
      $this->queryErrorHandling($this->error, $this->_sql);
    }

    return $bool;
  }

  /**
   * Error-handling for the sql-query.
   *
   * @param string $errorMsg
   * @param string $sql
   *
   * @throws \Exception
   */
  protected function queryErrorHandling($errorMsg, $sql)
  {
    if ($errorMsg === 'DB server has gone away' || $errorMsg === 'MySQL server has gone away') {
      static $reconnectCounter;

      // exit if we have more then 3 "DB server has gone away"-errors
      if ($reconnectCounter > 3) {
        $this->_debug->mailToAdmin('SQL-Fatal-Error', $errorMsg . ":\n<br />" . $sql, 5);
        throw new \Exception($errorMsg);
      } else {
        $this->_debug->mailToAdmin('SQL-Error', $errorMsg . ":\n<br />" . $sql);

        // reconnect
        $reconnectCounter++;
        $this->_db->reconnect(true);

        // re-run the current query
        $this->execute();
      }
    } else {
      $this->_debug->mailToAdmin('SQL-Warning', $errorMsg . ":\n<br />" . $sql);

      // this query returned an error, we must display it (only for dev) !!!
      $this->_debug->displayError($errorMsg . ' | ' . $sql);
    }
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
   * @return mixed true on success or false on failure.
   * @since 5.0
   */
  public function prepare($query)
  {
    $this->_sql = $query;

    if (!$this->_db->isReady()) {
      return false;
    }

    if (!$query || $query === '') {
      $this->_debug->displayError('Can\'t prepare an empty Query', false);

      return false;
    }

    $bool = parent::prepare($query);

    if ($bool === false) {
      $this->_debug->displayError('Can\'t prepare Query: ' . $query . ' | ' . $this->error, false);
    }

    return true;
  }
}
