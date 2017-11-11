<?php

declare(strict_types=1);

namespace tests;

use voku\db\ActiveRecord;

/**
 * Class FoobarUser
 *
 * @property int    $id
 * @property string $name
 * @property string $password
 */
class FoobarUser extends ActiveRecord
{
  public $table          = 'user';
  public $primaryKeyName = 'id';

  public $relations      = array(
      'contacts'              => array(
          self::HAS_MANY,
          'tests\FoobarContact',
          'user_id'
      ),
      'contacts_with_backref' => array(
          self::HAS_MANY,
          'tests\FoobarContact',
          'user_id',
          null,
          'user'
      ),
      'contact'         => array(
          self::HAS_ONE,
          'tests\FoobarContact',
          'user_id',
          array('where' => '1', 'orderBy' => 'id desc'),
      ),
  );
}
