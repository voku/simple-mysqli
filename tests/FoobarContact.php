<?php

declare(strict_types=1);

namespace tests;

use voku\db\ActiveRecord;

/**
 * Class FoobarContact
 *
 * @property int               $id
 * @property int               $user_id
 * @property string            $email
 * @property string            $address
 * @property \tests\FoobarUser $user_with_backref
 * @property \tests\FoobarUser $user
 */
class FoobarContact extends ActiveRecord
{
  public $table          = 'contact';
  public $primaryKeyName = 'id';

  public $relations = [
      'user_with_backref' => [
          self::BELONGS_TO,
          FoobarUser::class,
          'user_id',
          null,
          'contact',
      ],
      'user'              => [
          self::BELONGS_TO,
          FoobarUser::class,
          'user_id',
      ],
  ];
}
