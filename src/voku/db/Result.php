<?php

namespace voku\db;

use Arrayy\Arrayy;

/**
 * Result: this handles the result from "DB"-Class
 *
 * @package   voku\db
 */
class Result
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
   * Result
   *
   * @param string         $sql
   * @param \mysqli_result $result
   */
  public function __construct($sql = '', \mysqli_result $result)
  {
    $this->sql = $sql;

    $this->_result = $result;
    $this->num_rows = $this->_result->num_rows;
  }

  /**
   * @return string
   */
  public function getDefaultResultType()
  {
    return $this->_default_result_type;
  }

  /**
   * you can set the default result-type to 'object' or 'array'
   *
   * used for "fetch()" and "fetchAll()"
   *
   * @param string $default_result_type
   */
  public function setDefaultResultType($default_result_type = 'object')
  {
    if ($default_result_type === 'object' || $default_result_type === 'array') {
      $this->_default_result_type = $default_result_type;
    }
  }

  /**
   * fetch array-pair
   *
   * both "key" and "value" must exists in the fetched data
   * the key will be the new key of the result-array
   *
   * e.g.:
   *    fetchArrayPair('some_id', 'some_value');
   *    // array(127 => 'some value', 128 => 'some other value')
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
      if (isset($_row[$key], $_row[$value])) {
        $_key = $_row[$key];
        $_value = $_row[$value];
        $arrayPair[$_key] = $_value;
      }
    }

    return $arrayPair;
  }

  /**
   * fetchAllArray
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
      while ($row = mysqli_fetch_assoc($this->_result)) {
        $data[] = $row;
      }
    }

    return $data;
  }

  /**
   * fetch all results, return via Arrayy
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
      while ($row = mysqli_fetch_assoc($this->_result)) {
        $data[] = $row;
      }
    }

    return Arrayy::create($data);
  }

  /**
   * is_empty
   *
   * @return bool
   */
  public function is_empty()
  {
    if ($this->num_rows > 0) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * reset
   *
   * @return Result
   */
  public function reset()
  {
    if (!$this->is_empty()) {
      mysqli_data_seek($this->_result, 0);
    }

    return $this;
  }

  /**
   * json
   *
   * @return string
   */
  public function json()
  {
    $data = $this->fetchAllArray();

    return json_encode($data);
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
   * free
   */
  public function free()
  {
    mysqli_free_result($this->_result);
  }

  /**
   * get
   *
   * @return array|object|false false on error
   */
  public function get()
  {
    return $this->fetch();
  }

  /**
   * fetch (object -> not a array by default)
   *
   * @param $reset
   *
   * @return array|object|false false on error
   */
  public function fetch($reset = false)
  {
    $return = false;

    if ($this->_default_result_type === 'object') {
      $return = $this->fetchObject('', '', $reset);
    } elseif ($this->_default_result_type === 'array') {
      $return = $this->fetchArray($reset);
    }

    return $return;
  }

  /**
   * fetchObject
   *
   * @param string     $class
   * @param null|array $params
   * @param bool       $reset
   *
   * @return object|false false on error
   */
  public function fetchObject($class = '', $params = null, $reset = false)
  {
    if ($reset === true) {
      $this->reset();
    }

    if ($class && $params) {
      return ($row = mysqli_fetch_object($this->_result, $class, $params)) ? $row : false;
    }

    if ($class) {
      return ($row = mysqli_fetch_object($this->_result, $class)) ? $row : false;
    }

    return ($row = mysqli_fetch_object($this->_result)) ? $row : false;
  }

  /**
   * fetch as array
   *
   * @param bool $reset
   *
   * @return array|false false on error
   */
  public function fetchArray($reset = false)
  {
    if ($reset === true) {
      $this->reset();
    }

    $row = mysqli_fetch_assoc($this->_result);
    if ($row) {
      return $row;
    }

    return false;
  }

  /**
   * fetch as Arrayy-Object
   *
   * @param bool $reset
   *
   * @return Arrayy|false false on error
   */
  public function fetchArrayy($reset = false)
  {
    if ($reset === true) {
      $this->reset();
    }

    $row = mysqli_fetch_assoc($this->_result);
    if ($row) {
      return Arrayy::create($row);
    }

    return false;
  }

  /**
   * getAll
   *
   * @return array
   */
  public function getAll()
  {
    return $this->fetchAll();
  }

  /**
   * fetchAll
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
    }

    return $return;
  }

  /**
   * fetchAllObject
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
        while ($row = mysqli_fetch_object($this->_result, $class, $params)) {
          $data[] = $row;
        }
      } elseif ($class) {
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = mysqli_fetch_object($this->_result, $class)) {
          $data[] = $row;
        }
      } else {
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = mysqli_fetch_object($this->_result)) {
          $data[] = $row;
        }
      }
    }

    return $data;
  }

  /**
   * getObject
   *
   * @return array of mysql-objects
   */
  public function getObject()
  {
    return $this->fetchAllObject();
  }

  /**
   * getArray
   *
   * @return array
   */
  public function getArray()
  {
    return $this->fetchAllArray();
  }

  /**
   * getColumn
   *
   * @param $key
   *
   * @return string
   */
  public function getColumn($key)
  {
    return $this->fetchColumn($key);
  }

  /**
   * fetchColumn
   *
   * @param string $column
   *
   * @return string empty string if the $column wasn't found
   */
  public function fetchColumn($column = '')
  {
    $columnData = '';
    $data = $this->fetchAllArray();

    foreach ($data as $_row) {
      if (isset($_row[$column])) {
        $columnData = $_row[$column];
      }
    }

    return $columnData;
  }

  /**
   * get the num-rows as string
   *
   * @return string
   */
  public function __toString()
  {
    return (string)$this->num_rows;
  }
}
