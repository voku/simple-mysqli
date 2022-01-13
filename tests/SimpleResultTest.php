<?php

declare(strict_types=1);

use voku\db\DB;
use voku\db\Result;

/**
 * @internal
 */
final class SimpleResultTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DB
     */
    private $db;

    protected function setUpNonVoid()
    {
        $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test', 3306, 'utf8', false, true);

        $this->db->query('DROP TABLE IF EXISTS post ');

        $sql = <<<SQL
CREATE TABLE post (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    comments_count INT(11) NOT NULL DEFAULT 0,
    instrument_id INT(11) NOT NULL DEFAULT 0,
    `when` DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;
        $this->db->query($sql);

        $this->db->query("INSERT INTO post (title, body, `when`) VALUES ('Title #1', 'Body #1', NOW())");
        $this->db->query("INSERT INTO post (title, body, `when`) VALUES ('Title #2', 'Body #2', NOW())");
        $this->db->query("INSERT INTO post (title, body, `when`) VALUES ('Title #3', 'Body #3', NOW())");
    }

    public function testResult()
    {
        $this->setUpNonVoid();

        $result = $this->db->query('SELECT * FROM post ORDER BY id ASC');
        static::assertTrue($result instanceof Result);

        return $result;
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testSeek($result)
    {
        $this->setUpNonVoid();

        $result->seek(1);
        static::assertSame(2, $result->fetchCallable(null, 'id'));
        $result->seek();
        static::assertSame(1, $result->fetchCallable(null, 'id'));
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testSeek2($result)
    {
        $this->setUpNonVoid();

        static::assertFalse($result->seek(3));
    }

    public function testSeek3()
    {
        $this->setUpNonVoid();

        $result = $this->db->query('SELECT * FROM post WHERE id > 100');
        static::assertFalse($result->seek());
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testFetchFields($result)
    {
        $this->setUpNonVoid();

        $fields = $result->fetchFields(true);
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $fields);
        } else {
            static::assertIsArray($fields);
        }
        static::assertCount(6, $fields);
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testFetchCallable($result)
    {
        $this->setUpNonVoid();

        $result->seek();
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $result->fetchCallable());
        } else {
            static::assertIsArray($result->fetchCallable());
        }
        static::assertSame(2, $result->fetchCallable(null, 'id'));
        static::assertSame(3, $result->fetchCallable(2, 'id'));
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testFetchExtra($result)
    {
        $this->setUpNonVoid();

        $result->seek();
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $result->fetchAllArray());
        } else {
            static::assertIsArray($result->fetchAllArray());
        }
        $result->seek();
        static::assertSame(3, $result->fetchColumn('id'));
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testFetchAll($result)
    {
        $this->setUpNonVoid();

        $posts = $result->fetchAllArray();
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $posts);
        } else {
            static::assertIsArray($posts);
        }
        static::assertCount(3, $posts);
        $post_ids = $result->fetchAllColumn('id');
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $post_ids);
        } else {
            static::assertIsArray($post_ids);
        }
        static::assertCount(3, $post_ids);
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testFetchTranspose($result)
    {
        $this->setUpNonVoid();

        $transposed = $result->fetchTranspose();
        static::assertCount(6, $transposed);
        foreach ($transposed as $column => $rows) {
            static::assertCount(3, $rows);
        }

        $transposed = $result->fetchTranspose('id');
        foreach ($transposed as $column => $rows) {
            static::assertCount(3, $rows);
            static::assertSame([1, 2, 3], \array_keys($rows));
        }
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testFetchPairs($result)
    {
        $this->setUpNonVoid();

        $pairs = $result->fetchPairs('id');
        foreach ($pairs as $id => $row) {
            static::assertSame($id, $row['id']);
        }

        $pairs = $result->fetchPairs('id', 'title');
        foreach ($pairs as $id => $title) {
            static::assertSame("Title #{$id}", $title);
        }
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testFetchGroups($result)
    {
        $this->setUpNonVoid();

        $groups = $result->fetchGroups('id');
        foreach ($groups as $id => $group) {
            static::assertCount(1, $group);
            static::assertSame($id, $group[0]['id']);
        }

        $groups = $result->fetchGroups('id', 'title');
        static::assertSame([1 => ['Title #1'], 2 => ['Title #2'], 3 => ['Title #3']], $groups);
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testFirst($result)
    {
        $this->setUpNonVoid();

        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $result->first());
        } else {
            static::assertIsArray($result->first());
        }
        static::assertSame(1, $result->first('id'));
    }

    public function testFirst2()
    {
        $this->setUpNonVoid();

        $result = $this->db->query('SELECT * FROM post WHERE id > 100');
        static::assertNull($result->first());
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testLast($result)
    {
        $this->setUpNonVoid();

        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $result->last());
        } else {
            static::assertIsArray($result->last());
        }
        static::assertSame(3, $result->last('id'));
    }

    public function testLast2()
    {
        $this->setUpNonVoid();

        $result = $this->db->query('SELECT * FROM post WHERE id > 100');
        static::assertNull($result->last());
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testSlice($result)
    {
        $this->setUpNonVoid();

        $slice = $result->slice(1);
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $slice);
        } else {
            static::assertIsArray($slice);
        }
        static::assertCount(2, $slice);
        static::assertSame(3, $slice[1]['id']);

        $slice = $result->slice(-1);
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $slice);
        } else {
            static::assertIsArray($slice);
        }
        static::assertCount(1, $slice);
        static::assertSame(3, $slice[0]['id']);

        $slice = $result->slice();
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $slice);
        } else {
            static::assertIsArray($slice);
        }
        static::assertCount(3, $slice);
        static::assertSame(2, $slice[1]['id']);

        $slice = $result->slice(0, 100);
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $slice);
        } else {
            static::assertIsArray($slice);
        }
        static::assertCount(3, $slice);
        static::assertSame(2, $slice[1]['id']);

        $slice = $result->slice(-100, 100);
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $slice);
        } else {
            static::assertIsArray($slice);
        }
        static::assertCount(3, $slice);
        static::assertSame(2, $slice[1]['id']);

        $slice = $result->slice(-100);
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $slice);
        } else {
            static::assertIsArray($slice);
        }
        static::assertCount(3, $slice);
        static::assertSame(2, $slice[1]['id']);

        $slice = $result->slice(100, 100);
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $slice);
        } else {
            static::assertIsArray($slice);
        }
        static::assertCount(0, $slice);

        $slice = $result->slice(1, 1);
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $slice);
        } else {
            static::assertIsArray($slice);
        }
        static::assertCount(1, $slice);
        static::assertSame(2, $slice[0]['id']);

        $slice = $result->slice(1, 1, true);
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $slice);
        } else {
            static::assertIsArray($slice);
        }
        static::assertCount(1, $slice);
        static::assertSame(2, $slice[1]['id']);
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testCount($result)
    {
        $this->setUpNonVoid();

        static::assertSame(3, $result->count());
        static::assertCount(3, $result);
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testNumRows($result)
    {
        $this->setUpNonVoid();

        static::assertSame(3, $result->num_rows());
        static::assertSame(3, $result->num_rows);
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testIterator($result)
    {
        $this->setUpNonVoid();

        foreach ($result as $row) {
            if (method_exists($this, 'assertInternalType')) {
                static::assertInternalType('array', $row);
            } else {
                static::assertIsArray($row);
            }
        }
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testFetchInIterator($result)
    {
        $this->setUpNonVoid();

        foreach ($result as $i => $row) {
            static::assertSame($i + 1, $row['id']);
            static::assertCount(3, $result->fetchAllArray());
            static::assertSame(1, $result->first('id'));
            static::assertSame(2, $result->fetchCallable(1, 'id'));
            static::assertSame(3, $result->last('id'));
        }
    }

    /**
     * @depends testResult
     *
     * @param Result $result
     */
    public function testArrayAccess($result)
    {
        $this->setUpNonVoid();

        static::assertTrue(isset($result[0]));
        if (method_exists($this, 'assertInternalType')) {
            static::assertInternalType('array', $result[0]);
        } else {
            static::assertIsArray($result[0]);
        }
        static::assertSame($result[0]['id'], 1);
    }

    public function testFree()
    {
        $this->setUpNonVoid();

        $result = $this->db->query('SELECT * FROM post');
        static::assertTrue($result->free());
    }

    public function testIssue46()
    {
        $this->setUpNonVoid();

        $select = $this->db->select('post', ['instrument_id IN' => [0]]);

        static::assertCount(3, $select->getArray());
    }

    public function testInvokeV1()
    {
        $this->setUpNonVoid();

        $result = $this->db->query('SELECT * FROM post');
        static::assertTrue($result() instanceof \MySQLi_Result);
        $ids = [];
        $result(
            static function ($result) use (&$ids) {
                while ($row = \mysqli_fetch_assoc($result)) {
                    $ids[] = $row['id'];
                }
            }
        );
        static::assertCount(3, $ids);
    }

    public function testInvokeV2()
    {
        $this->setUpNonVoid();

        $db = $this->db;
        $ids = [];

        $result = $db('SELECT * FROM post');
        $result(
            static function ($result) use (&$ids) {
                while ($row = \mysqli_fetch_assoc($result)) {
                    $ids[] = $row['id'];
                }
            }
        );
        static::assertCount(3, $ids);
    }

    public function testMap()
    {
        $this->setUpNonVoid();

        $result = $this->db->query('SELECT * FROM post');
        $row = $result->fetchCallable(0);
        static::assertFalse($row instanceof \stdClass);
        static::assertSame('Title #1', $row['title']);

        $result->map(
            static function ($row) {
                return (object) $row;
            }
        );
        $row = $result->fetchCallable(0);
        static::assertTrue($row instanceof \stdClass);
        static::assertSame('Title #1', $row->title);
    }
}
