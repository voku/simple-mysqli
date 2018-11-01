<?php

declare(strict_types=1);

namespace tests;

use voku\db\ActiveRecord;

/**
 * Class FoobarUser
 *
 * @property int                    $id
 * @property string                 $name
 * @property string                 $password
 * @property \tests\FoobarContact[] $contacts_with_backref
 * @property \tests\FoobarContact[] $contacts
 * @property \tests\FoobarContact   $contact
 */
class FoobarUser extends ActiveRecord
{
  public $table          = 'user';
  public $primaryKeyName = 'id';

  public $relations = [
      'contacts'              => [
          self::HAS_MANY,
          FoobarContact::class,
          'user_id',
      ],
      'contacts_with_backref' => [
          self::HAS_MANY,
          FoobarContact::class,
          'user_id',
          null,
          'user',
      ],
      'contact'               => [
          self::HAS_ONE,
          FoobarContact::class,
          'user_id',
          ['where' => '1', 'orderBy' => 'id desc'],
      ],
  ];
}
