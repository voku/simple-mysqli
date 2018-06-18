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
 * @property FoobarContact[] $contacts_with_backref
 * @property FoobarContact[] $contacts
 * @property FoobarContact   $contact
 */
class FoobarUser extends ActiveRecord
{
  public $table          = 'user';
  public $primaryKeyName = 'id';

  public $relations = [
      'contacts'              => [
          self::HAS_MANY,
          'tests\FoobarContact',
          'user_id',
      ],
      'contacts_with_backref' => [
          self::HAS_MANY,
          'tests\FoobarContact',
          'user_id',
          null,
          'user',
      ],
      'contact'               => [
          self::HAS_ONE,
          'tests\FoobarContact',
          'user_id',
          ['where' => '1', 'orderBy' => 'id desc'],
      ],
  ];
}
