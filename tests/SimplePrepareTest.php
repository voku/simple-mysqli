<?php

declare(strict_types=1);

use voku\db\DB;
use voku\db\Prepare;

/**
 * Class SimplePrepareTest
 *
 * @internal
 */
final class SimplePrepareTest extends \PHPUnit\Framework\TestCase
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

    protected function setUp()
    {
        $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test', 3306, 'utf8', false, false);

        // INFO: we need this because of "bind_param()"
        $this->errorLevelOld = \error_reporting(\E_ERROR);
    }

    protected function tearDown()
    {
        \error_reporting($this->errorLevelOld);
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

        static::assertSame('Commands out of sync; you can\'t run this command now', $prepare->error);
        static::assertFalse($result);

        // -------------

        // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
        /** @noinspection PhpUnusedLocalVariableInspection */
        $template = 'tpl_new_中_123_?';
        /** @noinspection PhpUnusedLocalVariableInspection */
        $type = 'lall_foo';

        static::assertSame('Commands out of sync; you can\'t run this command now', $prepare->error);
        $result = $prepare->execute();

        static::assertFalse($result);
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

        static::assertSame('Commands out of sync; you can\'t run this command now', $prepare->error);
        static::assertFalse($result);

        // -------------

        // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
        /** @noinspection PhpUnusedLocalVariableInspection */
        $template = 'tpl_new_中_123_?';
        /** @noinspection PhpUnusedLocalVariableInspection */
        $type = 'lall_foo';

        static::assertSame('Commands out of sync; you can\'t run this command now', $prepare->error);
        $result = $prepare->execute();

        static::assertFalse($result);
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

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $new_page_id]);
        $result = $resultSelect->fetchArray();

        $expectedSql = 'INSERT INTO test_page 
          SET 
            page_template = \'tpl_new_中\', 
            page_type = \'lall\'
        ';

        static::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
        static::assertSame($new_page_id, $result['page_id']);
        static::assertSame('tpl_new_中', $result['page_template']);
        static::assertSame('lall', $result['page_type']);

        // -------------

        // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
        /** @noinspection PhpUnusedLocalVariableInspection */
        $template = 'tpl_new_中_123_?';
        /** @noinspection PhpUnusedLocalVariableInspection */
        $type = 'lall_foo';

        $new_page_id = $prepare->execute();

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $new_page_id]);
        $result = $resultSelect->fetchArray();

        $expectedSql = 'INSERT INTO test_page 
          SET 
            page_template = \'tpl_new_中_123_?\', 
            page_type = \'lall_foo\'
        ';

        static::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
        static::assertSame($new_page_id, $result['page_id']);
        static::assertSame('tpl_new_中_123_?', $result['page_template']);
        static::assertSame('lall_foo', $result['page_type']);

        // -------------

        // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
        /** @noinspection PhpUnusedLocalVariableInspection */
        $template = 'tpl_new_中_123_?';
        /** @noinspection PhpUnusedLocalVariableInspection */
        $type = 'lall_foo';

        $new_page_id = $prepare->execute();

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $new_page_id]);
        $result = $resultSelect->fetchArray();

        $expectedSql = 'INSERT INTO test_page 
          SET 
            page_template = \'tpl_new_中_123_?\', 
            page_type = \'lall_foo\'
        ';

        static::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
        static::assertSame($new_page_id, $result['page_id']);
        static::assertSame('tpl_new_中_123_?', $result['page_template']);
        static::assertSame('lall_foo', $result['page_type']);
    }

    public function testSelectWithBindParamHelperNoStringQuery()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$query was no string: NULL');

        (new Prepare($this->db, ''))->prepare(null);
    }

    public function testSelectWithBindParamHelper()
    {
        $data = [
            'page_template' => 'tpl_test_new123123',
            'page_type'     => 'ö\'ä"ü',
        ];

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

        static::assertSame($page_id, $data['page_id']);
        static::assertSame('tpl_test_new123123', $data['page_template']);

        // -------------

        $page_id = $resultInsert[1];
        $result = $prepare->execute();
        $data = $result->fetchArray();

        static::assertSame($page_id, $data['page_id']);
        static::assertSame('tpl_test_new123123', $data['page_template']);
    }

    public function testSelectWithBindParam()
    {
        $data = [
            'page_template' => 'tpl_test_new123123',
            'page_type'     => 'ö\'ä"ü',
        ];

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

        static::assertSame($page_id, $data['page_id']);
        static::assertSame('tpl_test_new123123', $data['page_template']);

        // -------------

        $page_id = $resultInsert[1];
        $result = $prepare->execute();
        $data = $result->fetchArray();

        static::assertSame($page_id, $data['page_id']);
        static::assertSame('tpl_test_new123123', $data['page_template']);
    }

    public function testInsertWithBindParamHelperV2()
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

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $new_page_id]);
        $result = $resultSelect->fetchArray();

        $expectedSql = 'INSERT INTO test_page 
          SET 
            page_template = 123, 
            page_type = 1.5
        ';

        static::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
        // INFO: mysql will return strings, but we can
        static::assertSame($new_page_id, $result['page_id']);
        static::assertSame('123', $result['page_template']);
        static::assertSame('1.5', $result['page_type']);
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

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $new_page_id]);
        $result = $resultSelect->fetchArray();

        static::assertSame($new_page_id, $result['page_id']);
        static::assertSame('tpl_new_中', $result['page_template']);
        static::assertSame('lall', $result['page_type']);

        // -------------

        // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
        /** @noinspection PhpUnusedLocalVariableInspection */
        $template = 'tpl_new_中_123_?';
        /** @noinspection PhpUnusedLocalVariableInspection */
        $type = 'lall_foo';

        $new_page_id = $prepare->execute();

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $new_page_id]);
        $result = $resultSelect->fetchArray();

        static::assertSame($new_page_id, $result['page_id']);
        static::assertSame('tpl_new_中_123_?', $result['page_template']);
        static::assertSame('lall_foo', $result['page_type']);
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

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $affected_rows]);
        $result = $resultSelect->fetchArray();

        $expectedSql = 'UPDATE test_page 
          SET 
            page_template = \'tpl_new_中_update\', 
            page_type = \'lall_update\'
          WHERE page_id = 1
        ';

        static::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
        static::assertSame($affected_rows, $result['page_id']);
        static::assertSame('tpl_new_中_update', $result['page_template']);
        static::assertSame('lall_update', $result['page_type']);

        // -------------

        // INFO: "$template" and "$type" are references, since we use "bind_param_debug"
        /** @noinspection PhpUnusedLocalVariableInspection */
        $template = 'tpl_new_中_123_?_update';
        /** @noinspection PhpUnusedLocalVariableInspection */
        $type = 'lall_foo_update';

        $affected_rows = $prepare->execute();

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $affected_rows]);
        $result = $resultSelect->fetchArray();

        $expectedSql = 'UPDATE test_page 
          SET 
            page_template = \'tpl_new_中_123_?_update\', 
            page_type = \'lall_foo_update\'
          WHERE page_id = 1
        ';

        static::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());
        static::assertSame($affected_rows, $result['page_id']);
        static::assertSame('tpl_new_中_123_?_update', $result['page_template']);
        static::assertSame('lall_foo_update', $result['page_type']);

        // -------------
    }

    public function testUpdateWithBindParamHelperV2()
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

        static::assertSame(0, $affected_rows, 'tested: ' . $affected_rows);
        static::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());

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

        static::assertSame(0, $affected_rows);
        static::assertSame($expectedSql, $prepare->get_sql_with_bound_parameters());

        // -------------
    }
}
