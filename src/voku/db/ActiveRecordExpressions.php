<?php

namespace voku\db;

use Arrayy\Arrayy;

/**
 * Class Expressions via Arrayy.
 *
 * - Every SQL can be split into multiple expressions.
 * - Each expression contains three parts: "$source, $operator, $target"
 *
 * @property string|ActiveRecordExpressions $source   (option) <p>Source of this expression.</p>
 * @property string                         $operator (required)
 * @property string|ActiveRecordExpressions $target   (required) <p>Target of this expression.</p>
 */
class ActiveRecordExpressions extends Arrayy
{
  /**
   * @return string
   */
  public function __toString()
  {
    return $this->source . ' ' . $this->operator . ' ' . $this->target;
  }
}
