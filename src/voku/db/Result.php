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

    if ($result === null) {
      return false;
    }

    $this->sql = $sql;
    $this->_result = $result;
    $this->num_rows = $this->_result->num_rows;

    return true;
  }

  /**
   * fetchArrayPair
   *
   * @param string $key
   * @param string $value
   *
   * @return array
   */
  public function fetchArrayPair($key, $value)
  {
    $ArrayPair = array();
    $data = $this->fetchAllArray();

    foreach ($data as $_row) {
      if (
          isset($_row[$key]) &&
          isset($_row[$value])
      ) {
        $_key = $_row[$key];
        $_value = $_row[$value];
        $ArrayPair[$_key] = $_value;
      }
    }

    return $ArrayPair;
  }

  /**
   * fetchAllArray
   *
   * @return array
   */
  public function fetchAllArray()
  {
    $data = array();

    if (!$this->is_empty()) {
      $this->reset();

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

    return;
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
   * @return array|bool|null|object
   */
  public function get()
  {
    return $this->fetch();
  }

  /**
   * fetch (object -> not a array by default)
   *
   * @return array|bool|null|object
   */
  public function fetch()
  {

    if ($this->_default_result_type == 'object') {
      return $this->fetchObject();
    }

    if ($this->_default_result_type == 'array') {
      return $this->fetchArray();
    }

    return false;
  }

  /**
   * fetchObject
   *
   * @param string $class
   * @param array  $params
   *
   * @return bool|null|object
   */
  public function fetchObject($class = '', $params = '')
  {
    if ($class && $params) {
      return ($row = mysqli_fetch_object($this->_result, $class, $params)) ? $row : false;
    } else if ($class) {
      return ($row = mysqli_fetch_object($this->_result, $class)) ? $row : false;
    } else {
      return ($row = mysqli_fetch_object($this->_result)) ? $row : false;
    }
  }

  /**
   * fetchArray
   *
   * @return array|bool|null
   */
  public function fetchArray()
  {
    return ($row = mysqli_fetch_assoc($this->_result)) ? $row : false;
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
   * @param string $class
   * @param array  $params
   *
   * @return array of mysql-objects
   */
  public function fetchAllObject($class = '', $params = '')
  {
    $data = array();

    if (!$this->is_empty()) {
      $this->reset();

      if ($class && $params) {
        while ($row = mysqli_fetch_object($this->_result, $class, $params)) {
          $data[] = $row;
        }
      } else if ($class) {
        while ($row = mysqli_fetch_object($this->_result, $class)) {
          $data[] = $row;
        }
      } else {
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
   * @return array
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
   * @return array
   */
  public function fetchColumn($column = '')
  {
    $columnData = array();
    $data = $this->fetchAllArray();

    foreach ($data as $_row) {
      if (isset($_row[$column])) {
        $columnData[] = $_row[$column];
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
