<?php

declare(strict_types=1);

use voku\db\DB;
use voku\db\Result;

class SimpleResultTest extends PHPUnit_Framework_TestCase
{
  /**
   * @var DB
   */
  private $db;

  public function setUp()
  {
    $this->db = DB::getInstance('localhost', 'root', '', 'mysql_test', 3306, 'utf8', false, true);

    $this->db->query('DROP TABLE IF EXISTS `post` ');

    $sql =<<<SQL
CREATE TABLE `post` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL DEFAULT '',
    `body` text NOT NULL,
    `comments_count` int(11) NOT NULL DEFAULT 0,
    `when` datetime DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;
    $this->db->query($sql);

    $this->db->query("INSERT INTO `post` (`title`, `body`, `when`) VALUES ('Title #1', 'Body #1', NOW())");
    $this->db->query("INSERT INTO `post` (`title`, `body`, `when`) VALUES ('Title #2', 'Body #2', NOW())");
    $this->db->query("INSERT INTO `post` (`title`, `body`, `when`) VALUES ('Title #3', 'Body #3', NOW())");
  }

  public function testResult()
  {
    $result = $this->db->query('SELECT * FROM `post` ORDER BY `id` ASC');
    self::assertTrue($result instanceof Result);
    return $result;
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testSeek($result)
  {
    $result->seek(1);
    self::assertEquals(2, $result->fetchCallable(null, 'id'));
    $result->seek();
    self::assertEquals(1, $result->fetchCallable(null, 'id'));
  }
  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testSeek2($result)
  {
    self::assertFalse($result->seek(3));
  }

  public function testSeek3()
  {
    $result = $this->db->query('SELECT * FROM `post` WHERE id > 100');
    self::assertFalse($result->seek());
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testFetchFields($result)
  {
    $fields = $result->fetchFields(true);
    self::assertTrue(is_array($fields));
    self::assertEquals(5, count($fields));
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testFetchCallable($result)
  {
    $result->seek();
    self::assertTrue(is_array($result->fetchCallable()));
    self::assertEquals(2, $result->fetchCallable(null, 'id'));
    self::assertEquals(3, $result->fetchCallable(2, 'id'));
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testFetchExtra($result)
  {
    $result->seek();
    self::assertTrue(is_array($result->fetchAllArray()));
    $result->seek();
    self::assertEquals(3, $result->fetchColumn('id'));
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testFetchAll($result)
  {
    $posts = $result->fetchAllArray();
    self::assertTrue(is_array($posts));
    self::assertEquals(3, count($posts));
    $post_ids = $result->fetchAllColumn('id');
    self::assertTrue(is_array($post_ids));
    self::assertEquals(3, count($post_ids));
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testFetchTranspose($result)
  {
    $transposed = $result->fetchTranspose();
    self::assertEquals(5, count($transposed));
    foreach ($transposed as $column => $rows) {
      self::assertEquals(3, count($rows));
    }

    $transposed = $result->fetchTranspose('id');
    foreach ($transposed as $column => $rows) {
      self::assertEquals(3, count($rows));
      self::assertEquals(array(1,2,3), array_keys($rows));
    }
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testFetchPairs($result)
  {
    $pairs = $result->fetchPairs('id');
    foreach ($pairs as $id => $row) {
      self::assertEquals($id, $row['id']);
    }

    $pairs = $result->fetchPairs('id', 'title');
    foreach ($pairs as $id => $title) {
      self::assertEquals("Title #{$id}", $title);
    }
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testFetchGroups($result)
  {
    $groups = $result->fetchGroups('id');
    foreach ($groups as $id => $group) {
      self::assertEquals(1, count($group));
      self::assertEquals($id, $group[0]['id']);
    }

    $groups = $result->fetchGroups('id', 'title');
    self::assertEquals(array(1 => array('Title #1'), 2 => array('Title #2'), 3 => array('Title #3')), $groups);
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testFirst($result)
  {
    self::assertTrue(is_array($result->first()));
    self::assertEquals(1, $result->first('id'));
  }
  public function testFirst2()
  {
    $result = $this->db->query('SELECT * FROM `post` WHERE id > 100');
    self::assertNull($result->first());
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testLast($result)
  {
    self::assertTrue(is_array($result->last()));
    self::assertEquals(3, $result->last('id'));
  }
  public function testLast2()
  {
    $result = $this->db->query('SELECT * FROM `post` WHERE id > 100');
    self::assertNull($result->last());
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testSlice($result)
  {
    $slice = $result->slice(1);
    self::assertTrue(is_array($slice));
    self::assertEquals(2, count($slice));
    self::assertEquals(3, $slice[1]['id']);

    $slice = $result->slice(-1);
    self::assertTrue(is_array($slice));
    self::assertEquals(1, count($slice));
    self::assertEquals(3, $slice[0]['id']);

    $slice = $result->slice();
    self::assertTrue(is_array($slice));
    self::assertEquals(3, count($slice));
    self::assertEquals(2, $slice[1]['id']);

    $slice = $result->slice(0, 100);
    self::assertTrue(is_array($slice));
    self::assertEquals(3, count($slice));
    self::assertEquals(2, $slice[1]['id']);

    $slice = $result->slice(-100, 100);
    self::assertTrue(is_array($slice));
    self::assertEquals(3, count($slice));
    self::assertEquals(2, $slice[1]['id']);

    $slice = $result->slice(-100);
    self::assertTrue(is_array($slice));
    self::assertEquals(3, count($slice));
    self::assertEquals(2, $slice[1]['id']);

    $slice = $result->slice(100, 100);
    self::assertTrue(is_array($slice));
    self::assertEquals(0, count($slice));

    $slice = $result->slice(1, 1);
    self::assertTrue(is_array($slice));
    self::assertEquals(1, count($slice));
    self::assertEquals(2, $slice[0]['id']);

    $slice = $result->slice(1, 1, true);
    self::assertTrue(is_array($slice));
    self::assertEquals(1, count($slice));
    self::assertEquals(2, $slice[1]['id']);
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testCount($result)
  {
    self::assertEquals(3, $result->count());
    self::assertEquals(3, count($result));
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testNumRows($result)
  {
    self::assertEquals(3, $result->num_rows());
    self::assertEquals(3, $result->num_rows);
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testIterator($result)
  {
    foreach ($result as $row) {
      self::assertTrue(is_array($row));
    }
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testFetchInIterator($result)
  {
    foreach ($result as $i => $row) {
      self::assertEquals($i + 1, $row['id']);
      self::assertEquals(3, count($result->fetchAllArray()));
      self::assertEquals(1, $result->first('id'));
      self::assertEquals(2, $result->fetchCallable(1, 'id'));
      self::assertEquals(3, $result->last('id'));
    }
  }

  /**
   * @depends testResult
   *
   * @param Result $result
   */
  public function testArrayAccess($result)
  {
    self::assertTrue(isset($result[0]));
    self::assertTrue(is_array($result[0]));
    self::assertEquals($result[0]['id'], 1);
  }

  public function testFree()
  {
    $result = $this->db->query('SELECT * FROM `post`');
    self::assertTrue($result->free());
  }

  public function testInvokeV1()
  {
    $result = $this->db->query('SELECT * FROM `post`');
    self::assertTrue($result() instanceof \MySQLi_Result);
    $ids = array();
    $result(function ($result) use (&$ids) {
      while ($row = mysqli_fetch_assoc($result)) {
        $ids[] = $row['id'];
      }
    });
    self::assertEquals(3, count($ids));
  }

  public function testInvokeV2()
  {
    $db = $this->db;
    $ids = array();

    $result = $db('SELECT * FROM `post`');
    $result(function ($result) use (&$ids) {
      while ($row = mysqli_fetch_assoc($result)) {
        $ids[] = $row['id'];
      }
    });
    self::assertEquals(3, count($ids));
  }

  public function testMap()
  {
    $result = $this->db->query('SELECT * FROM `post`');
    $row = $result->fetchCallable(0);
    self::assertFalse($row instanceof \stdClass);
    self::assertSame('Title #1', $row['title']);

    $result->map(function ($row) {
      return (object) $row;
    });
    $row = $result->fetchCallable(0);
    self::assertTrue($row instanceof \stdClass);
    self::assertSame('Title #1', $row->title);
  }
}
