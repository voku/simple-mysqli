<?php

use voku\db\DB;

class SimpleMySQLiTest extends PHPUnit_Framework_TestCase {

  /**
   * @var DB
   */
  public $db;

  public $tableName = 'test_page';

  public function __construct() {
    $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test');
  }

  function simple_test() {

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
    $tmpPage = $result->fetchObject();
    $this->assertEquals('tpl_update', $tmpPage->page_template);
  }

}
