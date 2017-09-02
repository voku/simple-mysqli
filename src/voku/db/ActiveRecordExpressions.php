<?php

namespace voku\db;

use Arrayy\Arrayy;

/**
 * Class Expressions, part of SQL.
 * Every SQL can be split into multiple expressions.
 * Each expression contains three parts:
 *
 * @property string|ActiveRecordExpressions $source   of this expression, (option)
 * @property string                         $operator (required)
 * @property string|ActiveRecordExpressions $target   of this expression (required)
 * Just implement one function __toString.
 */
class ActiveRecordExpressions extends Arrayy
{
  public function __toString()
  {
    return $this->source . ' ' . $this->operator . ' ' . $this->target;
  }
}
