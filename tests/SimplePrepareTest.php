<?php

declare(strict_types=1);

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

    self::assertSame('Commands out of sync; you can\'t run this command now', $prepare->error);
    self::assertFalse($result);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo';

    self::assertSame('Commands out of sync; you can\'t run this command now', $prepare->error);
    $result = $prepare->execute();

    self::assertFalse($result);
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

    self::assertSame('Commands out of sync; you can\'t run this command now', $prepare->error);
    self::assertFalse($result);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo';

    self::assertSame('Commands out of sync; you can\'t run this command now', $prepare->error);
    $result = $prepare->execute();

    self::assertFalse($result);
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
    $result = $resultSelect->fetchArray();

    $expectedSql = 'INSERT INTO test_page 
      SET 
        page_template = \'tpl_new_中\', 
        page_type = \'lall\'
    ';

    self::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
    self::assertSame($new_page_id, $result['page_id']);
    self::assertSame('tpl_new_中', $result['page_template']);
    self::assertSame('lall', $result['page_type']);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo';

    $new_page_id = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $new_page_id));
    $result = $resultSelect->fetchArray();

    $expectedSql = 'INSERT INTO test_page 
      SET 
        page_template = \'tpl_new_中_123_?\', 
        page_type = \'lall_foo\'
    ';

    self::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
    self::assertSame($new_page_id, $result['page_id']);
    self::assertSame('tpl_new_中_123_?', $result['page_template']);
    self::assertSame('lall_foo', $result['page_type']);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo';

    $new_page_id = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $new_page_id));
    $result = $resultSelect->fetchArray();

    $expectedSql = 'INSERT INTO test_page 
      SET 
        page_template = \'tpl_new_中_123_?\', 
        page_type = \'lall_foo\'
    ';

    self::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
    self::assertSame($new_page_id, $result['page_id']);
    self::assertSame('tpl_new_中_123_?', $result['page_template']);
    self::assertSame('lall_foo', $result['page_type']);
  }

  public function testSelectWithBindParamHelper()
  {
    $data = array(
        'page_template' => 'tpl_test_new123123',
        'page_type'     => 'ö\'ä"ü',
    );

    // will return the auto-increment value of the new row
    $resultInsert[0] = $this->db->insert($this->tableName, $data);
    $resultInsert[1] = $this->db->insert($this->tableName, $data);

    // -------------

    $sql = 'SELECT * FROM ' . $this->tableName . ' 
      WHERE page_id = ?
    ';

    $prepare = $this->db->prepare($sql);

    // -------------

    $page_id = 0;
    $prepare->bind_param_debug('i', $page_id);

    // -------------

    $page_id = $resultInsert[0];
    $result = $prepare->execute();
    $data = $result->fetchArray();

    self::assertSame($page_id, $data['page_id']);
    self::assertSame('tpl_test_new123123', $data['page_template']);

    // -------------

    $page_id = $resultInsert[1];
    $result = $prepare->execute();
    $data = $result->fetchArray();

    self::assertSame($page_id, $data['page_id']);
    self::assertSame('tpl_test_new123123', $data['page_template']);
  }

  public function testSelectWithBindParam()
  {
    $data = array(
        'page_template' => 'tpl_test_new123123',
        'page_type'     => 'ö\'ä"ü',
    );

    // will return the auto-increment value of the new row
    $resultInsert[0] = $this->db->insert($this->tableName, $data);
    $resultInsert[1] = $this->db->insert($this->tableName, $data);

    // -------------

    $sql = 'SELECT * FROM ' . $this->tableName . ' 
      WHERE page_id = ?
    ';

    $prepare = $this->db->prepare($sql);

    // -------------

    $page_id = 0;
    $prepare->bind_param('i', $page_id);

    // -------------

    $page_id = $resultInsert[0];
    $result = $prepare->execute();
    $data = $result->fetchArray();

    self::assertSame($page_id, $data['page_id']);
    self::assertSame('tpl_test_new123123', $data['page_template']);

    // -------------

    $page_id = $resultInsert[1];
    $result = $prepare->execute();
    $data = $result->fetchArray();

    self::assertSame($page_id, $data['page_id']);
    self::assertSame('tpl_test_new123123', $data['page_template']);
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
    $result = $resultSelect->fetchArray();

    $expectedSql = 'INSERT INTO test_page 
      SET 
        page_template = 123, 
        page_type = 1.5
    ';

    self::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
    // INFO: mysql will return strings, but we can
    self::assertSame($new_page_id, $result['page_id']);
    self::assertSame('123', $result['page_template']);
    self::assertSame('1.5', $result['page_type']);
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
    $result = $resultSelect->fetchArray();

    self::assertSame($new_page_id, $result['page_id']);
    self::assertSame('tpl_new_中', $result['page_template']);
    self::assertSame('lall', $result['page_type']);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo';

    $new_page_id = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $new_page_id));
    $result = $resultSelect->fetchArray();

    self::assertSame($new_page_id, $result['page_id']);
    self::assertSame('tpl_new_中_123_?', $result['page_template']);
    self::assertSame('lall_foo', $result['page_type']);
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
    $result = $resultSelect->fetchArray();

    $expectedSql = 'UPDATE test_page 
      SET 
        page_template = \'tpl_new_中_update\', 
        page_type = \'lall_update\'
      WHERE page_id = 1
    ';

    self::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
    self::assertSame($affected_rows, $result['page_id']);
    self::assertSame('tpl_new_中_update', $result['page_template']);
    self::assertSame('lall_update', $result['page_type']);

    // -------------

    // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $template = 'tpl_new_中_123_?_update';
    /** @noinspection PhpUnusedLocalVariableInspection */
    $type = 'lall_foo_update';

    $affected_rows = $prepare->execute();

    $resultSelect = $this->db->select($this->tableName, array('page_id' => $affected_rows));
    $result = $resultSelect->fetchArray();

    $expectedSql = 'UPDATE test_page 
      SET 
        page_template = \'tpl_new_中_123_?_update\', 
        page_type = \'lall_foo_update\'
      WHERE page_id = 1
    ';

    self::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
    self::assertSame($affected_rows, $result['page_id']);
    self::assertSame('tpl_new_中_123_?_update', $result['page_template']);
    self::assertSame('lall_foo_update', $result['page_type']);

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

    self::assertSame(0, $affected_rows, 'tested: ' . $affected_rows);
    self::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());

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

    self::assertSame(0, $affected_rows);
    self::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());

    // -------------
  }
}
