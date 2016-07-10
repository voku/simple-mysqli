<?php

use voku\db\DB;
use voku\db\Prepare;

/**
 * Class SimplePrepareTest
 */
class SimplePrepareTest extends PHPUnit_Framework_TestCase
{

  /**
   * @var DB
   */
  protected $db;

  /**
   * @var string
   */
  protected $tableName = 'test_page';

  /**
   * @var int
   */
  protected $errorLevelOld;

  public function setUp()
  {
    $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test', 3306, 'utf8', false, false);

    // INFO: we need this because we can't wrap "bind_param()"
    $this->errorLevelOld = error_reporting(E_ERROR);
  }

  public function tearDown()
  {
    error_reporting($this->errorLevelOld);
  }

  public function testInsertError()
  {
    // INFO: "page_template_error" do not exists
    $query = 'INSERT INTO ' . $this->tableName . ' 
      SET 
        page_template_error = ?, 
        page_type = ?
    ';

    $prepare = new Prepare($this->db, $query);

    // -------------

    $template = 'tpl_new_中';
    $type = 'lall';
    $prepare->bind_param('ss', $template, $type);

    $result = $prepare->execute();

    self::assertEquals(false, $result);

    // -------------

    $template = 'tpl_new_中_123';
    $type = 'lall_foo';
    $prepare->bind_param('ss', $template, $type);

    $result = $prepare->execute();

    self::assertEquals(false, $result);
  }

  public function testInsert()
  {
    $query = 'INSERT INTO ' . $this->tableName . ' 
      SET 
        page_template = ?, 
        page_type = ?
    ';

    $prepare = new Prepare($this->db, $query);

    // -------------

    $template = 'tpl_new_中';
    $type = 'lall';
    $prepare->bind_param('ss', $template, $type);

    $result = $prepare->execute();

    self::assertEquals(true, $result);

    // -------------

    $template = 'tpl_new_中_123';
    $type = 'lall_foo';
    $prepare->bind_param('ss', $template, $type);

    $result = $prepare->execute();

    self::assertEquals(true, $result);
  }
}
