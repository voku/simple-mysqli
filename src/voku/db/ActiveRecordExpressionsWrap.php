<?php

namespace voku\db;

/**
 * Class ActiveRecordExpressionsWrap
 */
class ActiveRecordExpressionsWrap extends ActiveRecordExpressions
{
  public function __toString()
  {
    $delimiter = $this->delimiter ?: ',';

    if ($this->start) {
      return $this->start . implode($delimiter, $this->target->getArray()) . ($this->end ?: ')');
    }

    return '(' . implode($delimiter, $this->target->getArray()) . ($this->end ? $this->end : ')');
  }
}
