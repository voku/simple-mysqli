<?php

namespace voku\db;

/**
 * Class WrapExpressions
 */
class WrapExpressions extends Expressions
{
  public function __toString()
  {
    $delimiter = $this->delimiter ?: ',';

    if ($delimiter != ',') {
      var_dump($this->delimiter); exit();
    }

    if ($this->start) {
      return $this->start . implode($delimiter, $this->target->getArray()) . ($this->end ?: ')');
    }

    return '(' . implode($delimiter, $this->target->getArray()) . ($this->end ? $this->end : ')');
  }
}
