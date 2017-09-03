<?php

namespace tests;

use voku\db\ActiveRecord;

/**
 * Class FoobarContact
 *
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property string $address
 */
class FoobarContact extends ActiveRecord
{
  public $table          = 'contact';
  public $primaryKeyName = 'id';

  public $relations      = array(
      'user_with_backref' => array(
          self::BELONGS_TO,
          'tests\FoobarUser',
          'user_id',
          null,
          'contact'
      ),
      'user'        => array(
          self::BELONGS_TO,
          'tests\FoobarUser',
          'user_id'
      ),
  );
}
