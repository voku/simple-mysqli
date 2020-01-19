<?php

declare(strict_types=1);

namespace voku\db;

use voku\db\exceptions\QueryException;

/**
 * Debug: This class can handle debug and error-logging for SQL-queries for the "Simple-MySQLi"-classes.
 */
class Debug
{
    /**
     * @var array
     */
    private $_errors = [];

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
     * define what a slow query is in ms
     *
     * @var float
     */
    private $slowQueryTimeWarning = 0.005;

    /**
     * define what a slow query is in ms
     *
     * @var float
     */
    private $slowQueryTimeError = 0.1;

    /**
     * define what a max query repeat is
     *
     * @var int
     */
    private $maxQueryRepeatWarning = 20;

    /**
     * define what a max query repeat is
     *
     * @var int
     */
    private $maxQueryRepeatError = 50;

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
    public function checkForDev(): bool
    {
        // init
        $return = false;

        if (\function_exists('checkForDev')) {
            $return = checkForDev();
        } else {

            // for testing with dev-address
            $noDev = isset($_GET['noDev']) ? (int) $_GET['noDev'] : 0;
            $remoteIpAddress = $_SERVER['REMOTE_ADDR'] ?? false;

            if (
                $noDev !== 1
                &&
                (
                    $remoteIpAddress === '127.0.0.1'
                    ||
                    $remoteIpAddress === '::1'
                    ||
                    \PHP_SAPI === 'cli'
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
    public function clearErrors(): bool
    {
        $this->_errors = [];

        return true;
    }

    /**
     * Display SQL-Errors or throw Exceptions (for dev).
     *
     * @param string    $error                          <p>The error message.</p>
     * @param bool|null $force_exception_after_error    <p>
     *                                                  If you use default "null" here, then the behavior depends
     *                                                  on "$this->exit_on_error (default: true)".
     *                                                  </p>
     *
     * @throws QueryException
     *
     * @return void
     */
    public function displayError($error, $force_exception_after_error = null)
    {
        $fileInfo = $this->getFileAndLineFromSql();

        $log = '[' . \date('Y-m-d H:i:s') . ']: SQL-Error: ' . $error . ' | Trace: ' . $fileInfo['path'] . '<br>';

        $this->logger(['error', $log]);

        $this->_errors[] = $log;

        if (
            $this->echo_on_error
            &&
            $this->checkForDev() === true
        ) {
            $box_border = $this->css_mysql_box_border;
            $box_bg = $this->css_mysql_box_bg;

            if (\PHP_SAPI === 'cli') {
                echo "\n";
                echo 'Error: ' . $error . "\n";
                echo 'Trace: ' . $fileInfo['path'] . "\n";
                echo "\n";
            } else {
                echo '
                <div class="OBJ-mysql-box" style="border: ' . $box_border . '; background: ' . $box_bg . '; padding: 10px; margin: 10px;">
                  <b style="font-size: 14px;">MYSQL Error:</b>
                  <code style="display: block;">
                    Error:' . $error . '
                    <br><br>
                    Trace: ' . $fileInfo['path'] . '
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
    public function getErrors(): array
    {
        return $this->_errors;
    }

    /**
     * Try to get the file & line from the current sql-query.
     *
     * @return array will return array['path']
     */
    private function getFileAndLineFromSql(): array
    {
        // init
        $return = [];
        $path = '';
        $referrer = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($referrer as $key => $ref) {
            if (
                isset($ref['class'])
                &&
                (
                    $ref['class'] === DB::class
                    ||
                    $ref['class'] === self::class
                )
            ) {
                continue;
            }

            $path .= ($referrer[$key]['class'] ?? $referrer[$key]['file'] ?? '') . '::' . ($referrer[$key]['function'] ?? '') . ':' . ($referrer[$key - 1]['line'] ?? '') . ' <- ';
        }

        $return['path'] = $path;

        return $return;
    }

    /**
     * @return string
     */
    public function getLoggerClassName(): string
    {
        return $this->logger_class_name;
    }

    /**
     * @return string
     */
    public function getLoggerLevel(): string
    {
        return $this->logger_level;
    }

    /**
     * @return bool
     */
    public function isEchoOnError(): bool
    {
        return $this->echo_on_error;
    }

    /**
     * @return bool
     */
    public function isExitOnError(): bool
    {
        return $this->exit_on_error;
    }

    /**
     * Log the current query via "$this->logger".
     *
     * @param string     $sql sql-query
     * @param float|int  $duration
     * @param false|int|string|null $results field_count | insert_id | affected_rows
     * @param bool       $sql_error
     *
     * @return false|mixed
     *                     <p>Will return false, if no logging was used.</p>
     */
    public function logQuery($sql, $duration, $results, bool $sql_error = false)
    {
        $logLevelUse = \strtolower($this->logger_level);

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

        //
        // logging
        //

        $traceStringExtra = '';
        if ($logLevelUse === 'trace') {
            $tmpLink = $this->_db->getLink();
            if ($tmpLink && $tmpLink instanceof \mysqli) {
                /** @noinspection PhpUsageOfSilenceOperatorInspection */
                $traceStringExtra = @\mysqli_info($tmpLink);
                if ($traceStringExtra) {
                    $traceStringExtra = ' | info => ' . $traceStringExtra;
                }
            }

            $traceStringExtra = ' | results => ' . \print_r($results, true) . $traceStringExtra;
        }

        static $SLOW_QUERY_WARNING = null;
        static $QUERY_LOG_FILE_INFO = [];

        $queryStatus = '';
        if ($duration >= $this->slowQueryTimeWarning) {
            $queryStatus = ' WARN (DURATION) ';
        }
        if ($duration >= $this->slowQueryTimeError) {
            $queryStatus = ' ERROR (DURATION) ';
        }

        $fileInfo = $this->getFileAndLineFromSql();
        $cacheKey = \md5($fileInfo['path']);
        if (empty($QUERY_LOG_FILE_INFO[$cacheKey])) {
            $QUERY_LOG_FILE_INFO[$cacheKey] = 0;
        }
        ++$QUERY_LOG_FILE_INFO[$cacheKey];

        if ($QUERY_LOG_FILE_INFO[$cacheKey] >= $this->maxQueryRepeatWarning) {
            $queryStatus = ' WARN (REPEAT) ';
        }
        if ($QUERY_LOG_FILE_INFO[$cacheKey] >= $this->maxQueryRepeatError) {
            $queryStatus = ' ERROR (REPEAT) ';
        }

        $queryLog = '[' . \date('Y-m-d H:i:s') . ']: ' . $queryStatus . ' Duration: SQL::::DURATION-START' . \round($duration, 5) . 'SQL::::DURATION-END | Repeat: ' . $QUERY_LOG_FILE_INFO[$cacheKey] . ' | Host: ' . $this->_db->getConfig()['hostname'] . ' | Trace: ' . $fileInfo['path'] . ' | SQL: SQL::::QUERY-START ' . \str_replace("\n", '', $sql) . ' SQL::::QUERY-END' . $traceStringExtra . "\n";

        return $this->logger([$logLevel, $queryLog, 'sql']);
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
     * @return false|mixed
     *                     <p>Will return false, if no logging was used.</p>
     */
    public function logger(array $log)
    {
        // init
        $logMethod = '';
        $logText = '';
        $logType = 'sql';
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
            $logMethod
            &&
            \class_exists($logClass)
            &&
            \method_exists($logClass, $logMethod)
        ) {
            if (\method_exists($logClass, 'getInstance')) {
                return $logClass::getInstance()->{$logMethod}($logText, ['log_type' => $logType]);
            }

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
     *
     * @return void
     */
    public function mailToAdmin($subject, $htmlBody, $priority = 3)
    {
        if (\function_exists('mailToAdmin')) {
            mailToAdmin($subject, $htmlBody, $priority);
        } else {
            if ($priority === 3) {
                $this->logger(['debug', $subject . ' | ' . $htmlBody]);
            } elseif ($priority > 3) {
                $this->logger(['error', $subject . ' | ' . $htmlBody]);
            } else {
                $this->logger(['info', $subject . ' | ' . $htmlBody]);
            }
        }
    }

    /**
     * @param bool $echo_on_error
     *
     * @return void
     */
    public function setEchoOnError($echo_on_error)
    {
        $this->echo_on_error = (bool) $echo_on_error;
    }

    /**
     * @param bool $exit_on_error
     *
     * @return void
     */
    public function setExitOnError($exit_on_error)
    {
        $this->exit_on_error = (bool) $exit_on_error;
    }

    /**
     * @param string $logger_class_name
     *
     * @return void
     */
    public function setLoggerClassName($logger_class_name)
    {
        $this->logger_class_name = (string) $logger_class_name;
    }

    /**
     * @param string $logger_level
     *
     * @return void
     */
    public function setLoggerLevel($logger_level)
    {
        $this->logger_level = (string) $logger_level;
    }
}
