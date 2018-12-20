<?php

declare(strict_types=1);

use voku\db\DB;
use voku\db\Helper;

/**
 * Class SimpleHelperTest
 *
 * @internal
 */
final class SimpleHelperTest extends \PHPUnit\Framework\TestCase
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

    public function testOptimizeTables()
    {
        $result = Helper::optimizeTables([$this->tableName]);

        static::assertSame(1, $result);
    }

    public function testRepairTables()
    {
        $result = Helper::repairTables([$this->tableName]);

        static::assertSame(1, $result);
    }

    public function testGetDbFields()
    {
        // don't use static cache

        $dbFields = Helper::getDbFields($this->tableName, false);

        static::assertSame(
        [
            0 => 'page_id',
            1 => 'page_template',
            2 => 'page_type',
        ],
        $dbFields
    );

        // fill static cache

        $dbFields = Helper::getDbFields($this->tableName, true);

        static::assertSame(
        [
            0 => 'page_id',
            1 => 'page_template',
            2 => 'page_type',
        ],
        $dbFields
    );

        // test static-cache

        $dbFields = Helper::getDbFields($this->tableName, true, $this->db);

        static::assertSame(
        [
            0 => 'page_id',
            1 => 'page_template',
            2 => 'page_type',
        ],
        $dbFields
    );

        $dbFields = Helper::getDbFields('mysql_test.test_page', true, $this->db);

        static::assertSame(
        [
            0 => 'page_id',
            1 => 'page_template',
            2 => 'page_type',
        ],
        $dbFields
    );
    }

    public function testCopyTableRow()
    {
        $data = [
            'page_template' => 'tpl_test_new5',
            'page_type'     => 'ö\'ä"ü',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        // ------------------------------

        // where
        $whereArray = [
            'page_id' => $resultInsert,
        ];

        // change column
        $updateArray = [];

        // change column
        $updateArray['page_template'] = 'tpl_test_new6';

        // auto increment column
        $ignoreArray = [
            'page_id',
        ];

        $new_page_id = Helper::copyTableRow($this->tableName, $whereArray, $updateArray, $ignoreArray);

        $resultSelect = $this->db->select($this->tableName, ['page_id' => $new_page_id]);
        $resultSelect = $resultSelect->fetchArray();
        static::assertSame(
        [
            'page_id'       => $new_page_id,
            'page_template' => 'tpl_test_new6',
            'page_type'     => 'ö\'ä"ü',
        ],
        $resultSelect
    );
    }

    public function testPhoneticSearch()
    {
        $data = [
            'page_template' => 'tpl_test_new5',
            'page_type'     => 'Moelleken',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        $data = [
            'page_template' => 'tpl_test_new5',
            'page_type'     => 'Mölecken Wosnitsa',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        // where
        $whereArray = [
            'page_id >=' => $resultInsert - 2000,
        ];

        // ------------------------------

        $result = Helper::phoneticSearch(
        'Moelleken Wosnitza',
        'page_type',
        'page_id',
        'de',
        $this->tableName,
        $whereArray
    );

        $resultValues = \array_values($result);
        static::assertSame(
        [
            'Moelleken' => 'Mölecken',
            'Wosnitza'  => 'Wosnitsa',
        ],
        $resultValues[0]
    );
    }

    public function testPhoneticSearchWithCache()
    {
        $data = [
            'page_template' => 'tpl_test_new5',
            'page_type'     => 'Moelleken',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        $data = [
            'page_template' => 'tpl_test_new5',
            'page_type'     => 'Mölecken Wosnitsa',
        ];

        // will return the auto-increment value of the new row
        $resultInsert = $this->db->insert($this->tableName, $data);
        static::assertGreaterThan(1, $resultInsert);

        // where
        $whereArray = [
            'page_id >=' => $resultInsert - 2000,
        ];

        // ------------------------------ save into cache (first call)

        $result = Helper::phoneticSearch(
        'Moelleken Wosnitza',
        'page_type',
        'page_id',
        'de',
        $this->tableName,
        $whereArray,
        null,
        null,
        true,
        200
    );

        $resultValues = \array_values($result);
        static::assertSame(
        [
            'Moelleken' => 'Mölecken',
            'Wosnitza'  => 'Wosnitsa',
        ],
        $resultValues[0]
    );

        // ------------------------------ remove the db-value, so it's only in the cache

        $this->db->delete($this->tableName, ['page_type' => 'Mölecken Wosnitsa']);

        // ------------------------------ get result from cache (second call)

        $result = Helper::phoneticSearch(
        'Moelleken Wosnitza',
        'page_type',
        'page_id',
        'de',
        $this->tableName,
        $whereArray,
        null,
        null,
        true,
        200
    );

        $resultValues = \array_values($result);
        static::assertSame(
        [
            'Moelleken' => 'Mölecken',
            'Wosnitza'  => 'Wosnitsa',
        ],
        $resultValues[0]
    );
    }
}
