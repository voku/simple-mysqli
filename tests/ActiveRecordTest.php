<?php

require_once __DIR__ . '/FoobarUser.php';
require_once __DIR__ . '/FoobarContact.php';

use tests\FoobarContact;
use tests\FoobarUser;
use voku\db\ActiveRecord;
use voku\db\DB;

/**
 * Class ActiveRecordTest
 */
class ActiveRecordTest extends \PHPUnit_Framework_TestCase
{

  /**
   * @var DB
   */
  private $db;

  public function testInit()
  {
    $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test', 3306, 'utf8', false, true);

    ActiveRecord::execute(
        'CREATE TABLE IF NOT EXISTS user (
            id INTEGER NOT NULL AUTO_INCREMENT, 
            name TEXT, 
            password TEXT,
            PRIMARY KEY (id)
        );'
    );

    ActiveRecord::execute(
        'CREATE TABLE IF NOT EXISTS contact (
            id INTEGER NOT NULL AUTO_INCREMENT, 
            user_id INTEGER, 
            email TEXT,
            address TEXT,
            PRIMARY KEY (id)
        );'
    );
  }

  /**
   * @depends testInit
   */
  public function testInsertUser()
  {
    $user = new FoobarUser();
    $user->name = 'demo';
    $user->password = md5('demo');
    $id = $user->insert();
    self::assertGreaterThan(0, $user->id);
    self::assertGreaterThan(0, $id);
    self::assertEquals($id, $user->getPrimaryKey());

    return $user;
  }

  /**
   * @depends testInsertUser
   *
   * @param FoobarUser $user
   *
   * @return mixed
   */
  public function testEditUser($user)
  {
    $user->name = 'demo1';
    $user->password = md5('demo1');
    $user->update();
    self::assertGreaterThan(0, $user->id);

    return $user;
  }

  /**
   * @depends testInsertUser
   *
   * @param FoobarUser $user
   *
   * @return FoobarContact
   */
  public function testInsertContact($user)
  {
    $contact = new FoobarContact();
    $contact->address = 'test';
    $contact->email = 'test@demo.com';
    $contact->user_id = $user->id;
    $contact->insert();
    self::assertGreaterThan(0, $contact->id);

    return $contact;
  }

  /**
   * @depends testInsertContact
   *
   * @param $contact FoobarContact
   *
   * @return mixed
   */
  public function testEditContact($contact)
  {
    $contact->address = 'test1';
    $contact->email = 'test1@demo.com';
    $contact->update();
    self::assertGreaterThan(0, $contact->id);

    return $contact;
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   *
   * @return mixed
   */
  public function testRelations($contact)
  {
    self::assertEquals($contact->user->id, $contact->user_id);
    self::assertEquals($contact->user->contact->id, $contact->id);
    self::assertEquals($contact->user->contacts[0]->id, $contact->id);
    self::assertGreaterThan(0, count($contact->user->contacts));

    return $contact;
  }

  /**
   * @depends testRelations
   *
   * @param FoobarContact $contact
   *
   * @return mixed
   */
  public function testRelationsBackRef($contact)
  {
    self::assertEquals(false, $contact->user->contact === $contact);
    self::assertEquals(true, $contact->user_with_backref->contact === $contact);
    $user = $contact->user;
    self::assertEquals(false, $user->contacts[0]->user === $user);
    self::assertEquals(true, $user->contacts_with_backref[0]->user === $user);

    return $contact;
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testJoin($contact)
  {
    $user = new FoobarUser();
    $user->select('*, c.email, c.address')->join('contact as c', 'c.user_id = ' . $contact->user_id)->fetch();

    // email and address will stored in user data array.
    self::assertEquals($contact->user_id, $user->id);
    self::assertEquals($contact->email, $user->email);
    self::assertEquals($contact->address, $user->address);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testFetch($contact)
  {
    $user = new FoobarUser();
    $user->fetch($contact->user_id);

    // email and address will stored in user data array.
    self::assertEquals($contact->user_id, $user->id);
    self::assertEquals($contact->user_id, $user->getPrimaryKey());
    self::assertEquals('demo1', $user->name);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testFetchAll($contact)
  {
    $user = new FoobarUser();
    /* @var $users FoobarUser[] */
    $users = $user->fetchAll();

    $found = false;
    $userForTesting = null;
    foreach ($users as $userTmp) {
      if ($userTmp->getPrimaryKey() === $contact->user_id) {
        $found = true;
        $userForTesting = clone $userTmp;
      }
    }

    // email and address will stored in user data array.
    self::assertTrue($found);
    self::assertEquals($contact->user_id, $userForTesting->id);
    self::assertEquals($contact->user_id, $userForTesting->getPrimaryKey());
    self::assertEquals('demo1', $userForTesting->name);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testOrder($contact)
  {
    $user = new FoobarUser();
    $user->where('id = ' . $contact->user_id)->order('id DESC', 'name ASC')->limit(2, 1)->fetch();

    // email and address will stored in user data array.
    self::assertEquals($contact->user_id, $user->id);
    self::assertEquals($contact->user_id, $user->getPrimaryKey());
    self::assertEquals('demo1', $user->name);
  }

  /**
   * @depends testInsertContact
   */
  public function testQuery()
  {
    $user = new FoobarUser();
    $user->isNotNull('id')->eq('id', 1)->lt('id', 2)->gt('id', 0)->fetch();
    self::assertGreaterThan(0, $user->id);
    self::assertSame(array(), $user->getDirty());
    $user->name = 'testname';
    self::assertSame(array('name' => 'testname'), $user->getDirty());
    $name = $user->name;
    self::assertEquals('testname', $name);
    unset($user->name);
    self::assertSame(array(), $user->getDirty());
    $user->reset()->isNotNull('id')->eq('id', 'aaa"')->wrap()->lt('id', 2)->gt('id', 0)->wrap('OR')->fetch();
    self::assertGreaterThan(0, $user->id);
    $user->reset()->isNotNull('id')->between('id', array(0, 2))->fetch();
    self::assertGreaterThan(0, $user->id);
  }

  /**
   * @depends testRelations
   *
   * @param FoobarContact $contact
   */
  public function testDelete($contact)
  {
    $cid = $contact->id;
    $uid = $contact->user_id;
    $new_contact = new FoobarContact();
    $new_user = new FoobarUser();
    self::assertEquals($cid, $new_contact->fetch($cid)->id);
    self::assertEquals($uid, $new_user->eq('id', $uid)->fetch()->id);
    self::assertEquals(1, $contact->user->delete());
    self::assertEquals(1, $contact->delete());
    $new_contact = new FoobarContact();
    $new_user = new FoobarUser();
    self::assertFalse($new_contact->eq('id', $cid)->fetch());
    self::assertFalse($new_user->fetch($uid));

    ActiveRecord::execute('DROP TABLE IF EXISTS user;');
    ActiveRecord::execute('DROP TABLE IF EXISTS contact;');
  }
}
