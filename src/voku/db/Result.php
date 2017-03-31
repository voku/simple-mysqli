<?php

namespace voku\db;

use Arrayy\Arrayy;
use voku\helper\Bootup;
use voku\helper\UTF8;

/**
 * Result: this handles the result from "DB"-Class
 *
 * @package   voku\db
 */
final class Result
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
   * @var string
   */
  private $_default_result_type = 'object';

  /**
   * Result constructor.
   *
   * @param string         $sql
   * @param \mysqli_result $result
   */
  public function __construct($sql = '', \mysqli_result $result)
  {
    $this->sql = $sql;

    $this->_result = $result;
    $this->num_rows = (int)$this->_result->num_rows;
  }

  /**
   * @return string
   */
  public function getDefaultResultType()
  {
    return $this->_default_result_type;
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
   * __destruct
   *
   */
  public function __destruct()
  {
    $this->free();
  }

  /**
   * free the memory
   */
  public function free()
  {
    \mysqli_free_result($this->_result);
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
   * Fetch.
   *
   * <p>
   *   <br />
   *   INFO: this will return an object by default, not an array<br />
   *   and you can change the behaviour via "Result->setDefaultResultType()"
   * </p>
   *
   * @param $reset
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
   * Fetch as object.
   *
   * @param string     $class
   * @param null|array $params
   * @param bool       $reset
   *
   * @return object|false <p><strong>false</strong> on error</p>
   */
  public function fetchObject($class = '', $params = null, $reset = false)
  {
    if ($reset === true) {
      $this->reset();
    }

    if ($class && $params) {
      return ($row = \mysqli_fetch_object($this->_result, $class, $params)) ? $row : false;
    }

    if ($class) {
      return ($row = \mysqli_fetch_object($this->_result, $class)) ? $row : false;
    }

    return ($row = \mysqli_fetch_object($this->_result)) ? $this->cast($row) : false;
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

    return false;
  }

  /**
   * Fetch as "Arrayy"-object.
   *
   * @param bool $reset
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

    return false;
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
   * Fetch all results as array with objects.
   *
   * @param string     $class
   * @param null|array $params
   *
   * @return array
   */
  public function fetchAllObject($class = '', $params = null)
  {
    // init
    $data = array();

    if (!$this->is_empty()) {
      $this->reset();

      if ($class && $params) {
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = \mysqli_fetch_object($this->_result, $class, $params)) {
          $data[] = $row;
        }
      } elseif ($class) {
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = \mysqli_fetch_object($this->_result, $class)) {
          $data[] = $row;
        }
      } else {
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = \mysqli_fetch_object($this->_result)) {
          $data[] = $this->cast($row);
        }
      }
    }

    return $data;
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
   * Get the current "num_rows" as string.
   *
   * @return string
   */
  public function __toString()
  {
    return (string)$this->num_rows;
  }
}
