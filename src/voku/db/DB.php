<?php

namespace voku\db;

use voku\helper\UTF8;

require_once 'Result.php';

/**
 * DB: this handles DB queries via MySQLi
 *
 * @package   voku\db
 */
Class DB
{

  /**
   * @var int
   */
  public $query_count = 0;

  /**
   * @var bool
   */
  protected $exit_on_error = false;

  /**
   * @var bool
   */
  protected $echo_on_error = true;

  /**
   * @var string
   */
  protected $css_mysql_box_border = '3px solid orange';

  /**
   * @var string
   */
  protected $css_mysql_box_bg = '#FFCC66';

  /**
   * @var \mysqli
   */
  protected $link = false;

  /**
   * @var bool
   */
  protected $connected = false;

  /**
   * @var array
   */
  protected $mysqlDefaultTimeFunctions;

  /**
   * @var string
   */
  private $logger_class_name;

  /**
   * @var string
   *
   * 'TRACE', 'DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'
   */
  private $logger_level;

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
   * @var string
   */
  private $port = '3306';

  /**
   * @var string
   */
  private $charset = 'utf8';

  /**
   * @var string
   */
  private $socket = '';

  /**
   * @var array
   */
  private $_errors = array();

  /**
   * @var bool
   */
  private $session_to_db = false;

  /**
   * @var bool
   */
  private $_in_transaction = false;

  /**
   * __construct()
   *
   * @param string  $hostname
   * @param string  $username
   * @param string  $password
   * @param string  $database
   * @param int     $port
   * @param string  $charset
   * @param boolean $exit_on_error
   * @param boolean $echo_on_error
   * @param string  $logger_class_name
   * @param string  $logger_level 'TRACE', 'DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'
   */
  private function __construct($hostname, $username, $password, $database, $port, $charset, $exit_on_error, $echo_on_error, $logger_class_name, $logger_level, $session_to_db)
  {
    $this->connected = false;

    $this->_loadConfig($hostname, $username, $password, $database, $port, $charset, $exit_on_error, $echo_on_error, $logger_class_name, $logger_level, $session_to_db);

    if ($this->connect()) {
      $this->set_charset($this->charset);
    }

    $this->mysqlDefaultTimeFunctions = array(
        'CURDATE()',	          // Returns the current date
        'CURRENT_DATE()',       // CURRENT_DATE	| Synonyms for CURDATE()
        'CURRENT_TIME()',       // CURRENT_TIME	| Synonyms for CURTIME()
        'CURRENT_TIMESTAMP()',  // CURRENT_TIMESTAMP | Synonyms for NOW()
        'CURTIME()',            // Returns the current time
        'LOCALTIME()',          // Synonym for NOW()
        'LOCALTIMESTAMP()',	    // Synonym for NOW()
        'NOW()',	              // Returns the current date and time
        'SYSDATE()',            // Returns the time at which the function executes
        'UNIX_TIMESTAMP()',     // Returns a UNIX timestamp
        'UTC_DATE()',	          // Returns the current UTC date
        'UTC_TIME()',	          // Returns the current UTC time
        'UTC_TIMESTAMP()'       // Returns the current UTC date and time
    );
  }

  /**
   * load the config
   *
   * @param string    $hostname
   * @param string    $username
   * @param string    $password
   * @param string    $database
   * @param int       $port
   * @param string    $charset e.g.: utf8
   * @param boolean   $exit_on_error
   * @param boolean   $echo_on_error
   * @param string    $logger_class_name
   * @param string    $logger_level
   * @parmm boolean   $session_to_db
   *
   * @return bool
   */
  private function _loadConfig($hostname, $username, $password, $database, $port, $charset, $exit_on_error, $echo_on_error, $logger_class_name, $logger_level, $session_to_db)
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
    }

    if ($exit_on_error === true || $exit_on_error === false) {
      $this->exit_on_error = $exit_on_error;
    }

    if ($echo_on_error === true || $echo_on_error === false) {
      $this->echo_on_error = $echo_on_error;
    }

    $this->logger_class_name = $logger_class_name;
    $this->logger_level = $logger_level;

    $this->session_to_db = $session_to_db;

    return $this->showConfigError();
  }

  /**
   * show config error and exit()
   */
  public function showConfigError()
  {

    if (
            !$this->hostname
        ||  !$this->username
        ||  !$this->database
        ||  (!$this->password && $this->password != '')
    ) {

      if (!$this->hostname) {
        var_dump('no sql-hostname');
        exit();
      }

      if (!$this->username) {
        var_dump('no sql-username');
        exit();
      }

      if (!$this->database) {
        var_dump('no sql-database');
        exit();
      }

      if (!$this->password) {
        var_dump('no sql-password');
        exit();
      }

      return false;
    } else {
      return true;
    }
  }

  /**
   * connect
   *
   * @return boolean
   */
  public function connect()
  {
    if ($this->isReady()) {
      return true;
    }

    $this->socket = @ini_get('mysqli.default_socket');

    $this->link = @mysqli_connect(
        $this->hostname,
        $this->username,
        $this->password,
        $this->database,
        $this->port
    );

    if (!$this->link) {
      $this->_displayError("Error connecting to mysql server: " . mysqli_connect_error(), true);
    } else {
      $this->connected = true;
    }

    return $this->isReady();
  }

  /**
   * check if db-connection is ready
   *
   * @return boolean
   */
  public function isReady()
  {
    return ($this->connected) ? true : false;
  }

  /**
   * _displayError
   *
   * @param string $e
   * @param null   $force_exit_after_error
   */
  private function _displayError($e, $force_exit_after_error = null)
  {

    $this->logger(array('error', '<strong>' . date("d. m. Y G:i:s") . ' (sql-error):</strong> ' . $e . '<br>'));

    if ($this->checkForDev() === true) {
      $this->_errors[] = $e;

      if ($this->echo_on_error) {
        $box_border = $this->css_mysql_box_border;
        $box_bg = $this->css_mysql_box_bg;

        echo '
        <div class="OBJ-mysql-box" style="border:' . $box_border . '; background:' . $box_bg . '; padding:10px; margin:10px;">
          <b style="font-size:14px;">MYSQL Error:</b>
          <code style="display:block;">
            ' . $e . '
          </code>
        </div>
        ';
      }

      if ($force_exit_after_error === true) {
        exit();
      } else if ($force_exit_after_error === false) {
        // nothing
      } else if ($force_exit_after_error === null) {
        // default
        if ($this->exit_on_error === true) {
          exit();
        }
      }
    }
  }

  /**
 * set_charset
 *
 * @param string $charset
 */
  public function set_charset($charset)
  {
    $this->charset = $charset;
    mysqli_set_charset($this->link, $charset);
  }

  /*
   * get a instance of this SQL-Class
   *
   * @param string  $hostname
   * @param string  $username
   * @param string  $password
   * @param string  $database
   * @param int     $port
   * @param string  $charset
   * @param boolean $exit_on_error
   * @param boolean $echo_on_error
   * @param string  $logger_class_name
   * @param string  $logger_level
   * @param boolean $session_to_db
   *
   * @return DB
   */
  public static function getInstance($hostname = '', $username = '', $password = '', $database = '', $port = '', $charset = '', $exit_on_error = '', $echo_on_error = '', $logger_class_name = '', $logger_level = '', $session_to_db = '')
  {
    /**
     * @var $instance DB[]
     */
    static $instance;

    /**
     * @var $firstInstance DB
     */
    static $firstInstance;

    if ($hostname . $username . $password . $database . $port . $charset == '') {
      if (null !== $firstInstance) {
        return $firstInstance;
      }
    }

    $connection = md5($hostname . $username . $password . $database . $port . $charset);

    if (null === $instance[$connection]) {
      $instance[$connection] = new self($hostname, $username, $password, $database, $port, $charset, $exit_on_error, $echo_on_error, $logger_class_name, $logger_level, $session_to_db);

      if (null === $firstInstance) {
        $firstInstance = $instance[$connection];
      }
    }

    return $instance[$connection];
  }

  /**
   * prevent from being unserialized
   *
   * @return void
   */
  public function __wakeup()
  {
    $this->reconnect();
  }

  /**
   * reconnect
   *
   * @param bool $checkViaPing
   *
   * @return bool
   */
  public function reconnect($checkViaPing = false)
  {
    $ping = false;

    if ($checkViaPing === true) {
      $ping = $this->ping();
    }

    if ($ping === false) {
      $this->connected = false;
      $this->connect();
    }

    return $this->isReady();
  }

  /**
   * ping
   *
   * @return boolean
   */
  public function ping()
  {
    return mysqli_ping($this->link);
  }

  /**
   * get the names of all tables
   *
   * @return array
   */
  public function getAllTables()
  {
    $query = "SHOW TABLES";
    $result = $this->query($query);

    return $result->fetchAllArray();
  }

  /**
   * run a sql-query
   *
   * @param string        $sql            sql-query string
   *
   * @param array|boolean $params         a "array" of sql-query-parameters
   *                                      "false" if you don't need any parameter
   *
   * @return bool|int|Result  "Result" by "<b>SELECT</b>"-queries<br />
   *                                      "int" (insert_id) by "<b>INSERT</b>"-queries<br />
   *                                      "int" (affected_rows) by "<b>UPDATE / DELETE</b>"-queries<br />
   *                                      "true" by e.g. "DROP"-queries<br />
   *                                      "false" on error
   */
  public function query($sql = '', $params = false)
  {
    static $reconnectCounter;

    if (!$this->isReady()) {
      return false;
    }

    if (!$sql || strlen($sql) == 0) {
      $this->_displayError('Can\'t execute an empty Query', false);

      return false;
    }

    if ($params !== false && is_array($params)) {
      $sql = $this->_parseQueryParams($sql, $params);
    }

    $this->query_count++;

    $query_start_time = microtime(true);
    $result = mysqli_query($this->link, $sql);
    $query_duration = microtime(true) - $query_start_time;

    $this->_logQuery($sql, $query_duration, (int)$this->affected_rows());

    if ($result instanceof \mysqli_result && $result !== null) {
      // return query result object
      return new Result($sql, $result);
    } else {
      // is the query successful
      if ($result === true) {

        if (preg_match('/^\s*"?(INSERT|UPDATE|DELETE|REPLACE)\s+/i', $sql)) {

          // it is an "INSERT"
          if ($this->insert_id() > 0) {
            return (int)$this->insert_id();
          }

          // it is an "UPDATE" || "DELETE"
          if ($this->affected_rows() > 0) {
            return (int)$this->affected_rows();
          }
        }

        return true;
      } else {

        $errorMsg = mysqli_error($this->link);

        if ($errorMsg == 'DB server has gone away' || $errorMsg == 'MySQL server has gone away') {

          // exit if we have more then 3 "DB server has gone away"-errors
          if ($reconnectCounter > 3) {
            $this->mailToAdmin('SQL-Fatal-Error', $errorMsg . ":\n<br />" . $sql, 5);
            exit();
          } else {
            $this->mailToAdmin('SQL-Error', $errorMsg . ":\n<br />" . $sql);

            // reconnect
            $reconnectCounter++;
            $this->reconnect(true);

            // re-run the current query
            $this->query($sql, $params);
          }
        } else {
          $this->mailToAdmin('SQL-Warning', $errorMsg . ":\n<br />" . $sql);

          // this query returned an error, we must display it (only for dev) !!!
          $this->_displayError($errorMsg . ' | ' . $sql);
        }
      }
    }

    return false;
  }

  private function checkForDev() {
    $return = false;

    if (function_exists('checkForDev')) {
      $return = checkForDev();
    } else {

      // for testing with dev-address
      $noDev = isset($_GET['noDev']) ? (int)$_GET['noDev'] : 0;
      $remoteAddr =  isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;

      if
      (
        ($noDev != 1) &&
        (
              ($remoteAddr == '127.0.0.1')
          ||  ($remoteAddr == '::1')
          ||  PHP_SAPI == 'cli'
        )
      ) {
        $return = true;
      }
    }

    return $return;
  }

  /**
   * _parseQueryParams
   *
   * @param string $sql
   * @param array  $params
   *
   * @return string
   */
  private function _parseQueryParams($sql, $params)
  {

    // is there anything to parse?
    if (strpos($sql, '?') === false) {
      return $sql;
    }

    // convert to array
    if (!is_array($params)) {
      $params = array($params);
    }

    $parse_key = md5(uniqid(time(), true));
    $parsed_sql = str_replace('?', $parse_key, $sql);

    $k = 0;
    while (strpos($parsed_sql, $parse_key) > 0) {

      // DEBUG
      /*
        if (checkForDev() === true) {
          echo $params[$k] . "\n<br>";
        }
       */

      $value = $this->secure($params[$k]);
      $parsed_sql = preg_replace("/$parse_key/", $value, $parsed_sql, 1);
      $k++;
    }

    return $parsed_sql;
  }

  /**
   * secure
   *
   * @param mixed $var
   *
   * @return string | null
   */
  public function secure($var)
  {
    if (is_string($var)) {

      if (!in_array($var, $this->mysqlDefaultTimeFunctions)) {
        $var = "'" . $this->escape(trim($var)) . "'";
      }

    } else if (is_int($var)) {
      $var = intval((int)$var);
    } else if (is_float($var)) {
      $var = "'" . floatval(str_replace(',', '.', $var)) . "'";
    } else if (is_bool($var)) {
      $var = (int)$var;
    } else if (is_array($var)) {
      $var = null;
    } else {
      $var = "'" . $this->escape(trim($var)) . "'";
    }

    return $var;
  }

  /**
   * escape
   *
   * @param string $str
   * @param bool   $stripe_non_utf8
   * @param bool   $html_entity_decode
   *
   * @return array|bool|float|int|string
   */
  public function escape($str = '', $stripe_non_utf8 = true, $html_entity_decode = true)
  {

    // DEBUG
    //dump($this);

    if (is_int($str) || is_bool($str)) {
      return intval((int)$str);
    } else if (is_float($str)) {
      return floatval(str_replace(',', '.', $str));
    } else if (is_array($str)) {
      foreach ($str as $key => $value) {
        $str[$this->escape($key)] = $this->escape($value);
      }

      return (array)$str;
    }

    if (is_string($str)) {

      if ($stripe_non_utf8 === true) {
        $str = UTF8::cleanup($str);
      }

      if ($html_entity_decode === true) {
        // use no-html-entity for db
        $str = UTF8::html_entity_decode($str);
      }

      $str = get_magic_quotes_gpc() ? stripslashes($str) : $str;

      $str = mysqli_real_escape_string($this->getLink(), $str);

      return (string)$str;
    } else {
      return false;
    }
  }

  /**
   * getLink
   *
   * @return \mysqli
   */
  public function getLink()
  {
    return $this->link;
  }

  /**
   * _logQuery
   *
   * @param $sql
   * @param $duration
   * @param $results
   *
   * @return bool
   */
  private function _logQuery($sql, $duration, $results)
  {
    $logLevelUse = strtolower($this->logger_level);
    if ($logLevelUse != 'trace' && $logLevelUse != 'debug') {
      return false;
    }

    // init
    $file = '';
    $line = '';
    $referrer = debug_backtrace();

    foreach ($referrer as $key => $ref) {

      if ($ref['function'] == '_logQuery') {
        $file = $referrer[$key + 1]['file'];
        $line = $referrer[$key + 1]['line'];
      }

      if ($ref['function'] == 'execSQL') {
        $file = $referrer[$key]['file'];
        $line = $referrer[$key]['line'];

        break;
      }
    }

    $info = 'time => ' . round($duration, 5) . ' - ' . 'results => ' . $results . ' - ' . 'SQL => ' . UTF8::htmlentities($sql);

    $this->logger(array('debug', '<strong>' . date("d. m. Y G:i:s") . ' (' . $file . ' line: ' . $line . '):</strong> ' . $info . '<br>', 'sql'));

    return true;
  }

  /**
   * affected_rows
   *
   * @return int
   */
  public function affected_rows()
  {
    return mysqli_affected_rows($this->link);
  }

  /**
   * insert_id
   *
   * @return int|string
   */
  public function insert_id()
  {
    return mysqli_insert_id($this->link);
  }

  /**
   * run a sql-multi-query
   *
   * @param $sql
   *
   * @return bool
   */
  public function multi_query($sql)
  {
    static $reconnectCounterMulti;

    if (!$this->isReady()) {
      return false;
    }

    if (strlen($sql) == 0) {
      $this->_displayError('Can\'t execute an empty Query', false);

      return false;
    }

    $query_start_time = microtime(true);
    $resultTmp = mysqli_multi_query($this->link, $sql);
    $query_duration = microtime(true) - $query_start_time;

    $this->_logQuery($sql, $query_duration, 0);

    $result = array();
    if ($resultTmp) {
      do {
        $resultTmpInner = mysqli_store_result($this->link);

        if (is_object($result) && $result !== null) {
          $result[] = new Result($sql, $resultTmpInner);
        } else {
          // is the query successful
          if ($resultTmpInner === true) {
            $result[] = true;
          } else {

            $errorMsg = mysqli_error($this->link);

            if ($errorMsg == 'DB server has gone away' || $errorMsg == 'MySQL server has gone away') {

              // exit if we have more then 3 "DB server has gone away"-errors
              if ($reconnectCounterMulti > 3) {
                $this->mailToAdmin('SQL-Fatal-Error', $errorMsg . ":\n<br />" . $sql, 5);
                exit();
              } else {
                $this->mailToAdmin('SQL-Error', $errorMsg . ":\n<br />" . $sql);

                // reconnect
                $reconnectCounterMulti++;
                $this->reconnect(true);
              }
            } else {
              $this->mailToAdmin('SQL-Warning', $errorMsg . ":\n<br />" . $sql);

              // this query returned an error, we must display it (only for dev) !!!
              $this->_displayError($errorMsg . ' | ' . $sql);
            }
          }
        }

        // free mem
        if ($resultTmpInner instanceof \mysqli_result) {
          mysqli_free_result($resultTmpInner);
        }

      }
      while (mysqli_next_result($this->link));

    } else {

      $errorMsg =  mysqli_error($this->link);

      if (checkForDev() === true) {
        echo "Info: maybe you have to increase your 'max_allowed_packet = 30M' in the config: 'my.conf' \n<br />";
        echo "Error:" . $errorMsg;
      }

      $this->mailToAdmin('SQL-Error in mysqli_multi_query', $errorMsg . ":\n<br />" . $sql);

    }

    return false;
  }

  /**
   * Begins a transaction, by turning off auto commit
   *
   * @return true or false indicating success of transaction
   */
  public function beginTransaction()
  {
    if ($this->inTransaction() === true) {
      $this->_displayError("Error mysql server already in transaction!", true);

      return false;
    } else if (mysqli_connect_errno($this->link)) {
      $this->_displayError("Error connecting to mysql server: " . mysqli_connect_error(), true);

      return false;
    } else {
      $this->_in_transaction = true;
      mysqli_autocommit($this->link, false);

      return true;

    }
  }

  /**
   * Check if in transaction
   *
   * @return boolean
   */
  public function inTransaction()
  {
    return $this->_in_transaction;
  }

  /**
   * Ends a transaction and commits if no errors, then ends autocommit
   *
   * @return true or false indicating success of transactions
   */
  public function endTransaction()
  {

    if (!$this->errors()) {
      mysqli_commit($this->link);
      $return = true;
    } else {
      mysqli_rollback($this->link);
      $return = false;
    }

    mysqli_autocommit($this->link, true);
    $this->_in_transaction = false;

    return $return;
  }

  /**
   * get all errors
   *
   * @return array false on error
   */
  public function errors()
  {
    return count($this->_errors) > 0 ? $this->_errors : false;
  }

  /**
   * insert
   *
   * @param string $table
   * @param array  $data
   *
   * @return bool|int|Result
   */
  public function insert($table, $data = array())
  {

    $table = trim($table);

    if (strlen($table) == 0) {
      $this->_displayError("invalid table name");

      return false;
    }

    if (count($data) == 0) {
      $this->_displayError("empty data for INSERT");

      return false;
    }

    $SET = $this->_parseArrayPair($data);

    $sql = "INSERT INTO " . $this->quote_string($table) . " SET $SET;";

    return $this->query($sql);
  }

  /**
   * Parses arrays with value pairs and generates SQL to use in queries
   *
   * @param array  $arrayPair
   * @param string $glue this is the separator
   *
   * @return string
   */
  private function _parseArrayPair($arrayPair, $glue = ',')
  {
    // init
    $sql = '';
    $pairs = array();

    if (!empty($arrayPair)) {

      foreach ($arrayPair as $_key => $_value) {
        $_connector = '=';

        if (strpos($_key, 'IS') !== false) {
          $_connector = 'IS';
        }

        if (strpos($_key, "IN") !== false) {
          $_connector = 'IN';
        }

        if (strpos($_key, '>') !== false && strpos($_key, '=') === false) {
          $_connector = ">";
        }

        if (strpos($_key, '<') !== false && strpos($_key, '=') === false) {
          $_connector = "<";
        }

        if (strpos($_key, '>=') !== false) {
          $_connector = '>=';
        }

        if (strpos($_key, '<=') !== false) {
          $_connector = '<=';
        }

        if (strpos($_key, 'LIKE') !== false) {
          $_connector = 'LIKE';
        }

        $pairs[] = " " . $this->quote_string(str_replace($_connector, '', $_key)) . " " . $_connector . " " . $this->secure($_value) . " \n";
      }

      $sql = implode($glue, $pairs);
    }

    return $sql;
  }

  /**
   * Quote e.g. a table name string
   *
   * @param string $str
   *
   * @return string
   */
  public function quote_string($str)
  {
    return "`" . $str . "`";
  }

  /**
   * replace
   *
   * @param string $table
   * @param array  $data
   *
   * @return bool|int|Result
   */
  public function replace($table, $data = array())
  {

    $table = trim($table);

    if (strlen($table) == 0) {
      $this->_displayError("invalid table name");

      return false;
    }

    if (count($data) == 0) {
      $this->_displayError("empty data for REPLACE");

      return false;
    }

    // extracting column names
    $columns = array_keys($data);
    foreach ($columns as $k => $_key) {
      $columns[$k] = $this->quote_string($_key);
    }

    $columns = implode(",", $columns);

    // extracting values
    foreach ($data as $k => $_value) {
      $data[$k] = $this->secure($_value);
    }
    $values = implode(",", $data);

    $sql = "REPLACE INTO " . $this->quote_string($table) . " ($columns) VALUES ($values);";

    return $this->query($sql);
  }

  /**
   * update
   *
   * @param string $table
   * @param array  $data
   * @param string $where
   *
   * @return bool|int|Result
   */
  public function update($table, $data = array(), $where = '1=1')
  {

    $table = trim($table);

    if (strlen($table) == 0) {
      $this->_displayError("invalid table name");

      return false;
    }

    if (count($data) == 0) {
      $this->_displayError("empty data for UPDATE");

      return false;
    }

    $SET = $this->_parseArrayPair($data);

    if (is_string($where)) {
      $WHERE = ($where);
    } else if (is_array($where)) {
      $WHERE = $this->_parseArrayPair($where, "AND");
    } else {
      $WHERE = '';
    }

    $sql = "UPDATE " . $this->quote_string($table) . " SET $SET WHERE ($WHERE);";

    return $this->query($sql);
  }

  /**
   * delete
   *
   * @param string $table
   * @param        $where
   *
   * @return bool|int|Result
   */
  public function delete($table, $where)
  {

    $table = trim($table);

    if (strlen($table) == 0) {
      $this->_displayError("invalid table name");

      return false;
    }

    if (is_string($where)) {
      $WHERE = ($where);
    } else if (is_array($where)) {
      $WHERE = $this->_parseArrayPair($where, "AND");
    } else {
      $WHERE = '';
    }

    $sql = "DELETE FROM " . $this->quote_string($table) . " WHERE ($WHERE);";

    return $this->query($sql);
  }

  /**
   * select
   *
   * @param string $table
   * @param string $where
   *
   * @return bool|int|Result
   */
  function select($table, $where = '1=1')
  {

    if (strlen($table) == 0) {
      $this->_displayError("invalid table name");

      return false;
    }

    if (is_string($where)) {
      $WHERE = ($where);
    } else if (is_array($where)) {
      $WHERE = $this->_parseArrayPair($where, 'AND');
    } else {
      $WHERE = '';
    }

    $sql = "SELECT * FROM " . $this->quote_string($table) . " WHERE ($WHERE);";

    return $this->query($sql);
  }

  /**
   * get the last error
   *
   * @return string false on error
   */
  public function lastError()
  {
    return count($this->_errors) > 0 ? end($this->_errors) : false;
  }

  /**
   * __destruct
   *
   */
  function __destruct()
  {
    // close the connection only if we don't save PHP-SESSION's in DB
    if ($this->session_to_db === false) {
      $this->close();
    }

    return;
  }

  /**
   * close
   */
  public function close()
  {
    $this->connected = false;

    if ($this->link) {
      mysqli_close($this->link);
    }
  }

  /**
   * prevent the instance from being cloned
   *
   * @return void
   */
  private function __clone()
  {
  }

  /**
   * send a error mail to the admin / dev
   *
   * @param     $subject
   * @param     $htmlBody
   * @param int $priority
   */
  private function mailToAdmin($subject, $htmlBody, $priority = 3)
  {
    if (function_exists('mailToAdmin')) {
      mailToAdmin($subject, $htmlBody, $priority);
    } else {

      if ($priority == 3) {
        $this->logger(array('debug', $subject . ' | ' . $htmlBody));
      } else if ($priority > 3) {
        $this->logger(array('error', $subject . ' | ' . $htmlBody));
      } else if ($priority < 3) {
        $this->logger(array('info', $subject . ' | ' . $htmlBody));
      }

    }
  }

  /**
   * wrapper for a "Logger"-Class
   *
   * @param $log array [method, text, type] e.g.: array('error', 'this is a error', 'sql')
   */
  private function logger($log)
  {
    $logMethod = '';
    $logText = '';
    $logType = '';
    $logClass = $this->logger_class_name;

    $tmpCount = count($log);

    if ($tmpCount == 2) {
      $logMethod = $log[0];
      $logText = $log[1];
    } else if ($tmpCount == 3) {
      $logMethod = $log[0];
      $logText = $log[1];
      $logType = $log[2];
    }

    if ($logClass && class_exists($logClass)) {
      if ($logMethod && method_exists($logClass, $logMethod)) {
        $logClass::$logMethod($logText, $logType);
      }
    }
  }

}
