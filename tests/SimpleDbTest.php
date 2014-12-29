<?php

use voku\db\DB;

class SimpleMySQLiTest extends PHPUnit_Framework_TestCase
{

  /**
   * @var DB
   */
  public $db;

  public $tableName = 'test_page';

  public function __construct()
  {
    $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test', '', '', false, false);
  }

  function testBasics()
  {

    // insert
    $pageArray = array(
        'page_template' => 'tpl_new',
        'page_type'     => 'lall'
    );
    $tmpId = $this->db->insert($this->tableName, $pageArray);

    // check (select)
    $result = $this->db->select($this->tableName, "page_id = $tmpId");
    $tmpPage = $result->fetchObject();
    $this->assertEquals('tpl_new', $tmpPage->page_template);

    // update
    $pageArray = array(
        'page_template' => 'tpl_update'
    );
    $this->db->update($this->tableName, $pageArray, "page_id = $tmpId");

    // check (select)
    $result = $this->db->select($this->tableName, "page_id = $tmpId");
    $tmpPage = $result->fetchAllObject();
    $this->assertEquals('tpl_update', $tmpPage[0]->page_template);

    $data = array(
        'page_id'       => 2,
        'page_template' => 'tpl_test',
        'page_type'     => 'öäü123'
    );
    $tmpId = $this->db->replace($this->tableName, $data);

    $result = $this->db->select($this->tableName, "page_id = $tmpId");
    $tmpPage = $result->fetchAllObject();
    $this->assertEquals('tpl_test', $tmpPage[0]->page_template);

    $deleteId = $this->db->delete($this->tableName, array('page_id' => $tmpId));
    $this->assertEquals(1, $deleteId);

    $result = $this->db->select($this->tableName, array('page_id' => 2));
    $this->assertEquals(0, $result->num_rows);
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
    $resultSelectArray = $resultSelect->fetchArray();
    $this->assertEquals('öäü', $resultSelectArray['page_type']);
  }

  public function testTransaction()
  {

    $data = array(
        'page_template' => 'tpl_test_new3',
        'page_type'     => 'öäü'
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    $this->assertGreaterThan(1, $resultInsert);

    // start - test a transaction
    $this->db->beginTransaction();

    $data = array(
        'page_type' => 'lall'
    );
    $where = array(
        'page_id' => $resultInsert
    );
    $this->db->update($this->tableName, $data, $where);

    $data = array(
        'page_type' => 'lall',
        'page_lall' => 'öäü'        // this will produce a mysql-error and a mysqli-rollback
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
        'page_lall' => 'öäü'        // this will produce a mysql-error and a mysqli-rollback
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
    $columnResult = $resultSelect->fetchColumn('page_type');
    $this->assertEquals('öäü', $columnResult[0]);
  }

}
