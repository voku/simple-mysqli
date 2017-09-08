<?php

namespace voku\db;

/**
 * Class Expressions Wrap via Arrayy.
 *
 * @property string $start     (option)
 * @property string $end       (option)
 * @property string $delimiter (option) <p>default is ","</p>
 */
class ActiveRecordExpressionsWrap extends ActiveRecordExpressions
{
  /**
   * @return string
   */
  public function __toString()
  {
    $delimiter = (string)($this->delimiter ?: ',');

    if ($this->start) {
      return $this->start . implode($delimiter, $this->target->getArray()) . ($this->end ?: ')');
    }

    return '(' . implode($delimiter, $this->target->getArray()) . ($this->end ? $this->end : ')');
  }
}
