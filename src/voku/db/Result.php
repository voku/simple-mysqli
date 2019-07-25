<?php

declare(strict_types=1);

namespace voku\db;

use Arrayy\Arrayy;
use Symfony\Component\PropertyAccess\PropertyAccess;
use voku\helper\UTF8;

/**
 * Result: This class can handle the results from the "DB"-class.
 */
final class Result implements \Countable, \SeekableIterator, \ArrayAccess
{
    const MYSQL_TYPE_BIT = 16;

    const MYSQL_TYPE_BLOB = 252;

    const MYSQL_TYPE_DATE = 10;

    const MYSQL_TYPE_DATETIME = 12;

    const MYSQL_TYPE_DECIMAL = 0;

    const MYSQL_TYPE_DOUBLE = 5;

    const MYSQL_TYPE_ENUM = 247;

    const MYSQL_TYPE_FLOAT = 4;

    const MYSQL_TYPE_GEOMETRY = 255;

    const MYSQL_TYPE_INT24 = 9;

    const MYSQL_TYPE_JSON = 245;

    const MYSQL_TYPE_LONG = 3;

    const MYSQL_TYPE_LONGLONG = 8;

    const MYSQL_TYPE_LONG_BLOB = 251;

    const MYSQL_TYPE_MEDIUM_BLOB = 250;

    const MYSQL_TYPE_NEWDATE = 14;

    const MYSQL_TYPE_NEWDECIMAL = 246;

    const MYSQL_TYPE_NULL = 6;

    const MYSQL_TYPE_SET = 248;

    const MYSQL_TYPE_SHORT = 2;

    const MYSQL_TYPE_STRING = 254;

    const MYSQL_TYPE_TIME = 11;

    const MYSQL_TYPE_TIMESTAMP = 7;

    const MYSQL_TYPE_TINY = 1;

    const MYSQL_TYPE_TINY_BLOB = 249;

    const MYSQL_TYPE_VARCHAR = 15;

    const MYSQL_TYPE_VAR_STRING = 253;

    const MYSQL_TYPE_YEAR = 13;

    const RESULT_TYPE_ARRAY = 'array';

    const RESULT_TYPE_ARRAYY = 'Arrayy';

    const RESULT_TYPE_OBJECT = 'object';

    const RESULT_TYPE_YIELD = 'yield';

    /**
     * @var int
     */
    public $num_rows;

    /**
     * @var string
     */
    public $sql;

    /**
     * @var \Doctrine\DBAL\Statement|\mysqli_result
     */
    private $_result;

    /**
     * @var int
     */
    private $current_row;

    /**
     * @var \Closure|null
     */
    private $_mapper;

    /**
     * @var string
     */
    private $_default_result_type = self::RESULT_TYPE_OBJECT;

    /**
     * @var \mysqli_stmt|null
     */
    private $doctrineMySQLiStmt;

    /**
     * @var \Doctrine\DBAL\Driver\PDOStatement|null
     */
    private $doctrinePdoStmt;

    /**
     * @var int
     */
    private $doctrinePdoStmtDataSeekFake = 0;

    /**
     * @var bool
     */
    private $doctrinePdoStmtDataSeekInit = false;

    /**
     * @var array
     */
    private $doctrinePdoStmtDataSeekFakeCache = [];

    /**
     * Result constructor.
     *
     * @param string                                  $sql
     * @param \Doctrine\DBAL\Statement|\mysqli_result $result
     * @param \Closure                                $mapper Optional callback mapper for the "fetchCallable()" method
     */
    public function __construct(string $sql, $result, \Closure $mapper = null)
    {
        $this->sql = $sql;

        if (
            !$result instanceof \mysqli_result
            &&
            !$result instanceof \Doctrine\DBAL\Statement
        ) {
            throw new \InvalidArgumentException('$result must be ' . \mysqli_result::class . ' or ' . \Doctrine\DBAL\Statement::class . ' !');
        }

        $this->_result = $result;

        if ($this->_result instanceof \Doctrine\DBAL\Statement) {
            $doctrineDriver = $this->_result->getWrappedStatement();

            if ($doctrineDriver instanceof \Doctrine\DBAL\Driver\PDOStatement) {
                $this->doctrinePdoStmt = $doctrineDriver;
            }

            if ($doctrineDriver instanceof \Doctrine\DBAL\Driver\Mysqli\MysqliStatement) {
                // try to get the mysqli driver from doctrine
                $reflectionTmp = new \ReflectionClass($doctrineDriver);
                $propertyTmp = $reflectionTmp->getProperty('_stmt');
                $propertyTmp->setAccessible(true);
                $this->doctrineMySQLiStmt = $propertyTmp->getValue($doctrineDriver);
            }

            $this->num_rows = $this->_result->rowCount();
        } else {
            $this->num_rows = (int) $this->_result->num_rows;
        }

        $this->current_row = 0;

        $this->_mapper = $mapper;
    }

    /**
     * __destruct
     */
    public function __destruct()
    {
        $this->free();
    }

    /**
     * Runs a user-provided callback with the MySQLi_Result object given as
     * argument and returns the result, or returns the MySQLi_Result object if
     * called without an argument.
     *
     * @param callable $callback User-provided callback (optional)
     *
     * @return \Doctrine\DBAL\Statement|mixed|\mysqli_result
     */
    public function __invoke(callable $callback = null)
    {
        if ($callback !== null) {
            return $callback($this->_result);
        }

        return $this->_result;
    }

    /**
     * Get the current "num_rows" as string.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->num_rows;
    }

    /**
     * Cast data into int, float or string.
     *
     * <p>
     *   <br />
     *   INFO: install / use "mysqlnd"-driver for better performance
     * </p>
     *
     * @param array|object $data
     *
     * @return array|false|object
     *                            <p><strong>false</strong> on error</p>
     */
    private function cast(&$data)
    {
        if (
            !$this->doctrinePdoStmt // pdo only have limited support for types, so we try to improve it
            &&
            Helper::isMysqlndIsUsed()
        ) {
            return $data;
        }

        // init
        static $FIELDS_CACHE = [];
        static $TYPES_CACHE = [];

        $result_hash = \spl_object_hash($this->_result);

        if (!isset($FIELDS_CACHE[$result_hash])) {
            $FIELDS_CACHE[$result_hash] = $this->fetch_fields();
        }

        if (
            !isset($FIELDS_CACHE[$result_hash])
            ||
            $FIELDS_CACHE[$result_hash] === false
        ) {
            return false;
        }

        if (!isset($TYPES_CACHE[$result_hash])) {
            foreach ($FIELDS_CACHE[$result_hash] as $field) {
                switch ($field->type) {
                    case self::MYSQL_TYPE_BIT:
                        $TYPES_CACHE[$result_hash][$field->name] = 'boolean';

                        break;
                    case self::MYSQL_TYPE_TINY:
                    case self::MYSQL_TYPE_SHORT:
                    case self::MYSQL_TYPE_LONG:
                    case self::MYSQL_TYPE_LONGLONG:
                    case self::MYSQL_TYPE_INT24:
                        $TYPES_CACHE[$result_hash][$field->name] = 'integer';

                        break;
                    case self::MYSQL_TYPE_DOUBLE:
                    case self::MYSQL_TYPE_DECIMAL:
                    case self::MYSQL_TYPE_NEWDECIMAL:
                    case self::MYSQL_TYPE_FLOAT:
                        $TYPES_CACHE[$result_hash][$field->name] = 'float';

                        break;
                    default:
                        $TYPES_CACHE[$result_hash][$field->name] = 'string';

                        break;
                }
            }
        }

        if (\is_array($data)) {
            foreach ($TYPES_CACHE[$result_hash] as $type_name => $type) {
                if (isset($data[$type_name])) {
                    \settype($data[$type_name], $type);
                }
            }
        } elseif (\is_object($data)) {
            foreach ($TYPES_CACHE[$result_hash] as $type_name => $type) {
                if (isset($data->{$type_name})) {
                    \settype($data->{$type_name}, $type);
                }
            }
        }

        return $data;
    }

    /**
     * Countable interface implementation.
     *
     * @return int The number of rows in the result
     */
    public function count(): int
    {
        return $this->num_rows;
    }

    /**
     * Iterator interface implementation.
     *
     * @return mixed The current element
     */
    public function current()
    {
        return $this->fetchCallable($this->current_row);
    }

    /**
     * Iterator interface implementation.
     *
     * @return int The current element key (row index; zero-based)
     */
    public function key(): int
    {
        return $this->current_row;
    }

    /**
     * Iterator interface implementation.
     *
     * @return void
     */
    public function next()
    {
        $this->current_row++;
    }

    /**
     * Iterator interface implementation.
     *
     * @param int $row Row position to rewind to; defaults to 0
     *
     * @return void
     */
    public function rewind($row = 0)
    {
        if ($this->seek($row)) {
            $this->current_row = $row;
        }
    }

    /**
     * Moves the internal pointer to the specified row position.
     *
     * @param int $row <p>Row position; zero-based and set to 0 by default</p>
     *
     * @return bool
     *              <p>true on success, false otherwise</p>
     */
    public function seek($row = 0): bool
    {
        if (\is_int($row) && $row >= 0 && $row < $this->num_rows) {
            if (
                $this->doctrineMySQLiStmt
                &&
                $this->doctrineMySQLiStmt instanceof \mysqli_stmt
            ) {
                $this->doctrineMySQLiStmt->data_seek($row);

                return true;
            }

            if (
                $this->doctrinePdoStmt
                &&
                $this->doctrinePdoStmt instanceof \Doctrine\DBAL\Driver\PDOStatement
            ) {
                return true;
            }

            if ($this->_result instanceof \mysqli_result) {
                return \mysqli_data_seek($this->_result, $row);
            }
        }

        return false;
    }

    /**
     * Iterator interface implementation.
     *
     * @return bool
     *              <p>true if the current index is valid, false otherwise</p>
     */
    public function valid(): bool
    {
        return $this->current_row < $this->num_rows;
    }

    /**
     * Fetch.
     *
     * <p>
     *   <br />
     *   INFO: this will return an object by default, not an array<br />
     *   and you can change the behaviour via "Result->setDefaultResultType()"
     * </p>
     *
     * @param bool $reset optional <p>Reset the \mysqli_result counter.</p>
     *
     * @return array|false|object
     *                            <p><strong>false</strong> on error</p>
     */
    public function fetch(bool $reset = false)
    {
        $return = false;

        if ($this->_default_result_type === self::RESULT_TYPE_OBJECT) {
            $return = $this->fetchObject('', null, $reset);
        } elseif ($this->_default_result_type === self::RESULT_TYPE_ARRAY) {
            $return = $this->fetchArray($reset);
        } elseif ($this->_default_result_type === self::RESULT_TYPE_ARRAYY) {
            $return = $this->fetchArrayy($reset);
        } elseif ($this->_default_result_type === self::RESULT_TYPE_YIELD) {
            $return = $this->fetchYield('', null, $reset);
        }

        return $return;
    }

    /**
     * Fetch all results.
     *
     * <p>
     *   <br />
     *   INFO: this will return an object by default, not an array<br />
     *   and you can change the behaviour via "Result->setDefaultResultType()"
     * </p>
     *
     * @return array
     */
    public function fetchAll(): array
    {
        $return = [];

        if ($this->_default_result_type === self::RESULT_TYPE_OBJECT) {
            $return = $this->fetchAllObject();
        } elseif ($this->_default_result_type === self::RESULT_TYPE_ARRAY) {
            $return = $this->fetchAllArray();
        } elseif ($this->_default_result_type === self::RESULT_TYPE_ARRAYY) {
            $return = $this->fetchAllArrayy();
        } elseif ($this->_default_result_type === self::RESULT_TYPE_YIELD) {
            $return = $this->fetchAllYield();
        }

        return $return;
    }

    /**
     * Fetch all results as array.
     *
     * @return array
     */
    public function fetchAllArray(): array
    {
        if ($this->is_empty()) {
            return [];
        }

        $this->reset();

        $data = [];
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $this->fetch_assoc()) {
            $data[] = $this->cast($row);
        }

        return $data;
    }

    /**
     * Fetch all results as "Arrayy"-object.
     *
     * @return Arrayy
     */
    public function fetchAllArrayy(): Arrayy
    {
        if ($this->is_empty()) {
            return Arrayy::create([]);
        }

        $this->reset();

        $data = [];
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $this->fetch_assoc()) {
            $data[] = $this->cast($row);
        }

        return Arrayy::create($data);
    }

    /**
     * Fetch all results as "Arrayy"-object.
     *
     * @return Arrayy
     */
    public function fetchAllArrayyYield(): Arrayy
    {
        if ($this->is_empty()) {
            return Arrayy::create([]);
        }

        $this->reset();

        return Arrayy::createFromGeneratorFunction(
            function () {
                /** @noinspection PhpAssignmentInConditionInspection */
                while ($row = $this->fetch_assoc()) {
                    yield $this->cast($row);
                }
            }
        );
    }

    /**
     * Fetch a single column as an 1-dimension array.
     *
     * @param string $column
     * @param bool   $skipNullValues <p>Skip "NULL"-values. | default: false</p>
     *
     * @return array
     *               <p>Return an empty array if the "$column" wasn't found</p>
     */
    public function fetchAllColumn(string $column, bool $skipNullValues = false): array
    {
        $return = $this->fetchColumn($column, $skipNullValues, true);

        \assert(\is_array($return));

        return $return;
    }

    /**
     * Fetch all results as array with objects.
     *
     * @param object|string $class  <p>
     *                              <strong>string</strong>: create a new object (with optional constructor
     *                              parameter)<br>
     *                              <strong>object</strong>: use a object and fill the the data into
     *                              </p>
     * @param array|null    $params optional
     *                              <p>
     *                              An array of parameters to pass to the constructor, used if $class is a
     *                              string.
     *                              </p>
     *
     * @return array
     */
    public function fetchAllObject($class = '', array $params = null): array
    {
        if ($this->is_empty()) {
            return [];
        }

        // fallback
        if (!$class || $class === 'stdClass') {
            $class = \stdClass::class;
        }

        // init
        $data = [];
        $this->reset();

        if (\is_object($class)) {
            $classTmpOrig = new $class();
        } elseif ($class && $params) {
            $reflectorTmp = new \ReflectionClass($class);
            $classTmpOrig = $reflectorTmp->newInstanceArgs($params);
        } else {
            $classTmpOrig = new $class();
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $this->fetch_assoc()) {
            $classTmp = clone $classTmpOrig;
            $row = $this->cast($row);
            if ($row !== false) {
                foreach ($row as $key => $value) {
                    if ($class === \stdClass::class) {
                        $classTmp->{$key} = $value;
                    } else {
                        $propertyAccessor->setValue($classTmp, $key, $value);
                    }
                }
            }

            $data[] = $classTmp;
        }

        return $data;
    }

    /**
     * Fetch all results as "\Generator" via yield.
     *
     * @param object|string $class  <p>
     *                              <strong>string</strong>: create a new object (with optional constructor
     *                              parameter)<br>
     *                              <strong>object</strong>: use a object and fill the the data into
     *                              </p>
     * @param array|null    $params optional
     *                              <p>
     *                              An array of parameters to pass to the constructor, used if $class is a
     *                              string.
     *                              </p>
     *
     * @return \Generator
     */
    public function fetchAllYield($class = '', array $params = null): \Generator
    {
        if ($this->is_empty()) {
            return;
        }

        // init
        $this->reset();

        // fallback
        if (!$class || $class === 'stdClass') {
            $class = \stdClass::class;
        }

        if (\is_object($class)) {
            $classTmpOrig = $class;
        } elseif ($class && $params) {
            $reflectorTmp = new \ReflectionClass($class);
            $classTmpOrig = $reflectorTmp->newInstanceArgs($params);
        } else {
            $classTmpOrig = new $class();
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $this->fetch_assoc()) {
            $classTmp = clone $classTmpOrig;

            $row = $this->cast($row);
            if ($row !== false) {
                foreach ($row as $key => $value) {
                    if ($class === \stdClass::class) {
                        $classTmp->{$key} = $value;
                    } else {
                        $propertyAccessor->setValue($classTmp, $key, $value);
                    }
                }
            }

            yield $classTmp;
        }
    }

    /**
     * Fetch as array.
     *
     * @param bool $reset
     *
     * @return array|false
     *                     <p><strong>false</strong> on error</p>
     */
    public function fetchArray(bool $reset = false)
    {
        if ($reset) {
            $this->reset();
        }

        $row = $this->fetch_assoc();
        if ($row) {
            $return = $this->cast($row);

            \assert(\is_array($return));

            return $return;
        }

        if ($row === null || $row === false) {
            return [];
        }

        return false;
    }

    /**
     * Fetch data as a key/value pair array.
     *
     * <p>
     *   <br />
     *   INFO: both "key" and "value" must exists in the fetched data
     *   the key will be the new key of the result-array
     *   <br /><br />
     * </p>
     *
     * e.g.:
     * <code>
     *    fetchArrayPair('some_id', 'some_value');
     *    // array(127 => 'some value', 128 => 'some other value')
     * </code>
     *
     * @param string $key
     * @param string $value
     *
     * @return array
     */
    public function fetchArrayPair(string $key, string $value): array
    {
        $arrayPair = [];
        $data = $this->fetchAllArray();

        foreach ($data as &$_row) {
            if (
                \array_key_exists($key, $_row)
                &&
                \array_key_exists($value, $_row)
            ) {
                $_key = $_row[$key];
                $_value = $_row[$value];
                $arrayPair[$_key] = $_value;
            }
        }

        return $arrayPair;
    }

    /**
     * Fetch as "Arrayy"-object.
     *
     * @param bool $reset optional <p>Reset the \mysqli_result counter.</p>
     *
     * @return Arrayy|false
     *                      <p><strong>false</strong> on error</p>
     */
    public function fetchArrayy(bool $reset = false)
    {
        if ($reset) {
            $this->reset();
        }

        $row = $this->fetch_assoc();
        if ($row) {
            return Arrayy::create($this->cast($row));
        }

        if ($row === null || $row === false) {
            return Arrayy::create();
        }

        return false;
    }

    /**
     * Fetches a row or a single column within a row. Returns null if there are
     * no more rows in the result.
     *
     * @param int    $row    The row number (optional)
     * @param string $column The column name (optional)
     *
     * @return mixed An associative array or a scalar value
     */
    public function fetchCallable(int $row = null, string $column = null)
    {
        if (!$this->num_rows) {
            return null;
        }

        if ($row !== null) {
            $this->seek($row);
        }

        $rows = $this->fetch_assoc();

        if ($column) {
            if (
                \is_array($rows)
                &&
                isset($rows[$column])
            ) {
                return $rows[$column];
            }

            return null;
        }

        if (\is_callable($this->_mapper)) {
            return \call_user_func($this->_mapper, $rows);
        }

        return $rows;
    }

    /**
     * Fetch a single column as string (or as 1-dimension array).
     *
     * @param string $column
     * @param bool   $skipNullValues <p>Skip "NULL"-values. | default: true</p>
     * @param bool   $asArray        <p>Get all values and not only the last one. | default: false</p>
     *
     * @return array|string
     *                      <p>Return a empty string or an empty array if the "$column" wasn't found, depend on
     *                      "$asArray"</p>
     */
    public function fetchColumn(
        string $column = '',
        bool $skipNullValues = true,
        bool $asArray = false
    ) {
        if (!$asArray) {
            $columnData = '';

            $data = $this->fetchAllArrayy()->reverse()->getArray();
            foreach ($data as $_row) {
                if ($skipNullValues) {
                    if (!isset($_row[$column])) {
                        continue;
                    }
                } elseif (!\array_key_exists($column, $_row)) {
                    break;
                }

                $columnData = $_row[$column];

                break;
            }

            return $columnData;
        }

        // -- return as array -->

        $columnData = [];

        foreach ($this->fetchAllYield() as $_row) {
            if ($skipNullValues) {
                if (!isset($_row->{$column})) {
                    continue;
                }
            } elseif (!\array_key_exists($column, $_row)) {
                break;
            }

            $columnData[] = $_row->{$column};
        }

        return $columnData;
    }

    /**
     * Return rows of field information in a result set.
     *
     * @param bool $as_array Return each field info as array; defaults to false
     *
     * @return array
     *               <p>Array of field information each as an associative array.</p>
     */
    public function fetchFields(bool $as_array = false): array
    {
        $fields = $this->fetch_fields();
        if ($fields === false) {
            return [];
        }

        if ($as_array) {
            return \array_map(
                static function ($object) {
                    return (array) $object;
                },
                $fields
            );
        }

        return $fields;
    }

    /**
     * Returns all rows at once as a grouped array of scalar values or arrays.
     *
     * @param string $group  The column name to use for grouping
     * @param string $column The column name to use as values (optional)
     *
     * @return array
     *               <p>A grouped array of scalar values or arrays.</p>
     */
    public function fetchGroups(string $group, string $column = null): array
    {
        // init
        $groups = [];
        $pos = $this->current_row;

        foreach ($this as $row) {
            if (!\array_key_exists($group, $row)) {
                continue;
            }

            if ($column !== null) {
                if (!\array_key_exists($column, $row)) {
                    continue;
                }

                $groups[$row[$group]][] = $row[$column];
            } else {
                $groups[$row[$group]][] = $row;
            }
        }

        $this->rewind($pos);

        return $groups;
    }

    /**
     * Fetch as object.
     *
     * @param object|string $class  <p>
     *                              <strong>string</strong>: create a new object (with optional constructor
     *                              parameter)<br>
     *                              <strong>object</strong>: use a object and fill the the data into
     *                              </p>
     * @param array|null    $params optional
     *                              <p>
     *                              An array of parameters to pass to the constructor, used if $class is a
     *                              string.
     *                              </p>
     * @param bool          $reset  optional <p>Reset the \mysqli_result counter.</p>
     *
     * @return false|object
     *                      <p><strong>false</strong> on error</p>
     */
    public function fetchObject($class = '', array $params = null, bool $reset = false)
    {
        if ($reset) {
            $this->reset();
        }

        // fallback
        if (!$class || $class === 'stdClass') {
            $class = \stdClass::class;
        }

        $row = $this->fetch_assoc();
        $row = $row ? $this->cast($row) : false;

        if (!$row) {
            return false;
        }

        if (\is_object($class)) {
            $classTmp = $class;
        } elseif ($class && $params) {
            $reflectorTmp = new \ReflectionClass($class);
            $classTmp = $reflectorTmp->newInstanceArgs($params);
        } else {
            $classTmp = new $class();
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($row as $key => $value) {
            if ($class === \stdClass::class) {
                $classTmp->{$key} = $value;
            } else {
                $propertyAccessor->setValue($classTmp, $key, $value);
            }
        }

        return $classTmp;
    }

    /**
     * Returns all rows at once as key-value pairs.
     *
     * @param string $key    The column name to use as keys
     * @param string $column The column name to use as values (optional)
     *
     * @return array
     *               <p>An array of key-value pairs.</p>
     */
    public function fetchPairs(string $key, string $column = null): array
    {
        // init
        $pairs = [];
        $pos = $this->current_row;

        foreach ($this as $row) {
            if (!\array_key_exists($key, $row)) {
                continue;
            }

            if ($column !== null) {
                if (!\array_key_exists($column, $row)) {
                    continue;
                }

                $pairs[$row[$key]] = $row[$column];
            } else {
                $pairs[$row[$key]] = $row;
            }
        }

        $this->rewind($pos);

        return $pairs;
    }

    /**
     * Returns all rows at once, transposed as an array of arrays. Instead of
     * returning rows of columns, this method returns columns of rows.
     *
     * @param string $column The column name to use as keys (optional)
     *
     * @return array
     *               <p>A transposed array of arrays</p>
     */
    public function fetchTranspose(string $column = null)
    {
        // init
        $keys = $column !== null ? $this->fetchAllColumn($column) : [];
        $rows = [];
        $pos = $this->current_row;

        foreach ($this as $row) {
            foreach ($row as $key => $value) {
                $rows[$key][] = $value;
            }
        }

        $this->rewind($pos);

        if (empty($keys)) {
            return $rows;
        }

        return \array_map(
            static function ($values) use ($keys) {
                return \array_combine($keys, $values);
            },
            $rows
        );
    }

    /**
     * Fetch as "\Generator" via yield.
     *
     * @param object|string $class  <p>
     *                              <strong>string</strong>: create a new object (with optional constructor
     *                              parameter)<br>
     *                              <strong>object</strong>: use a object and fill the the data into
     *                              </p>
     * @param array|null    $params optional
     *                              <p>
     *                              An array of parameters to pass to the constructor, used if $class is a
     *                              string.
     *                              </p>
     * @param bool          $reset  optional <p>Reset the \mysqli_result counter.</p>
     *
     * @return \Generator
     */
    public function fetchYield($class = '', array $params = null, bool $reset = false): \Generator
    {
        if ($reset) {
            $this->reset();
        }

        // fallback
        if (!$class || $class === 'stdClass') {
            $class = \stdClass::class;
        }

        if (\is_object($class)) {
            $classTmp = $class;
        } elseif ($class && $params) {
            $reflectorTmp = new \ReflectionClass($class);
            $classTmp = $reflectorTmp->newInstanceArgs($params);
        } else {
            $classTmp = new $class();
        }

        $row = $this->fetch_assoc();
        $row = $row ? $this->cast($row) : false;

        if (!$row) {
            return;
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($row as $key => $value) {
            if ($class === \stdClass::class) {
                $classTmp->{$key} = $value;
            } else {
                $propertyAccessor->setValue($classTmp, $key, $value);
            }
        }

        yield $classTmp;
    }

    /**
     * @return mixed|null
     */
    private function fetch_assoc()
    {
        if ($this->_result instanceof \Doctrine\DBAL\Statement) {
            if (
                $this->doctrinePdoStmt
                &&
                $this->doctrinePdoStmt instanceof \Doctrine\DBAL\Driver\PDOStatement
            ) {
                if ($this->doctrinePdoStmtDataSeekInit === false) {
                    $this->doctrinePdoStmtDataSeekInit = true;

                    $this->doctrinePdoStmtDataSeekFakeCache = $this->_result->fetchAll(\Doctrine\DBAL\FetchMode::ASSOCIATIVE);
                }

                $return = ($this->doctrinePdoStmtDataSeekFakeCache[$this->doctrinePdoStmtDataSeekFake] ?? null);

                $this->doctrinePdoStmtDataSeekFake++;

                return $return;
            }

            if (
                $this->doctrineMySQLiStmt
                &&
                $this->doctrineMySQLiStmt instanceof \mysqli_stmt
            ) {
                return $this->_result->fetch(
                    \Doctrine\DBAL\FetchMode::ASSOCIATIVE,
                    0 // FETCH_ORI_NEXT
                );
            }

            return null;
        }

        return \mysqli_fetch_assoc($this->_result);
    }

    /**
     * @return array|false
     */
    private function fetch_fields()
    {
        if ($this->_result instanceof \mysqli_result) {
            return \mysqli_fetch_fields($this->_result);
        }

        if ($this->doctrineMySQLiStmt) {
            $metadataTmp = $this->doctrineMySQLiStmt->result_metadata();
            if ($metadataTmp === false) {
                return [];
            }

            return $metadataTmp->fetch_fields();
        }

        if ($this->doctrinePdoStmt) {
            $fields = [];

            static $THIS_CLASS_TMP = null;
            if ($THIS_CLASS_TMP === null) {
                $THIS_CLASS_TMP = new \ReflectionClass(__CLASS__);
            }

            $totalColumnsTmp = $this->doctrinePdoStmt->columnCount();
            for ($counterTmp = 0; $counterTmp < $totalColumnsTmp; $counterTmp++) {
                $metadataTmp = $this->doctrinePdoStmt->getColumnMeta($counterTmp);
                $fieldTmp = new \stdClass();
                foreach ($metadataTmp as $metadataTmpKey => $metadataTmpValue) {
                    $fieldTmp->{$metadataTmpKey} = $metadataTmpValue;
                }

                $typeNativeTmp = 'MYSQL_TYPE_' . $metadataTmp['native_type'];
                $typeTmp = $THIS_CLASS_TMP->getConstant($typeNativeTmp);
                if ($typeTmp) {
                    $fieldTmp->type = $typeTmp;
                } else {
                    $fieldTmp->type = '';
                }

                $fields[] = $fieldTmp;
            }

            return $fields;
        }

        return false;
    }

    /**
     * Returns the first row element from the result.
     *
     * @param string $column The column name to use as value (optional)
     *
     * @return mixed
     *               <p>A row array or a single scalar value</p>
     */
    public function first(string $column = null)
    {
        $pos = $this->current_row;
        $first = $this->fetchCallable(0, $column);
        $this->rewind($pos);

        return $first;
    }

    /**
     * free the memory
     */
    public function free()
    {
        if ($this->_result instanceof \mysqli_result) {
            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            @\mysqli_free_result($this->_result);

            return true;
        }

        if (
            $this->doctrineMySQLiStmt
            &&
            $this->doctrineMySQLiStmt instanceof \mysqli_stmt
        ) {
            $this->doctrineMySQLiStmt->free_result();

            return true;
        }

        return false;
    }

    /**
     * alias for "Result->fetch()"
     *
     * @return array|false|object
     *                            <p><strong>false</strong> on error</p>
     *
     * @see Result::fetch()
     */
    public function get()
    {
        return $this->fetch();
    }

    /**
     * alias for "Result->fetchAll()"
     *
     * @return array
     *
     * @see Result::fetchAll()
     */
    public function getAll(): array
    {
        return $this->fetchAll();
    }

    /**
     * alias for "Result->fetchAllColumn()"
     *
     * @param string $column
     * @param bool   $skipNullValues
     *
     * @return array
     *
     * @see Result::fetchAllColumn()
     */
    public function getAllColumn(string $column, bool $skipNullValues = false): array
    {
        return $this->fetchAllColumn($column, $skipNullValues);
    }

    /**
     * alias for "Result->fetchAllArray()"
     *
     * @return array
     *
     * @see Result::fetchAllArray()
     */
    public function getArray(): array
    {
        return $this->fetchAllArray();
    }

    /**
     * alias for "Result->fetchAllArrayy()"
     *
     * @return Arrayy
     *
     * @see Result::fetchAllArrayy()
     */
    public function getArrayy(): Arrayy
    {
        return $this->fetchAllArrayy();
    }

    /**
     * alias for "Result->fetchColumn()"
     *
     * @param string $column
     * @param bool   $asArray
     * @param bool   $skipNullValues
     *
     * @return array|string
     *                      <p>Return a empty string or an empty array if the "$column" wasn't found, depend on
     *                      "$asArray"</p>
     *
     * @see Result::fetchColumn()
     */
    public function getColumn(
        string $column,
        bool $skipNullValues = true,
        bool $asArray = false
    ) {
        return $this->fetchColumn($column, $skipNullValues, $asArray);
    }

    /**
     * @return string
     */
    public function getDefaultResultType(): string
    {
        return $this->_default_result_type;
    }

    /**
     * alias for "Result->fetchAllObject()"
     *
     * @return array of mysql-objects
     *
     * @see Result::fetchAllObject()
     */
    public function getObject(): array
    {
        return $this->fetchAllObject();
    }

    /**
     * alias for "Result->fetchAllYield()"
     *
     * @return \Generator
     *
     * @see Result::fetchAllYield()
     */
    public function getYield(): \Generator
    {
        return $this->fetchAllYield();
    }

    /**
     * Check if the result is empty.
     *
     * @return bool
     */
    public function is_empty(): bool
    {
        return !($this->num_rows > 0);
    }

    /**
     * Fetch all results as "json"-string.
     *
     * @return false|string
     */
    public function json()
    {
        $data = $this->fetchAllArray();

        return UTF8::json_encode($data);
    }

    /**
     * Returns the last row element from the result.
     *
     * @param string $column The column name to use as value (optional)
     *
     * @return mixed A row array or a single scalar value
     */
    public function last(string $column = null)
    {
        $pos = $this->current_row;
        $last = $this->fetchCallable($this->num_rows - 1, $column);
        $this->rewind($pos);

        return $last;
    }

    /**
     * Set the mapper...
     *
     * @param \Closure $callable
     *
     * @return $this
     */
    public function map(\Closure $callable): self
    {
        $this->_mapper = $callable;

        return $this;
    }

    /**
     * Alias of count(). Deprecated.
     *
     * @return int
     *             <p>The number of rows in the result.</p>
     */
    public function num_rows(): int
    {
        return $this->count();
    }

    /**
     * ArrayAccess interface implementation.
     *
     * @param int $offset <p>Offset number</p>
     *
     * @return bool
     *              <p>true if offset exists, false otherwise.</p>
     */
    public function offsetExists($offset): bool
    {
        return \is_int($offset) && $offset >= 0 && $offset < $this->num_rows;
    }

    /**
     * ArrayAccess interface implementation.
     *
     * @param int $offset Offset number
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->fetchCallable($offset);
        }

        throw new \OutOfBoundsException("undefined offset (${offset})");
    }

    /**
     * ArrayAccess interface implementation. Not implemented by design.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        /** @noinspection UselessReturnInspection */
    }

    /**
     * ArrayAccess interface implementation. Not implemented by design.
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        /** @noinspection UselessReturnInspection */
    }

    /**
     * Reset the offset (data_seek) for the results.
     *
     * @return Result
     */
    public function reset(): self
    {
        $this->doctrinePdoStmtDataSeekFake = 0;

        if (!$this->is_empty()) {
            if ($this->doctrineMySQLiStmt instanceof \mysqli_stmt) {
                $this->doctrineMySQLiStmt->data_seek(0);
            }

            if ($this->_result instanceof \mysqli_result) {
                \mysqli_data_seek($this->_result, 0);
            }
        }

        return $this;
    }

    /**
     * You can set the default result-type to Result::RESULT_TYPE_*.
     *
     * INFO: used for "fetch()" and "fetchAll()"
     *
     * @param string $default_result_type
     */
    public function setDefaultResultType(string $default_result_type = self::RESULT_TYPE_OBJECT)
    {
        if (
            $default_result_type === self::RESULT_TYPE_OBJECT
            ||
            $default_result_type === self::RESULT_TYPE_ARRAY
            ||
            $default_result_type === self::RESULT_TYPE_ARRAYY
            ||
            $default_result_type === self::RESULT_TYPE_YIELD
        ) {
            $this->_default_result_type = $default_result_type;
        }
    }

    /**
     * @param int      $offset
     * @param int|null $length
     * @param bool     $preserve_keys
     *
     * @return array
     */
    public function slice(int $offset = 0, int $length = null, bool $preserve_keys = false): array
    {
        // init
        $slice = [];

        if ($offset < 0) {
            if (\abs($offset) > $this->num_rows) {
                $offset = 0;
            } else {
                $offset = $this->num_rows - (int) \abs($offset);
            }
        }

        $length = $length !== null ? (int) $length : $this->num_rows;
        $n = 0;
        for ($i = $offset; $i < $this->num_rows && $n < $length; $i++) {
            if ($preserve_keys) {
                $slice[$i] = $this->fetchCallable($i);
            } else {
                $slice[] = $this->fetchCallable($i);
            }
            ++$n;
        }

        return $slice;
    }
}
