<?php

namespace voku\db;

use Arrayy\Arrayy;
use Symfony\Component\PropertyAccess\PropertyAccess;
use voku\helper\Bootup;
use voku\helper\UTF8;

/**
 * Result: This class can handle the results from the "DB"-class.
 *
 * @package   voku\db
 */
final class Result implements \Countable, \SeekableIterator, \ArrayAccess
{

  /**
   * @var int
   */
  public $num_rows;

  /**
   * @var string
   */
  public $sql;

  /**
   * @var \mysqli_result
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
  private $_default_result_type = 'object';

  /**
   * Result constructor.
   *
   * @param string         $sql
   * @param \mysqli_result $result
   * @param \Closure       $mapper Optional callback mapper for the "fetchCallable()" method
   */
  public function __construct($sql = '', \mysqli_result $result, $mapper = null)
  {
    $this->sql = $sql;

    $this->_result = $result;

    $this->current_row = 0;
    $this->num_rows = (int)$this->_result->num_rows;

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
   * @return mixed|\mysqli_result
   */
  public function __invoke($callback = null)
  {
    if (isset($callback)) {
      return call_user_func($callback, $this->_result);
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
    return (string)$this->num_rows;
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
   * @return array|object|false <p><strong>false</strong> on error</p>
   */
  private function cast(&$data)
  {
    if (Helper::isMysqlndIsUsed() === true) {
      return $data;
    }

    // init
    if (Bootup::is_php('5.4')) {
      static $FIELDS_CACHE = array();
      static $TYPES_CACHE = array();
    } else {
      $FIELDS_CACHE = array();
      $TYPES_CACHE = array();
    }

    $result_hash = spl_object_hash($this->_result);

    if (!isset($FIELDS_CACHE[$result_hash])) {
      $FIELDS_CACHE[$result_hash] = \mysqli_fetch_fields($this->_result);
    }

    if ($FIELDS_CACHE[$result_hash] === false) {
      return false;
    }

    if (!isset($TYPES_CACHE[$result_hash])) {
      foreach ($FIELDS_CACHE[$result_hash] as $field) {
        switch ($field->type) {
          case 3:
            $TYPES_CACHE[$result_hash][$field->name] = 'int';
            break;
          case 4:
            $TYPES_CACHE[$result_hash][$field->name] = 'float';
            break;
          default:
            $TYPES_CACHE[$result_hash][$field->name] = 'string';
            break;
        }
      }
    }

    if (is_array($data) === true) {
      foreach ($TYPES_CACHE[$result_hash] as $type_name => $type) {
        if (isset($data[$type_name])) {
          settype($data[$type_name], $type);
        }
      }
    } elseif (is_object($data)) {
      foreach ($TYPES_CACHE[$result_hash] as $type_name => $type) {
        if (isset($data->{$type_name})) {
          settype($data->{$type_name}, $type);
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
  public function count()
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
  public function key()
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
   * @param int $row Row position; zero-based and set to 0 by default
   *
   * @return bool Boolean true on success, false otherwise
   */
  public function seek($row = 0)
  {
    if (is_int($row) && $row >= 0 && $row < $this->num_rows) {
      return mysqli_data_seek($this->_result, $row);
    }

    return false;
  }

  /**
   * Iterator interface implementation.
   *
   * @return bool Boolean true if the current index is valid, false otherwise
   */
  public function valid()
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
   * @return array|object|false <p><strong>false</strong> on error</p>
   */
  public function fetch($reset = false)
  {
    $return = false;

    if ($this->_default_result_type === 'object') {
      $return = $this->fetchObject('', '', $reset);
    } elseif ($this->_default_result_type === 'array') {
      $return = $this->fetchArray($reset);
    } elseif ($this->_default_result_type === 'Arrayy') {
      $return = $this->fetchArrayy($reset);
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
  public function fetchAll()
  {
    $return = array();

    if ($this->_default_result_type === 'object') {
      $return = $this->fetchAllObject();
    } elseif ($this->_default_result_type === 'array') {
      $return = $this->fetchAllArray();
    } elseif ($this->_default_result_type === 'Arrayy') {
      $return = $this->fetchAllArray();
    }

    return $return;
  }

  /**
   * Fetch all results as array.
   *
   * @return array
   */
  public function fetchAllArray()
  {
    // init
    $data = array();

    if (
        $this->_result
        &&
        !$this->is_empty()
    ) {
      $this->reset();

      /** @noinspection PhpAssignmentInConditionInspection */
      while ($row = \mysqli_fetch_assoc($this->_result)) {
        $data[] = $this->cast($row);
      }
    }

    return $data;
  }

  /**
   * Fetch all results as "Arrayy"-object.
   *
   * @return Arrayy
   */
  public function fetchAllArrayy()
  {
    // init
    $data = array();

    if (
        $this->_result
        &&
        !$this->is_empty()
    ) {
      $this->reset();

      /** @noinspection PhpAssignmentInConditionInspection */
      while ($row = \mysqli_fetch_assoc($this->_result)) {
        $data[] = $this->cast($row);
      }
    }

    return Arrayy::create($data);
  }

  /**
   * Fetch a single column as an 1-dimension array.
   *
   * @param string $column
   * @param bool   $skipNullValues <p>Skip "NULL"-values. | default: false</p>
   *
   * @return array <p>Return an empty array if the "$column" wasn't found</p>
   */
  public function fetchAllColumn($column, $skipNullValues = false)
  {
    return $this->fetchColumn($column, $skipNullValues, true);
  }

  /**
   * Fetch all results as array with objects.
   *
   * @param object|string $class  <p>
   *                              <strong>string</strong>: create a new object (with optional constructor
   *                              parameter)<br>
   *                              <strong>object</strong>: use a object and fill the the data into
   *                              </p>
   * @param null|array    $params optional
   *                              <p>
   *                              An array of parameters to pass to the constructor, used if $class is a
   *                              string.
   *                              </p>
   *
   * @return array
   */
  public function fetchAllObject($class = '', $params = null)
  {

    if ($this->is_empty()) {
      return array();
    }

    // init
    $data = array();
    $this->reset();

    if ($class && is_object($class)) {
      $propertyAccessor = PropertyAccess::createPropertyAccessor();
      /** @noinspection PhpAssignmentInConditionInspection */
      while ($row = \mysqli_fetch_assoc($this->_result)) {
        $classTmp = clone $class;
        $row = $this->cast($row);
        foreach ($row as $key => $value) {
          $propertyAccessor->setValue($classTmp, $key, $value);
        }
        $data[] = $classTmp;
      }

      return $data;
    }

    if ($class && $params) {
      /** @noinspection PhpAssignmentInConditionInspection */
      while ($row = \mysqli_fetch_object($this->_result, $class, $params)) {
        $data[] = $this->cast($row);
      }

      return $data;
    }

    if ($class) {
      /** @noinspection PhpAssignmentInConditionInspection */
      while ($row = \mysqli_fetch_object($this->_result, $class)) {
        $data[] = $this->cast($row);
      }

      return $data;
    }

    /** @noinspection PhpAssignmentInConditionInspection */
    while ($row = \mysqli_fetch_object($this->_result)) {
      $data[] = $this->cast($row);
    }

    return $data;
  }

  /**
   * Fetch as array.
   *
   * @param bool $reset
   *
   * @return array|false <p><strong>false</strong> on error</p>
   */
  public function fetchArray($reset = false)
  {
    if ($reset === true) {
      $this->reset();
    }

    $row = \mysqli_fetch_assoc($this->_result);
    if ($row) {
      return $this->cast($row);
    }

    if ($row === null) {
      return array();
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
  public function fetchArrayPair($key, $value)
  {
    $arrayPair = array();
    $data = $this->fetchAllArray();

    foreach ($data as $_row) {
      if (
          array_key_exists($key, $_row) === true
          &&
          array_key_exists($value, $_row) === true
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
   * @return Arrayy|false <p><strong>false</strong> on error</p>
   */
  public function fetchArrayy($reset = false)
  {
    if ($reset === true) {
      $this->reset();
    }

    $row = \mysqli_fetch_assoc($this->_result);
    if ($row) {
      return Arrayy::create($this->cast($row));
    }

    if ($row === null) {
      return Arrayy::create();
    }

    return false;
  }

  /**
   * Fetch a single column as string (or as 1-dimension array).
   *
   * @param string $column
   * @param bool   $skipNullValues <p>Skip "NULL"-values. | default: true</p>
   * @param bool   $asArray        <p>Get all values and not only the last one. | default: false</p>
   *
   * @return string|array <p>Return a empty string or an empty array if the "$column" wasn't found, depend on
   *                      "$asArray"</p>
   */
  public function fetchColumn($column = '', $skipNullValues = true, $asArray = false)
  {
    if ($asArray === false) {
      $columnData = '';

      $data = $this->fetchAllArrayy()->reverse();
      foreach ($data as $_row) {

        if ($skipNullValues === true) {
          if (isset($_row[$column]) === false) {
            continue;
          }
        } else {
          if (array_key_exists($column, $_row) === false) {
            break;
          }
        }

        $columnData = $_row[$column];
        break;
      }

      return $columnData;
    }

    // -- return as array -->

    $columnData = array();

    $data = $this->fetchAllArray();

    foreach ($data as $_row) {

      if ($skipNullValues === true) {
        if (isset($_row[$column]) === false) {
          continue;
        }
      } else {
        if (array_key_exists($column, $_row) === false) {
          break;
        }
      }

      $columnData[] = $_row[$column];
    }

    return $columnData;
  }

  /**
   * Fetch as object.
   *
   * @param object|string $class  <p>
   *                              <strong>string</strong>: create a new object (with optional constructor
   *                              parameter)<br>
   *                              <strong>object</strong>: use a object and fill the the data into
   *                              </p>
   * @param null|array    $params optional
   *                              <p>
   *                              An array of parameters to pass to the constructor, used if $class is a
   *                              string.
   *                              </p>
   * @param bool          $reset  optional <p>Reset the \mysqli_result counter.</p>
   *
   * @return object|false <p><strong>false</strong> on error</p>
   */
  public function fetchObject($class = '', $params = null, $reset = false)
  {
    if ($reset === true) {
      $this->reset();
    }

    if ($class && is_object($class)) {
      $row = \mysqli_fetch_assoc($this->_result);
      $row = $row ? $this->cast($row) : false;

      if (!$row) {
        return false;
      }

      $propertyAccessor = PropertyAccess::createPropertyAccessor();
      foreach ($row as $key => $value) {
        $propertyAccessor->setValue($class, $key, $value);
      }

      return $class;
    }

    if ($class && $params) {
      $row = \mysqli_fetch_object($this->_result, $class, $params);

      return $row ? $this->cast($row) : false;
    }

    if ($class) {
      $row = \mysqli_fetch_object($this->_result, $class);

      return $row ? $this->cast($row) : false;
    }

    $row = \mysqli_fetch_object($this->_result);

    return $row ? $this->cast($row) : false;
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
  public function fetchCallable($row = null, $column = null)
  {
    if (!$this->num_rows) {
      return null;
    }

    if (isset($row)) {
      $this->seek($row);
    }

    $rows = \mysqli_fetch_assoc($this->_result);

    if ($column) {
      return is_array($rows) && isset($rows[$column]) ? $rows[$column] : null;
    }

    return is_callable($this->_mapper) ? call_user_func($this->_mapper, $rows) : $rows;
  }

  /**
   * Return rows of field information in a result set. This function is a
   * basically a wrapper on the native mysqli_fetch_fields function.
   *
   * @param bool $as_array Return each field info as array; defaults to false
   *
   * @return array Array of field information each as an associative array
   */
  public function fetchFields($as_array = false)
  {
    if ($as_array) {
      return array_map(
          function ($object) {
            return (array)$object;
          },
          \mysqli_fetch_fields($this->_result)
      );
    }

    return \mysqli_fetch_fields($this->_result);
  }

  /**
   * Returns all rows at once as a grouped array of scalar values or arrays.
   *
   * @param string $group  The column name to use for grouping
   * @param string $column The column name to use as values (optional)
   *
   * @return array A grouped array of scalar values or arrays
   */
  public function fetchGroups($group, $column = null)
  {
    // init
    $groups = array();
    $pos = $this->current_row;

    foreach ($this as $row) {

      if (!array_key_exists($group, $row)) {
        continue;
      }

      if (isset($column)) {

        if (!array_key_exists($column, $row)) {
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
   * Returns all rows at once as key-value pairs.
   *
   * @param string $key    The column name to use as keys
   * @param string $column The column name to use as values (optional)
   *
   * @return array An array of key-value pairs
   */
  public function fetchPairs($key, $column = null)
  {
    // init
    $pairs = array();
    $pos = $this->current_row;

    foreach ($this as $row) {

      if (!array_key_exists($key, $row)) {
        continue;
      }

      if (isset($column)) {

        if (!array_key_exists($column, $row)) {
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
   * @return mixed A transposed array of arrays
   */
  public function fetchTranspose($column = null)
  {
    // init
    $keys = isset($column) ? $this->fetchAllColumn($column) : array();
    $rows = array();
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

    return array_map(
        function ($values) use ($keys) {
          return array_combine($keys, $values);
        }, $rows
    );
  }

  /**
   * Returns the first row element from the result.
   *
   * @param string $column The column name to use as value (optional)
   *
   * @return mixed A row array or a single scalar value
   */
  public function first($column = null)
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
    if (isset($this->_result) && $this->_result) {
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      /** @noinspection UsageOfSilenceOperatorInspection */
      @\mysqli_free_result($this->_result);
      $this->_result = null;

      return true;
    }

    return false;
  }

  /**
   * alias for "Result->fetch()"
   *
   * @see Result::fetch()
   *
   * @return array|object|false <p><strong>false</strong> on error</p>
   */
  public function get()
  {
    return $this->fetch();
  }

  /**
   * alias for "Result->fetchAll()"
   *
   * @see Result::fetchAll()
   *
   * @return array
   */
  public function getAll()
  {
    return $this->fetchAll();
  }

  /**
   * alias for "Result->fetchAllColumn()"
   *
   * @see Result::fetchAllColumn()
   *
   * @param string $column
   * @param bool   $skipNullValues
   *
   * @return array
   */
  public function getAllColumn($column, $skipNullValues = false)
  {
    return $this->fetchAllColumn($column, $skipNullValues);
  }

  /**
   * alias for "Result->fetchAllArray()"
   *
   * @see Result::fetchAllArray()
   *
   * @return array
   */
  public function getArray()
  {
    return $this->fetchAllArray();
  }

  /**
   * alias for "Result->fetchAllArrayy()"
   *
   * @see Result::fetchAllArrayy()
   *
   * @return Arrayy
   */
  public function getArrayy()
  {
    return $this->fetchAllArrayy();
  }

  /**
   * alias for "Result->fetchColumn()"
   *
   * @see Result::fetchColumn()
   *
   * @param $column
   * @param $asArray
   * @param $skipNullValues
   *
   * @return string|array <p>Return a empty string or an empty array if the "$column" wasn't found, depend on
   *                      "$asArray"</p>
   */
  public function getColumn($column, $skipNullValues = true, $asArray = false)
  {
    return $this->fetchColumn($column, $skipNullValues, $asArray);
  }

  /**
   * @return string
   */
  public function getDefaultResultType()
  {
    return $this->_default_result_type;
  }

  /**
   * alias for "Result->fetchAllObject()"
   *
   * @see Result::fetchAllObject()
   *
   * @return array of mysql-objects
   */
  public function getObject()
  {
    return $this->fetchAllObject();
  }

  /**
   * Check if the result is empty.
   *
   * @return bool
   */
  public function is_empty()
  {
    if ($this->num_rows > 0) {
      return false;
    }

    return true;
  }

  /**
   * Fetch all results as "json"-string.
   *
   * @return string
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
  public function last($column = null)
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
  public function map(\Closure $callable)
  {
    $this->_mapper = $callable;

    return $this;
  }

  /**
   * Alias of count(). Deprecated.
   *
   * @return int The number of rows in the result
   */
  public function num_rows()
  {
    return $this->count();
  }

  /**
   * ArrayAccess interface implementation.
   *
   * @param int $offset Offset number
   *
   * @return bool Boolean true if offset exists, false otherwise
   */
  public function offsetExists($offset)
  {
    return is_int($offset) && $offset >= 0 && $offset < $this->num_rows;
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

    throw new \OutOfBoundsException("undefined offset ($offset)");
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
    return;
  }

  /**
   * ArrayAccess interface implementation. Not implemented by design.
   *
   * @param mixed $offset
   */
  public function offsetUnset($offset)
  {
    /** @noinspection UselessReturnInspection */
    return;
  }

  /**
   * Reset the offset (data_seek) for the results.
   *
   * @return Result
   */
  public function reset()
  {
    if (!$this->is_empty()) {
      \mysqli_data_seek($this->_result, 0);
    }

    return $this;
  }

  /**
   * You can set the default result-type to 'object', 'array' or 'Arrayy'.
   *
   * INFO: used for "fetch()" and "fetchAll()"
   *
   * @param string $default_result_type
   */
  public function setDefaultResultType($default_result_type = 'object')
  {
    if (
        $default_result_type === 'object'
        ||
        $default_result_type === 'array'
        ||
        $default_result_type === 'Arrayy'
    ) {
      $this->_default_result_type = $default_result_type;
    }
  }

  /**
   * @param int      $offset
   * @param null|int $length
   * @param bool     $preserve_keys
   *
   * @return array
   */
  public function slice($offset = 0, $length = null, $preserve_keys = false)
  {
    // init
    $slice = array();
    $offset = (int)$offset;

    if ($offset < 0) {
      if (abs($offset) > $this->num_rows) {
        $offset = 0;
      } else {
        $offset = $this->num_rows - abs($offset);
      }
    }

    $length = isset($length) ? (int)$length : $this->num_rows;
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
