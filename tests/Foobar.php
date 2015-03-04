<?php

/**
 * Class Foobar
 */
class Foobar extends stdClass
{
  protected $data = array();

  /**
   * @param array $attributes
   */
  public function __construct(array $attributes = array())
  {
    foreach ($attributes as $name => $value) {
      $this->{$name} = $value;
    }
  }

  /**
   * @param $name
   * @param $value
   */
  public function __set($name, $value)
  {
    $this->data[$name] = $value;
  }

  /**
   * @param $name
   *
   * @return null
   */
  public function __get($name)
  {
    if (array_key_exists($name, $this->data)) {
      return $this->data[$name];
    }

    return null;
  }
}
