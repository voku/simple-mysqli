<?php

require_once __DIR__ . '/FoobarUser.php';
require_once __DIR__ . '/FoobarContact.php';

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
        "CREATE TABLE IF NOT EXISTS user (
            id INTEGER NOT NULL AUTO_INCREMENT, 
            name TEXT, 
            password TEXT,
            PRIMARY KEY (id)
        );"
    );

    ActiveRecord::execute(
        "CREATE TABLE IF NOT EXISTS contact (
            id INTEGER NOT NULL AUTO_INCREMENT, 
            user_id INTEGER, 
            email TEXT,
            address TEXT,
            PRIMARY KEY (id)
        );"
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
    $user->insert();
    $this->assertGreaterThan(0, $user->id);

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
    $this->assertGreaterThan(0, $user->id);

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
    $this->assertGreaterThan(0, $contact->id);

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
    $this->assertGreaterThan(0, $contact->id);

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
    $this->assertEquals($contact->user->id, $contact->user_id);
    $this->assertEquals($contact->user->contact->id, $contact->id);
    $this->assertEquals($contact->user->contacts[0]->id, $contact->id);
    $this->assertGreaterThan(0, count($contact->user->contacts));

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
    $this->assertEquals(false, $contact->user->contact === $contact);
    $this->assertEquals(false, $contact->user_with_backref->contact === $contact);
    $user = $contact->user;
    $this->assertEquals(false, $user->contacts[0]->user === $user);
    $this->assertEquals(true, $user->contacts_with_backref[0]->user === $user);

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

    var_dump($user);exit();

    // email and address will stored in user data array.
    $this->assertEquals($contact->user_id, $user->id);
    $this->assertEquals($contact->email, $user->email);
    $this->assertEquals($contact->address, $user->address);
  }

  /**
   * @depends testInsertContact
   */
  public function testQuery()
  {
    $user = new FoobarUser();
    $user->isnotnull('id')->eq('id', 1)->lt('id', 2)->gt('id', 0)->fetch();
    $this->assertGreaterThan(0, $user->id);
    $this->assertSame(array(), $user->dirty);
    $user->name = 'testname';
    $this->assertSame(array('name' => 'testname'), $user->dirty);
    $name = $user->name;
    $this->assertEquals('testname', $name);
    unset($user->name);
    $this->assertSame(array(), $user->dirty);
    $user->reset()->isnotnull('id')->eq('id', 'aaa"')->wrap()->lt('id', 2)->gt('id', 0)->wrap('OR')->fetch();
    $this->assertGreaterThan(0, $user->id);
    $user->reset()->isnotnull('id')->between('id', array(0, 2))->fetch();
    $this->assertGreaterThan(0, $user->id);
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
    $this->assertEquals($cid, $new_contact->fetch($cid)->id);
    $this->assertEquals($uid, $new_user->eq('id', $uid)->fetch()->id);
    $this->assertTrue($contact->user->delete());
    $this->assertTrue($contact->delete());
    $new_contact = new FoobarContact();
    $new_user = new FoobarUser();
    $this->assertFalse($new_contact->eq('id', $cid)->fetch());
    $this->assertFalse($new_user->fetch($uid));
  }
}