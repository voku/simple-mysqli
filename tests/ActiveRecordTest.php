<?php

declare(strict_types=1);

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

    self::assertSame('demo', $user->get('name'));
    self::assertSame('demo', $user->name);

    $id = $user->insert();

    self::assertGreaterThan(0, $user->id);
    self::assertGreaterThan(0, $id);
    self::assertSame($id, $user->getPrimaryKey());

    self::assertSame('demo', $user->get('name'));
    self::assertSame('demo', $user->name);

    return $user;
  }

  /**
   * @depends testInit
   */
  public function testInsertUserV2()
  {
    $user = FoobarUser::fetchEmpty();
    $user->name = 'demo';
    $user->password = md5('demo');

    self::assertSame('demo', $user->get('name'));
    self::assertSame('demo', $user->name);

    $id = $user->insert();

    self::assertGreaterThan(0, $user->id);
    self::assertGreaterThan(0, $id);
    self::assertSame($id, $user->getPrimaryKey());

    self::assertSame('demo', $user->get('name'));
    self::assertSame('demo', $user->name);

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
    self::assertSame($contact->user->id, $contact->user_id);
    self::assertSame($contact->user->contact->id, $contact->id);
    self::assertSame($contact->user->contacts[0]->id, $contact->id);
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
    self::assertSame(false, $contact->user->contact === $contact);
    self::assertSame(true, $contact->user_with_backref->contact === $contact);
    $user = $contact->user;
    self::assertSame(false, $user->contacts[0]->user === $user);
    self::assertSame(true, $user->contacts_with_backref[0]->user === $user);

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
    self::assertSame($contact->user_id, $user->id);
    self::assertSame($contact->email, $user->email);
    self::assertSame($contact->address, $user->address);
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

    // name etc. will stored in user data array.
    self::assertSame($contact->user_id, $user->id);
    self::assertSame($contact->user_id, $user->getPrimaryKey());
    self::assertSame('demo1', $user->name);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testFetchAll($contact)
  {
    $user = new FoobarUser();
    $users = $user->fetchAll();

    $found = false;
    $userForTesting = null;
    foreach ($users as $userTmp) {
      if ($userTmp->getPrimaryKey() === $contact->user_id) {
        $found = true;
        $userForTesting = clone $userTmp;
      }
    }

    // name etc. will stored in user data array.
    self::assertTrue($found);
    self::assertSame($contact->user_id, $userForTesting->id);
    self::assertSame($contact->user_id, $userForTesting->getPrimaryKey());
    self::assertSame('demo1', $userForTesting->name);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testFetchOneByQuery($contact)
  {
    $user = new FoobarUser();
    $sql = 'SELECT * FROM user WHERE id = ' . (int)$contact->user_id;
    $user->fetchOneByQuery($sql);

    // name etc. will stored in user data array.
    self::assertSame($contact->user_id, $user->id);
    self::assertSame($contact->user_id, $user->getPrimaryKey());
    self::assertSame('demo1', $user->name);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testFetchManyByQuery($contact)
  {
    $user = new FoobarUser();
    $sql = 'SELECT * FROM user WHERE id >= ' . (int)$contact->user_id;
    $users = $user->fetchManyByQuery($sql);

    $found = false;
    $userForTesting = null;
    foreach ($users as $userTmp) {
      if ($userTmp->getPrimaryKey() === $contact->user_id) {
        $found = true;
        $userForTesting = clone $userTmp;
      }
    }

    // name etc. will stored in user data array.
    self::assertTrue($found);
    self::assertSame($contact->user_id, $userForTesting->id);
    self::assertSame($contact->user_id, $userForTesting->getPrimaryKey());
    self::assertSame('demo1', $userForTesting->name);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testFetchById($contact)
  {
    $user = new FoobarUser();
    $user->fetchById($contact->user_id);

    // name etc. will stored in user data array.
    self::assertSame($contact->user_id, $user->id);
    self::assertSame($contact->user_id, $user->getPrimaryKey());
    self::assertSame('demo1', $user->name);
    self::assertSame('demo1', $user->get('name'));
  }

  /**
   * @depends testInsertUser
   *
   * @param FoobarUser $user
   */
  public function testCopy($user)
  {
    $userCopy = $user->copy(true);

    // name etc. will stored in user data array.
    self::assertNotSame($userCopy, $user);
    self::assertNotSame($user->id, $userCopy->id);
    self::assertNotSame($user->getPrimaryKey(), $userCopy->getPrimaryKey());
    self::assertSame($user->name, $userCopy->name);
  }

  /**
   * @expectedException voku\db\exceptions\FetchingException
   */
  public function testFetchByIdFail()
  {
    $userNon = new FoobarUser();
    $userNon->fetchById(-1);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testFetchByIds($contact)
  {
    $user = new FoobarUser();
    $users = $user->fetchByIds(array($contact->user_id, $contact->user_id - 1));

    $found = false;
    $userForTesting = null;
    foreach ($users as $userTmp) {
      if ($userTmp->getPrimaryKey() === $contact->user_id) {
        $found = true;
        $userForTesting = clone $userTmp;
      }
    }

    // name etc. will stored in user data array.
    self::assertTrue($found);
    self::assertSame($contact->user_id, $userForTesting->id);
    self::assertSame($contact->user_id, $userForTesting->getPrimaryKey());
    self::assertSame('demo1', $userForTesting->name);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testFetchByIdsFail($contact)
  {
    $user = new FoobarUser();
    $users = $user->fetchByIds(array(-1, -2));

    $found = false;
    $userForTesting = null;
    foreach ($users as $userTmp) {
      if ($userTmp->getPrimaryKey() === $contact->user_id) {
        $found = true;
      }
    }

    self::assertFalse($found);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testFetchByIdsPrimaryKeyAsArrayIndex($contact)
  {
    $user = new FoobarUser();
    $users = $user->fetchByIdsPrimaryKeyAsArrayIndex(array($contact->user_id, $contact->user_id - 1));

    $found = false;
    $userForTesting = null;
    foreach ($users as $userId => $userTmp) {
      if (
          $userId === $contact->user_id
          &&
          $userTmp->getPrimaryKey() === $contact->user_id
      ) {
        $found = true;
        $userForTesting = clone $userTmp;
      }
    }

    // name etc. will stored in user data array.
    self::assertTrue($found);
    self::assertSame($contact->user_id, $userForTesting->id);
    self::assertSame($contact->user_id, $userForTesting->getPrimaryKey());
    self::assertSame('demo1', $userForTesting->name);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testfetchByIdIfExists($contact)
  {
    $user = new FoobarUser();
    $result = $user->fetchByIdIfExists($contact->user_id);

    // name etc. will stored in user data array.
    self::assertSame($user, $result);
    self::assertSame($contact->user_id, $user->id);
    self::assertSame($contact->user_id, $user->getPrimaryKey());
    self::assertSame('demo1', $user->name);
  }

  public function testfetchByIdIfExistsFail()
  {
    $userNon = new FoobarUser();
    $result = $userNon->fetchByIdIfExists(-1);

    // name etc. will not stored in user data array.
    self::assertSame(null, $result);
    self::assertSame(null, $userNon->id);
    self::assertSame(null, $userNon->id);
    self::assertSame(null, $userNon->getPrimaryKey());
    self::assertSame(null, $userNon->name);
  }

  /**
   * @depends testInsertContact
   *
   * @param FoobarContact $contact
   */
  public function testOrder($contact)
  {
    $user = new FoobarUser();
    $user->where('id = ' . $contact->user_id)->orderBy('id DESC', 'name ASC')->limit(2, 1)->fetch();

    // email and address will stored in user data array.
    self::assertSame($contact->user_id, $user->id);
    self::assertSame($contact->user_id, $user->getPrimaryKey());
    self::assertSame('demo1', $user->name);
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
    self::assertSame('testname', $name);
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
    self::assertSame($cid, $new_contact->fetch($cid)->id);
    self::assertSame($uid, $new_user->eq('id', $uid)->fetch()->id);
    self::assertSame(true, $contact->user->delete());
    self::assertSame(true, $contact->delete());
    $new_contact = new FoobarContact();
    $new_user = new FoobarUser();
    self::assertFalse($new_contact->eq('id', $cid)->fetch());
    self::assertFalse($new_user->fetch($uid));

    ActiveRecord::execute('DROP TABLE IF EXISTS user;');
    ActiveRecord::execute('DROP TABLE IF EXISTS contact;');
  }
}
