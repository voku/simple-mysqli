<?php

use voku\db\DB;
use voku\db\Result;
use voku\helper\UTF8;

class SimpleMySQLiTest extends PHPUnit_Framework_TestCase
{

  /**
   * @var DB
   */
  protected $db;

  protected $tableName = 'test_page';

  public function setUp()
  {
    $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test', '3306', 'utf8', false, false);
  }

  public function testLogQuery()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true, '', 'debug');
    $this->assertEquals(true, $db_1 instanceof DB);

    // sql - true
    $pageArray = array(
        'page_template' => 'tpl_new',
        'page_type'     => 'lall'
    );
    $tmpId = $db_1->insert($this->tableName, $pageArray);
    $this->assertEquals(true, $tmpId > 0);
  }

  public function testEchoOnError1()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
    $this->assertEquals(true, $db_1 instanceof DB);

    // insert - false
    $false = $db_1->insert($this->tableName, array());
    $this->expectOutputRegex('/(.)*empty-data-for-INSERT(.)*/');
    $this->assertEquals(false, $false);
  }

  public function testEchoOnError4()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
    $this->assertEquals(true, $db_1 instanceof DB);

    // sql - false
    $false = $db_1->query();
    $this->expectOutputRegex('/(.)*SimpleDbTest\.php \/(.)*/');
    $this->assertEquals(false, $false);
  }

  public function testEchoOnError3()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
    $this->assertEquals(true, $db_1 instanceof DB);

    // sql - false
    $false = $db_1->query();
    $this->expectOutputRegex('/<div class="OBJ-mysql-box"(.)*/');
    $this->assertEquals(false, $false);
  }

  public function testEchoOnError2()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, true);
    $this->assertEquals(true, $db_1 instanceof DB);

    // sql - false
    $false = $db_1->query();
    $this->expectOutputRegex('/(.)*Can\'t execute an empty Query(.)*/');
    $this->assertEquals(false, $false);

    // close db-connection
    $this->assertEquals(true, $this->db->isReady());
    $this->db->close();
    $this->assertEquals(false, $this->db->isReady());

    // insert - false
    $false = $db_1->query("INSERT INTO lall SET false=1");
    $this->assertEquals(false, $false);
  }

  /**
   * @expectedException Exception invalid-table-name
   */
  public function testExitOnError1()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', true, false);
    $this->assertEquals(true, $db_1 instanceof DB);

    // insert - false
    $pageArray = array(
        'page_template' => 'tpl_new',
        'page_type'     => 'lall'
    );
    $false = $db_1->insert('', $pageArray);
    $this->assertEquals(false, $false);
  }

  /**
   * @expectedException Exception empty-data-for-INSERT
   */
  public function testExitOnError2()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', true, false);
    $this->assertEquals(true, $db_1 instanceof DB);

    // insert - false
    $false = $db_1->insert($this->tableName, array());
    $this->assertEquals(false, $false);
  }

  public function testGetInstance()
  {
    $db_1 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, false);
    $this->assertEquals(true, $db_1 instanceof DB);

    $db_2 = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', true, false);
    $this->assertEquals(true, $db_2 instanceof DB);

    $db_3 = DB::getInstance('localhost', 'root', '', 'mysql_test', null, '', true, false);
    $this->assertEquals(true, $db_3 instanceof DB);

    $db_4 = DB::getInstance();
    $this->assertEquals(true, $db_4 instanceof DB);
    $db_4_serial = serialize($db_4);
    unset($db_4);
    $db_4 = unserialize($db_4_serial);
    $this->assertEquals(true, $db_4 instanceof DB);

    $true = $this->db->connect();
    $this->assertEquals(true, $true);

    $true = $this->db->connect();
    $this->assertEquals(true, $true);

    $true = $this->db->reconnect(false);
    $this->assertEquals(true, $true);

    $true = $this->db->reconnect(true);
    $this->assertEquals(true, $true);
  }

  /**
   * @expectedException Exception no-sql-hostname
   */
  public function testGetInstanceHostnameException()
  {
    DB::getInstance('', 'root', '', 'mysql_test', '3306', 'utf8', false, false);
  }

  /**
   * @expectedException Exception no-sql-username
   */
  public function testGetInstanceUsernameException()
  {
    DB::getInstance('localhost', '', '', 'mysql_test', '3306', 'utf8', false, false);
  }

  /**
   * @expectedException Exception no-sql-database
   */
  public function testGetInstanceDatabaseException()
  {
    DB::getInstance('localhost', 'root', '', '', '3306', 'utf8', false, false);
  }

  public function testCharset()
  {
    $this->assertEquals('utf8', $this->db->get_charset());
    $return = $this->db->set_charset('utf8');
    $this->assertEquals(true, $return);
    $this->assertEquals('utf8', $this->db->get_charset());
  }

  public function testBasics()
  {
    require_once 'Foobar.php';

    // insert - true
    $pageArray = array(
        'page_template' => 'tpl_new',
        'page_type'     => 'lall'
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);

    // insert - false
    $false = $this->db->insert($this->tableName);
    $this->assertEquals(false, $false);

    // insert - false
    $false = $this->db->insert('', $pageArray);
    $this->assertEquals(false, $false);

    // select - true
    $result = $this->db->select($this->tableName, "page_id = $tmpId");
    $tmpPage = $result->fetchObject();
    $this->assertEquals('tpl_new', $tmpPage->page_template);

    // select - true
    $result = $this->db->select($this->tableName, "page_id = $tmpId");
    $tmpPage = $result->fetchObject('stdClass');
    $this->assertEquals('tpl_new', $tmpPage->page_template);

    // select - true
    $result = $this->db->select($this->tableName, "page_id = $tmpId");
    $tmpPage = $result->fetchObject(
        'Foobar',
        array(
            array(
                'foo' => 1,
                'bar' => 2
            )
        )
    );
    $this->assertEquals(1, $tmpPage->foo);
    $this->assertEquals(2, $tmpPage->bar);
    $this->assertEquals(null, $tmpPage->nothing);
    $this->assertEquals('tpl_new', $tmpPage->page_template);

    $tmpPage = $result->fetchAllObject(
        'Foobar',
        array(
            array(
                'foo' => 1,
                'bar' => 2
            )
        )
    );
    $this->assertEquals(1, $tmpPage[0]->foo);
    $this->assertEquals(2, $tmpPage[0]->bar);
    $this->assertEquals(null, $tmpPage[0]->nothing);
    $this->assertEquals('tpl_new', $tmpPage[0]->page_template);

    $tmpPage = $result->fetchAllObject(
        'Foobar'
    );
    $this->assertEquals(null, $tmpPage[0]->foo);
    $this->assertEquals(null, $tmpPage[0]->bar);
    $this->assertEquals('lall', $tmpPage[0]->page_type);
    $this->assertEquals('tpl_new', $tmpPage[0]->page_template);

    // update - true
    $pageArray = array(
        'page_template' => 'tpl_update'
    );
    $this->db->update($this->tableName, $pageArray, "page_id = $tmpId");

    // update - false
    $false = $this->db->update($this->tableName, array(), "page_id = $tmpId");
    $this->assertEquals(false, $false);

    // update - false
    $false = $this->db->update($this->tableName, $pageArray, "");
    $this->assertEquals(false, $false);

    // update - false
    $false = $this->db->update($this->tableName, $pageArray, null);
    $this->assertEquals(false, $false);

    // update - false
    $false = $this->db->update('', $pageArray, "page_id = $tmpId");
    $this->assertEquals(false, $false);

    // check (select)
    $result = $this->db->select($this->tableName, "page_id = $tmpId");
    $tmpPage = $result->fetchAllObject();
    $this->assertEquals('tpl_update', $tmpPage[0]->page_template);

    // replace - true
    $data = array(
        'page_id'       => 2,
        'page_template' => 'tpl_test',
        'page_type'     => 'öäü123'
    );
    $tmpId = $this->db->replace($this->tableName, $data);

    // replace - false
    $false = $this->db->replace($this->tableName);
    $this->assertEquals(false, $false);

    // replace - false
    $false = $this->db->replace('', $data);
    $this->assertEquals(false, $false);

    $result = $this->db->select($this->tableName, "page_id = $tmpId");
    $tmpPage = $result->fetchAllObject();
    $this->assertEquals('tpl_test', $tmpPage[0]->page_template);

    // delete - true
    $deleteId = $this->db->delete($this->tableName, array('page_id' => $tmpId));
    $this->assertEquals(1, $deleteId);

    // delete - false
    $false = $this->db->delete('', array('page_id' => $tmpId));
    $this->assertEquals(false, $false);

    // insert - true
    $pageArray = array(
        'page_template' => 'tpl_new',
        'page_type'     => 'lall'
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);

    // delete - false
    $false = $this->db->delete($this->tableName, "");
    $this->assertEquals(false, $false);

    // delete - false
    $false = $this->db->delete($this->tableName, null);
    $this->assertEquals(false, $false);

    // delete - true
    $false = $this->db->delete($this->tableName, "page_id = " . $this->db->escape($tmpId));
    $this->assertEquals(true, $false);

    // select - true
    $result = $this->db->select($this->tableName, array('page_id' => 2));
    $this->assertEquals(0, $result->num_rows);

    // select - true
    $result = $this->db->select($this->tableName);
    $this->assertEquals(true, $result->num_rows > 0);

    // select - false
    $false = $this->db->select($this->tableName, null);
    $this->assertEquals(false, $false);

    // select - false
    $false = $this->db->select('', array('page_id' => 2));
    $this->assertEquals(false, $false);
  }

  public function testQry()
  {
    $result = $this->db->qry(
        "UPDATE " . $this->db->escape($this->tableName) . "
      SET
        page_template = 'tpl_test'
      WHERE page_id = ?
    ", 1
    );
    $this->assertEquals(1, ($result));

    $result = $this->db->qry(
        "SELECT * FROM " . $this->db->escape($this->tableName) . "
      WHERE page_id = 1
    "
    );
    $this->assertEquals('tpl_test', ($result[0]['page_template']));

    $result = $this->db->qry(
        "SELECT * FROM " . $this->db->escape($this->tableName) . "
      WHERE page_id_lall = 1
    "
    );
    $this->assertEquals(false, $result);
  }

  public function testEscape()
  {
    $this->db = DB::getInstance();

    $data = array(
        'page_template' => "tpl_test_'new2",
        'page_type'     => 1.1
    );

    $newData = $this->db->escape($data);

    $this->assertEquals("tpl_test_\'new2", $newData['page_template']);
    $this->assertEquals(1.10000000, $newData['page_type']);
  }

  public function testConnector()
  {
    $data = array(
        'page_template' => 'tpl_test_new'
    );
    $where = array(
        'page_id LIKE' => '1'
    );

    // will return the number of effected rows
    $resultUpdate = $this->db->update($this->tableName, $data, $where);
    $this->assertEquals(1, $resultUpdate);

    $data = array(
        'page_template' => 'tpl_test_new2',
        'page_type'     => 'öäü'
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    $this->assertGreaterThan(1, $resultInsert);

    $where = array(
        'page_type ='        => 'öäü',
        'page_type NOT LIKE' => '%öäü123',
        'page_id ='          => $resultInsert,
    );

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchArray();
    $this->assertEquals('öäü', $resultSelectArray['page_type']);

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
    $this->assertEquals('öäü', $resultSelectArray['öäü']);

    $where = array(
        'page_type LIKE'     => 'öäü',
        'page_type NOT LIKE' => 'öäü123',
        'page_id ='          => $resultInsert,
    );

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetch();
    $getDefaultResultType = $resultSelect->getDefaultResultType();
    $this->assertEquals('object', $getDefaultResultType);
    $this->assertEquals('öäü', $resultSelectArray->page_type);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelect->setDefaultResultType('array'); // switch default result-type
    $resultSelectArray = $resultSelect->fetch();
    $getDefaultResultType = $resultSelect->getDefaultResultType();
    $this->assertEquals('array', $getDefaultResultType);
    $this->assertEquals('öäü', $resultSelectArray['page_type']);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchArray();
    $this->assertEquals('öäü', $resultSelectArray['page_type']);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->get();
    $this->assertEquals('öäü', $resultSelectArray->page_type);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->getAll();
    $this->assertEquals('öäü', $resultSelectArray[0]->page_type);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->getArray();
    $this->assertEquals('öäü', $resultSelectArray[0]['page_type']);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->getObject();
    $this->assertEquals('öäü', $resultSelectArray[0]->page_type);

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectTmp = $resultSelect->getColumn('page_type');
    $this->assertEquals('öäü', $resultSelectTmp);

    $resultSelect = $this->db->select($this->tableName, $where);
    $this->assertEquals(1, (string)$resultSelect);
  }

  public function testTransactionFalse()
  {

    $data = array(
        'page_template' => 'tpl_test_new3',
        'page_type'     => 'öäü'
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    $this->assertGreaterThan(1, $resultInsert);

    // start - test a transaction - true
    $beginTransaction = $this->db->beginTransaction();
    $this->assertEquals(true, $beginTransaction);

    $data = array(
        'page_type' => 'lall'
    );
    $where = array(
        'page_id' => $resultInsert
    );
    $this->db->update($this->tableName, $data, $where);

    $data = array(
        'page_type' => 'lall',
        'page_lall' => 'öäü'
        // this will produce a mysql-error and a mysqli-rollback
    );
    $where = array(
        'page_id' => $resultInsert
    );
    $this->db->update($this->tableName, $data, $where);

    // end - test a transaction
    $this->db->endTransaction();

    $where = array(
        'page_id' => $resultInsert,
    );

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchAllArray();
    $this->assertEquals('öäü', $resultSelectArray[0]['page_type']);
  }

  public function testGetErrors()
  {
    // INFO: run all previous tests and generate some errors

    $error = $this->db->lastError();
    $this->assertEquals(true, is_string($error));
    $this->assertContains('Unknown column \'page_lall\' in \'field list', $error);

    $errors = $this->db->getErrors();
    $this->assertEquals(true, is_array($errors));
    $this->assertContains('Unknown column \'page_lall\' in \'field list', $errors[0]);
  }

  public function testTransactionTrue()
  {

    $data = array(
        'page_template' => 'tpl_test_new3',
        'page_type'     => 'öäü'
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    $this->assertGreaterThan(1, $resultInsert);

    // start - test a transaction
    $this->db->startTransaction();

    $data = array(
        'page_type' => 'lall'
    );
    $where = array(
        'page_id' => $resultInsert
    );
    $this->db->update($this->tableName, $data, $where);

    $data = array(
        'page_type'     => 'lall',
        'page_template' => 'öäü'
    );
    $where = array(
        'page_id' => $resultInsert
    );
    $this->db->update($this->tableName, $data, $where);

    // end - test a transaction
    $this->db->endTransaction();

    $where = array(
        'page_id' => $resultInsert,
    );

    $resultSelect = $this->db->select($this->tableName, $where);
    $resultSelectArray = $resultSelect->fetchAllArray();
    $this->assertEquals('lall', $resultSelectArray[0]['page_type']);
  }

  public function testRollback()
  {
    // start - test a transaction
    $this->db->beginTransaction();

    $data = array(
        'page_template' => 'tpl_test_new4',
        'page_type'     => 'öäü'
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    $this->assertGreaterThan(1, $resultInsert);

    $data = array(
        'page_type' => 'lall'
    );
    $where = array(
        'page_id' => $resultInsert
    );
    $this->db->update($this->tableName, $data, $where);

    $data = array(
        'page_type' => 'lall',
        'page_lall' => 'öäü'
        // this will produce a mysql-error and a mysqli-rollback
    );
    $where = array(
        'page_id' => $resultInsert
    );
    $this->db->update($this->tableName, $data, $where);

    // end - test a transaction, with a rollback!
    $this->db->rollback();

    $where = array(
        'page_id' => $resultInsert,
    );
    $resultSelect = $this->db->select($this->tableName, $where);
    $this->assertEquals(0, $resultSelect->num_rows);
  }

  /**
   * @expectedException Exception
   * @expectedExceptionMessage Error mysql server already in transaction!
   */
  public function testTransactionException()
  {
    // start - test a transaction - true
    $beginTransaction = $this->db->beginTransaction();
    $this->assertEquals(true, $beginTransaction);

    // start - test a transaction - false
    $beginTransaction = $this->db->beginTransaction();
    $this->assertEquals(false, $beginTransaction);
  }

  public function testFetchColumn()
  {
    $data = array(
        'page_template' => 'tpl_test_new5',
        'page_type'     => 'öäü'
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    $this->assertGreaterThan(1, $resultInsert);

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $resultInsert));
    $columnResult = $resultSelect->fetchColumn('page_template');
    $this->assertEquals('tpl_test_new5', $columnResult);
  }

  public function testJson()
  {
    $data = array(
        'page_template' => 'tpl_test_new6',
        'page_type'     => 'öäü'
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    $this->assertGreaterThan(1, $resultInsert);

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $resultInsert));
    $columnResult = $resultSelect->json();
    $columnResultDecode = json_decode($columnResult, true);
    $this->assertEquals('tpl_test_new6', $columnResultDecode[0]['page_template']);
  }

  public function testFetchObject()
  {
    $data = array(
        'page_template' => 'tpl_test_new7',
        'page_type'     => 'öäü'
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    $this->assertGreaterThan(1, $resultInsert);

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $resultInsert));
    $columnResult = $resultSelect->fetchObject();
    $this->assertEquals('tpl_test_new7', $columnResult->page_template);
  }

  public function testDefaultResultType()
  {
    $data = array(
        'page_template' => 'tpl_test_new8',
        'page_type'     => 'öäü'
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    $this->assertGreaterThan(1, $resultInsert);

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $resultInsert));

    // array
    $resultSelect->setDefaultResultType('array');

    $columnResult = $resultSelect->fetch(true);
    $this->assertEquals('tpl_test_new8', $columnResult['page_template']);

    $columnResult = $resultSelect->fetchAll();
    $this->assertEquals('tpl_test_new8', $columnResult[0]['page_template']);

    $columnResult = $resultSelect->fetchAllArray();
    $this->assertEquals('tpl_test_new8', $columnResult[0]['page_template']);

    // object
    $resultSelect->setDefaultResultType('object');

    $columnResult = $resultSelect->fetch(true);
    $this->assertEquals('tpl_test_new8', $columnResult->page_template);

    $columnResult = $resultSelect->fetchAll();
    $this->assertEquals('tpl_test_new8', $columnResult[0]->page_template);

    $columnResult = $resultSelect->fetchAllObject();
    $this->assertEquals('tpl_test_new8', $columnResult[0]->page_template);
  }

  public function testGetAllTables()
  {
    $tableArray = $this->db->getAllTables();

    $return = false;
    foreach ($tableArray as $table) {
      if (in_array($this->tableName, $table) === true) {
        $return = true;
        break;
      }
    }

    $this->assertEquals(true, $return);
  }

  public function testPing()
  {
    $ping = $this->db->ping();
    $this->assertEquals(true, $ping);
  }

  public function testMultiQuery()
  {
    $sql = "
    INSERT INTO " . $this->tableName . "
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
    $this->assertEquals(true, $result);

    $sql = "
    SELECT * FROM " . $this->tableName . ";
    SELECT * FROM " . $this->tableName . ";
    ";
    // multi_query - true
    $result = $this->db->multi_query($sql);
    $this->assertEquals(true, is_array($result));
    foreach ($result as $resultForEach) {
      /* @var $resultForEach Result */
      $tmpArray = $resultForEach->fetchArray();

      $this->assertEquals(true, is_array($tmpArray));
      $this->assertEquals(true, count($tmpArray) > 0);
    }

    // multi_query - false
    $false = $this->db->multi_query('');
    $this->assertEquals(false, $false);
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
            '99999'
        ),
        'page_id NOT IN'      => array(
            'test',
            'test123'
        ),
        'page_type IN'        => array(
            'öäü',
            '123',
            'abc'
        )
    );
    $resultSelect = $this->db->select($this->tableName, $where);
    $this->assertNotEquals(false, $resultSelect);
    $this->assertEquals(true, ($resultSelect->num_rows > 0));

    // select - false
    $where = array(
        'page_type IS NOT' => 'lall',
        'page_type IS'     => 'öäü'
    );
    $resultSelect = $this->db->select($this->tableName, $where);
    $this->assertEquals(false, $resultSelect);
  }

  public function testExecSQL()
  {
    // execSQL - false
    $sql = "INSERT INTO " . $this->tableName . "
      SET
        page_template_lall = '" . $this->db->escape('tpl_test_new7') . "',
        page_type = " . $this->db->secure('öäü') . "
    ";
    $return = $this->db->execSQL($sql);
    $this->assertEquals(false, $return);

    // execSQL - true
    $sql = "INSERT INTO " . $this->tableName . "
      SET
        page_template = '" . $this->db->escape('tpl_test_new7') . "',
        page_type = " . $this->db->secure('öäü') . "
    ";
    $return = $this->db->execSQL($sql);
    $this->assertEquals(true, is_int($return));
    $this->assertEquals(true, $return > 0);
  }

  public function testUtf8Query()
  {
    $sql = "INSERT INTO " . $this->tableName . "
      SET
        page_template = '" . $this->db->escape(UTF8::urldecode('D%26%23xFC%3Bsseldorf')) . "',
        page_type = '" . UTF8::urldecode('DÃ¼sseldorf') . "'
    ";
    $return = $this->db->execSQL($sql);
    $this->assertEquals(true, is_int($return));
    $this->assertEquals(true, $return > 0);

    $data = $this->db->select($this->tableName, 'page_id=' . (int)$return);
    $dataArray = $data->fetchArray();
    $this->assertEquals('Düsseldorf', $dataArray['page_template']);
    $this->assertEquals('Düsseldorf', $dataArray['page_type']);
  }

  public function testQuery()
  {
    // query - true
    $sql = "INSERT INTO " . $this->tableName . "
      SET
        page_template = ?,
        page_type = ?
    ";
    $return = $this->db->query(
        $sql, array(
            1.1,
            1
        )
    );
    $this->assertEquals(true, $return);

    // query - true
    $sql = "INSERT INTO " . $this->tableName . "
      SET
        page_template = ?,
        page_type = ?
    ";
    $tmpDate = new DateTime();
    $tmpId = $this->db->query(
        $sql, array(
            'dateTest',
            $tmpDate
        )
    );
    $this->assertEquals(true, $tmpId);

    // select - true
    $result = $this->db->select($this->tableName, "page_id = $tmpId");
    $tmpPage = $result->fetchObject();
    $this->assertEquals($tmpDate->format('Y-m-d H:i:s'), $tmpPage->page_type);

    // select - false
    $result = new Result();
    $tmpPage = $result->fetchObject();
    $this->assertEquals(false, $tmpPage);
    $tmpPage = $result->fetch();
    $this->assertEquals(false, $tmpPage);
    $tmpPage = $result->fetchArray();
    $this->assertEquals(false, $tmpPage);

    // query - false
    $sql = "INSERT INTO " . $this->tableName . "
      SET
        page_template = ?,
        page_type = ?
    ";
    $return = $this->db->query(
        $sql, array(
            true,
            array('test')
        )
    );
    // array('test') => null
    $this->assertEquals(false, $return);

    // query - false
    $sql = "INSERT INTO " . $this->tableName . "
      SET
        page_template_lall = ?,
        page_type = ?
    ";
    $return = $this->db->query(
        $sql, array(
            'tpl_test_new15',
            1
        )
    );
    $this->assertEquals(false, $return);

    // query - false
    $return = $this->db->query(
        '', array(
            'tpl_test_new15',
            1
        )
    );
    $this->assertEquals(false, $return);
  }

  public function testCache()
  {
    $_GET['testCache'] = 1;

    // no-cache
    $sql = "SELECT * FROM " . $this->tableName;
    $result = $this->db->execSQL($sql, false);
    if (count($result) > 0) {
      $return = true;
    } else {
      $return = false;
    }
    $this->assertEquals(true, $return);

    // set cache
    $sql = "SELECT * FROM " . $this->tableName;
    $result = $this->db->execSQL($sql, true);
    if (count($result) > 0) {
      $return = true;
    } else {
      $return = false;
    }
    $this->assertEquals(true, $return);

    $queryCount = $this->db->query_count;

    // use cache
    $sql = "SELECT * FROM " . $this->tableName;
    $result = $this->db->execSQL($sql, true);
    if (count($result) > 0) {
      $return = true;
    } else {
      $return = false;
    }
    $this->assertEquals(true, $return);

    // check cache
    $this->assertEquals($queryCount, $this->db->query_count);
  }

  public function testQueryErrorHandling()
  {
    $this->db->close();
    $this->assertEquals(false, $this->db->isReady());
    $this->invokeMethod(
        $this->db, "queryErrorHandling",
        array(
            "DB server has gone away",
            "SELECT * FROM " . $this->tableName . " WHERE page_id = 1"
        )
    );
    $this->assertEquals(true, $this->db->isReady());
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
    $this->assertInstanceOf('voku\db\DB', DB::getInstance());
  }
}
