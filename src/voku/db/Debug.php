<?php

namespace voku\db;

use voku\db\exceptions\QueryException;
use voku\helper\Bootup;
use voku\helper\UTF8;

/**
 * Debug: This class can handle debug and error-logging for SQL-queries for the "Simple-MySQLi"-classes.
 *
 * @package   voku\db
 */
class Debug
{
  /**
   * @var array
   */
  private $_errors = array();

  /**
   * @var bool
   */
  private $exit_on_error = true;

  /**
   * echo the error if "checkForDev()" returns true
   *
   * @var bool
   */
  private $echo_on_error = true;

  /**
   * @var string
   */
  private $css_mysql_box_border = '3px solid red';

  /**
   * @var string
   */
  private $css_mysql_box_bg = '#FFCCCC';

  /**
   * @var string
   */
  private $logger_class_name;

  /**
   * @var DB
   */
  private $_db;

  /**
   * @var string
   *
   * 'TRACE', 'DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'
   */
  private $logger_level;

  /**
   * Debug constructor.
   *
   * @param DB $db
   */
  public function __construct(DB $db)
  {
    $this->_db = $db;
  }

  /**
   * Check is the current user is a developer.
   *
   * INFO:
   * By default we will return "true" if the remote-ip-address is localhost or
   * if the script is called via CLI. But you can also overwrite this method or
   * you can implement a global "checkForDev()"-function.
   *
   * @return bool
   */
  public function checkForDev()
  {
    // init
    $return = false;

    if (function_exists('checkForDev')) {
      $return = checkForDev();
    } else {

      // for testing with dev-address
      $noDev = isset($_GET['noDev']) ? (int)$_GET['noDev'] : 0;
      $remoteIpAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;

      if (
          $noDev != 1
          &&
          (
              $remoteIpAddress === '127.0.0.1'
              ||
              $remoteIpAddress === '::1'
              ||
              PHP_SAPI === 'cli'
          )
      ) {
        $return = true;
      }
    }

    return $return;
  }

  /**
   * Clear the errors in "$this->_errors".
   *
   * @return bool
   */
  public function clearErrors()
  {
    $this->_errors = array();

    return true;
  }

  /**
   * Display SQL-Errors or throw Exceptions (for dev).
   *
   * @param string       $error                       <p>The error message.</p>
   * @param null|boolean $force_exception_after_error <p>
   *                                                  If you use default "null" here, then the behavior depends
   *                                                  on "$this->exit_on_error (default: true)".
   *                                                  </p>
   *
   * @throws QueryException
   */
  public function displayError($error, $force_exception_after_error = null)
  {
    $fileInfo = $this->getFileAndLineFromSql();

    $this->logger(
        array(
            'error',
            '<strong>' . date(
                'd. m. Y G:i:s'
            ) . ' (' . $fileInfo['file'] . ' line: ' . $fileInfo['line'] . ') (sql-error):</strong> ' . $error . '<br>',
        )
    );

    $this->_errors[] = $error;

    if ($this->checkForDev() === true) {
      if ($this->echo_on_error) {
        $box_border = $this->css_mysql_box_border;
        $box_bg = $this->css_mysql_box_bg;

        echo '
        <div class="OBJ-mysql-box" style="border:' . $box_border . '; background:' . $box_bg . '; padding:10px; margin:10px;">
          <b style="font-size:14px;">MYSQL Error:</b>
          <code style="display:block;">
            file / line: ' . $fileInfo['file'] . ' / ' . $fileInfo['line'] . '
            ' . $error . '
          </code>
        </div>
        ';
      }
    }

    if (
        $force_exception_after_error === true
        ||
        (
            $force_exception_after_error === null
            &&
            $this->exit_on_error === true
        )
    ) {
      throw new QueryException($error);
    }
  }

  /**
   * Get errors from "$this->_errors".
   *
   * @return array
   */
  public function getErrors()
  {
    return $this->_errors;
  }

  /**
   * Try to get the file & line from the current sql-query.
   *
   * @return array will return array['file'] and array['line']
   */
  private function getFileAndLineFromSql()
  {
    // init
    $return = array();
    $file = '';
    $line = '';

    if (Bootup::is_php('5.4') === true) {
      $referrer = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
    } else {
      $referrer = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    }

    foreach ($referrer as $key => $ref) {

      if (
          $ref['function'] === 'execSQL'
          ||
          $ref['function'] === 'query'
          ||
          $ref['function'] === 'qry'
          ||
          $ref['function'] === 'execute'
          ||
          $ref['function'] === 'insert'
          ||
          $ref['function'] === 'update'
          ||
          $ref['function'] === 'replace'
          ||
          $ref['function'] === 'delete'
      ) {
        $file = $referrer[$key]['file'];
        $line = $referrer[$key]['line'];
      }

    }

    $return['file'] = $file;
    $return['line'] = $line;

    return $return;
  }

  /**
   * @return string
   */
  public function getLoggerClassName()
  {
    return $this->logger_class_name;
  }

  /**
   * @return string
   */
  public function getLoggerLevel()
  {
    return $this->logger_level;
  }

  /**
   * @return boolean
   */
  public function isEchoOnError()
  {
    return $this->echo_on_error;
  }

  /**
   * @return boolean
   */
  public function isExitOnError()
  {
    return $this->exit_on_error;
  }

  /**
   * Log the current query via "$this->logger".
   *
   * @param string $sql     sql-query
   * @param int    $duration
   * @param int    $results field_count | insert_id | affected_rows
   * @param bool   $sql_error
   *
   * @return bool
   */
  public function logQuery($sql, $duration, $results, $sql_error = false)
  {
    $logLevelUse = strtolower($this->logger_level);

    if (
        $sql_error === false
        &&
        ($logLevelUse !== 'trace' && $logLevelUse !== 'debug')
    ) {
      return false;
    }

    // set log-level
    $logLevel = $logLevelUse;
    if ($sql_error === true) {
      $logLevel = 'error';
    }

    // get extra info
    $infoExtra = \mysqli_info($this->_db->getLink());
    if ($infoExtra) {
      $infoExtra = ' | info => ' . $infoExtra;
    }

    //
    // logging
    //

    $info = 'time => ' . round($duration, 5) . ' | results => ' . (int)$results . $infoExtra . ' | SQL => ' . UTF8::htmlentities($sql);

    $fileInfo = $this->getFileAndLineFromSql();

    return $this->logger(
        array(
            $logLevel,
            '<strong>' . date('d. m. Y G:i:s') . ' (' . $fileInfo['file'] . ' line: ' . $fileInfo['line'] . '):</strong> ' . $info . '<br>',
            'sql',
        )
    );
  }

  /**
   * Wrapper-Function for a "Logger"-Class.
   *
   * INFO:
   * The "Logger"-ClassName is set by "$this->logger_class_name",<br />
   * the "Logger"-Method is the [0] element from the "$log"-parameter,<br />
   * the text you want to log is the [1] element and<br />
   * the type you want to log is the next [2] element.
   *
   * @param string[] $log [method, text, type]<br />e.g.: array('error', 'this is a error', 'sql')
   *
   * @return bool
   */
  public function logger(array $log)
  {
    $logMethod = '';
    $logText = '';
    $logType = '';
    $logClass = $this->logger_class_name;

    if (isset($log[0])) {
      $logMethod = $log[0];
    }
    if (isset($log[1])) {
      $logText = $log[1];
    }
    if (isset($log[2])) {
      $logType = $log[2];
    }

    if (
        $logClass
        &&
        class_exists($logClass)
        &&
        method_exists($logClass, $logMethod)
    ) {
      return $logClass::$logMethod($logText, $logType);
    }

    return false;
  }

  /**
   * Send a error mail to the admin / dev.
   *
   * @param string $subject
   * @param string $htmlBody
   * @param int    $priority
   */
  public function mailToAdmin($subject, $htmlBody, $priority = 3)
  {
    if (function_exists('mailToAdmin')) {
      mailToAdmin($subject, $htmlBody, $priority);
    } else {

      if ($priority === 3) {
        $this->logger(
            array(
                'debug',
                $subject . ' | ' . $htmlBody,
            )
        );
      } elseif ($priority > 3) {
        $this->logger(
            array(
                'error',
                $subject . ' | ' . $htmlBody,
            )
        );
      } elseif ($priority < 3) {
        $this->logger(
            array(
                'info',
                $subject . ' | ' . $htmlBody,
            )
        );
      }

    }
  }

  /**
   * @param boolean $echo_on_error
   */
  public function setEchoOnError($echo_on_error)
  {
    $this->echo_on_error = (boolean)$echo_on_error;
  }

  /**
   * @param boolean $exit_on_error
   */
  public function setExitOnError($exit_on_error)
  {
    $this->exit_on_error = (boolean)$exit_on_error;
  }

  /**
   * @param string $logger_class_name
   */
  public function setLoggerClassName($logger_class_name)
  {
    $this->logger_class_name = (string)$logger_class_name;
  }

  /**
   * @param string $logger_level
   */
  public function setLoggerLevel($logger_level)
  {
    $this->logger_level = (string)$logger_level;
  }

}
