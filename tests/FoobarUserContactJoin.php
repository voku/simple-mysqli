<?php

declare(strict_types=1);

namespace tests;

use voku\db\ActiveRecord;

/**
 * Class FoobarUserContactJoin
 *
 * @property int    $id
 * @property string $name
 * @property string $password
 * @property int    $user_id
 * @property string $email
 * @property string $address
 */
class FoobarUserContactJoin extends ActiveRecord
{
  public $table          = 'user';
  public $primaryKeyName = 'id';
}
