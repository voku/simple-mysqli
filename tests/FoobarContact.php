<?php

use voku\db\ActiveRecord;

class FoobarContact extends ActiveRecord
{
  public $table      = 'contact';
  public $primaryKey = 'id';
  public $relations  = array(
      'user_with_backref' => array(
          self::BELONGS_TO,
          'FoobarUser',
          'user_id',
          null,
          'contact'
      ),
      'user'        => array(
          self::BELONGS_TO,
          'FoobarUser',
          'user_id'
      ),
  );
}
