<?php

use voku\db\DB;
use voku\db\Helper;

/**
 * Class SimpleHelperTest
 */
class SimpleHelperTest extends PHPUnit_Framework_TestCase
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

  public function testGetDbFields()
  {
    // don't use static cache

    $dbFields = Helper::getDbFields($this->tableName, false);

    self::assertEquals(
        array(
            0 => 'page_id',
            1 => 'page_template',
            2 => 'page_type',
        ),
        $dbFields
    );

    // fill static cache

    $dbFields = Helper::getDbFields($this->tableName, true);

    self::assertEquals(
        array(
            0 => 'page_id',
            1 => 'page_template',
            2 => 'page_type',
        ),
        $dbFields
    );

    // test static-cache

    $dbFields = Helper::getDbFields($this->tableName, true);

    self::assertEquals(
        array(
            0 => 'page_id',
            1 => 'page_template',
            2 => 'page_type',
        ),
        $dbFields
    );
  }

  public function testLall()
  {

    $data = array(
        'page_template' => 'tpl_test_new5',
        'page_type'     => 'öäü',
    );

    // will return the auto-increment value of the new row
    $resultInsert = $this->db->insert($this->tableName, $data);
    self::assertGreaterThan(1, $resultInsert);

    // ------------------------------

    // where
    $whereArray = array(
        'page_id' => $resultInsert,
    );

    // change column
    $updateArray = array();

    // change column
    $updateArray['page_template'] = 'tpl_test_new6';

    // auto increment column
    $ignoreArray = array(
        'page_id',
    );

    $new_page_id = Helper::copyTableRow($this->tableName, $whereArray, $updateArray, $ignoreArray);

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $new_page_id));
    $resultSelect = $resultSelect->fetchArray();
    self::assertEquals(
        array(
            'page_id'       => $new_page_id,
            'page_template' => 'tpl_test_new6',
            'page_type'     => 'öäü',
        ),
        $resultSelect
    );
  }
}
