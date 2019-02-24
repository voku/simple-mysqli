<?php

declare(strict_types=1);

use Arrayy\Arrayy;
use voku\db\DB;
use voku\db\Helper;
use voku\db\Result;
use voku\helper\UTF8;

/**
 * Class SimpleDbTest
 *
 * @internal
 */
final class SimpleDbTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DB
     */
    protected $db;

    /**
     * @var string
     */
    protected $tableName = 'test_page';

    protected function setUp()
    {
        $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test', 3306, 'utf8', false, false);
    }

    public function testLogQuery()
    {
        $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true, '', 'debug');
        static::assertInstanceOf('\\voku\\db\\DB', $db_1);

        // sql - true
        $pageArray = [
            'page_template' => 'tpl_new_中',
            'page_type'     => 'lall',
        ];
        $tmpId = $db_1->insert($this->tableName, $pageArray);
        static::assertTrue($tmpId > 0);

        // sql - true v2
        $pageArray = [
            'page_template' => 'this_is_a_new_test',
            'page_type'     => 'fooooo',
        ];
        $tmpId = $db_1->insert($this->tableName, $pageArray);
        static::assertTrue($tmpId > 0);

        // update - true (affected_rows === 1)
        $pageArray = [
            'page_template' => 'this_is_a_new_test__update',
        ];
        $affected_rows = $this->db->update($this->tableName, $pageArray, 'page_id = ' . (int) $tmpId);
        static::assertSame(1, $affected_rows);

        // update - true (affected_rows === 0)
        $pageArray = [
            'page_template' => 'this_is_a_new_test__update',
        ];
        $affected_rows = $this->db->update($this->tableName, $pageArray, 'page_id = -1');
        static::assertSame(0, $affected_rows);

        // update - false
        $false = $this->db->update($this->tableName, [], 'page_id = ' . (int) $tmpId);
        static::assertFalse($false);
    }

    public function testEchoOnError1()
    {
        $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
        static::assertInstanceOf('\\voku\\db\\DB', $db_1);

        // insert - false
        $false = $db_1->insert($this->tableName, []);
        $this->expectOutputRegex('/(.)*Invalid data for INSERT(.)*/');
        static::assertFalse($false);
    }

    public function testEchoOnError4()
    {
        $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
        static::assertInstanceOf('\\voku\\db\\DB', $db_1);

        // sql - false
        $false = $db_1->query();
        $this->expectOutputRegex('/.*SimpleDbTest\.php.*/');
        static::assertFalse($false);
    }

    public function testEchoOnError3()
    {
        $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
        static::assertInstanceOf('\\voku\\db\\DB', $db_1);

        // sql - false
        $false = $db_1->query();
        $this->expectOutputRegex('/error:/');
        static::assertFalse($false);
    }

    public function testEchoOnError2()
    {
        $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
        static::assertInstanceOf('\\voku\\db\\DB', $db_1);

        // sql - false
        $false = $db_1->query();
        $this->expectOutputRegex('/(.)*Can not execute an empty query(.)*/');
        static::assertFalse($false);

        // close db-connection
        static::assertTrue($this->db->isReady());
        static::assertTrue($this->db->close());
        static::assertFalse($this->db->isReady());
        static::assertFalse($this->db->close());
        static::assertFalse($this->db->isReady());

        // insert - false
        $false = $db_1->query('INSERT INTO lall SET false = 1');
        static::assertFalse($false);
    }

    public function testExitOnError1()
    {
        $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', true, false);
        static::assertInstanceOf('\\voku\\db\\DB', $db_1);

        // insert - false
        $pageArray = [
            'page_template' => 'tpl_new_中',
            'page_type'     => 'lall',
        ];
        $false = $db_1->insert('', $pageArray);
        static::assertFalse($false);
    }

    public function testExitOnError2()
    {
        $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', true, false);
        static::assertInstanceOf('\\voku\\db\\DB', $db_1);

        // insert - false
        $false = $db_1->insert($this->tableName, []);
        static::assertFalse($false);
    }

    public function testGetFalseInstanceV1()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error connecting to mysql server: Access denied for user \'root\'@\'localhost\' (using password: YES)');

        DB::getInstance('localhost', 'root', 'test', 'mysql_test', '', '', false, false);
    }

    public function testGetFalseInstanceV2()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp('#Error connecting to mysql server:.*#');

        DB::getInstance('localhost_lall', 'root123', '', 'mysql_test', '', '', true, false);
    }

    public function testGetFalseInstanceV3()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp('#Error connecting to mysql server: Unknown database \'mysql_test_foo\'#');

        DB::getInstance('localhost', 'root', '', 'mysql_test_foo', null, '', true, false);
    }

    public function testGetInstance()
    {
        $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, false);
        static::assertInstanceOf('\\voku\\db\\DB', $db_1);

        $db_2 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', true, false);
        static::assertInstanceOf('\\voku\\db\\DB', $db_2);

        $db_3 = DB::getInstance('localhost', 'root', '', 'mysql_test', null, '', true, false);
        static::assertInstanceOf('\\voku\\db\\DB', $db_3);

        $db_4 = DB::getInstance();
        static::assertInstanceOf('\\voku\\db\\DB', $db_4);
        $db_4_serial = \serialize($db_4);
        unset($db_4);
        $db_4 = \unserialize($db_4_serial);
        static::assertInstanceOf('\\voku\\db\\DB', $db_4);

        $true = $this->db->connect();
        static::assertTrue($true);

        $true = $this->db->connect();
        static::assertTrue($true);

        $true = $this->db->reconnect(false);
        static::assertTrue($true);

        $true = $this->db->reconnect(true);
        static::assertTrue($true);
    }

    public function testInsertOnlyAndSimple()
    {
        // insert - true
        $pageArray = [
            'page_template' => '<p>foo</p>',
            'page_type'     => 'lallll',
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);
        static::assertTrue($tmpId > 0);
    }

    public function testInsertOnlyViaGetContent()
    {
        $html = \file_get_contents(__DIR__ . '/fixtures/sample-simple-html.txt');

        // insert - true
        $pageArray = [
            'page_template' => $html,
            'page_type'     => 'lallll',
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);
        static::assertTrue($tmpId > 0);
    }

    public function testInsertBugPregReplace()
    {
        // insert - true
        $pageArray = [
            'page_template' => '$2y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC',
            'page_type'     => 'lall',
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);
        static::assertTrue($tmpId > 0);

        // select - true
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject();
        static::assertSame('$2y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC', $tmpPage->page_template);

        // select - true
        foreach ($result as $resultItem) {
            static::assertSame('$2y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC', $resultItem['page_template']);
        }

        $tmpPage = $result->fetchObject('', null, true);
        static::assertSame('$2y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC', $tmpPage->page_template);

        // --

        $sql = 'INSERT INTO ' . $this->tableName . '
      SET
        page_template = ?,
        page_type = ?
    ';
        $tmpId = $this->db->query(
            $sql,
            [
                '$2y$10$HURk5OhFbsJV5G?mLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC',
                '$0y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4v?hlc79kWlCpeiHBC$',
            ]
        );

        // select - true
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject();
        static::assertSame('$2y$10$HURk5OhFbsJV5G?mLHtBgKeD1Ul86Saa4YnWE4vhlc79kWlCpeiHBC', $tmpPage->page_template);
        static::assertSame('$0y$10$HURk5OhFbsJV5GmLHtBgKeD1Ul86Saa4YnWE4v?hlc79kWlCpeiHBC$', $tmpPage->page_type);
    }

    public function testInsertAndSelectOnlyUtf84mbV1()
    {
        $html = UTF8::clean(\file_get_contents(__DIR__ . '/fixtures/sample-html.txt'), true, true, true);

        // insert - true
        $pageArray = [
            'page_template' => $html,
            'page_type'     => 'lallll',
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);
        static::assertTrue($tmpId > 0);

        // select - true
        $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
    }

    public function testInsertAndSelectOnlyUtf84mbV2()
    {
        $html = UTF8::clean(\file_get_contents(__DIR__ . '/fixtures/sample-html.txt'), true, true, true);

        // insert - true
        $pageArray = [
            'page_template' => $html,
            'page_type'     => 'lallll',
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);
        static::assertTrue($tmpId > 0);

        // select - true
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $result->fetchArray();
    }

    public function testInsertAndSelectOnlyUtf84mbV3()
    {
        $html = UTF8::clean(\file_get_contents(__DIR__ . '/fixtures/sample-html.txt'), true, true, true);

        // insert - true
        $pageArray = [
            'page_template' => $html,
            'page_type'     => 'lallll',
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);
        static::assertTrue($tmpId > 0);

        // select - true
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchArray();
        static::assertSame('<li><a href="/">鼦վͼ</a></li>' . "\n", $tmpPage['page_template']);
    }

    public function testGetInstanceHostnameException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no-sql-hostname');

        DB::getInstance('', 'root', '', 'mysql_test', 3306, 'utf8', false, false);
    }

    public function testGetInstanceUsernameException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no-sql-username');

        DB::getInstance('localhost', '', '', 'mysql_test', 3306, 'utf8', false, false);
    }

    public function testGetInstanceDatabaseException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no-sql-database');

        DB::getInstance('localhost', 'root', '', '', 3306, 'utf8', false, false);
    }

    public function testCharset()
    {
        if (Helper::isUtf8mb4Supported($this->db) === true) {
            static::assertSame('utf8mb4', $this->db->get_charset());
        } else {
            static::assertSame('utf8', $this->db->get_charset());
        }

        $return = $this->db->set_charset('utf8');
        static::assertTrue($return);

        if (Helper::isUtf8mb4Supported($this->db) === true) {
            static::assertSame('utf8mb4', $this->db->get_charset());
        } else {
            static::assertSame('utf8', $this->db->get_charset());
        }
    }

    public function testInsertUtf84mb()
    {
        $html = UTF8::clean(\file_get_contents(__DIR__ . '/fixtures/sample-html.txt'), true, true, true);

        // insert - true
        $pageArray = [
            'page_template' => $html,
            'page_type'     => 'lall',
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);
        static::assertTrue($tmpId > 0);

        // select - true
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject();
        static::assertSame('<li><a href="/">鼦վͼ</a></li>' . "\n", $tmpPage->page_template);
    }

    public function testBasics()
    {
        // insert - true
        $pageArray = [
            'page_template' => 'tpl_new_中',
            'page_type'     => 'lall',
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);

        // insert - false
        $false = $this->db->insert($this->tableName);
        static::assertFalse($false);

        // insert - false
        $false = $this->db->insert('', $pageArray);
        static::assertFalse($false);

        // select - true
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject();
        static::assertSame('tpl_new_中', $tmpPage->page_template);

        // select - true (but 0 results)
        $result = $this->db->select($this->tableName, 'page_id = -1');
        static::assertSame(0, $result->num_rows);

        // select - true
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject('stdClass');
        static::assertSame('tpl_new_中', $tmpPage->page_template);

        // select - true (but 0 results)
        $result = $this->db->select($this->tableName, 'page_id = -1');
        $result->fetchObject('stdClass');
        static::assertSame(0, $result->num_rows);

        // select - true
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);

        /* @var $tmpPage Foobar */
        $tmpPage = $result->fetchObject(
            'Foobar',
            [
                [
                    'foo' => 1,
                    'bar' => 2,
                ],
            ]
        );

        static::assertSame(1, $tmpPage->foo);
        static::assertSame(2, $tmpPage->bar);
        static::assertTrue($tmpPage->test);
        static::assertNull($tmpPage->nothing);
        static::assertSame('tpl_new_中', $tmpPage->page_template);

        $tmpFooBar = new Foobar();
        $tmpFooBar->test = null;
        $tmpPage = $result->fetchObject($tmpFooBar, null, true);

        static::assertNull($tmpPage->foo);
        static::assertNull($tmpPage->bar);
        static::assertNull($tmpPage->test);
        static::assertNull($tmpPage->nothing);
        static::assertSame('tpl_new_中', $tmpPage->page_template);

        /* @var $tmpPages Foobar[] */
        $tmpPages = $result->fetchAllObject(
            'Foobar',
            [
                [
                    'foo' => 1,
                    'bar' => 2,
                ],
            ]
        );

        static::assertSame(1, $tmpPages[0]->foo);
        static::assertSame(2, $tmpPages[0]->bar);
        static::assertTrue($tmpPages[0]->test);
        static::assertNull($tmpPages[0]->nothing);
        static::assertSame('tpl_new_中', $tmpPages[0]->page_template);

        /* @var $tmpPages [] Foobar */
        $tmpPages = $result->fetchAllObject('Foobar');

        static::assertNull($tmpPages[0]->foo);
        static::assertNull($tmpPages[0]->bar);
        static::assertTrue($tmpPages[0]->test);
        static::assertSame('lall', $tmpPages[0]->page_type);
        static::assertSame('tpl_new_中', $tmpPages[0]->page_template);

        // update - true
        $pageArray = [
            'page_template' => 'tpl_update',
        ];
        $this->db->update($this->tableName, $pageArray, 'page_id = ' . (int) $tmpId);

        // update - false
        $false = $this->db->update($this->tableName, [], 'page_id = ' . (int) $tmpId);
        static::assertFalse($false);

        // update - false
        $false = $this->db->update($this->tableName, $pageArray, '');
        static::assertFalse($false);

        // update - false
        $false = $this->db->update($this->tableName, $pageArray, null);
        static::assertFalse($false);

        // update - false
        $false = $this->db->update('', $pageArray, 'page_id = ' . (int) $tmpId);
        static::assertFalse($false);

        // check (select)
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchAllObject();
        static::assertSame('tpl_update', $tmpPage[0]->page_template);

        // replace - true
        $data = [
            'page_id'       => 2,
            'page_template' => 'tpl_test',
            'page_type'     => 'öäü123',
        ];
        $tmpId = $this->db->replace($this->tableName, $data);

        // replace - false
        $false = $this->db->replace($this->tableName);
        static::assertFalse($false);

        // replace - false
        $false = $this->db->replace('', $data);
        static::assertFalse($false);

        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchAllObject();
        static::assertSame('tpl_test', $tmpPage[0]->page_template);

        // delete - true
        $affected_rows = $this->db->delete($this->tableName, ['page_id' => $tmpId]);
        static::assertSame(1, $affected_rows);

        // delete - true (but 0 affected_rows)
        $affected_rows = $this->db->delete($this->tableName, ['page_id' => -1]);
        static::assertSame(0, $affected_rows);

        // delete - false
        $false = $this->db->delete('', ['page_id' => $tmpId]);
        static::assertFalse($false);

        // insert - true
        $pageArray = [
            'page_template' => 'tpl_new_中',
            'page_type'     => 'lall',
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);

        // delete - nothing
        $false = $this->db->delete($this->tableName, '');
        static::assertFalse($false);

        // delete - false
        $false = $this->db->delete($this->tableName, null);
        static::assertFalse($false);

        // delete - true
        $false = $this->db->delete($this->tableName, 'page_id = ' . $this->db->escape($tmpId));
        static::assertSame(1, $false);

        // select - true
        $result = $this->db->select($this->tableName, ['page_id' => 2]);
        static::assertSame(0, $result->num_rows);

        $resultArray = $result->fetchArray();
        static::assertSame([], $resultArray);

        $resultArray = $result->fetchArrayy();
        static::assertSame([], $resultArray->getArray());

        // select - true
        $result = $this->db->select($this->tableName);
        static::assertTrue($result->num_rows > 0);

        // select - true (but empty)
        $result = $this->db->select($this->tableName, ['page_id' => -1]);
        static::assertSame(0, $result->num_rows);

        // select - false
        $false = $this->db->select($this->tableName, null);
        static::assertFalse($false);

        // select - false
        $false = $this->db->select('', ['page_id' => 2]);
        static::assertFalse($false);

        // insert - true (float)
        $pageArray = [
            'page_template' => 'tpl_new_中',
            'page_type'     => (float) 21.3123,
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);

        // delete - true
        $delete = $this->db->delete($this->tableName, 'page_id = ' . $this->db->escape($tmpId));
        static::assertSame(1, $delete);

        // insert - true (int)
        $pageArray = [
            'page_template' => 'tpl_new_中',
            'page_type'     => (float) 213123,
        ];
        $tmpId = $this->db->insert($this->tableName, $pageArray);

        // delete - true
        $delete = $this->db->delete($this->tableName, 'page_id = ' . $this->db->escape($tmpId));
        static::assertSame(1, $delete);
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
        static::assertSame(1, $result);

        $sql = 'SELECT * FROM ' . $this->db->escape($this->tableName) . '
          WHERE page_id = 1
        ';
        /** @noinspection StaticInvocationViaThisInspection */
        /** @noinspection PhpStaticAsDynamicMethodCallInspection */
        $result = (array) $this->db->qry($sql);
        static::assertSame('tpl_test_?', $result[0]['page_template']);

        $sql = 'SELECT * FROM ' . $this->db->escape($this->tableName) . '
          WHERE page_id_lall = 1
        ';
        /** @noinspection StaticInvocationViaThisInspection */
        /** @noinspection PhpStaticAsDynamicMethodCallInspection */
        $result = $this->db->qry($sql);
        static::assertFalse($result);
    }

    public function testTableExists()
    {
        $result = $this->db->table_exists($this->tableName);
        static::assertTrue($result);

        // ---------

        $result = $this->db->table_exists('no_table_name');
        static::assertFalse($result);
    }

    public function testNumRows()
    {
        $sql = 'SELECT * FROM ' . $this->db->escape($this->tableName) . '
          WHERE page_id = 1
        ';
        $num_rows = $this->db->num_rows($sql);
        static::assertSame(1, $num_rows);

        // ---------

        $sql = 'SELECT * FROM ' . $this->db->escape($this->tableName) . '
          WHERE page_id = -1
        ';
        $num_rows = $this->db->num_rows($sql);
        static::assertSame(0, $num_rows);
    }

    public function testEscape()
    {
        $date = new DateTimeImmutable('2016-08-15 09:22:18');

        static::assertSame($date->format('Y-m-d H:i:s'), $this->db->escape($date));

        // ---

        $object = new stdClass();
        $object->bar = 'foo';

        $errorCatch = false;

        try {
            $this->db->secure($object);
        } catch (InvalidArgumentException $e) {
            $errorCatch = true;
        }
        static::assertTrue($errorCatch);

        // ---

        $object = new Arrayy(['foo', 123, 'öäü']);

        static::assertSame('foo,123,öäü', $this->db->escape($object));

        // ---

        static::assertSame('', $this->db->escape(''));

        // ---

        static::assertSame('NULL', $this->db->escape(null));

        // ---

        $testArray = [
            'NOW()'                                  => 'NOW()',
            'fooo'                                   => 'fooo',
            123                                      => 123,
            'κόσμε'                                  => 'κόσμε',
            '&lt;abcd&gt;\'$1\'(&quot;&amp;2&quot;)' => '&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)',
            '&#246;&#228;&#252;'                     => '&#246;&#228;&#252;',
        ];

        foreach ($testArray as $before => $after) {
            static::assertSame($after, $this->db->escape($before));
        }

        static::assertSame(\array_values($testArray), $this->db->escape(\array_keys($testArray)));

        static::assertSame('NOW(),fooo,123,κόσμε,<abcd>\\\'$1\\\'(\"&2\"),öäü', $this->db->escape(\array_keys($testArray), false, true, true));
        static::assertSame('NOW(),fooo,123,κόσμε,&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;),&#246;&#228;&#252;', $this->db->escape(\array_keys($testArray), true, false, true));
        static::assertSame('NOW(),fooo,123,κόσμε,&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;),&#246;&#228;&#252;', $this->db->escape(\array_keys($testArray), false, false, true));
        static::assertSame('NOW(),fooo,123,κόσμε,<abcd>\\\'$1\\\'(\"&2\"),öäü', $this->db->escape(\array_keys($testArray), true, true, true));

        static::assertSame(
            [
                0 => 'NOW()',
                1 => 'fooo',
                2 => 123,
                3 => 'κόσμε',
                4 => '<abcd>\\\'$1\\\'(\"&2\")',
                5 => 'öäü',
            ],
            $this->db->escape(\array_keys($testArray), false, true, false)
        );
        static::assertSame(
            [
                0 => 'NOW()',
                1 => 'fooo',
                2 => 123,
                3 => 'κόσμε',
                4 => '&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)',
                5 => '&#246;&#228;&#252;',
            ],
            $this->db->escape(\array_keys($testArray), true, false, false)
        );
        static::assertSame(
            [
                0 => 'NOW()',
                1 => 'fooo',
                2 => 123,
                3 => 'κόσμε',
                4 => '&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)',
                5 => '&#246;&#228;&#252;',
            ],
            $this->db->escape(\array_keys($testArray), false, false, false)
        );
        static::assertSame(
            [
                0 => 'NOW()',
                1 => 'fooo',
                2 => 123,
                3 => 'κόσμε',
                4 => '<abcd>\\\'$1\\\'(\"&2\")',
                5 => 'öäü',
            ],
            $this->db->escape(\array_keys($testArray), true, true, false)
        );

        static::assertSame('NULL', $this->db->escape(\array_keys($testArray), false, true, null));
        static::assertSame('NULL', $this->db->escape(\array_keys($testArray), true, false, null));
        static::assertSame('NULL', $this->db->escape(\array_keys($testArray), false, false, null));
        static::assertSame('NULL', $this->db->escape(\array_keys($testArray), true, true, null));

        // ---

        $this->db = DB::getInstance();

        $data = [
            'page_template' => "tpl_test_'new2",
            'page_type'     => 1.1,
        ];

        $newData = (array) $this->db->escape($data);

        static::assertSame('tpl_test_\\\'new2', $newData['page_template']);
        static::assertSame(1.10000000, $newData['page_type']);

        // ---

        $data = [
            'page_template' => "tpl_test_'new2",
            'page_type'     => 1.1,
        ];

        $newData = $this->db->escape($data, true, true, true);

        static::assertSame('tpl_test_\\\'new2,1.1', $newData);

        // ---

        $data = [
            'page_template' => "tpl_test_'new2",
            'page_type'     => '0111',
        ];

        $newData = $this->db->escape($data, true, false, true);

        static::assertSame('tpl_test_\\\'new2,0111', $newData);

        // ---

        $data = [
            'page_template' => "tpl_test_'new2",
            'page_type'     => '111',
        ];

        $newData = $this->db->escape($data, true, false, true);

        static::assertSame('tpl_test_\\\'new2,111', $newData);

        // ---

        $data = [];

        $tested = $this->db->escape($data);

        static::assertSame([], $tested);

        // ---

        $data = ['foo\'', 'bar"'];

        $tested = $this->db->escape($data);

        static::assertSame(['foo\\\'', 'bar\"'], $tested);

        // ---

        $data = 123;

        $tested = $this->db->escape($data);

        static::assertSame(123, $tested);

        // ---

        $data = true;

        $tested = $this->db->escape($data);

        static::assertSame(1, $tested);

        // ---

        $data = false;

        $tested = $this->db->escape($data);

        static::assertSame(0, $tested);

        // ---

        $data = [true, false];

        $tested = $this->db->escape($data);

        static::assertSame([1, 0], $tested);

        // ---

        $data = 'http://foobar.com?test=1';

        $tested = $this->db->escape($data);

        static::assertSame('http://foobar.com?test=1', $tested);
    }

    public function testFormatQuery()
    {
        $result = $this->invokeMethod(
            $this->db,
            '_parseQueryParamsByName',
            [
                'SELECT * FROM post WHERE id = :id',
                ['id' => 1],
            ]
        );
        static::assertSame(
            'SELECT * FROM post WHERE id = 1',
            $result['sql']
        );
        static::assertSame(
            [],
            $result['params']
        );

        $result = $this->invokeMethod(
            $this->db,
            '_parseQueryParamsByName',
            [
                'SELECT * FROM post WHERE id=:id',
                ['id' => 1],
            ]
        );
        static::assertSame(
            'SELECT * FROM post WHERE id=1',
            $result['sql']
        );
        static::assertSame(
            [],
            $result['params']
        );

        $result = $this->invokeMethod(
            $this->db,
            '_parseQueryParamsByName',
            [
                'SELECT * FROM post WHERE id = ' . "\n" . '  :id;',
                ['id' => 1],
            ]
        );
        static::assertSame(
            'SELECT * FROM post WHERE id = ' . "\n" . '  1;',
            $result['sql']
        );
        static::assertSame(
            [],
            $result['params']
        );

        $result = $this->invokeMethod(
            $this->db,
            '_parseQueryParamsByName',
            [
                'SELECT * FROM post WHERE id = ' . "\n" . '  :id;',
                ['id' => 1, 'foo' => 'bar'],
            ]
        );
        static::assertSame(
            'SELECT * FROM post WHERE id = ' . "\n" . '  1;',
            $result['sql']
        );
        static::assertSame(
            ['foo' => 'bar'],
            $result['params']
        );
    }

    public function testConnector()
    {
        $data = [
            'page_template' => '123',
        ];
        $where = [
            'page_id LIKE' => '1',
        ];

        // will return the number of effected rows
        $resultUpdate = $this->db->update($this->tableName, $data, $where);
        static::assertSame(1, $resultUpdate);

        $data = [
            'page_template' => '123',
            'page_type'     => 'öäü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        $where = [
            'page_type ='        => 'öäü',
            'page_type NOT LIKE' => '%öäü123',
            'page_id ='          => $resultInsert,
        ];

        $data = [
            'page_template' => ['page_template +' => 1],
            'page_type'     => 'abc',
        ];

        // will return the number of effected rows
        $resultUpdate = $this->db->update($this->tableName, $data, $where);
        static::assertSame(1, $resultUpdate);

        $where = [
            'page_type ='        => 'abc',
            'page_type NOT LIKE' => '%öäü123',
            'page_id ='          => $resultInsert,
        ];

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->fetchArray();
        static::assertSame('abc', $resultSelectArray['page_type']);
        static::assertSame('124', $resultSelectArray['page_template']);

        $where = [
            'page_type ='        => 'abc',
            'page_type NOT LIKE' => '%öäü123',
            'page_id ='          => $resultInsert,
        ];

        $data = [
            'page_template' => ['page_template -' => '2'],
            'page_type'     => 'öäü',
        ];

        // will return the number of effected rows
        $resultUpdate = $this->db->update($this->tableName, $data, $where);
        static::assertSame(1, $resultUpdate);

        $where = [
            'page_type ='        => 'öäü',
            'page_type NOT LIKE' => '%öäü123',
            'page_id ='          => $resultInsert,
        ];

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->fetchArray();
        static::assertSame('öäü', $resultSelectArray['page_type']);
        static::assertSame('122', $resultSelectArray['page_template']);

        $resultSelect = $this->db->select($this->tableName, $where);
        foreach ($resultSelect->fetchYield() as $resultSelectArray) {
            static::assertSame('öäü', $resultSelectArray->page_type);
        }

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->fetchArrayy();
        static::assertSame('öäü', $resultSelectArray['page_type']);

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->fetchArrayy()->clean()->getArray();
        static::assertSame('öäü', $resultSelectArray['page_type']);

        $where = [
            'page_type ='  => 'öäü',
            'page_type <>' => 'öäü123',
            'page_id >'    => 0,
            'page_id >='   => 0,
            'page_id <'    => 1000000,
            'page_id <='   => 1000000,
            'page_id ='    => $resultInsert,
        ];

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->fetchArrayPair('page_type', 'page_type');
        static::assertSame('öäü', $resultSelectArray['öäü']);

        $where = [
            'page_type LIKE'     => 'öäü',
            'page_type NOT LIKE' => 'öäü123',
            'page_id ='          => $resultInsert,
        ];

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->fetch();
        $getDefaultResultType = $resultSelect->getDefaultResultType();
        static::assertSame('object', $getDefaultResultType);
        static::assertSame(Result::RESULT_TYPE_OBJECT, $getDefaultResultType);
        static::assertSame('öäü', $resultSelectArray->page_type);

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelect->setDefaultResultType('array'); // switch default result-type
        $resultSelectArray = $resultSelect->fetch();
        $getDefaultResultType = $resultSelect->getDefaultResultType();
        static::assertSame('array', $getDefaultResultType);
        static::assertSame(Result::RESULT_TYPE_ARRAY, $getDefaultResultType);
        /** @noinspection OffsetOperationsInspection */
        static::assertSame('öäü', $resultSelectArray['page_type']);

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->fetchArray();
        static::assertSame('öäü', $resultSelectArray['page_type']);

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->get();
        static::assertSame('öäü', $resultSelectArray->page_type);

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->getAll();
        static::assertSame('öäü', $resultSelectArray[0]->page_type);

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->getArray();
        static::assertSame('öäü', $resultSelectArray[0]['page_type']);

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->getObject();
        static::assertSame('öäü', $resultSelectArray[0]->page_type);

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectTmp = $resultSelect->getColumn('page_type');
        static::assertSame('öäü', $resultSelectTmp);

        $resultSelect = $this->db->select($this->tableName, $where);
        static::assertInstanceOf('\\voku\\db\\Result', $resultSelect);
    }

    public function testTransactionFalse()
    {
        $data = [
            'page_template' => 'tpl_test_new3',
            'page_type'     => 'öäü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        // start - test a transaction - true
        $beginTransaction = $this->db->beginTransaction();
        static::assertTrue($beginTransaction);

        $data = [
            'page_type' => 'lall',
        ];
        $where = [
            'page_id' => $resultInsert,
        ];
        $this->db->update($this->tableName, $data, $where);

        $data = [
            'page_type' => 'lall',
            'page_lall' => 'öäü',
            // this will produce a mysql-error and a mysqli-rollback via "db->endTransaction()"
        ];
        $where = [
            'page_id' => $resultInsert,
        ];
        $this->db->update($this->tableName, $data, $where);

        // end - test a transaction
        $this->db->endTransaction();

        $where = [
            'page_id' => $resultInsert,
        ];

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->fetchAllArray();
        static::assertSame('öäü', $resultSelectArray[0]['page_type']);

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->fetchAllArrayy()->filterBy('page_type', 'öäü')->first();
        static::assertSame('öäü', $resultSelectArray['page_type']);

        $i = 0;
        $resultSelect = $this->db->select($this->tableName, $where);
        foreach ($resultSelect->fetchAllYield('Foobar') as $tmpResult) {
            $i++;
            static::assertSame('öäü', $tmpResult->page_type);
        }
        static::assertTrue($i > 0);

        $i = 0;
        $resultSelect = $this->db->select($this->tableName, $where);
        foreach ($resultSelect->fetchYield('Foobar') as $tmpResult) {
            $i++;
            static::assertSame('öäü', $tmpResult->page_type);
        }
        static::assertTrue($i > 0);

        $i = 0;
        $resultSelect = $this->db->select($this->tableName);
        foreach ($resultSelect->fetchAllYield() as $tmpResult) {
            $i++;
            static::assertNotNull($tmpResult->page_type);
        }
        static::assertTrue($i > 0);

        $i = 0;
        $resultSelect = $this->db->select($this->tableName);
        foreach ($resultSelect->getYield() as $tmpResult) {
            $i++;
            static::assertNotNull($tmpResult->page_type);
        }
        static::assertTrue($i > 0);
    }

    public function testTransactionTrue()
    {
        $data = [
            'page_template' => 'tpl_test_new3',
            'page_type'     => 'öäü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        // start - test a transaction
        $this->db->startTransaction();

        $data = [
            'page_type' => 'lall',
        ];
        $where = [
            'page_id' => $resultInsert,
        ];
        $this->db->update($this->tableName, $data, $where);

        $data = [
            'page_type'     => 'lall',
            'page_template' => 'öäü',
        ];
        $where = [
            'page_id' => $resultInsert,
        ];
        $this->db->update($this->tableName, $data, $where);

        // end - test a transaction
        $this->db->endTransaction();

        $where = [
            'page_id' => $resultInsert,
        ];

        $resultSelect = $this->db->select($this->tableName, $where);
        $resultSelectArray = $resultSelect->fetchAllArray();
        static::assertSame('lall', $resultSelectArray[0]['page_type']);
    }

    public function testRollback()
    {
        // start - test a transaction
        $this->db->beginTransaction();

        $data = [
            'page_template' => 'tpl_test_new4',
            'page_type'     => 'öäü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        $data = [
            'page_type' => 'lall',
        ];
        $where = [
            'page_id' => $resultInsert,
        ];
        $this->db->update($this->tableName, $data, $where);

        $data = [
            'page_type' => 'lall',
            'page_lall' => 'öäü',
            // this will produce a mysql-error and a mysqli-rollback
        ];
        $where = [
            'page_id' => $resultInsert,
        ];
        $this->db->update($this->tableName, $data, $where);

        // end - test a transaction, with a rollback!
        $this->db->rollback();

        $where = [
            'page_id' => $resultInsert,
        ];
        $resultSelect = $this->db->select($this->tableName, $where);
        static::assertSame(0, $resultSelect->num_rows);
    }

    /**
     * @depends testRollback
     */
    public function testGetErrors()
    {
        // INFO: run all previous tests and generate some errors

        $error = $this->db->lastError();
        static::assertInternalType('string', $error);
        static::assertContains('Unknown column \'page_lall\' in \'field list', $error);

        $errors = $this->db->getErrors();
        static::assertInternalType('array', $errors);
        static::assertContains('Unknown column \'page_lall\' in \'field list', $errors[0]);
    }

    public function testCommit()
    {
        // start - test a transaction
        $this->db->beginTransaction();

        $data = [
            'page_template' => 'tpl_test_new4',
            'page_type'     => 'öäü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        $data = [
            'page_type' => 'lall',
        ];
        $where = [
            'page_id' => $resultInsert,
        ];
        $this->db->update($this->tableName, $data, $where);

        $data = [
            'page_type' => 'lall',
            'page_lall' => 'öäü',
            // this will produce a mysql-error and a mysqli-rollback
        ];
        $where = [
            'page_id' => $resultInsert,
        ];
        $this->db->update($this->tableName, $data, $where);

        // end - test a transaction, with a commit!
        $this->db->commit();

        $where = [
            'page_id' => $resultInsert,
        ];
        $resultSelect = $this->db->select($this->tableName, $where);
        static::assertSame(1, $resultSelect->num_rows);
    }

    public function testTransactionException()
    {
        // start - test a transaction - true
        $beginTransaction = $this->db->beginTransaction();
        static::assertTrue($beginTransaction);

        // start - test a transaction - false
        $beginTransaction = $this->db->beginTransaction();
        static::assertFalse($beginTransaction);

        // reset
        $this->db->endTransaction();
    }

    public function testFetchColumn()
    {
        $data = [
            'page_template' => 'tpl_test_new5',
            'page_type'     => 'öäü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        $dataV2 = [
            'page_template' => 'tpl_test_new5V2',
            'page_type'     => 'öäüV2',
        ];

        // will return the auto-increment value of the new row (v2)
        $resultInsertV2 = $this->db->insert($this->tableName, $dataV2);
        static::assertGreaterThan($resultInsert, $resultInsertV2);

        // ---

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $resultInsert]);

        $columnResult = $resultSelect->fetchColumn('page_template');
        static::assertSame('tpl_test_new5', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', false, false);
        static::assertSame('tpl_test_new5', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', true, true);
        static::assertSame(['tpl_test_new5'], $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', false, true);
        static::assertSame(['tpl_test_new5'], $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template_foo');
        static::assertSame('', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template_foo', false, false);
        static::assertSame('', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template_foo', true, true);
        static::assertSame([], $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template_foo', false, true);
        static::assertSame([], $columnResult);

        // ---

        $resultSelect = $this->db->select($this->tableName, ['page_id AND' => [$resultInsert, $resultInsert]]);

        $columnResult = $resultSelect->fetchColumn('page_template');
        static::assertSame('tpl_test_new5', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', false, false);
        static::assertSame('tpl_test_new5', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', true, true);
        static::assertSame(['tpl_test_new5'], $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', false, true);
        static::assertSame(['tpl_test_new5'], $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', true, false);
        static::assertSame('tpl_test_new5', $columnResult);

        // ---

        $resultSelect = $this->db->select($this->tableName, ['page_id OR' => [$resultInsert, $resultInsertV2]]);

        $columnResult = $resultSelect->fetchColumn('page_template');
        static::assertSame('tpl_test_new5V2', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', false, false);
        static::assertSame('tpl_test_new5V2', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', true, true);
        static::assertSame(['tpl_test_new5', 'tpl_test_new5V2'], $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', true, false);
        static::assertSame('tpl_test_new5V2', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template', false, true);
        static::assertSame(['tpl_test_new5', 'tpl_test_new5V2'], $columnResult);

        // ---

        $columnResult = $resultSelect->fetchColumn('page_template_foo');
        static::assertSame('', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template_foo', false, false);
        static::assertSame('', $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template_foo', true, true);
        static::assertSame([], $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template_foo', false, true);
        static::assertSame([], $columnResult);

        $columnResult = $resultSelect->fetchColumn('page_template_foo', true, false);
        static::assertSame('', $columnResult);

        // ---

        $columnResult = $resultSelect->fetchAllColumn('page_template', false);
        static::assertSame(['tpl_test_new5', 'tpl_test_new5V2'], $columnResult);

        $columnResult = $resultSelect->fetchAllColumn('page_template', true);
        static::assertSame(['tpl_test_new5', 'tpl_test_new5V2'], $columnResult);

        $columnResult = $resultSelect->fetchAllColumn('page_template', true);
        static::assertSame(['tpl_test_new5', 'tpl_test_new5V2'], $columnResult);
    }

    public function testIsEmpty()
    {
        $data = [
            'page_template' => 'tpl_test_new5',
            'page_type'     => 'öäü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $resultInsert]);
        static::assertFalse($resultSelect->is_empty());

        $resultSelect = $this->db->select($this->tableName, ['page_id' => 999999]);
        static::assertTrue($resultSelect->is_empty());
    }

    public function testJson()
    {
        $data = [
            'page_template' => 'tpl_test_new6',
            'page_type'     => 'öäü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $resultInsert]);
        $columnResult = $resultSelect->json();
        $columnResultDecode = \json_decode($columnResult, true);
        static::assertSame('tpl_test_new6', $columnResultDecode[0]['page_template']);
    }

    public function testFetchObject()
    {
        $data = [
            'page_template' => 'tpl_test_new7',
            'page_type'     => 'öäü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $resultInsert]);
        $columnResult = $resultSelect->fetchObject();
        static::assertSame('tpl_test_new7', $columnResult->page_template);
    }

    public function testDefaultResultType()
    {
        $data = [
            'page_template' => 'tpl_test_new8',
            'page_type'     => 'öäü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $resultInsert]);

        // array
        $resultSelect->setDefaultResultType('array');

        $columnResult = (array) $resultSelect->fetch(true);
        static::assertSame('tpl_test_new8', $columnResult['page_template']);

        $columnResult = (array) $resultSelect->fetchAll();
        static::assertSame('tpl_test_new8', $columnResult[0]['page_template']);

        $columnResult = (array) $resultSelect->fetchAllArray();
        static::assertSame('tpl_test_new8', $columnResult[0]['page_template']);

        // object
        $resultSelect->setDefaultResultType('object');

        $columnResult = $resultSelect->fetch(true);
        static::assertSame('tpl_test_new8', $columnResult->page_template);

        $columnResult = $resultSelect->fetchAll();
        static::assertSame('tpl_test_new8', $columnResult[0]->page_template);

        $columnResult = $resultSelect->fetchAllObject();
        static::assertSame('tpl_test_new8', $columnResult[0]->page_template);
    }

    public function testGetAllTables()
    {
        $tableArray = $this->db->getAllTables();

        $return = false;
        foreach ($tableArray as $table) {
            if (\in_array($this->tableName, $table, true) === true) {
                $return = true;

                break;
            }
        }

        static::assertTrue($return);
    }

    public function testPing()
    {
        $ping = $this->db->ping();
        static::assertTrue($ping);
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
        static::assertTrue($result);

        $sql = '
        SELECT * FROM ' . $this->tableName . ';
        SELECT * FROM ' . $this->tableName . ';
        ';
        // multi_query - true
        $result = $this->db->multi_query($sql);
        static::assertInternalType('array', $result);
        /** @noinspection ForeachSourceInspection */
        foreach ($result as $resultForEach) {
            /* @var $resultForEach Result */
            $tmpArray = $resultForEach->fetchArray();

            static::assertInternalType('array', $tmpArray);
            static::assertTrue(\count($tmpArray) > 0);
        }

        // multi_query - false
        $false = $this->db->multi_query('');
        static::assertFalse($false);

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
        static::assertFalse($result);
    }

    public function testEscapeData()
    {
        static::assertSame('NULL', $this->db->escape(null, true));
        static::assertSame(\mysqli_real_escape_string($this->db->getLink(), "O'Toole"), $this->db->escape("O'Toole"));
        static::assertSame(\mysqli_real_escape_string($this->db->getLink(), "O'Toole"), $this->db->escape("O'Toole", true));
        static::assertSame(1, $this->db->escape(true));
        static::assertSame(0, $this->db->escape(false));
        static::assertSame(1, $this->db->escape(true, false));
        static::assertSame(0, $this->db->escape(false, false));
        static::assertSame('NOW()', $this->db->escape('NOW()'));
        static::assertSame(
            [
                \mysqli_real_escape_string($this->db->getLink(), "O'Toole"),
                1,
                'NULL',
            ],
            $this->db->escape(["O'Toole", true, null])
        );
        static::assertSame(
            [
                \mysqli_real_escape_string($this->db->getLink(), "O'Toole"),
                1,
                'NULL',
            ],
            $this->db->escape(["O'Toole", true, null], false)
        );
    }

    public function testInvoke()
    {
        $db = $this->db;
        static::assertInstanceOf('\\voku\\db\\DB', $db());
        static::assertInstanceOf('\\voku\\db\\Result', $db('SELECT * FROM ' . $this->tableName));
    }

    public function testConnector2()
    {
        // select - true
        $where = [
            'page_type ='         => 'öäü',
            'page_type NOT LIKE'  => '%öäü123',
            'page_id >='          => 0,
            'page_id NOT BETWEEN' => [
                '99997',
                '99999',
            ],
            'page_id NOT IN' => [
                'test',
                'test123',
            ],
            'page_type IN' => [
                'öäü',
                '123',
                'abc',
            ],
            'page_type OR' => [
                'öäü',
                '123',
                'abc',
            ],
        ];
        $resultSelect = $this->db->select($this->tableName, $where);
        static::assertNotFalse($resultSelect, 'tested: ' . \print_r($where, true));
        static::assertTrue($resultSelect->num_rows > 0);

        // select - false
        $where = [
            'page_type IS NOT' => 'lall',
            'page_type IS'     => 'öäü',
        ];
        $resultSelect = $this->db->select($this->tableName, $where);
        static::assertFalse($resultSelect);
    }

    public function testExecSQL()
    {
        // execSQL - false
        $sql = 'INSERT INTO ' . $this->tableName . "
          SET
            page_template_lall = '" . $this->db->escape('tpl_test_new7') . "',
            page_type = " . $this->db->secure('öäü') . '
        ';
        $return = DB::execSQL($sql);
        static::assertFalse($return);

        // execSQL - true
        $sql = 'INSERT INTO ' . $this->tableName . "
          SET
            page_template = '" . $this->db->escape('tpl_test_new7') . "',
            page_type = " . $this->db->secure('öäü') . '
        ';
        $return = DB::execSQL($sql);
        static::assertInternalType('int', $return);
        static::assertTrue($return > 0);
    }

    public function testSecure()
    {
        // --- object: DateTime

        $date = new DateTime('2016-08-15 09:22:18');

        static::assertSame("'" . $date->format('Y-m-d H:i:s') . "'", $this->db->secure($date));

        // --- object: stdClass

        $object = new stdClass();
        $object->bar = 'foo';

        $errorCatch = false;

        try {
            $this->db->secure($object);
        } catch (InvalidArgumentException $e) {
            $errorCatch = true;
        }
        static::assertTrue($errorCatch);

        // --- object: Arrayy

        $object = new Arrayy(['foo', 123, 'öäü']);

        static::assertSame('\'foo,123,öäü\'', $this->db->secure($object));

        // --- 0.0

        static::assertSame(0.0, $this->db->secure(0.0));
        static::assertSame("'0,0'", $this->db->secure('0,0'));

        // --- empty string

        static::assertSame("''", $this->db->secure(''));

        // --- '' string

        static::assertSame("''", $this->db->secure("''"));

        // --- NULL

        $this->db->set_convert_null_to_empty_string(true);
        static::assertSame("''", $this->db->secure(null));

        $this->db->set_convert_null_to_empty_string(false);
        static::assertSame('NULL', $this->db->secure(null));

        // --- array

        $testArray = [
            'NOW()'                                  => 'NOW()',
            'fooo'                                   => '\'fooo\'',
            123                                      => 123,
            'κόσμε'                                  => '\'κόσμε\'',
            '&lt;abcd&gt;\'$1\'(&quot;&amp;2&quot;)' => '\'&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)\'',
            '&#246;&#228;&#252;'                     => '\'&#246;&#228;&#252;\'',
        ];

        foreach ($testArray as $before => $after) {
            static::assertSame($after, $this->db->secure($before));
        }

        static::assertSame('NOW(),\'fooo\',123,\'κόσμε\',\'&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)\',\'&#246;&#228;&#252;\'', $this->db->secure(\array_keys($testArray)));
    }

    public function testUtf8Query()
    {
        $sql = 'INSERT INTO ' . $this->tableName . "
          SET
            page_template = '" . $this->db->escape(UTF8::urldecode('D%26%23xFC%3Bsseldorf')) . "',
            page_type = '" . $this->db->escape('DÃ¼sseldorf') . "'
        ";
        $return = DB::execSQL($sql);
        static::assertInternalType('int', $return);
        static::assertTrue($return > 0);

        $data = $this->db->select($this->tableName, 'page_id=' . (int) $return);
        $dataArray = $data->fetchArray();
        static::assertSame('Düsseldorf', $dataArray['page_template']);
        static::assertSame('Düsseldorf', $dataArray['page_type']);
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
            [
                1.1,
                1,
            ]
        );
        static::assertTrue($return > 1, \print_r($return, true));

        //
        // query - true
        //
        $sql = 'INSERT INTO ' . $this->tableName . '
          SET
            page_template = :foo,
            page_type = ?
        ';
        $return = $this->db->query(
            $sql,
            [
                'foo' => 1.1,
                1,
            ]
        );
        static::assertTrue($return > 1, \print_r($return, true));

        //
        // query - true
        //
        $sql = 'INSERT INTO ' . $this->tableName . '
          SET
            page_template = :page_template,
            page_type = :page_type
        ';
        $return = $this->db->query(
            $sql,
            [
                'page_template' => 1.1,
                'page_type'     => 1,
            ]
        );
        static::assertTrue($return > 1, \print_r($return, true));

        //
        // query + UTF-8 - true
        //
        $sql = 'INSERT INTO ' . $this->tableName . '
          SET
            page_template = :page_template,
            page_type = :page_type
        ';
        $return = $this->db->query(
            $sql,
            [
                'page_template' => 'Iñtërnâtiônàlizætiøn',
                'page_type'     => '中文空白-ÖÄÜ-中文空白',
            ]
        );
        static::assertTrue($return > 1, \print_r($return, true));

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
            []
        );
        static::assertTrue($return > 1);

        //
        // query - true
        //
        $sql = 'INSERT INTO ' . $this->tableName . '
          SET
            page_template = ?,
            page_type = ?
        ';
        $tmpDate = new DateTimeImmutable();
        $tmpId = $this->db->query(
            $sql,
            [
                'dateTest',
                $tmpDate,
            ]
        );
        static::assertTrue($tmpId > 1);
        //
        // select - true
        //
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject();
        static::assertSame($tmpDate->format('Y-m-d H:i:s'), $tmpPage->page_type);
        static::assertSame('dateTest', $tmpPage->page_template);

        //
        // query - true
        //
        $sql = 'INSERT INTO ' . $this->tableName . '
          SET
            page_template = :page_template,
            page_type = :page_type
        ';
        $tmpDate = new DateTimeImmutable();
        $tmpId = $this->db->query(
            $sql,
            [
                'page_template' => 'dateTest',
                'page_type'     => $tmpDate,
            ]
        );
        static::assertTrue($tmpId > 1);
        //
        // select - true
        //
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject();
        static::assertSame($tmpDate->format('Y-m-d H:i:s'), $tmpPage->page_type);
        static::assertSame('dateTest', $tmpPage->page_template);

        //
        // query - true
        //
        $sql = 'INSERT INTO ' . $this->tableName . '
          SET
            page_template = ?,
            page_type = :page_type
        ';
        $tmpDate = new DateTime();
        $tmpId = $this->db->query(
            $sql,
            [
                0           => 'dateTest',
                'page_type' => $tmpDate,
            ]
        );
        static::assertTrue($tmpId > 1);
        //
        // select - true
        //
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject();
        static::assertSame($tmpDate->format('Y-m-d H:i:s'), $tmpPage->page_type);
        static::assertSame('dateTest', $tmpPage->page_template);

        //
        // query - true
        //
        $sql = 'INSERT INTO ' . $this->tableName . '
          SET
            page_template = :page_template,
            page_type = :page_type
        ';
        $tmpDate = new DateTimeImmutable();
        $tmpId = $this->db->query(
            $sql,
            [
                'page_template' => ':page_type',
                'page_type'     => $tmpDate,
            ]
        );
        static::assertTrue($tmpId > 1);
        //
        // select - true
        //
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject();
        static::assertSame($tmpDate->format('Y-m-d H:i:s'), $tmpPage->page_type);
        static::assertSame(':page_type', $tmpPage->page_template);

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
            [
                'http://foo.com/?foo=1',
                'foo\'bar',
            ]
        );
        static::assertTrue($tmpId > 1);
        // select - true
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject();
        static::assertSame('http://foo.com/?foo=1', $tmpPage->page_template);
        static::assertSame('foo\'bar', $tmpPage->page_type);

        //
        // query - true (with '?' in the string)
        //
        $sql = 'INSERT INTO ' . $this->tableName . '
          SET
            page_template = :page_template,
            page_type = :page_type
        ';
        $tmpId = $this->db->query(
            $sql,
            [
                'page_template' => 'http://foo.com/?foo=1',
                'page_type'     => 'foo\'bar',
            ]
        );
        static::assertTrue($tmpId > 1);
        // select - true
        $result = $this->db->select($this->tableName, 'page_id = ' . (int) $tmpId);
        $tmpPage = $result->fetchObject();
        static::assertSame('http://foo.com/?foo=1', $tmpPage->page_template);
        static::assertSame('foo\'bar', $tmpPage->page_type);

        //
        // query - ok
        //
        $sql = 'INSERT INTO ' . $this->tableName . '
          SET
            page_template = ?,
            page_type = ?
        ';
        $return = $this->db->query(
            $sql,
            [
                true,
                ['test'],
            ]
        );
        static::assertTrue($return > 0);

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
            [
                'tpl_test_new15',
                1,
            ]
        );
        static::assertFalse($return);

        //
        // query - false
        //
        $return = $this->db->query(
            '',
            [
                'tpl_test_new15',
                1,
            ]
        );
        static::assertFalse($return);
    }

    public function testCache()
    {
        $_GET['testCache'] = 1;

        // no-cache
        $sql = 'SELECT * FROM ' . $this->tableName;
        $result = DB::execSQL($sql, false);
        if (\count($result) > 0) {
            $return = true;
        } else {
            $return = false;
        }
        static::assertTrue($return);

        // set cache
        $sql = 'SELECT * FROM ' . $this->tableName;
        $result = DB::execSQL($sql, true);
        if (\count($result) > 0) {
            $return = true;
        } else {
            $return = false;
        }
        static::assertTrue($return);

        $queryCount = $this->db->query_count;

        // use cache
        $sql = 'SELECT * FROM ' . $this->tableName;
        $result = DB::execSQL($sql, true);
        if (\count($result) > 0) {
            $return = true;
        } else {
            $return = false;
        }
        static::assertTrue($return);

        // check cache
        static::assertSame($queryCount, $this->db->query_count);
    }

    public function testQueryErrorHandling()
    {
        $this->db->close();
        static::assertFalse($this->db->isReady());
        $this->invokeMethod(
            $this->db,
            'queryErrorHandling',
            [
                'DB server has gone away',
                2006,
                'SELECT * FROM ' . $this->tableName . ' WHERE page_id = 1',
            ]
        );
        static::assertTrue($this->db->isReady());
    }

    public function testSerializable()
    {
        $dbSerializable = \serialize($this->db);
        $dbTmp = \unserialize($dbSerializable);
        static::assertTrue($dbTmp->isReady());

        // query - true
        $sql = 'INSERT INTO ' . $this->tableName . "
          SET
            page_template = '1.1',
            page_type = '1'
        ";
        $return = $dbTmp->query($sql);
        static::assertTrue($return > 1);
    }

    public function testQuoteString()
    {
        $testArray = [
            'NOW()'                                  => '`NOW()`',
            'fooo'                                   => '`fooo`',
            '`fooo'                                  => '`fooo`',
            'fooo`'                                  => '`fooo`',
            '`fooo`'                                 => '`fooo`',
            '``fooo``'                               => '`fooo`',
            '`fo`oo`'                                => '`fo``oo`',
            '``fooo'                                 => '`fooo`',
            '``fooo`'                                => '`fooo`',
            '\'fooo\''                               => '`\\\'fooo\\\'`',
            123                                      => '`123`',
            'κόσμε'                                  => '`κόσμε`',
            '&lt;abcd&gt;\'$1\'(&quot;&amp;2&quot;)' => '`&lt;abcd&gt;\\\'$1\\\'(&quot;&amp;2&quot;)`',
            '&#246;&#228;&#252;'                     => '`&#246;&#228;&#252;`',
        ];

        foreach ($testArray as $before => $after) {
            static::assertSame($after, $this->db->quote_string($before));
        }
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object     Instantiated object that we will run method on
     * @param string  $methodName Method name to call
     * @param array   $parameters array of parameters to pass into method
     *
     * @return mixed method return
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(\get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function testInstanceOf()
    {
        static::assertInstanceOf('voku\db\DB', DB::getInstance());
    }

    public function testTransaction()
    {
        $tableName = $this->tableName;

        // --------------------

        static::assertTrue(
            $this->db->transact(
                static function (DB $db) use ($tableName) {
                    return $db->insert(
                        $tableName,
                        [
                            'page_template' => '',
                            'page_type'     => 'foo!',
                        ]
                    );
                }
            )
        );

        // --------------------

        static::assertTrue(
            $this->db->transact(
                static function (DB $db) use ($tableName) {
                    return $db->insert(
                        $tableName,
                        [
                            'page_template' => 'page' . \mt_rand(),
                            'page_type'     => 'foo!',
                        ]
                    );
                }
            )
        );

        // --------------------

        static::assertFalse(
            $this->db->transact(
                static function (DB $db) {
                    /** @noinspection ThrowRawExceptionInspection */
                    throw new \Exception();
                }
            )
        );

        // --------------------

        $this->db->beginTransaction(); // (1)
        static::assertFalse(
            $this->db->transact(
                static function (DB $db) {
                    return $db->beginTransaction(); // (2)
                }
            )
        );
        $this->db->endTransaction();

        // --------------------

        static::assertFalse(
            $this->db->transact(
                static function (DB $db) use ($tableName) {
                    return $db->insert(
                        $tableName,
                        [
                            'page_template_noop' => 'page' . \mt_rand(),
                            'page_type'          => 'foo!',
                        ]
                    );
                }
            )
        );

        // --------------------
    }
}
