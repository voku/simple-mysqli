<?php

use Arrayy\Arrayy;
use voku\db\DB;
use voku\db\Helper;
use voku\db\Result;
use voku\helper\UTF8;

/**
 * Class SimpleDbTest
 */
class SimpleDbTest extends PHPUnit_Framework_TestCase
{

  /**
   * @var DB
   */
  protected $db;

  /**
   * @var string
   */
  protected $tableName = 'test_page';

  public function setUp()
  {
    $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test', 3306, 'utf8', false, false);
  }

  public function testLogQuery()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true, '', 'debug');
    self::assertInstanceOf('\\voku\\db\\DB', $db_1);

    // sql - true
    $pageArray = array(
        'page_template' => 'tpl_new_中',
        'page_type'     => 'lall',
    );
    $tmpId = $db_1->insert($this->tableName, $pageArray);
    self::assertTrue($tmpId > 0);

    // sql - true v2
    $pageArray = array(
        'page_template' => 'this_is_a_new_test',
        'page_type'     => 'fooooo',
    );
    $tmpId = $db_1->insert($this->tableName, $pageArray);
    self::assertTrue($tmpId > 0);

    // update - true (affected_rows === 1)
    $pageArray = array(
        'page_template' => 'this_is_a_new_test__update',
    );
    $affected_rows = $this->db->update($this->tableName, $pageArray, 'page_id = ' . (int)$tmpId);
    self::assertSame(1, $affected_rows);

    // update - true (affected_rows === 0)
    $pageArray = array(
        'page_template' => 'this_is_a_new_test__update',
    );
    $affected_rows = $this->db->update($this->tableName, $pageArray, 'page_id = -1');
    self::assertSame(0, $affected_rows);

    // update - false
    $false = $this->db->update($this->tableName, array(), 'page_id = ' . (int)$tmpId);
    self::assertFalse($false);

  }

  public function testEchoOnError1()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
    self::assertInstanceOf('\\voku\\db\\DB', $db_1);

    // insert - false
    $false = $db_1->insert($this->tableName, array());
    $this->expectOutputRegex('/(.)*Invalid data for INSERT(.)*/');
    self::assertFalse($false);
  }

  public function testEchoOnError4()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
    self::assertInstanceOf('\\voku\\db\\DB', $db_1);

    // sql - false
    $false = $db_1->query();
    $this->expectOutputRegex('/(.)*SimpleDbTest\.php \/(.)*/');
    self::assertFalse($false);
  }

  public function testEchoOnError3()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
    self::assertInstanceOf('\\voku\\db\\DB', $db_1);

    // sql - false
    $false = $db_1->query();
    $this->expectOutputRegex('/<div class="OBJ-mysql-box"(.)*/');
    self::assertFalse($false);
  }

  public function testEchoOnError2()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
    self::assertInstanceOf('\\voku\\db\\DB', $db_1);

    // sql - false
    $false = $db_1->query();
    $this->expectOutputRegex('/(.)*Can not execute an empty query(.)*/');
    self::assertFalse($false);

    // close db-connection
    self::assertTrue($this->db->isReady());
    $this->db->close();
    self::assertFalse($this->db->isReady());

    // insert - false
    $false = $db_1->query('INSERT INTO lall SET false = 1');
    self::assertFalse($false);
  }

  public function testExitOnError1()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', true, false);
    self::assertInstanceOf('\\voku\\db\\DB', $db_1);

    // insert - false
    $pageArray = array(
        'page_template' => 'tpl_new_中',
        'page_type'     => 'lall',
    );
    $false = $db_1->insert('', $pageArray);
    self::assertFalse($false);
  }

  public function testExitOnError2()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', true, false);
    self::assertInstanceOf('\\voku\\db\\DB', $db_1);

    // insert - false
    $false = $db_1->insert($this->tableName, array());
    self::assertFalse($false);
  }

  /**
   * @expectedException Exception
   * @expectedExceptionMessage Error connecting to mysql server: Access denied for user 'root'@'localhost' (using
   *                           password: YES)
   */
  public function testGetFalseInstanceV1()
  {
    DB::getInstance('localhost', 'root', 'test', 'mysql_test', '', '', false, false);
  }

  /**
   * @expectedException Exception
   * @expectedExceptionMessageRegExp #Error connecting to mysql server:.*#
   */
  public function testGetFalseInstanceV2()
  {
    DB::getInstance('localhost_lall', 'root123', '', 'mysql_test', '', '', true, false);
  }

  /**
   * @expectedException Exception
   * @expectedExceptionMessageRegExp #Error connecting to mysql server: Unknown database 'mysql_test_foo'#
   */
  public function testGetFalseInstanceV3()
  {
    DB::getInstance('localhost', 'root', '', 'mysql_test_foo', null, '', true, false);
  }

  /**
   *
   */
  public function testGetInstance()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, false);
    self::assertInstanceOf('\\voku\\db\\DB', $db_1);

    $db_2 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', true, false);
    self::assertInstanceOf('\\voku\\db\\DB', $db_2);

    $db_3 = DB::getInstance('localhost', 'root', '', 'mysql_test', null, '', true, false);
    self::assertInstanceOf('\\voku\\db\\DB', $db_3);

    $db_4 = DB::getInstance();
    self::assertInstanceOf('\\voku\\db\\DB', $db_4);
    $db_4_serial = serialize($db_4);
    unset($db_4);
    $db_4 = unserialize($db_4_serial);
    self::assertInstanceOf('\\voku\\db\\DB', $db_4);

    $true = $this->db->connect();
    self::assertTrue($true);

    $true = $this->db->connect();
    self::assertTrue($true);

    $true = $this->db->reconnect(false);
    self::assertTrue($true);

    $true = $this->db->reconnect(true);
    self::assertTrue($true);
  }

  public function testInsertOnlyAndSimple()
  {
    // insert - true
    $pageArray = array(
      'page_template' => '<p>foo</p>',
      'page_type'     => 'lallll',
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);
    self::assertTrue($tmpId > 0);
  }

  public function testInsertOnlyViaGetContent()
  {
    $html = file_get_contents(__DIR__ . '/fixtures/sample-simple-html.txt');

    // insert - true
    $pageArray = array(
      'page_template' => $html,
      'page_type'     => 'lallll',
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);
    self::assertTrue($tmpId > 0);
  }

  public function testInsertBugPregReplace()
  {
    // insert - true
    $pageArray = array(
      'page_template' => '$2y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC',
      'page_type'     => 'lall',
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);
    self::assertTrue($tmpId > 0);

    // select - true
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchObject();
    self::assertSame('$2y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC', $tmpPage->page_template);

    // select - true
    foreach ($result as $resultItem) {
      self::assertSame('$2y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC', $resultItem['page_template']);
    }

    $tmpPage = $result->fetchObject('', null, true);
    self::assertSame('$2y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC', $tmpPage->page_template);

    // --

    $sql = 'INSERT INTO ' . $this->tableName . '
      SET
        page_template = ?,
        page_type = ?
    ';
    $tmpId = $this->db->query(
      $sql,
      array(
        '$2y$10$HURk5OhFbsJV5G?mLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC',
        '$0y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4v?hlc79kWlCpeiHBC$',
      )
    );

    // select - true
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchObject();
    self::assertSame('$2y$10$HURk5OhFbsJV5G?mLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC', $tmpPage->page_template);
    self::assertSame('$0y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4v?hlc79kWlCpeiHBC$', $tmpPage->page_type);
  }

  public function testInsertAndSelectOnlyUtf84mbV1()
  {
    $html = UTF8::clean(file_get_contents(__DIR__ . '/fixtures/sample-html.txt'), true, true, true);

    // insert - true
    $pageArray = array(
      'page_template' => $html,
      'page_type'     => 'lallll',
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);
    self::assertTrue($tmpId > 0);

    // select - true
    $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
  }

  public function testInsertAndSelectOnlyUtf84mbV2()
  {
    $html = UTF8::clean(file_get_contents(__DIR__ . '/fixtures/sample-html.txt'), true, true, true);

    // insert - true
    $pageArray = array(
      'page_template' => $html,
      'page_type'     => 'lallll',
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);
    self::assertTrue($tmpId > 0);

    // select - true
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $result->fetchArray();
  }

  public function testInsertAndSelectOnlyUtf84mbV3()
  {
    $html = UTF8::clean(file_get_contents(__DIR__ . '/fixtures/sample-html.txt'), true, true, true);

    // insert - true
    $pageArray = array(
      'page_template' => $html,
      'page_type'     => 'lallll',
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);
    self::assertTrue($tmpId > 0);

    // select - true
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchArray();
    self::assertSame('<li><a href="/">鼦վͼ</a></li>', $tmpPage['page_template']);
  }

  /**
   * @expectedException Exception
   * @expectedExceptionMessage no-sql-hostname
   */
  public function testGetInstanceHostnameException()
  {
    DB::getInstance('', 'root', '', 'mysql_test', 3306, 'utf8', false, false);
  }

  /**
   * @expectedException Exception
   * @expectedExceptionMessage no-sql-username
   */
  public function testGetInstanceUsernameException()
  {
    DB::getInstance('localhost', '', '', 'mysql_test', 3306, 'utf8', false, false);
  }

  /**
   * @expectedException Exception
   * @expectedExceptionMessage no-sql-database
   */
  public function testGetInstanceDatabaseException()
  {
    DB::getInstance('localhost', 'root', '', '', 3306, 'utf8', false, false);
  }

  public function testCharset()
  {
    if (Helper::isUtf8mb4Supported($this->db) === true) {
      self::assertSame('utf8mb4', $this->db->get_charset());
    } else {
      self::assertSame('utf8', $this->db->get_charset());
    }

    $return = $this->db->set_charset('utf8');
    self::assertTrue($return);

    if (Helper::isUtf8mb4Supported($this->db) === true) {
      self::assertSame('utf8mb4', $this->db->get_charset());
    } else {
      self::assertSame('utf8', $this->db->get_charset());
    }
  }

  public function testInsertUtf84mb()
  {
    $html = UTF8::clean(file_get_contents(__DIR__ . '/fixtures/sample-html.txt'), true, true, true);

    // insert - true
    $pageArray = array(
        'page_template' => $html,
        'page_type'     => 'lall',
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);
    self::assertTrue($tmpId > 0);

    // select - true
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchObject();
    self::assertSame('<li><a href="/">鼦վͼ</a></li>', $tmpPage->page_template);
  }

  public function testBasics()
  {
    require_once __DIR__ . '/Foobar.php';

    // insert - true
    $pageArray = array(
        'page_template' => 'tpl_new_中',
        'page_type'     => 'lall',
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);

    // insert - false
    $false = $this->db->insert($this->tableName);
    self::assertFalse($false);

    // insert - false
    $false = $this->db->insert('', $pageArray);
    self::assertFalse($false);

    // select - true
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchObject();
    self::assertSame('tpl_new_中', $tmpPage->page_template);

    // select - true (but 0 results)
    $result = $this->db->select($this->tableName, 'page_id = -1');
    self::assertSame(0, $result->num_rows);

    // select - true
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchObject('stdClass');
    self::assertSame('tpl_new_中', $tmpPage->page_template);

    // select - true (but 0 results)
    $result = $this->db->select($this->tableName, 'page_id = -1');
    $result->fetchObject('stdClass');
    self::assertSame(0, $result->num_rows);

    // select - true
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchObject(
        'Foobar',
        array(
            array(
                'foo' => 1,
                'bar' => 2,
            ),
        )
    );

    /* @var $tmpPage Foobar */

    self::assertSame(1, $tmpPage->foo);
    self::assertSame(2, $tmpPage->bar);
    self::assertTrue($tmpPage->test);
    self::assertNull($tmpPage->nothing);
    self::assertSame('tpl_new_中', $tmpPage->page_template);

    $tmpPages = $result->fetchAllObject(
        'Foobar',
        array(
            array(
                'foo' => 1,
                'bar' => 2,
            ),
        )
    );

    /* @var $tmpPages [] Foobar */

    self::assertSame(1, $tmpPages[0]->foo);
    self::assertSame(2, $tmpPages[0]->bar);
    self::assertTrue($tmpPages[0]->test);
    self::assertNull($tmpPages[0]->nothing);
    self::assertSame('tpl_new_中', $tmpPages[0]->page_template);

    $tmpPages = $result->fetchAllObject(
        'Foobar'
    );

    /* @var $tmpPages [] Foobar */

    self::assertNull($tmpPages[0]->foo);
    self::assertNull($tmpPages[0]->bar);
    self::assertTrue($tmpPages[0]->test);
    self::assertSame('lall', $tmpPages[0]->page_type);
    self::assertSame('tpl_new_中', $tmpPages[0]->page_template);

    // update - true
    $pageArray = array(
        'page_template' => 'tpl_update',
    );
    $this->db->update($this->tableName, $pageArray, 'page_id = ' . (int)$tmpId);

    // update - false
    $false = $this->db->update($this->tableName, array(), 'page_id = ' . (int)$tmpId);
    self::assertFalse($false);

    // update - true
    $false = $this->db->update($this->tableName, $pageArray, '');
    self::assertFalse($false);

    // update - false
    $false = $this->db->update($this->tableName, $pageArray, null);
    self::assertFalse($false);

    // update - false
    $false = $this->db->update('', $pageArray, 'page_id = ' . (int)$tmpId);
    self::assertFalse($false);

    // check (select)
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchAllObject();
    self::assertSame('tpl_update', $tmpPage[0]->page_template);

    // replace - true
    $data = array(
        'page_id'       => 2,
        'page_template' => 'tpl_test',
        'page_type'     => 'öäü123',
    );
    $tmpId = $this->db->replace($this->tableName, $data);

    // replace - false
    $false = $this->db->replace($this->tableName);
    self::assertFalse($false);

    // replace - false
    $false = $this->db->replace('', $data);
    self::assertFalse($false);

    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchAllObject();
    self::assertSame('tpl_test', $tmpPage[0]->page_template);

    // delete - true
    $affected_rows = $this->db->delete($this->tableName, array('page_id' => $tmpId));
    self::assertSame(1, $affected_rows);

    // delete - true (but 0 affected_rows)
    $affected_rows = $this->db->delete($this->tableName, array('page_id' => -1));
    self::assertSame(0, $affected_rows);

    // delete - false
    $false = $this->db->delete('', array('page_id' => $tmpId));
    self::assertFalse($false);

    // insert - true
    $pageArray = array(
        'page_template' => 'tpl_new_中',
        'page_type'     => 'lall',
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);

    // delete - false
    $false = $this->db->delete($this->tableName, '');
    self::assertFalse($false);

    // delete - false
    $false = $this->db->delete($this->tableName, null);
    self::assertFalse($false);

    // delete - true
    $false = $this->db->delete($this->tableName, 'page_id = ' . $this->db->escape($tmpId));
    self::assertSame(1, $false);

    // select - true
    $result = $this->db->select($this->tableName, array('page_id' => 2));
    self::assertSame(0, $result->num_rows);

    $resultArray = $result->fetchArray();
    self::assertSame(array(), $resultArray);

    $resultArray = $result->fetchArrayy();
    self::assertEquals(new Arrayy(), $resultArray);

    // select - true
    $result = $this->db->select($this->tableName);
    self::assertTrue($result->num_rows > 0);

    // select - true (but empty)
    $result = $this->db->select($this->tableName, array('page_id' => -1));
    self::assertSame(0, $result->num_rows);

    // select - false
    $false = $this->db->select($this->tableName, null);
    self::assertFalse($false);

    // select - false
    $false = $this->db->select('', array('page_id' => 2));
    self::assertFalse($false);

    // insert - true (float)
    $pageArray = array(
        'page_template' => 'tpl_new_中',
        'page_type'     => (float)21.3123,
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);

    // delete - true
    $delete = $this->db->delete($this->tableName, 'page_id = ' . $this->db->escape($tmpId));
    self::assertSame(1, $delete);

    // insert - true (int)
    $pageArray = array(
        'page_template' => 'tpl_new_中',
        'page_type'     => (float)213123,
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);

    // delete - true
    $delete = $this->db->delete($this->tableName, 'page_id = ' . $this->db->escape($tmpId));
    self::assertSame(1, $delete);
  }

  public function testQry()
  {
    $sql = 'UPDATE ' . $this->db->escape($this->tableName) . "
      SET
        page_template = '?'
      WHERE page_id = ?
    ";
    /** @noinspection StaticInvocationViaThisInspection */
    /** @noinspection PhpStaticAsDynamicMethodCallInspection */
    $result = $this->db->qry($sql, 'tpl_test_?', 1);
    self::assertSame(1, $result);

    $sql = 'SELECT * FROM ' . $this->db->escape($this->tableName) . '
      WHERE page_id = 1
    ';
    /** @noinspection StaticInvocationViaThisInspection */
    /** @noinspection PhpStaticAsDynamicMethodCallInspection */
    $result = (array)$this->db->qry($sql);
    self::assertSame('tpl_test_?', $result[0]['page_template']);

    $sql = 'SELECT * FROM ' . $this->db->escape($this->tableName) . '
      WHERE page_id_lall = 1
    ';
    /** @noinspection StaticInvocationViaThisInspection */
    /** @noinspection PhpStaticAsDynamicMethodCallInspection */
    $result = $this->db->qry($sql);
    self::assertFalse($result);
  }

  public function testEscape()
  {
    $date = new DateTime('2016-08-15 09:22:18');

    self::assertSame($date->format('Y-m-d H:i:s'), $this->db->escape($date));

    // ---

    $object = new stdClass();
    $object->bar = 'foo';

    self::assertFalse($this->db->escape($object));

    // ---

    $object = new Arrayy(array('foo', 123, 'öäü'));

    self::assertSame('foo,123,öäü', $this->db->escape($object));

    // ---

    self::assertSame('', $this->db->escape(''));

    // ---

    self::assertNull($this->db->escape(null));

    // ---

    $testArray = array(
        'NOW()'                                  => 'NOW()',
        'fooo'                                   => 'fooo',
        123                                      => 123,
        'κόσμε'                                  => 'κόσμε',
        '&lt;abcd&gt;\'$1\'(&quot;&amp;2&quot;)' => '&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)',
        '&#246;&#228;&#252;'                     => '&#246;&#228;&#252;',
    );

    foreach ($testArray as $before => $after) {
      self::assertSame($after, $this->db->escape($before));
    }

    self::assertSame(array_values($testArray), $this->db->escape(array_keys($testArray)));

    self::assertSame('NOW(),fooo,123,κόσμε,<abcd>\\\'$1\\\'(\"&2\"),öäü', $this->db->escape(array_keys($testArray), false, true, true));
    self::assertSame('NOW(),fooo,123,κόσμε,&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;),&#246;&#228;&#252;', $this->db->escape(array_keys($testArray), true, false, true));
    self::assertSame('NOW(),fooo,123,κόσμε,&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;),&#246;&#228;&#252;', $this->db->escape(array_keys($testArray), false, false, true));
    self::assertSame('NOW(),fooo,123,κόσμε,<abcd>\\\'$1\\\'(\"&2\"),öäü', $this->db->escape(array_keys($testArray), true, true, true));

    self::assertSame(
        array(
            0 => 'NOW()',
            1 => 'fooo',
            2 => 123,
            3 => 'κόσμε',
            4 => '<abcd>\\\'$1\\\'(\"&2\")',
            5 => 'öäü',
        ),
        $this->db->escape(array_keys($testArray), false, true, false)
    );
    self::assertSame(
        array(
            0 => 'NOW()',
            1 => 'fooo',
            2 => 123,
            3 => 'κόσμε',
            4 => '&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)',
            5 => '&#246;&#228;&#252;',
        ),
        $this->db->escape(array_keys($testArray), true, false, false)
    );
    self::assertSame(
        array(
            0 => 'NOW()',
            1 => 'fooo',
            2 => 123,
            3 => 'κόσμε',
            4 => '&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)',
            5 => '&#246;&#228;&#252;',
        ),
        $this->db->escape(array_keys($testArray), false, false, false)
    );
    self::assertSame(
        array(
            0 => 'NOW()',
            1 => 'fooo',
            2 => 123,
            3 => 'κόσμε',
            4 => '<abcd>\\\'$1\\\'(\"&2\")',
            5 => 'öäü',
        ),
        $this->db->escape(array_keys($testArray), true, true, false)
    );

    self::assertNull($this->db->escape(array_keys($testArray), false, true, null));
    self::assertNull($this->db->escape(array_keys($testArray), true, false, null));
    self::assertNull($this->db->escape(array_keys($testArray), false, false, null));
    self::assertNull($this->db->escape(array_keys($testArray), true, true, null));


    // ---

    $this->db = DB::getInstance();

    $data = array(
        'page_template' => "tpl_test_'new2",
        'page_type'     => 1.1,
    );

    $newData = (array)$this->db->escape($data);

    self::assertSame('tpl_test_\\\'new2', $newData['page_template']);
    self::assertEquals(1.10000000, $newData['page_type']);

    // ---

    $data = array(
        'page_template' => "tpl_test_'new2",
        'page_type'     => 1.1,
    );

    $newData = $this->db->escape($data, true, true, true);

    self::assertSame('tpl_test_\\\'new2,1.1', $newData);

    // ---

    $data = array(
        'page_template' => "tpl_test_'new2",
        'page_type'     => '0111',
    );

    $newData = $this->db->escape($data, true, false, true);

    self::assertSame('tpl_test_\\\'new2,0111', $newData);

    // ---

    $data = array(
        'page_template' => "tpl_test_'new2",
        'page_type'     => '111',
    );

    $newData = $this->db->escape($data, true, false, true);

    self::assertSame('tpl_test_\\\'new2,111', $newData);

    // ---

    $data = array();

    $tested = $this->db->escape($data);

    self::assertSame(array(), $tested);

    // ---

    $data = array('foo\'', 'bar"');

    $tested = $this->db->escape($data);

    self::assertSame(array('foo\\\'', 'bar\"'), $tested);

    // ---

    $data = 123;

    $tested = $this->db->escape($data);

    self::assertSame(123, $tested);

    // ---

    $data = true;

    $tested = $this->db->escape($data);

    self::assertSame(1, $tested);

    // ---

    $data = false;

    $tested = $this->db->escape($data);

    self::assertSame(0, $tested);

    // ---

    $data = array(true, false);

    $tested = $this->db->escape($data);

    self::assertSame(array(1, 0), $tested);

    // ---

    $data = 'http://foobar.com?test=1';

    $tested = $this->db->escape($data);

    self::assertSame('http://foobar.com?test=1', $tested);

  }

  public function testConnector()
  {
    $data = array(
        'page_template' => 'tpl_test_new',
    );
    $where = array(
        'page_id LIKE' => '1',
    );

    // will return the number of effected rows
    $resultUpdate = $this->db->update($this->tableName, $data, $where);
    self::assertSame(1, $resultUpdate);

    $data = array(
        'page_template' => 'tpl_test_new2',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    $where = array(
        'page_type ='        => 'öäü',
        'page_type NOT LIKE' => '%öäü123',
        'page_id ='          => $resultInsert,
    );

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchArray();
    self::assertSame('öäü', $resultSelectArray['page_type']);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchArrayy();
    self::assertSame('öäü', $resultSelectArray['page_type']);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchArrayy()->clean()->getArray();
    self::assertSame('öäü', $resultSelectArray['page_type']);

    $where = array(
        'page_type ='  => 'öäü',
        'page_type <>' => 'öäü123',
        'page_id >'    => 0,
        'page_id >='   => 0,
        'page_id <'    => 1000000,
        'page_id <='   => 1000000,
        'page_id ='    => $resultInsert,
    );

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchArrayPair('page_type', 'page_type');
    self::assertSame('öäü', $resultSelectArray['öäü']);

    $where = array(
        'page_type LIKE'     => 'öäü',
        'page_type NOT LIKE' => 'öäü123',
        'page_id ='          => $resultInsert,
    );

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetch();
    $getDefaultResultType = $resultSelect->getDefaultResultType();
    self::assertSame('object', $getDefaultResultType);
    self::assertSame('öäü', $resultSelectArray->page_type);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelect->setDefaultResultType('array'); // switch default result-type
    $resultSelectArray = $resultSelect->fetch();
    $getDefaultResultType = $resultSelect->getDefaultResultType();
    self::assertSame('array', $getDefaultResultType);
    /** @noinspection OffsetOperationsInspection */
    self::assertSame('öäü', $resultSelectArray['page_type']);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchArray();
    self::assertSame('öäü', $resultSelectArray['page_type']);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->get();
    self::assertSame('öäü', $resultSelectArray->page_type);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->getAll();
    self::assertSame('öäü', $resultSelectArray[0]->page_type);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->getArray();
    self::assertSame('öäü', $resultSelectArray[0]['page_type']);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->getObject();
    self::assertSame('öäü', $resultSelectArray[0]->page_type);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectTmp = $resultSelect->getColumn('page_type');
    self::assertSame('öäü', $resultSelectTmp);

    $resultSelect = $this->db->select($this->tableName, $where);
    self::assertInstanceOf('\\voku\\db\\Result', $resultSelect);
  }

  public function testTransactionFalse()
  {

    $data = array(
        'page_template' => 'tpl_test_new3',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    // start - test a transaction - true
    $beginTransaction = $this->db->beginTransaction();
    self::assertTrue($beginTransaction);

    $data = array(
        'page_type' => 'lall',
    );
    $where = array(
        'page_id' => $resultInsert,
    );
    $this->db->update($this->tableName, $data, $where);

    $data = array(
        'page_type' => 'lall',
        'page_lall' => 'öäü'
        // this will produce a mysql-error and a mysqli-rollback
    );
    $where = array(
        'page_id' => $resultInsert,
    );
    $this->db->update($this->tableName, $data, $where);

    // end - test a transaction
    $this->db->endTransaction();

    $where = array(
        'page_id' => $resultInsert,
    );

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchAllArray();
    self::assertSame('öäü', $resultSelectArray[0]['page_type']);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchAllArrayy()->filterBy('page_type', 'öäü')->first();
    self::assertSame('öäü', $resultSelectArray['page_type']);
  }

  public function testGetErrors()
  {
    // INFO: run all previous tests and generate some errors

    $error = $this->db->lastError();
    self::assertTrue(is_string($error));
    self::assertContains('Unknown column \'page_lall\' in \'field list', $error);

    $errors = $this->db->getErrors();
    self::assertTrue(is_array($errors));
    self::assertContains('Unknown column \'page_lall\' in \'field list', $errors[0]);
  }

  public function testTransactionTrue()
  {

    $data = array(
        'page_template' => 'tpl_test_new3',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    // start - test a transaction
    $this->db->startTransaction();

    $data = array(
        'page_type' => 'lall',
    );
    $where = array(
        'page_id' => $resultInsert,
    );
    $this->db->update($this->tableName, $data, $where);

    $data = array(
        'page_type'     => 'lall',
        'page_template' => 'öäü',
    );
    $where = array(
        'page_id' => $resultInsert,
    );
    $this->db->update($this->tableName, $data, $where);

    // end - test a transaction
    $this->db->endTransaction();

    $where = array(
        'page_id' => $resultInsert,
    );

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchAllArray();
    self::assertSame('lall', $resultSelectArray[0]['page_type']);
  }

  public function testRollback()
  {
    // start - test a transaction
    $this->db->beginTransaction();

    $data = array(
        'page_template' => 'tpl_test_new4',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    $data = array(
        'page_type' => 'lall',
    );
    $where = array(
        'page_id' => $resultInsert,
    );
    $this->db->update($this->tableName, $data, $where);

    $data = array(
        'page_type' => 'lall',
        'page_lall' => 'öäü'
        // this will produce a mysql-error and a mysqli-rollback
    );
    $where = array(
        'page_id' => $resultInsert,
    );
    $this->db->update($this->tableName, $data, $where);

    // end - test a transaction, with a rollback!
    $this->db->rollback();

    $where = array(
        'page_id' => $resultInsert,
    );
    $resultSelect = $this->db->select($this->tableName, $where);
    self::assertSame(0, $resultSelect->num_rows);
  }

  public function testCommit()
  {
    // start - test a transaction
    $this->db->beginTransaction();

    $data = array(
        'page_template' => 'tpl_test_new4',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    $data = array(
        'page_type' => 'lall',
    );
    $where = array(
        'page_id' => $resultInsert,
    );
    $this->db->update($this->tableName, $data, $where);

    $data = array(
        'page_type' => 'lall',
        'page_lall' => 'öäü'
        // this will produce a mysql-error and a mysqli-rollback
    );
    $where = array(
        'page_id' => $resultInsert,
    );
    $this->db->update($this->tableName, $data, $where);

    // end - test a transaction, with a commit!
    $this->db->commit();

    $where = array(
        'page_id' => $resultInsert,
    );
    $resultSelect = $this->db->select($this->tableName, $where);
    self::assertSame(1, $resultSelect->num_rows);
  }

  public function testTransactionException()
  {
    // start - test a transaction - true
    $beginTransaction = $this->db->beginTransaction();
    self::assertTrue($beginTransaction);

    // start - test a transaction - false
    $beginTransaction = $this->db->beginTransaction();
    self::assertFalse($beginTransaction);
  }

  public function testFetchColumn()
  {
    $data = array(
        'page_template' => 'tpl_test_new5',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    $dataV2 = array(
        'page_template' => 'tpl_test_new5V2',
        'page_type'     => 'öäüV2',
    );

    // will return the auto-increment value of the new row (v2)
    $resultInsertV2 = $this->db->insert($this->tableName, $dataV2);
    self::assertGreaterThan($resultInsert, $resultInsertV2);

    // ---

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $resultInsert));

    $columnResult = $resultSelect->fetchColumn('page_template');
    self::assertSame('tpl_test_new5', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', false, false);
    self::assertSame('', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', true, true);
    self::assertSame(array('tpl_test_new5'), $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', false, true);
    self::assertSame(array('tpl_test_new5'), $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template_foo');
    self::assertSame('', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template_foo', false, false);
    self::assertSame('', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template_foo', true, true);
    self::assertSame(array(), $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template_foo', false, true);
    self::assertSame(array(), $columnResult);

    // ---

    $resultSelect = $this->db->select($this->tableName, array('page_id AND' => array($resultInsert, $resultInsert)));

    $columnResult = $resultSelect->fetchColumn('page_template');
    self::assertSame('tpl_test_new5', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', false, false);
    self::assertSame('', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', true, true);
    self::assertSame(array('tpl_test_new5'), $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', false, true);
    self::assertSame(array('tpl_test_new5'), $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', true, false);
    self::assertSame('tpl_test_new5', $columnResult);

    // ---

    $resultSelect = $this->db->select($this->tableName, array('page_id OR' => array($resultInsert, $resultInsertV2)));

    $columnResult = $resultSelect->fetchColumn('page_template');
    self::assertSame('tpl_test_new5V2', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', false, false);
    self::assertSame('', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', true, true);
    self::assertSame(array('tpl_test_new5', 'tpl_test_new5V2'), $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', true, false);
    self::assertSame('tpl_test_new5V2', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template', false, true);
    self::assertSame(array('tpl_test_new5', 'tpl_test_new5V2'), $columnResult);

    // ---

    $columnResult = $resultSelect->fetchColumn('page_template_foo');
    self::assertSame('', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template_foo', false, false);
    self::assertSame('', $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template_foo', true, true);
    self::assertSame(array(), $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template_foo', false, true);
    self::assertSame(array(), $columnResult);

    $columnResult = $resultSelect->fetchColumn('page_template_foo', true, false);
    self::assertSame('', $columnResult);

    // ---

    $columnResult = $resultSelect->fetchAllColumn('page_template', false);
    self::assertSame(array('tpl_test_new5', 'tpl_test_new5V2'), $columnResult);

    $columnResult = $resultSelect->fetchAllColumn('page_template', true);
    self::assertSame(array('tpl_test_new5', 'tpl_test_new5V2'), $columnResult);

    $columnResult = $resultSelect->fetchAllColumn('page_template', true);
    self::assertSame(array('tpl_test_new5', 'tpl_test_new5V2'), $columnResult);
  }

  public function testIsEmpty()
  {
    $data = array(
        'page_template' => 'tpl_test_new5',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $resultInsert));
    self::assertFalse($resultSelect->is_empty());

    $resultSelect = $this->db->select($this->tableName, array('page_id' => 999999));
    self::assertTrue($resultSelect->is_empty());
  }

  public function testJson()
  {
    $data = array(
        'page_template' => 'tpl_test_new6',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $resultInsert));
    $columnResult = $resultSelect->json();
    $columnResultDecode = json_decode($columnResult, true);
    self::assertSame('tpl_test_new6', $columnResultDecode[0]['page_template']);
  }

  public function testFetchObject()
  {
    $data = array(
        'page_template' => 'tpl_test_new7',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $resultInsert));
    $columnResult = $resultSelect->fetchObject();
    self::assertSame('tpl_test_new7', $columnResult->page_template);
  }

  public function testDefaultResultType()
  {
    $data = array(
        'page_template' => 'tpl_test_new8',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $resultInsert));

    // array
    $resultSelect->setDefaultResultType('array');

    $columnResult = (array)$resultSelect->fetch(true);
    self::assertSame('tpl_test_new8', $columnResult['page_template']);

    $columnResult = (array)$resultSelect->fetchAll();
    self::assertSame('tpl_test_new8', $columnResult[0]['page_template']);

    $columnResult = (array)$resultSelect->fetchAllArray();
    self::assertSame('tpl_test_new8', $columnResult[0]['page_template']);

    // object
    $resultSelect->setDefaultResultType('object');

    $columnResult = $resultSelect->fetch(true);
    self::assertSame('tpl_test_new8', $columnResult->page_template);

    $columnResult = $resultSelect->fetchAll();
    self::assertSame('tpl_test_new8', $columnResult[0]->page_template);

    $columnResult = $resultSelect->fetchAllObject();
    self::assertSame('tpl_test_new8', $columnResult[0]->page_template);
  }

  public function testGetAllTables()
  {
    $tableArray = $this->db->getAllTables();

    $return = false;
    foreach ($tableArray as $table) {
      if (in_array($this->tableName, $table, true) === true) {
        $return = true;
        break;
      }
    }

    self::assertTrue($return);
  }

  public function testPing()
  {
    $ping = $this->db->ping();
    self::assertTrue($ping);
  }

  public function testMultiQuery()
  {
    $sql = '
    INSERT INTO ' . $this->tableName . "
      SET
        page_template = 'lall1',
        page_type = 'test1';
    INSERT INTO " . $this->tableName . "
      SET
        page_template = 'lall2',
        page_type = 'test2';
    INSERT INTO " . $this->tableName . "
      SET
        page_template = 'lall3',
        page_type = 'test3';
    ";
    // multi_query - true
    $result = $this->db->multi_query($sql);
    self::assertTrue($result);

    $sql = '
    SELECT * FROM ' . $this->tableName . ';
    SELECT * FROM ' . $this->tableName . ';
    ';
    // multi_query - true
    $result = $this->db->multi_query($sql);
    self::assertTrue(is_array($result));
    /** @noinspection ForeachSourceInspection */
    foreach ($result as $resultForEach) {
      /* @var $resultForEach Result */
      $tmpArray = $resultForEach->fetchArray();

      self::assertTrue(is_array($tmpArray));
      self::assertTrue(count($tmpArray) > 0);
    }

    // multi_query - false
    $false = $this->db->multi_query('');
    self::assertFalse($false);

    // multi_query - false
    $sql = '
    INSERT INTO ' . $this->tableName . "
      SET
        page_template_no = 'lall1',
        page_type = 'test1';
    INSERT INTO " . $this->tableName . "
      SET
        page_template = 'lall2',
        page_type = 'test2';
    INSERT INTO " . $this->tableName . "
      SET
        page_template = 'lall3',
        page_type = 'test3';
    ";
    // multi_query - true
    $result = $this->db->multi_query($sql);
    self::assertFalse($result);
  }

  public function testEscapeData()
  {
    self::assertSame(null, $this->db->escape(null, true));
    self::assertSame(\mysqli_real_escape_string($this->db->getLink(), "O'Toole"), $this->db->escape("O'Toole"));
    self::assertSame(\mysqli_real_escape_string($this->db->getLink(), "O'Toole"), $this->db->escape("O'Toole", true));
    self::assertSame(1, $this->db->escape(true));
    self::assertSame(0, $this->db->escape(false));
    self::assertSame(1, $this->db->escape(true, false));
    self::assertSame(0, $this->db->escape(false, false));
    self::assertSame('NOW()', $this->db->escape('NOW()'));
    self::assertSame(array(\mysqli_real_escape_string($this->db->getLink(), "O'Toole"), 1, null), $this->db->escape(array("O'Toole", true, null)));
    self::assertSame(array(\mysqli_real_escape_string($this->db->getLink(), "O'Toole"), 1, null), $this->db->escape(array("O'Toole", true, null), false));
  }

  public function testInvoke()
  {
    $db = $this->db;
    $this->assertInstanceOf('\\voku\\db\\DB', $db());
    $this->assertInstanceOf('\\voku\\db\\Result', $db('SELECT * FROM ' . $this->tableName));
  }

  public function testConnector2()
  {
    // select - true
    $where = array(
        'page_type ='         => 'öäü',
        'page_type NOT LIKE'  => '%öäü123',
        'page_id >='          => 0,
        'page_id NOT BETWEEN' => array(
            '99997',
            '99999',
        ),
        'page_id NOT IN'      => array(
            'test',
            'test123',
        ),
        'page_type IN'        => array(
            'öäü',
            '123',
            'abc',
        ),
        'page_type OR'        => array(
            'öäü',
            '123',
            'abc',
        ),
    );
    $resultSelect = $this->db->select($this->tableName, $where);
    self::assertNotEquals(false, $resultSelect, 'tested: ' . print_r($where, true));
    self::assertTrue($resultSelect->num_rows > 0);

    // select - false
    $where = array(
        'page_type IS NOT' => 'lall',
        'page_type IS'     => 'öäü',
    );
    $resultSelect = $this->db->select($this->tableName, $where);
    self::assertFalse($resultSelect);
  }

  /**
   *
   */
  public function testExecSQL()
  {
    // execSQL - false
    $sql = 'INSERT INTO ' . $this->tableName . "
      SET
        page_template_lall = '" . $this->db->escape('tpl_test_new7') . "',
        page_type = " . $this->db->secure('öäü') . '
    ';
    $return = DB::execSQL($sql);
    self::assertFalse($return);

    // execSQL - true
    $sql = 'INSERT INTO ' . $this->tableName . "
      SET
        page_template = '" . $this->db->escape('tpl_test_new7') . "',
        page_type = " . $this->db->secure('öäü') . '
    ';
    $return = DB::execSQL($sql);
    self::assertTrue(is_int($return));
    self::assertTrue($return > 0);
  }

  public function testSecure()
  {
    $date = new DateTime('2016-08-15 09:22:18');

    self::assertSame("'" . $date->format('Y-m-d H:i:s') . "'", $this->db->secure($date));

    // ---

    $object = new stdClass();
    $object->bar = 'foo';

    self::assertFalse($this->db->secure($object));

    // ---

    $object = new Arrayy(array('foo', 123, 'öäü'));

    self::assertSame('\'foo,123,öäü\'', $this->db->secure($object));

    // ---

    self::assertSame(0.0, $this->db->secure(0.0));
    self::assertSame("'0,0'", $this->db->secure('0,0'));

    // ---

    self::assertSame("''", $this->db->secure(''));

    // ---

    $this->db->set_convert_null_to_empty_string(true);
    self::assertSame("''", $this->db->secure(null));

    $this->db->set_convert_null_to_empty_string(false);
    self::assertNull($this->db->secure(null));

    // ---

    $testArray = array(
        'NOW()'                                  => 'NOW()',
        'fooo'                                   => '\'fooo\'',
        123                                      => 123,
        'κόσμε'                                  => '\'κόσμε\'',
        '&lt;abcd&gt;\'$1\'(&quot;&amp;2&quot;)' => '\'&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)\'',
        '&#246;&#228;&#252;'                     => '\'&#246;&#228;&#252;\'',
    );

    foreach ($testArray as $before => $after) {
      self::assertSame($after, $this->db->secure($before));
    }

    self::assertNull($this->db->secure(array_keys($testArray)));
  }

  public function testUtf8Query()
  {
    $sql = 'INSERT INTO ' . $this->tableName . "
      SET
        page_template = '" . $this->db->escape(UTF8::urldecode('D%26%23xFC%3Bsseldorf')) . "',
        page_type = '" . $this->db->escape('DÃ¼sseldorf') . "'
    ";
    $return = DB::execSQL($sql);
    self::assertTrue(is_int($return));
    self::assertTrue($return > 0);

    $data = $this->db->select($this->tableName, 'page_id=' . (int)$return);
    $dataArray = $data->fetchArray();
    self::assertSame('Düsseldorf', $dataArray['page_template']);
    self::assertSame('Düsseldorf', $dataArray['page_type']);
  }

  public function testQuery()
  {
    //
    // query - true
    //
    $sql = 'INSERT INTO ' . $this->tableName . '
      SET
        page_template = ?,
        page_type = ?
    ';
    $return = $this->db->query(
        $sql,
        array(
            1.1,
            1,
        )
    );
    self::assertTrue($return > 1, print_r($return, true));

    //
    // query - true (with empty array)
    //
    $sql = 'INSERT INTO ' . $this->tableName . "
      SET
        page_template = '1.1',
        page_type = '1'
    ";
    $return = $this->db->query(
        $sql,
        array()
    );
    self::assertTrue($return > 1);

    //
    // query - true
    //
    $sql = 'INSERT INTO ' . $this->tableName . '
      SET
        page_template = ?,
        page_type = ?
    ';
    $tmpDate = new DateTime();
    $tmpId = $this->db->query(
        $sql,
        array(
            'dateTest',
            $tmpDate,
        )
    );
    self::assertTrue($tmpId > 1);

    //
    // select - true
    //
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchObject();
    self::assertSame($tmpDate->format('Y-m-d H:i:s'), $tmpPage->page_type);

    //
    // query - true (with '?' in the string)
    //
    $sql = 'INSERT INTO ' . $this->tableName . '
      SET
        page_template = ?,
        page_type = ?
    ';
    $tmpId = $this->db->query(
        $sql,
        array('http://foo.com/?foo=1', 'foo\'bar')
    );
    self::assertTrue($tmpId > 1);
    // select - true
    $result = $this->db->select($this->tableName, 'page_id = ' . (int)$tmpId);
    $tmpPage = $result->fetchObject();
    self::assertSame('http://foo.com/?foo=1', $tmpPage->page_template);
    self::assertSame('foo\'bar', $tmpPage->page_type);

    //
    // query - false
    //
    $sql = 'INSERT INTO ' . $this->tableName . '
      SET
        page_template = ?,
        page_type = ?
    ';
    $return = $this->db->query(
        $sql,
        array(
            true,
            array('test'),
        )
    );
    // array('test') => null
    self::assertFalse($return);

    //
    // query - false
    //
    $sql = 'INSERT INTO ' . $this->tableName . '
      SET
        page_template_lall = ?,
        page_type = ?
    ';
    $return = $this->db->query(
        $sql,
        array(
            'tpl_test_new15',
            1,
        )
    );
    self::assertFalse($return);

    //
    // query - false
    //
    $return = $this->db->query(
        '',
        array(
            'tpl_test_new15',
            1,
        )
    );
    self::assertFalse($return);
  }

  public function testCache()
  {
    $_GET['testCache'] = 1;

    // no-cache
    $sql = 'SELECT * FROM ' . $this->tableName;
    $result = DB::execSQL($sql, false);
    if (count($result) > 0) {
      $return = true;
    } else {
      $return = false;
    }
    self::assertTrue($return);

    // set cache
    $sql = 'SELECT * FROM ' . $this->tableName;
    $result = DB::execSQL($sql, true);
    if (count($result) > 0) {
      $return = true;
    } else {
      $return = false;
    }
    self::assertTrue($return);

    $queryCount = $this->db->query_count;

    // use cache
    $sql = 'SELECT * FROM ' . $this->tableName;
    $result = DB::execSQL($sql, true);
    if (count($result) > 0) {
      $return = true;
    } else {
      $return = false;
    }
    self::assertTrue($return);

    // check cache
    self::assertSame($queryCount, $this->db->query_count);
  }

  public function testQueryErrorHandling()
  {
    $this->db->close();
    self::assertFalse($this->db->isReady());
    $this->invokeMethod(
        $this->db, 'queryErrorHandling',
        array(
            'DB server has gone away',
            2006,
            'SELECT * FROM ' . $this->tableName . ' WHERE page_id = 1',
        )
    );
    self::assertTrue($this->db->isReady());
  }

  public function testSerializable()
  {
    $dbSerializable = serialize($this->db);
    $dbTmp = unserialize($dbSerializable);
    self::assertTrue($dbTmp->isReady());

    // query - true
    $sql = 'INSERT INTO ' . $this->tableName . "
      SET
        page_template = '1.1',
        page_type = '1'
    ";
    $return = $dbTmp->query($sql);
    self::assertTrue($return > 1);
  }

  public function testQuoteString()
  {
    $testArray = array(
        'NOW()'                                  => '`NOW()`',
        'fooo'                                   => '`fooo`',
        '`fooo`'                                 => '`fooo`',
        '`fo`oo`'                                => '`fo``oo`',
        '``fooo'                                 => '`fooo`',
        '``fooo`'                                => '`fooo`',
        '\'fooo\''                               => '`\\\'fooo\\\'`',
        123                                      => '`123`',
        'κόσμε'                                  => '`κόσμε`',
        '&lt;abcd&gt;\'$1\'(&quot;&amp;2&quot;)' => '`&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)`',
        '&#246;&#228;&#252;'                     => '`&#246;&#228;&#252;`',
    );

    foreach ($testArray as $before => $after) {
      self::assertSame($after, $this->db->quote_string($before));
    }
  }

  /**
   * Call protected/private method of a class.
   *
   * @param object &$object    Instantiated object that we will run method on.
   * @param string $methodName Method name to call
   * @param array  $parameters Array of parameters to pass into method.
   *
   * @return mixed Method return.
   */
  public function invokeMethod(&$object, $methodName, array $parameters = array())
  {
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($object, $parameters);
  }

  public function testInstanceOf()
  {
    self::assertInstanceOf('voku\db\DB', DB::getInstance());
  }
}
