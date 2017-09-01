<?php

use voku\db\ActiveRecord;

class FoobarUser extends ActiveRecord
{
  public $table      = 'user';
  public $primaryKey = 'id';
  public $relations  = array(
      'contacts'              => array(
          self::HAS_MANY,
          'FoobarContact',
          'user_id'
      ),
      'contacts_with_backref' => array(
          self::HAS_MANY,
          'FoobarContact',
          'user_id',
          null,
          'user'
      ),
      'contact'         => array(
          self::HAS_ONE,
          'FoobarContact',
          'user_id',
          array('where' => '1', 'order' => 'id desc'),
      ),
  );
}
