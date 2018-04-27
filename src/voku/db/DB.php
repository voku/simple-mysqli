<?php

declare(strict_types=1);

namespace voku\db;

use voku\cache\Cache;
use voku\db\exceptions\DBConnectException;
use voku\db\exceptions\DBGoneAwayException;
use voku\db\exceptions\QueryException;
use voku\helper\UTF8;

/**
 * DB: This class can handle DB queries via MySQLi.
 *
 * @package voku\db
 */
final class DB
{

  /**
   * @var int
   */
  public $query_count = 0;

  /**
   * @var \mysqli|null
   */
  private $link;

  /**
   * @var bool
   */
  private $connected = false;

  /**
   * @var array
   */
  private $mysqlDefaultTimeFunctions;

  /**
   * @var string
   */
  private $hostname = '';

  /**
   * @var string
   */
  private $username = '';

  /**
   * @var string
   */
  private $password = '';

  /**
   * @var string
   */
  private $database = '';

  /**
   * @var int
   */
  private $port = 3306;

  /**
   * @var string
   */
  private $charset = 'utf8';

  /**
   * @var string
   */
  private $socket = '';

  /**
   * @var bool
   */
  private $session_to_db = false;

  /**
   * @var bool
   */
  private $_in_transaction = false;

  /**
   * @var bool
   */
  private $_convert_null_to_empty_string = false;

  /**
   * @var bool
   */
  private $_ssl = false;

  /**
   * The path name to the key file
   *
   * @var string
   */
  private $_clientkey;

  /**
   * The path name to the certificate file
   *
   * @var string
   */
  private $_clientcert;

  /**
   * The path name to the certificate authority file
   *
   * @var string
   */
  private $_cacert;

  /**
   * @var Debug
   */
  private $_debug;

  /**
   * __construct()
   *
   * @param string $hostname
   * @param string $username
   * @param string $password
   * @param string $database
   * @param int    $port
   * @param string $charset
   * @param bool   $exit_on_error         <p>Throw a 'Exception' when a query failed, otherwise it will return 'false'.
   *                                      Use false to disable it.</p>
   * @param bool   $echo_on_error         <p>Echo the error if "checkForDev()" returns true.
   *                                      Use false to disable it.</p>
   * @param string $logger_class_name
   * @param string $logger_level          <p>'TRACE', 'DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'</p>
   * @param array  $extra_config          <p>
   *                                      'session_to_db' => bool<br>
   *                                      'socket'        => 'string (path)'<br>
   *                                      'ssl'           => bool<br>
   *                                      'clientkey'     => 'string (path)'<br>
   *                                      'clientcert'    => 'string (path)'<br>
   *                                      'cacert'        => 'string (path)'<br>
   *                                      </p>
   */
  private function __construct(string $hostname, string $username, string $password, string $database, $port, string $charset, bool $exit_on_error, bool $echo_on_error, string $logger_class_name, string $logger_level, array $extra_config = [])
  {
    $this->_debug = new Debug($this);

    $this->_loadConfig(
        $hostname,
        $username,
        $password,
        $database,
        $port,
        $charset,
        $exit_on_error,
        $echo_on_error,
        $logger_class_name,
        $logger_level,
        $extra_config
    );

    $this->connect();

    $this->mysqlDefaultTimeFunctions = [
      // Returns the current date.
      'CURDATE()',
      // CURRENT_DATE	| Synonyms for CURDATE()
      'CURRENT_DATE()',
      // CURRENT_TIME	| Synonyms for CURTIME()
      'CURRENT_TIME()',
      // CURRENT_TIMESTAMP | Synonyms for NOW()
      'CURRENT_TIMESTAMP()',
      // Returns the current time.
      'CURTIME()',
      // Synonym for NOW()
      'LOCALTIME()',
      // Synonym for NOW()
      'LOCALTIMESTAMP()',
      // Returns the current date and time.
      'NOW()',
      // Returns the time at which the function executes.
      'SYSDATE()',
      // Returns a UNIX timestamp.
      'UNIX_TIMESTAMP()',
      // Returns the current UTC date.
      'UTC_DATE()',
      // Returns the current UTC time.
      'UTC_TIME()',
      // Returns the current UTC date and time.
      'UTC_TIMESTAMP()',
    ];
  }

  /**
   * Prevent the instance from being cloned.
   *
   * @return void
   */
  private function __clone()
  {
  }

  /**
   * __destruct
   *
   */
  public function __destruct()
  {
    // close the connection only if we don't save PHP-SESSION's in DB
    if ($this->session_to_db === false) {
      $this->close();
    }
  }

  /**
   * @param null|string $sql
   * @param array       $bindings
   *
   * @return bool|int|Result|DB           <p>
   *                                      "DB" by "$sql" === null<br />
   *                                      "Result" by "<b>SELECT</b>"-queries<br />
   *                                      "int" (insert_id) by "<b>INSERT / REPLACE</b>"-queries<br />
   *                                      "int" (affected_rows) by "<b>UPDATE / DELETE</b>"-queries<br />
   *                                      "true" by e.g. "DROP"-queries<br />
   *                                      "false" on error
   *                                      </p>
   */
  public function __invoke(string $sql = null, array $bindings = [])
  {
    return null !== $sql ? $this->query($sql, $bindings) : $this;
  }

  /**
   * __wakeup
   *
   * @return void
   */
  public function __wakeup()
  {
    $this->reconnect();
  }

  /**
   * Load the config from the constructor.
   *
   * @param string $hostname
   * @param string $username
   * @param string $password
   * @param string $database
   * @param int    $port                  <p>default is (int)3306</p>
   * @param string $charset               <p>default is 'utf8' or 'utf8mb4' (if supported)</p>
   * @param bool   $exit_on_error         <p>Throw a 'Exception' when a query failed, otherwise it will return 'false'.
   *                                      Use false to disable it.</p>
   * @param bool   $echo_on_error         <p>Echo the error if "checkForDev()" returns true.
   *                                      Use false to disable it.</p>
   * @param string $logger_class_name
   * @param string $logger_level
   * @param array  $extra_config          <p>
   *                                      'session_to_db' => false|true<br>
   *                                      'socket' => 'string (path)'<br>
   *                                      'ssl' => 'bool'<br>
   *                                      'clientkey' => 'string (path)'<br>
   *                                      'clientcert' => 'string (path)'<br>
   *                                      'cacert' => 'string (path)'<br>
   *                                      </p>
   *
   * @return bool
   */
  private function _loadConfig(
      string $hostname,
      string $username,
      string $password,
      string $database,
      $port,
      string $charset,
      bool $exit_on_error,
      bool $echo_on_error,
      string $logger_class_name,
      string $logger_level,
      array $extra_config = []
  ): bool
  {
    $this->hostname = $hostname;
    $this->username = $username;
    $this->password = $password;
    $this->database = $database;

    if ($charset) {
      $this->charset = $charset;
    }

    if ($port) {
      $this->port = (int)$port;
    } else {
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      $this->port = (int)@ini_get('mysqli.default_port');
    }

    // fallback
    if (!$this->port) {
      $this->port = 3306;
    }

    if (!$this->socket) {
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      $this->socket = @ini_get('mysqli.default_socket');
    }

    $this->_debug->setExitOnError($exit_on_error);
    $this->_debug->setEchoOnError($echo_on_error);

    $this->_debug->setLoggerClassName($logger_class_name);
    $this->_debug->setLoggerLevel($logger_level);

    if (\is_array($extra_config) === true) {

      $this->setConfigExtra($extra_config);

    } else {

      // only for backward compatibility
      $this->session_to_db = (boolean)$extra_config;

    }

    return $this->showConfigError();
  }

  /**
   * @param array $extra_config           <p>
   *                                      'session_to_db' => false|true<br>
   *                                      'socket' => 'string (path)'<br>
   *                                      'ssl' => 'bool'<br>
   *                                      'clientkey' => 'string (path)'<br>
   *                                      'clientcert' => 'string (path)'<br>
   *                                      'cacert' => 'string (path)'<br>
   *                                      </p>
   */
  public function setConfigExtra(array $extra_config)
  {
    if (isset($extra_config['session_to_db'])) {
      $this->session_to_db = (boolean)$extra_config['session_to_db'];
    }

    if (isset($extra_config['socket'])) {
      $this->socket = $extra_config['socket'];
    }

    if (isset($extra_config['ssl'])) {
      $this->_ssl = $extra_config['ssl'];
    }

    if (isset($extra_config['clientkey'])) {
      $this->_clientkey = $extra_config['clientkey'];
    }

    if (isset($extra_config['clientcert'])) {
      $this->_clientcert = $extra_config['clientcert'];
    }

    if (isset($extra_config['cacert'])) {
      $this->_cacert = $extra_config['cacert'];
    }
  }

  /**
   * Parses arrays with value pairs and generates SQL to use in queries.
   *
   * @param array  $arrayPair
   * @param string $glue <p>This is the separator.</p>
   *
   * @return string
   *
   * @internal
   */
  public function _parseArrayPair(array $arrayPair, string $glue = ','): string
  {
    // init
    $sql = '';

    if (\count($arrayPair) === 0) {
      return '';
    }

    $arrayPairCounter = 0;
    foreach ($arrayPair as $_key => $_value) {
      $_connector = '=';
      $_glueHelper = '';
      $_key_upper = \strtoupper($_key);

      if (\strpos($_key_upper, ' NOT') !== false) {
        $_connector = 'NOT';
      }

      if (\strpos($_key_upper, ' IS') !== false) {
        $_connector = 'IS';
      }

      if (\strpos($_key_upper, ' IS NOT') !== false) {
        $_connector = 'IS NOT';
      }

      if (\strpos($_key_upper, ' IN') !== false) {
        $_connector = 'IN';
      }

      if (\strpos($_key_upper, ' NOT IN') !== false) {
        $_connector = 'NOT IN';
      }

      if (\strpos($_key_upper, ' BETWEEN') !== false) {
        $_connector = 'BETWEEN';
      }

      if (\strpos($_key_upper, ' NOT BETWEEN') !== false) {
        $_connector = 'NOT BETWEEN';
      }

      if (\strpos($_key_upper, ' LIKE') !== false) {
        $_connector = 'LIKE';
      }

      if (\strpos($_key_upper, ' NOT LIKE') !== false) {
        $_connector = 'NOT LIKE';
      }

      if (\strpos($_key_upper, ' >') !== false && \strpos($_key_upper, ' =') === false) {
        $_connector = '>';
      }

      if (\strpos($_key_upper, ' <') !== false && \strpos($_key_upper, ' =') === false) {
        $_connector = '<';
      }

      if (\strpos($_key_upper, ' >=') !== false) {
        $_connector = '>=';
      }

      if (\strpos($_key_upper, ' <=') !== false) {
        $_connector = '<=';
      }

      if (\strpos($_key_upper, ' <>') !== false) {
        $_connector = '<>';
      }

      if (\strpos($_key_upper, ' OR') !== false) {
        $_glueHelper = 'OR';
      }

      if (\strpos($_key_upper, ' AND') !== false) {
        $_glueHelper = 'AND';
      }

      if (\is_array($_value) === true) {
        foreach ($_value as $oldKey => $oldValue) {
          $_value[$oldKey] = $this->secure($oldValue);
        }

        if ($_connector === 'NOT IN' || $_connector === 'IN') {
          $_value = '(' . \implode(',', $_value) . ')';
        } elseif ($_connector === 'NOT BETWEEN' || $_connector === 'BETWEEN') {
          $_value = '(' . \implode(' AND ', $_value) . ')';
        }

      } else {
        $_value = $this->secure($_value);
      }

      $quoteString = $this->quote_string(
          \trim(
              \str_ireplace(
                  [
                      $_connector,
                      $_glueHelper,
                  ],
                  '',
                  $_key
              )
          )
      );

      $_value = (array)$_value;

      if (!$_glueHelper) {
        $_glueHelper = $glue;
      }

      $tmpCounter = 0;
      foreach ($_value as $valueInner) {

        $_glueHelperInner = $_glueHelper;

        if ($arrayPairCounter === 0) {

          if ($tmpCounter === 0 && $_glueHelper === 'OR') {
            $_glueHelperInner = '1 = 1 AND ('; // first "OR"-query glue
          } elseif ($tmpCounter === 0) {
            $_glueHelperInner = ''; // first query glue e.g. for "INSERT"-query -> skip the first ","
          }

        } elseif ($tmpCounter === 0 && $_glueHelper === 'OR') {
          $_glueHelperInner = 'AND ('; // inner-loop "OR"-query glue
        }

        if (\is_string($valueInner) && $valueInner === '') {
          $valueInner = "''";
        }

        $sql .= ' ' . $_glueHelperInner . ' ' . $quoteString . ' ' . $_connector . ' ' . $valueInner . " \n";
        $tmpCounter++;
      }

      if ($_glueHelper === 'OR') {
        $sql .= ' ) ';
      }

      $arrayPairCounter++;
    }

    return $sql;
  }

  /**
   * _parseQueryParams
   *
   * @param string $sql
   * @param array  $params
   *
   * @return array <p>with the keys -> 'sql', 'params'</p>
   */
  private function _parseQueryParams(string $sql, array $params = []): array
  {
    $offset = \strpos($sql, '?');

    // is there anything to parse?
    if (
        $offset === false
        ||
        \count($params) === 0
    ) {
      return ['sql' => $sql, 'params' => $params];
    }

    foreach ($params as $key => $param) {

      // use this only for not named parameters
      if (!is_int($key)) {
        continue;
      }

      if ($offset === false) {
        continue;
      }

      $replacement = $this->secure($param);

      unset($params[$key]);

      $sql = \substr_replace($sql, $replacement, $offset, 1);
      $offset = \strpos($sql, '?', $offset + \strlen((string)$replacement));
    }

    return ['sql' => $sql, 'params' => $params];
  }

  /**
   * Gets the number of affected rows in a previous MySQL operation.
   *
   * @return int
   */
  public function affected_rows(): int
  {
    return \mysqli_affected_rows($this->link);
  }

  /**
   * Begins a transaction, by turning off auto commit.
   *
   * @return bool <p>This will return true or false indicating success of transaction</p>
   */
  public function beginTransaction(): bool
  {
    if ($this->_in_transaction === true) {
      $this->_debug->displayError('Error: mysql server already in transaction!', false);

      return false;
    }

    $this->clearErrors(); // needed for "$this->endTransaction()"
    $this->_in_transaction = true;
    $return = \mysqli_autocommit($this->link, false);
    if ($return === false) {
      $this->_in_transaction = false;
    }

    return $return;
  }

  /**
   * Clear the errors in "_debug->_errors".
   *
   * @return bool
   */
  public function clearErrors(): bool
  {
    return $this->_debug->clearErrors();
  }

  /**
   * Closes a previously opened database connection.
   */
  public function close(): bool
  {
    $this->connected = false;

    if (
        $this->link
        &&
        $this->link instanceof \mysqli
    ) {
      $result = \mysqli_close($this->link);
      $this->link = null;

      return $result;
    }

    return false;
  }

  /**
   * Commits the current transaction and end the transaction.
   *
   * @return bool <p>Boolean true on success, false otherwise.</p>
   */
  public function commit(): bool
  {
    if ($this->_in_transaction === false) {
      $this->_debug->displayError('Error: mysql server is not in transaction!', false);

      return false;
    }

    $return = \mysqli_commit($this->link);
    \mysqli_autocommit($this->link, true);
    $this->_in_transaction = false;

    return $return;
  }

  /**
   * Open a new connection to the MySQL server.
   *
   * @return bool
   *
   * @throws DBConnectException
   */
  public function connect(): bool
  {
    if ($this->isReady()) {
      return true;
    }

    $flags = null;

    \mysqli_report(MYSQLI_REPORT_STRICT);
    try {
      $this->link = \mysqli_init();

      if (Helper::isMysqlndIsUsed() === true) {
        \mysqli_options($this->link, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
      }

      if ($this->_ssl === true) {

        if (empty($this->clientcert)) {
          throw new DBConnectException('Error connecting to mysql server: clientcert not defined');
        }

        if (empty($this->clientkey)) {
          throw new DBConnectException('Error connecting to mysql server: clientkey not defined');
        }

        if (empty($this->cacert)) {
          throw new DBConnectException('Error connecting to mysql server: cacert not defined');
        }

        \mysqli_options($this->link, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);

        /** @noinspection PhpParamsInspection */
        \mysqli_ssl_set(
            $this->link,
            $this->_clientkey,
            $this->_clientcert,
            $this->_cacert,
            null,
            null
        );

        $flags = MYSQLI_CLIENT_SSL;
      }

      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      $this->connected = @\mysqli_real_connect(
          $this->link,
          $this->hostname,
          $this->username,
          $this->password,
          $this->database,
          $this->port,
          $this->socket,
          (int)$flags
      );

    } catch (\Exception $e) {
      $error = 'Error connecting to mysql server: ' . $e->getMessage();
      $this->_debug->displayError($error, true);
      throw new DBConnectException($error, 100, $e);
    }
    \mysqli_report(MYSQLI_REPORT_OFF);

    $errno = \mysqli_connect_errno();
    if (!$this->connected || $errno) {
      $error = 'Error connecting to mysql server: ' . \mysqli_connect_error() . ' (' . $errno . ')';
      $this->_debug->displayError($error, true);
      throw new DBConnectException($error, 101);
    }

    $this->set_charset($this->charset);

    return $this->isReady();
  }

  /**
   * Execute a "delete"-query.
   *
   * @param string       $table
   * @param string|array $where
   * @param string|null  $databaseName <p>Use <strong>null</strong> if you will use the current database.</p>
   *
   * @return false|int <p>false on error</p>
   *
   *    * @throws QueryException
   */
  public function delete(string $table, $where, string $databaseName = null)
  {
    // init
    $table = \trim($table);

    if ($table === '') {
      $this->_debug->displayError('Invalid table name, table name in empty.', false);

      return false;
    }

    if (\is_string($where)) {
      $WHERE = $this->escape($where, false);
    } elseif (\is_array($where)) {
      $WHERE = $this->_parseArrayPair($where, 'AND');
    } else {
      $WHERE = '';
    }

    if ($databaseName) {
      $databaseName = $this->quote_string(\trim($databaseName)) . '.';
    }

    $sql = 'DELETE FROM ' . $databaseName . $this->quote_string($table) . " WHERE ($WHERE)";

    return $this->query($sql);
  }

  /**
   * Ends a transaction and commits if no errors, then ends autocommit.
   *
   * @return bool <p>This will return true or false indicating success of transactions.</p>
   */
  public function endTransaction(): bool
  {
    if ($this->_in_transaction === false) {
      $this->_debug->displayError('Error: mysql server is not in transaction!', false);

      return false;
    }

    if (!$this->errors()) {
      $return = \mysqli_commit($this->link);
    } else {
      $this->rollback();
      $return = false;
    }

    \mysqli_autocommit($this->link, true);
    $this->_in_transaction = false;

    return $return;
  }

  /**
   * Get all errors from "$this->_errors".
   *
   * @return array|false <p>false === on errors</p>
   */
  public function errors()
  {
    $errors = $this->_debug->getErrors();

    return \count($errors) > 0 ? $errors : false;
  }

  /**
   * Returns the SQL by replacing :placeholders with SQL-escaped values.
   *
   * @param mixed $sql    <p>The SQL string.</p>
   * @param array $params <p>An array of key-value bindings.</p>
   *
   * @return array <p>with the keys -> 'sql', 'params'</p>
   */
  public function _parseQueryParamsByName(string $sql, array $params = []): array
  {
    // is there anything to parse?
    if (
        \strpos($sql, ':') === false
        ||
        \count($params) === 0
    ) {
      return ['sql' => $sql, 'params' => $params];
    }

    $offset = null;
    $replacement = null;
    foreach ($params as $name => $param) {

      // use this only for named parameters
      if (is_int($name)) {
        continue;
      }

      // add ":" if needed
      if (\strpos($name, ':') !== 0) {
        $nameTmp = ':' . $name;
      } else {
        $nameTmp = $name;
      }

      if ($offset === null) {
        $offset = \strpos($sql, $nameTmp);
      } else {
        $offset = \strpos($sql, $nameTmp, $offset + \strlen((string)$replacement));
      }

      if ($offset === false) {
        continue;
      }

      $replacement = $this->secure($param);

      unset($params[$name]);

      $sql = \substr_replace($sql, $replacement, $offset, \strlen($nameTmp));
    }

    return ['sql' => $sql, 'params' => $params];
  }

  /**
   * Escape: Use "mysqli_real_escape_string" and clean non UTF-8 chars + some extra optional stuff.
   *
   * @param mixed     $var           boolean: convert into "integer"<br />
   *                                 int: int (don't change it)<br />
   *                                 float: float (don't change it)<br />
   *                                 null: null (don't change it)<br />
   *                                 array: run escape() for every key => value<br />
   *                                 string: run UTF8::cleanup() and mysqli_real_escape_string()<br />
   * @param bool      $stripe_non_utf8
   * @param bool      $html_entity_decode
   * @param bool|null $convert_array <strong>false</strong> => Keep the array.<br />
   *                                 <strong>true</strong> => Convert to string var1,var2,var3...<br />
   *                                 <strong>null</strong> => Convert the array into null, every time.
   *
   * @return mixed
   */
  public function escape($var = '', bool $stripe_non_utf8 = true, bool $html_entity_decode = false, $convert_array = false)
  {
    // [empty]
    if ($var === '') {
      return '';
    }

    // ''
    if ($var === "''") {
      return "''";
    }

    // check the type
    $type = gettype($var);

    if ($type === 'object') {
      if ($var instanceof \DateTime) {
        $var = $var->format('Y-m-d H:i:s');
        $type = 'string';
      } elseif (\method_exists($var, '__toString')) {
        $var = (string)$var;
        $type = 'string';
      }
    }

    switch ($type) {
      case 'boolean':
        $var = (int)$var;
        break;

      case 'double':
      case 'integer':
        break;

      case 'string':
        if ($stripe_non_utf8 === true) {
          $var = UTF8::cleanup($var);
        }

        if ($html_entity_decode === true) {
          $var = UTF8::html_entity_decode($var);
        }

        $var = \get_magic_quotes_gpc() ? \stripslashes($var) : $var;
        $var = \mysqli_real_escape_string($this->getLink(), $var);
        break;

      case 'array':
        if ($convert_array === null) {

          if ($this->_convert_null_to_empty_string === true) {
            $var = "''";
          } else {
            $var = 'NULL';
          }

        } else {

          $varCleaned = [];
          foreach ((array)$var as $key => $value) {

            $key = $this->escape($key, $stripe_non_utf8, $html_entity_decode);
            $value = $this->escape($value, $stripe_non_utf8, $html_entity_decode);

            /** @noinspection OffsetOperationsInspection */
            $varCleaned[$key] = $value;
          }

          if ($convert_array === true) {
            $varCleaned = \implode(',', $varCleaned);

            $var = $varCleaned;
          } else {
            $var = $varCleaned;
          }

        }
        break;

      case 'NULL':
        if ($this->_convert_null_to_empty_string === true) {
          $var = "''";
        } else {
          $var = 'NULL';
        }
        break;

      default:
        throw new \InvalidArgumentException(sprintf('Not supported value "%s" of type %s.', print_r($var, true), $type));
        break;
    }

    return $var;
  }

  /**
   * Execute select/insert/update/delete sql-queries.
   *
   * @param string  $query    <p>sql-query</p>
   * @param bool    $useCache optional <p>use cache?</p>
   * @param int     $cacheTTL optional <p>cache-ttl in seconds</p>
   * @param DB|null $db       optional <p>the database connection</p>
   *
   * @return mixed "array" by "<b>SELECT</b>"-queries<br />
   *               "int" (insert_id) by "<b>INSERT</b>"-queries<br />
   *               "int" (affected_rows) by "<b>UPDATE / DELETE</b>"-queries<br />
   *               "true" by e.g. "DROP"-queries<br />
   *               "false" on error
   *
   * @throws QueryException
   */
  public static function execSQL(string $query, bool $useCache = false, int $cacheTTL = 3600, DB $db = null)
  {
    // init
    $cacheKey = null;
    if (!$db) {
      $db = self::getInstance();
    }

    if ($useCache === true) {
      $cache = new Cache(null, null, false, $useCache);
      $cacheKey = 'sql-' . \md5($query);

      if (
          $cache->getCacheIsReady() === true
          &&
          $cache->existsItem($cacheKey)
      ) {
        return $cache->getItem($cacheKey);
      }

    } else {
      $cache = false;
    }

    $result = $db->query($query);

    if ($result instanceof Result) {

      $return = $result->fetchAllArray();

      // save into the cache
      if (
          $cacheKey !== null
          &&
          $useCache === true
          &&
          $cache instanceof Cache
          &&
          $cache->getCacheIsReady() === true
      ) {
        $cache->setItem($cacheKey, $return, $cacheTTL);
      }

    } else {
      $return = $result;
    }

    return $return;
  }

  /**
   * Get all table-names via "SHOW TABLES".
   *
   * @return array
   */
  public function getAllTables(): array
  {
    $query = 'SHOW TABLES';
    $result = $this->query($query);

    return $result->fetchAllArray();
  }

  /**
   * @return Debug
   */
  public function getDebugger(): Debug
  {
    return $this->_debug;
  }

  /**
   * Get errors from "$this->_errors".
   *
   * @return array
   */
  public function getErrors(): array
  {
    return $this->_debug->getErrors();
  }

  /**
   * getInstance()
   *
   * @param string $hostname
   * @param string $username
   * @param string $password
   * @param string $database
   * @param int    $port                 <p>default is (int)3306</p>
   * @param string $charset              <p>default is 'utf8' or 'utf8mb4' (if supported)</p>
   * @param bool   $exit_on_error        <p>Throw a 'Exception' when a query failed, otherwise it will return 'false'.
   *                                     Use false to disable it.</p>
   * @param bool   $echo_on_error        <p>Echo the error if "checkForDev()" returns true.
   *                                     Use false to disable it.</p>
   * @param string $logger_class_name
   * @param string $logger_level         <p>'TRACE', 'DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'</p>
   * @param array  $extra_config         <p>
   *                                     're_connect'    => bool<br>
   *                                     'session_to_db' => bool<br>
   *                                     'socket'        => 'string (path)'<br>
   *                                     'ssl'           => bool<br>
   *                                     'clientkey'     => 'string (path)'<br>
   *                                     'clientcert'    => 'string (path)'<br>
   *                                     'cacert'        => 'string (path)'<br>
   *                                     </p>
   *
   * @return self
   */
  public static function getInstance(
      string $hostname = '',
      string $username = '',
      string $password = '',
      string $database = '',
      $port = 3306,
      string $charset = 'utf8',
      bool $exit_on_error = true,
      bool $echo_on_error = true,
      string $logger_class_name = '',
      string $logger_level = '',
      array $extra_config = []
  ): self
  {
    /**
     * @var $instance self[]
     */
    static $instance = [];

    /**
     * @var $firstInstance self
     */
    static $firstInstance = null;

    if (
        $hostname . $username . $password . $database . $port . $charset == '3306utf8'
        &&
        null !== $firstInstance
    ) {
      if (isset($extra_config['re_connect']) && $extra_config['re_connect'] === true) {
        $firstInstance->reconnect(true);
      }

      return $firstInstance;
    }

    $extra_config_string = '';
    if (\is_array($extra_config) === true) {
      foreach ($extra_config as $extra_config_key => $extra_config_value) {
        $extra_config_string .= $extra_config_key . (string)$extra_config_value;
      }
    } else {
      // only for backward compatibility
      $extra_config_string = (int)$extra_config;
    }

    $connection = \md5(
        $hostname . $username . $password . $database . $port . $charset . (int)$exit_on_error . (int)$echo_on_error . $logger_class_name . $logger_level . $extra_config_string
    );

    if (!isset($instance[$connection])) {
      $instance[$connection] = new self(
          $hostname,
          $username,
          $password,
          $database,
          $port,
          $charset,
          $exit_on_error,
          $echo_on_error,
          $logger_class_name,
          $logger_level,
          $extra_config
      );

      if (null === $firstInstance) {
        $firstInstance = $instance[$connection];
      }
    }

    if (isset($extra_config['re_connect']) && $extra_config['re_connect'] === true) {
      $instance[$connection]->reconnect(true);
    }

    return $instance[$connection];
  }

  /**
   * Get the mysqli-link (link identifier returned by mysqli-connect).
   *
   * @return \mysqli
   */
  public function getLink(): \mysqli
  {
    return $this->link;
  }

  /**
   * Get the current charset.
   *
   * @return string
   */
  public function get_charset(): string
  {
    return $this->charset;
  }

  /**
   * Check if we are in a transaction.
   *
   * @return bool
   */
  public function inTransaction(): bool
  {
    return $this->_in_transaction;
  }

  /**
   * Execute a "insert"-query.
   *
   * @param string      $table
   * @param array       $data
   * @param string|null $databaseName <p>Use <strong>null</strong> if you will use the current database.</p>
   *
   * @return false|int <p>false on error</p>
   *
   * @throws QueryException
   */
  public function insert(string $table, array $data = [], string $databaseName = null)
  {
    // init
    $table = \trim($table);

    if ($table === '') {
      $this->_debug->displayError('Invalid table name, table name in empty.', false);

      return false;
    }

    if (\count($data) === 0) {
      $this->_debug->displayError('Invalid data for INSERT, data is empty.', false);

      return false;
    }

    $SET = $this->_parseArrayPair($data);

    if ($databaseName) {
      $databaseName = $this->quote_string(\trim($databaseName)) . '.';
    }

    $sql = 'INSERT INTO ' . $databaseName . $this->quote_string($table) . " SET $SET";

    return $this->query($sql);
  }

  /**
   * Returns the auto generated id used in the last query.
   *
   * @return int|string
   */
  public function insert_id()
  {
    return \mysqli_insert_id($this->link);
  }

  /**
   * Check if db-connection is ready.
   *
   * @return boolean
   */
  public function isReady(): bool
  {
    return $this->connected ? true : false;
  }

  /**
   * Get the last sql-error.
   *
   * @return string|false <p>false === there was no error</p>
   */
  public function lastError()
  {
    $errors = $this->_debug->getErrors();

    return \count($errors) > 0 ? end($errors) : false;
  }

  /**
   * Execute a sql-multi-query.
   *
   * @param string $sql
   *
   * @return false|Result[] "Result"-Array by "<b>SELECT</b>"-queries<br />
   *                        "boolean" by only "<b>INSERT</b>"-queries<br />
   *                        "boolean" by only (affected_rows) by "<b>UPDATE / DELETE</b>"-queries<br />
   *                        "boolean" by only by e.g. "DROP"-queries<br />
   *
   * @throws QueryException
   */
  public function multi_query(string $sql)
  {
    if (!$this->isReady()) {
      return false;
    }

    if (!$sql || $sql === '') {
      $this->_debug->displayError('Can not execute an empty query.', false);

      return false;
    }

    $query_start_time = microtime(true);
    $resultTmp = \mysqli_multi_query($this->link, $sql);
    $query_duration = microtime(true) - $query_start_time;

    $this->_debug->logQuery($sql, $query_duration, 0);

    $returnTheResult = false;
    $result = [];

    if ($resultTmp) {
      do {

        $resultTmpInner = \mysqli_store_result($this->link);

        if ($resultTmpInner instanceof \mysqli_result) {

          $returnTheResult = true;
          $result[] = new Result($sql, $resultTmpInner);

        } else {

          // is the query successful
          if ($resultTmpInner === true || !\mysqli_errno($this->link)) {
            $result[] = true;
          } else {
            $result[] = false;
          }

        }

      } while (\mysqli_more_results($this->link) === true ? \mysqli_next_result($this->link) : false);

    } else {

      // log the error query
      $this->_debug->logQuery($sql, $query_duration, 0, true);

      return $this->queryErrorHandling(\mysqli_error($this->link), \mysqli_errno($this->link), $sql, false, true);
    }

    // return the result only if there was a "SELECT"-query
    if ($returnTheResult === true) {
      return $result;
    }

    if (
        \count($result) > 0
        &&
        \in_array(false, $result, true) === false
    ) {
      return true;
    }

    return false;
  }

  /**
   * Pings a server connection, or tries to reconnect
   * if the connection has gone down.
   *
   * @return boolean
   */
  public function ping(): bool
  {
    if (
        $this->link
        &&
        $this->link instanceof \mysqli
    ) {
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      return (bool)@\mysqli_ping($this->link);
    }

    return false;
  }

  /**
   * Get a new "Prepare"-Object for your sql-query.
   *
   * @param string $query
   *
   * @return Prepare
   */
  public function prepare(string $query): Prepare
  {
    return new Prepare($this, $query);
  }

  /**
   * Execute a sql-query and return the result-array for select-statements.
   *
   * @param string $query
   *
   * @return mixed
   * @deprecated
   * @throws \Exception
   */
  public static function qry(string $query)
  {
    $db = self::getInstance();

    $args = \func_get_args();
    /** @noinspection SuspiciousAssignmentsInspection */
    $query = \array_shift($args);
    $query = \str_replace('?', '%s', $query);
    $args = \array_map(
        [
            $db,
            'escape',
        ],
        $args
    );
    \array_unshift($args, $query);
    $query = \sprintf(...$args);
    $result = $db->query($query);

    if ($result instanceof Result) {
      return $result->fetchAllArray();
    }

    return $result;
  }

  /**
   * Execute a sql-query.
   *
   * @param string        $sql            <p>The sql query-string.</p>
   *
   * @param array|boolean $params         <p>
   *                                      "array" of sql-query-parameters<br/>
   *                                      "false" if you don't need any parameter (default)<br/>
   *                                      </p>
   *
   * @return bool|int|Result              <p>
   *                                      "Result" by "<b>SELECT</b>"-queries<br />
   *                                      "int" (insert_id) by "<b>INSERT / REPLACE</b>"-queries<br />
   *                                      "int" (affected_rows) by "<b>UPDATE / DELETE</b>"-queries<br />
   *                                      "true" by e.g. "DROP"-queries<br />
   *                                      "false" on error
   *                                      </p>
   *
   * @throws QueryException
   */
  public function query(string $sql = '', $params = false)
  {
    if (!$this->isReady()) {
      return false;
    }

    if (!$sql || $sql === '') {
      $this->_debug->displayError('Can not execute an empty query.', false);

      return false;
    }

    if (
        $params !== false
        &&
        \is_array($params)
        &&
        \count($params) > 0
    ) {
      $parseQueryParams = $this->_parseQueryParams($sql, $params);
      $parseQueryParamsByName = $this->_parseQueryParamsByName($parseQueryParams['sql'], $parseQueryParams['params']);
      $sql = $parseQueryParamsByName['sql'];
    }

    // DEBUG
    // var_dump($params);
    // echo $sql . "\n";

    $query_start_time = microtime(true);
    $query_result = \mysqli_real_query($this->link, $sql);
    $query_duration = microtime(true) - $query_start_time;

    $this->query_count++;

    $mysqli_field_count = \mysqli_field_count($this->link);
    if ($mysqli_field_count) {
      $result = \mysqli_store_result($this->link);
    } else {
      $result = $query_result;
    }

    if ($result instanceof \mysqli_result) {

      // log the select query
      $this->_debug->logQuery($sql, $query_duration, $mysqli_field_count);

      // return query result object
      return new Result($sql, $result);
    }

    if ($query_result === true) {

      // "INSERT" || "REPLACE"
      if (preg_match('/^\s*?(?:INSERT|REPLACE)\s+/i', $sql)) {
        $insert_id = (int)$this->insert_id();
        $this->_debug->logQuery($sql, $query_duration, $insert_id);

        return $insert_id;
      }

      // "UPDATE" || "DELETE"
      if (preg_match('/^\s*?(?:UPDATE|DELETE)\s+/i', $sql)) {
        $affected_rows = $this->affected_rows();
        $this->_debug->logQuery($sql, $query_duration, $affected_rows);

        return $affected_rows;
      }

      // log the ? query
      $this->_debug->logQuery($sql, $query_duration, 0);

      return true;
    }

    // log the error query
    $this->_debug->logQuery($sql, $query_duration, 0, true);

    return $this->queryErrorHandling(\mysqli_error($this->link), \mysqli_errno($this->link), $sql, $params);
  }

  /**
   * Error-handling for the sql-query.
   *
   * @param string     $errorMessage
   * @param int        $errorNumber
   * @param string     $sql
   * @param array|bool $sqlParams <p>false if there wasn't any parameter</p>
   * @param bool       $sqlMultiQuery
   *
   * @throws QueryException
   * @throws DBGoneAwayException
   *
   * @return mixed|false
   */
  private function queryErrorHandling(string $errorMessage, int $errorNumber, string $sql, $sqlParams = false, bool $sqlMultiQuery = false)
  {
    if (
        $errorMessage === 'DB server has gone away'
        ||
        $errorMessage === 'MySQL server has gone away'
        ||
        $errorNumber === 2006
    ) {
      static $RECONNECT_COUNTER;

      // exit if we have more then 3 "DB server has gone away"-errors
      if ($RECONNECT_COUNTER > 3) {
        $this->_debug->mailToAdmin('DB-Fatal-Error', $errorMessage . '(' . $errorNumber . ') ' . ":\n<br />" . $sql, 5);
        throw new DBGoneAwayException($errorMessage);
      }

      $this->_debug->mailToAdmin('DB-Error', $errorMessage . '(' . $errorNumber . ') ' . ":\n<br />" . $sql);

      // reconnect
      $RECONNECT_COUNTER++;
      $this->reconnect(true);

      // re-run the current (non multi) query
      if ($sqlMultiQuery === false) {
        return $this->query($sql, $sqlParams);
      }

      return false;
    }

    $this->_debug->mailToAdmin('SQL-Error', $errorMessage . '(' . $errorNumber . ') ' . ":\n<br />" . $sql);

    $force_exception_after_error = null; // auto
    if ($this->_in_transaction === true) {
      $force_exception_after_error = false;
    }
    // this query returned an error, we must display it (only for dev) !!!

    $this->_debug->displayError($errorMessage . '(' . $errorNumber . ') ' . ' | ' . $sql, $force_exception_after_error);

    return false;
  }

  /**
   * Quote && Escape e.g. a table name string.
   *
   * @param mixed $str
   *
   * @return string
   */
  public function quote_string($str): string
  {
    $str = \str_replace(
        '`',
        '``',
        \trim(
            (string)$this->escape($str, false),
            '`'
        )
    );

    return '`' . $str . '`';
  }

  /**
   * Reconnect to the MySQL-Server.
   *
   * @param bool $checkViaPing
   *
   * @return bool
   */
  public function reconnect(bool $checkViaPing = false): bool
  {
    $ping = false;

    if ($checkViaPing === true) {
      $ping = $this->ping();
    }

    if ($ping !== true) {
      $this->connected = false;
      $this->connect();
    }

    return $this->isReady();
  }

  /**
   * Execute a "replace"-query.
   *
   * @param string      $table
   * @param array       $data
   * @param null|string $databaseName <p>Use <strong>null</strong> if you will use the current database.</p>
   *
   * @return false|int <p>false on error</p>
   *
   * @throws QueryException
   */
  public function replace(string $table, array $data = [], string $databaseName = null)
  {
    // init
    $table = \trim($table);

    if ($table === '') {
      $this->_debug->displayError('Invalid table name, table name in empty.', false);

      return false;
    }

    if (\count($data) === 0) {
      $this->_debug->displayError('Invalid data for REPLACE, data is empty.', false);

      return false;
    }

    // extracting column names
    $columns = \array_keys($data);
    foreach ($columns as $k => $_key) {
      /** @noinspection AlterInForeachInspection */
      $columns[$k] = $this->quote_string($_key);
    }

    $columns = \implode(',', $columns);

    // extracting values
    foreach ($data as $k => $_value) {
      /** @noinspection AlterInForeachInspection */
      $data[$k] = $this->secure($_value);
    }
    $values = \implode(',', $data);

    if ($databaseName) {
      $databaseName = $this->quote_string(\trim($databaseName)) . '.';
    }

    $sql = 'REPLACE INTO ' . $databaseName . $this->quote_string($table) . " ($columns) VALUES ($values)";

    return $this->query($sql);
  }

  /**
   * Rollback in a transaction and end the transaction.
   *
   * @return bool <p>Boolean true on success, false otherwise.</p>
   */
  public function rollback(): bool
  {
    if ($this->_in_transaction === false) {
      $this->_debug->displayError('Error: mysql server is not in transaction!', false);

      return false;
    }

    $return = \mysqli_rollback($this->link);
    \mysqli_autocommit($this->link, true);
    $this->_in_transaction = false;

    return $return;
  }

  /**
   * Try to secure a variable, so can you use it in sql-queries.
   *
   * <p>
   * <strong>int:</strong> (also strings that contains only an int-value)<br />
   * 1. parse into "int"
   * </p><br />
   *
   * <p>
   * <strong>float:</strong><br />
   * 1. return "float"
   * </p><br />
   *
   * <p>
   * <strong>string:</strong><br />
   * 1. check if the string isn't a default mysql-time-function e.g. 'CURDATE()'<br />
   * 2. trim '<br />
   * 3. escape the string (and remove non utf-8 chars)<br />
   * 4. trim ' again (because we maybe removed some chars)<br />
   * 5. add ' around the new string<br />
   * </p><br />
   *
   * <p>
   * <strong>array:</strong><br />
   * 1. return null
   * </p><br />
   *
   * <p>
   * <strong>object:</strong><br />
   * 1. return false
   * </p><br />
   *
   * <p>
   * <strong>null:</strong><br />
   * 1. return null
   * </p>
   *
   * @param mixed $var
   *
   * @return mixed
   */
  public function secure($var)
  {
    if ($var === '') {
      return "''";
    }

    if ($var === "''") {
      return "''";
    }

    if ($var === null) {
      if ($this->_convert_null_to_empty_string === true) {
        return "''";
      }

      return 'NULL';
    }

    if (\in_array($var, $this->mysqlDefaultTimeFunctions, true)) {
      return $var;
    }

    if (\is_string($var)) {
      $var = \trim($var, "'");
    }

    $var = $this->escape($var, false, false, null);

    if (\is_string($var)) {
      $var = "'" . \trim($var, "'") . "'";
    }

    return $var;
  }

  /**
   * Execute a "select"-query.
   *
   * @param string       $table
   * @param string|array $where
   * @param string|null  $databaseName <p>Use <strong>null</strong> if you will use the current database.</p>
   *
   * @return false|Result <p>false on error</p>
   *
   * @throws QueryException
   */
  public function select(string $table, $where = '1=1', string $databaseName = null)
  {
    // init
    $table = \trim($table);

    if ($table === '') {
      $this->_debug->displayError('Invalid table name, table name in empty.', false);

      return false;
    }

    if (\is_string($where)) {
      $WHERE = $this->escape($where, false);
    } elseif (\is_array($where)) {
      $WHERE = $this->_parseArrayPair($where, 'AND');
    } else {
      $WHERE = '';
    }

    if ($databaseName) {
      $databaseName = $this->quote_string(\trim($databaseName)) . '.';
    }

    $sql = 'SELECT * FROM ' . $databaseName . $this->quote_string($table) . " WHERE ($WHERE)";

    return $this->query($sql);
  }

  /**
   * Selects a different database than the one specified on construction.
   *
   * @param string $database <p>Database name to switch to.</p>
   *
   * @return bool <p>Boolean true on success, false otherwise.</p>
   */
  public function select_db(string $database): bool
  {
    if (!$this->isReady()) {
      return false;
    }

    return mysqli_select_db($this->link, $database);
  }

  /**
   * Set the current charset.
   *
   * @param string $charset
   *
   * @return bool
   */
  public function set_charset(string $charset): bool
  {
    $charsetLower = strtolower($charset);
    if ($charsetLower === 'utf8' || $charsetLower === 'utf-8') {
      $charset = 'utf8';
    }
    if ($charset === 'utf8' && Helper::isUtf8mb4Supported($this) === true) {
      $charset = 'utf8mb4';
    }

    $this->charset = $charset;

    $return = mysqli_set_charset($this->link, $charset);
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    @\mysqli_query($this->link, 'SET CHARACTER SET ' . $charset);
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    @\mysqli_query($this->link, "SET NAMES '" . $charset . "'");

    return $return;
  }

  /**
   * Set the option to convert null to "''" (empty string).
   *
   * Used in secure() => select(), insert(), update(), delete()
   *
   * @deprecated It's not recommended to convert NULL into an empty string!
   *
   * @param bool $bool
   *
   * @return self
   */
  public function set_convert_null_to_empty_string(bool $bool): self
  {
    $this->_convert_null_to_empty_string = $bool;

    return $this;
  }

  /**
   * Enables or disables internal report functions
   *
   * @link http://php.net/manual/en/function.mysqli-report.php
   *
   * @param int $flags <p>
   *                   <table>
   *                   Supported flags
   *                   <tr valign="top">
   *                   <td>Name</td>
   *                   <td>Description</td>
   *                   </tr>
   *                   <tr valign="top">
   *                   <td><b>MYSQLI_REPORT_OFF</b></td>
   *                   <td>Turns reporting off</td>
   *                   </tr>
   *                   <tr valign="top">
   *                   <td><b>MYSQLI_REPORT_ERROR</b></td>
   *                   <td>Report errors from mysqli function calls</td>
   *                   </tr>
   *                   <tr valign="top">
   *                   <td><b>MYSQLI_REPORT_STRICT</b></td>
   *                   <td>
   *                   Throw <b>mysqli_sql_exception</b> for errors
   *                   instead of warnings
   *                   </td>
   *                   </tr>
   *                   <tr valign="top">
   *                   <td><b>MYSQLI_REPORT_INDEX</b></td>
   *                   <td>Report if no index or bad index was used in a query</td>
   *                   </tr>
   *                   <tr valign="top">
   *                   <td><b>MYSQLI_REPORT_ALL</b></td>
   *                   <td>Set all options (report all)</td>
   *                   </tr>
   *                   </table>
   *                   </p>
   *
   * @return bool
   */
  public function set_mysqli_report(int $flags): bool
  {
    return \mysqli_report($flags);
  }

  /**
   * Show config errors by throw exceptions.
   *
   * @return bool
   *
   * @throws \InvalidArgumentException
   */
  public function showConfigError(): bool
  {

    if (
        !$this->hostname
        ||
        !$this->username
        ||
        !$this->database
    ) {

      if (!$this->hostname) {
        throw new \InvalidArgumentException('no-sql-hostname');
      }

      if (!$this->username) {
        throw new \InvalidArgumentException('no-sql-username');
      }

      if (!$this->database) {
        throw new \InvalidArgumentException('no-sql-database');
      }

      return false;
    }

    return true;
  }

  /**
   * alias: "beginTransaction()"
   */
  public function startTransaction(): bool
  {
    return $this->beginTransaction();
  }

  /**
   * Execute a callback inside a transaction.
   *
   * @param callback $callback <p>The callback to run inside the transaction, if it's throws an "Exception" or if it's
   *                           returns "false", all SQL-statements in the callback will be rollbacked.</p>
   *
   * @return bool <p>Boolean true on success, false otherwise.</p>
   */
  public function transact($callback): bool
  {
    try {

      $beginTransaction = $this->beginTransaction();
      if ($beginTransaction === false) {
        $this->_debug->displayError('Error: transact -> can not start transaction!', false);

        return false;
      }

      $result = $callback($this);
      if ($result === false) {
        /** @noinspection ThrowRawExceptionInspection */
        throw new \Exception('call_user_func [' . $callback . '] === false');
      }

      return $this->commit();

    } catch (\Exception $e) {

      $this->rollback();

      return false;
    }
  }

  /**
   * Execute a "update"-query.
   *
   * @param string       $table
   * @param array        $data
   * @param array|string $where
   * @param null|string  $databaseName <p>Use <strong>null</strong> if you will use the current database.</p>
   *
   * @return false|int <p>false on error</p>
   *
   * @throws QueryException
   */
  public function update(string $table, array $data = [], $where = '1=1', string $databaseName = null)
  {
    // init
    $table = \trim($table);

    if ($table === '') {
      $this->_debug->displayError('Invalid table name, table name in empty.', false);

      return false;
    }

    if (\count($data) === 0) {
      $this->_debug->displayError('Invalid data for UPDATE, data is empty.', false);

      return false;
    }

    $SET = $this->_parseArrayPair($data);

    if (\is_string($where)) {
      $WHERE = $this->escape($where, false);
    } elseif (\is_array($where)) {
      $WHERE = $this->_parseArrayPair($where, 'AND');
    } else {
      $WHERE = '';
    }

    if ($databaseName) {
      $databaseName = $this->quote_string(\trim($databaseName)) . '.';
    }

    $sql = 'UPDATE ' . $databaseName . $this->quote_string($table) . " SET $SET WHERE ($WHERE)";

    return $this->query($sql);
  }

  /**
   * Determine if database table exists
   *
   * @param string $table
   *
   * @return bool
   */
  public function table_exists(string $table): bool
  {
    $check = $this->query('SELECT 1 FROM ' . $this->quote_string($table));

    return $check !== false
           &&
           $check instanceof Result
           &&
           $check->num_rows > 0;
  }

  /**
   * Count number of rows found matching a specific query.
   *
   * @param string $query
   *
   * @return int
   */
  public function num_rows(string $query): int
  {
    $check = $this->query($query);

    if (
        $check === false
        ||
        !$check instanceof Result
    ) {
      return 0;
    }

    return $check->num_rows;
  }
}
