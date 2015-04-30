<?php

namespace voku\db;

/**
 * Result: this handles the result from "DB"-Class
 *
 * @package   voku\db
 */
Class Result
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
   * @var \mysqli_result|null
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
  public function __construct($sql = '', $result = null)
  {
    $this->sql = $sql;

    if ($result === null) {
      return;
    }

    if ($result instanceof \mysqli_result) {
      $this->_result = $result;
      $this->num_rows = $this->_result->num_rows;
    }
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
    if ($default_result_type == 'object' || $default_result_type == 'array') {
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
      if (
          isset($_row[$key])
          &&
          isset($_row[$value])
      ) {
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
    $data = array();

    if (
        !$this->is_empty()
        &&
        $this->_result
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
   * is_empty
   *
   * @return boolean
   */
  public function is_empty()
  {
    return ($this->num_rows > 0) ? false : true;
  }

  /**
   * reset
   *
   * @return Result
   */
  public function reset()
  {
    if (
        !$this->is_empty()
        &&
        $this->_result
    ) {
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

    return;
  }

  /**
   * free
   */
  public function free()
  {
    if ($this->_result && $this->_result instanceof \mysqli_result) {
      mysqli_free_result($this->_result);
    }
  }

  /**
   * get
   *
   * @return array|bool|null|object
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
   * @return array|bool|null|object
   */
  public function fetch($reset = false)
  {
    $return = false;

    if ($this->_default_result_type == 'object') {
      $return = $this->fetchObject('', '', $reset);
    } else if ($this->_default_result_type == 'array') {
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
   * @return bool|null|object
   */
  public function fetchObject($class = '', $params = null, $reset = false)
  {
    if ($reset === true) {
      $this->reset();
    }

    if ($this->_result) {
      if ($class && $params) {
        return ($row = mysqli_fetch_object($this->_result, $class, $params)) ? $row : false;
      } else if ($class) {
        return ($row = mysqli_fetch_object($this->_result, $class)) ? $row : false;
      } else {
        return ($row = mysqli_fetch_object($this->_result)) ? $row : false;
      }
    }

    return false;
  }

  /**
   * fetchArray
   *
   * @param bool $reset
   *
   * @return array|bool|null
   */
  public function fetchArray($reset = false)
  {
    if ($reset === true) {
      $this->reset();
    }

    if ($this->_result) {
      return ($row = mysqli_fetch_assoc($this->_result)) ? $row : false;
    } else {
      return false;
    }
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

    if ($this->_default_result_type == 'object') {
      $return = $this->fetchAllObject();
    } else if ($this->_default_result_type == 'array') {
      $return = $this->fetchAllArray();
    }

    return $return;
  }

  // only aliases ------>

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
    $data = array();

    if (
        !$this->is_empty()
        &&
        $this->_result
    ) {
      $this->reset();

      if ($class && $params) {
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = mysqli_fetch_object($this->_result, $class, $params)) {
          $data[] = $row;
        }
      } else if ($class) {
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
   * @return string
   */
  public function fetchColumn($column = '')
  {
    $columnData = array();
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
