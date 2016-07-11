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

    // INFO: we need this because of "bind_param()"
    $this->errorLevelOld = error_reporting(E_ERROR);
  }

  public function tearDown()
  {
    error_reporting($this->errorLevelOld);
  }

  public function testInsertErrorWithBindParamHelper()
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
    $prepare->bind_param_debug('ss', $template, $type);

    $result = $prepare->execute();

    self::assertEquals('Commands out of sync; you can\'t run this command now', $prepare->error);
    self::assertEquals(false, $result);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo';

    self::assertEquals('Commands out of sync; you can\'t run this command now', $prepare->error);
    $result = $prepare->execute();

    self::assertEquals(false, $result);
  }

  public function testInsertErrorWithoutBindParamHelper()
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

    self::assertEquals('Commands out of sync; you can\'t run this command now', $prepare->error);
    self::assertEquals(false, $result);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo';

    self::assertEquals('Commands out of sync; you can\'t run this command now', $prepare->error);
    $result = $prepare->execute();

    self::assertEquals(false, $result);
  }

  public function testInsertWithBindParamHelper_v2()
  {
    $query = 'INSERT INTO ' . $this->tableName . ' 
      SET 
        page_template = ?, 
        page_type = ?
    ';

    $prepare = new Prepare($this->db, $query);

    // -------------

    $template = 123;
    $type = 1.5;

    $prepare->bind_param_debug('id', $template, $type);

    $new_page_id = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $new_page_id));
    $resultSelect = $resultSelect->fetchArray();

    $expectedSql = 'INSERT INTO test_page 
      SET 
        page_template = 123, 
        page_type = 1.50000000
    ';

    self::assertEquals($expectedSql, $prepare->get_sql_with_bound_parameters());
    // INFO: mysql will return strings, but we can
    self::assertEquals(true, $new_page_id === $resultSelect['page_id']);
    self::assertEquals(true, '123' === $resultSelect['page_template']);
    self::assertEquals(true, '1.5' === $resultSelect['page_type']);
  }

  public function testInsertWithBindParamHelper()
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

    $prepare->bind_param_debug('ss', $template, $type);

    $new_page_id = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $new_page_id));
    $resultSelect = $resultSelect->fetchArray();

    $expectedSql = 'INSERT INTO test_page 
      SET 
        page_template = \'tpl_new_中\', 
        page_type = \'lall\'
    ';

    self::assertEquals($expectedSql, $prepare->get_sql_with_bound_parameters());
    self::assertEquals($new_page_id, $resultSelect['page_id']);
    self::assertEquals('tpl_new_中', $resultSelect['page_template']);
    self::assertEquals('lall', $resultSelect['page_type']);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo';

    $new_page_id = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $new_page_id));
    $resultSelect = $resultSelect->fetchArray();

    $expectedSql = 'INSERT INTO test_page 
      SET 
        page_template = \'tpl_new_中_123_?\', 
        page_type = \'lall_foo\'
    ';

    self::assertEquals($expectedSql, $prepare->get_sql_with_bound_parameters());
    self::assertEquals($new_page_id, $resultSelect['page_id']);
    self::assertEquals('tpl_new_中_123_?', $resultSelect['page_template']);
    self::assertEquals('lall_foo', $resultSelect['page_type']);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo';

    $new_page_id = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $new_page_id));
    $resultSelect = $resultSelect->fetchArray();

    $expectedSql = 'INSERT INTO test_page 
      SET 
        page_template = \'tpl_new_中_123_?\', 
        page_type = \'lall_foo\'
    ';

    self::assertEquals($expectedSql, $prepare->get_sql_with_bound_parameters());
    self::assertEquals($new_page_id, $resultSelect['page_id']);
    self::assertEquals('tpl_new_中_123_?', $resultSelect['page_template']);
    self::assertEquals('lall_foo', $resultSelect['page_type']);
  }

  public function testUpdateWithBindParamHelper()
  {
    $query = 'UPDATE ' . $this->tableName . ' 
      SET 
        page_template = ?, 
        page_type = ?
      WHERE page_id = 1
    ';

    $prepare = new Prepare($this->db, $query);

    // -------------

    $template = 'tpl_new_中_update';
    $type = 'lall_update';

    $prepare->bind_param_debug('ss', $template, $type);

    $affected_rows = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $affected_rows));
    $resultSelect = $resultSelect->fetchArray();

    $expectedSql = 'UPDATE test_page 
      SET 
        page_template = \'tpl_new_中_update\', 
        page_type = \'lall_update\'
      WHERE page_id = 1
    ';

    self::assertEquals($expectedSql, $prepare->get_sql_with_bound_parameters());
    self::assertEquals($affected_rows, $resultSelect['page_id']);
    self::assertEquals('tpl_new_中_update', $resultSelect['page_template']);
    self::assertEquals('lall_update', $resultSelect['page_type']);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?_update';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo_update';

    $affected_rows = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $affected_rows));
    $resultSelect = $resultSelect->fetchArray();

    $expectedSql = 'UPDATE test_page 
      SET 
        page_template = \'tpl_new_中_123_?_update\', 
        page_type = \'lall_foo_update\'
      WHERE page_id = 1
    ';

    self::assertEquals($expectedSql, $prepare->get_sql_with_bound_parameters());
    self::assertEquals($affected_rows, $resultSelect['page_id']);
    self::assertEquals('tpl_new_中_123_?_update', $resultSelect['page_template']);
    self::assertEquals('lall_foo_update', $resultSelect['page_type']);

    // -------------
  }

  public function testUpdateWithBindParamHelper_v2()
  {
    $query = 'UPDATE ' . $this->tableName . ' 
      SET 
        page_template = ?, 
        page_type = ?
      WHERE page_type = \'lall_update_fdsfsdfsdfdsfsdfsd_non\' 
    ';

    $prepare = new Prepare($this->db, $query);

    // -------------

    $template = 'tpl_new_中_update';
    $type = 'lall_update';

    $prepare->bind_param_debug('ss', $template, $type);

    $affected_rows = $prepare->execute();

    $expectedSql = 'UPDATE test_page 
      SET 
        page_template = \'tpl_new_中_update\', 
        page_type = \'lall_update\'
      WHERE page_type = \'lall_update_fdsfsdfsdfdsfsdfsd_non\' 
    ';

    self::assertEquals(true, 0 === $affected_rows, 'tested: ' . $affected_rows);
    self::assertEquals($expectedSql, $prepare->get_sql_with_bound_parameters());

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?_update';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo_update';

    $affected_rows = $prepare->execute();

    $expectedSql = 'UPDATE test_page 
      SET 
        page_template = \'tpl_new_中_123_?_update\', 
        page_type = \'lall_foo_update\'
      WHERE page_type = \'lall_update_fdsfsdfsdfdsfsdfsd_non\' 
    ';

    self::assertEquals(true, 0 === $affected_rows);
    self::assertEquals($expectedSql, $prepare->get_sql_with_bound_parameters());

    // -------------
  }

  public function testInsertWithoutBindParamHelper()
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

    $new_page_id = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $new_page_id));
    $resultSelect = $resultSelect->fetchArray();

    self::assertEquals($new_page_id, $resultSelect['page_id']);
    self::assertEquals('tpl_new_中', $resultSelect['page_template']);
    self::assertEquals('lall', $resultSelect['page_type']);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo';

    $new_page_id = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $new_page_id));
    $resultSelect = $resultSelect->fetchArray();

    self::assertEquals($new_page_id, $resultSelect['page_id']);
    self::assertEquals('tpl_new_中_123_?', $resultSelect['page_template']);
    self::assertEquals('lall_foo', $resultSelect['page_type']);
  }
}
