<?php

declare(strict_types=1);

/**
 * Class Foobar
 */
class Foobar extends stdClass
{
  /**
   * @var array
   */
  protected $data = [];

  /**
   * @var bool
   */
  public $test = true;

  /**
   * @param array $attributes
   */
  public function __construct(array $attributes = [])
  {
    foreach ($attributes as $name => $value) {
      $this->{$name} = $value;
    }
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
   * @return bool
   */
  public function __isset($name)
  {
    return isset($this->data[$name]);
  }
}
