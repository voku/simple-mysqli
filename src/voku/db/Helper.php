<?php

namespace voku\db;

/**
 * Helper: this handles extra functions that use the "DB"-Class
 *
 * @package   voku\db
 */
class Helper
{
  /**
   * Check if "mysqlnd"-driver is used.
   *
   * @return bool
   */
  public static function isMysqlndIsUsed()
  {
    static $_mysqlnd_is_used = null;

    if ($_mysqlnd_is_used === null) {
      $_mysqlnd_is_used = (extension_loaded('mysqlnd') && function_exists('mysqli_fetch_all'));
    }

    return $_mysqlnd_is_used;
  }

  /**
   * Check if the current environment supports "utf8mb4".
   *
   * @param DB $db
   *
   * @return bool
   */
  public static function isUtf8mb4Supported(DB $db)
  {
    /**
     *  https://make.wordpress.org/core/2015/04/02/the-utf8mb4-upgrade/
     *
     * - You’re currently using the utf8 character set.
     * - Your MySQL server is version 5.5.3 or higher (including all 10.x versions of MariaDB).
     * - Your MySQL client libraries are version 5.5.3 or higher. If you’re using mysqlnd, 5.0.9 or higher.
     *
     * INFO: utf8mb4 is 100% backwards compatible with utf8.
     */

    $server_version = self::get_mysql_server_version($db);
    $client_version = self::get_mysql_client_version($db);

    if (
        $server_version >= 50503
        &&
        (
            (
                self::isMysqlndIsUsed() === true
                &&
                $client_version >= 50009
            )
            ||
            (
                self::isMysqlndIsUsed() === false
                &&
                $client_version >= 50503
            )
        )

    ) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * A string that represents the MySQL client library version.
   *
   * @param DB $db
   *
   * @return string
   */
  public static function get_mysql_client_version(DB $db)
  {
    static $_mysqli_client_version = null;

    if ($_mysqli_client_version === null) {
      $_mysqli_client_version = \mysqli_get_client_version($db->getLink());
    }

    return $_mysqli_client_version;
  }


  /**
   * Returns a string representing the version of the MySQL server that the MySQLi extension is connected to.
   *
   * @param DB $db
   *
   * @return string
   */
  public static function get_mysql_server_version(DB $db)
  {
    static $_mysqli_server_version = null;

    if ($_mysqli_server_version === null) {
      $_mysqli_server_version = \mysqli_get_server_version($db->getLink());
    }

    return $_mysqli_server_version;
  }

  /**
   * Return all db-fields from a table.
   *
   * @param string  $table
   * @param bool    $useStaticCache
   * @param DB|null $db
   *
   * @return array
   */
  public static function getDbFields($table, $useStaticCache = true, DB $db = null)
  {
    static $dbFieldsCache = array();

    // use the static cache
    if (
        $useStaticCache === true
        &&
        isset($dbFieldsCache[$table])
    ) {
      return $dbFieldsCache[$table];
    }

    // init
    $dbFields = array();

    if ($db === null) {
      $db = DB::getInstance();
    }

    $debug = new Debug($db);
    if ($table === '') {
      $debug->displayError('invalid table name');

      return array();
    }

    $sql = 'SHOW COLUMNS FROM ' . $db->quote_string($table);
    $result = $db->query($sql);

    if ($result && $result->num_rows > 0) {
      foreach ($result->fetchAllArray() as $tmpResult) {
        $dbFields[] = $tmpResult['Field'];
      }
    }

    // add to static cache
    $dbFieldsCache[$table] = $dbFields;

    return $dbFields;
  }

  /**
   * Copy row within a DB table and making updates to the columns.
   *
   * @param string  $table
   * @param array   $whereArray
   * @param array   $updateArray
   * @param array   $ignoreArray
   * @param DB|null $db           <p>Use <strong>null</strong>
   *
   * @return bool|int "int" (insert_id) by "<b>INSERT / REPLACE</b>"-queries<br />
   *                   "false" on error
   */
  public static function copyTableRow($table, array $whereArray, array $updateArray = array(), array $ignoreArray = array(), DB $db = null)
  {
    // init
    $table = trim($table);

    if ($db === null) {
      $db = DB::getInstance();
    }

    $debug = new Debug($db);
    if ($table === '') {
      $debug->displayError('invalid table name');

      return false;
    }

    $whereSQL = '';
    foreach ($whereArray as $key => $value) {
      $whereSQL .= ' AND ' . $db->escape($key) . ' = ' . $db->escape($value);
    }

    // get the row
    $query = 'SELECT * FROM ' . $db->quote_string($table) . '
      WHERE 1 = 1
      ' . $whereSQL . '
    ';
    $result = $db->query($query);

    // make sure the row exists
    if ($result->num_rows > 0) {

      foreach ($result->fetchAllArray() as $tmpArray) {

        // re-build a new DB query and ignore some field-names
        $bindings = array();
        $insert_keys = '';
        $insert_values = '';

        foreach ($tmpArray as $fieldName => $value) {

          if (!in_array($fieldName, $ignoreArray, true)) {
            if (array_key_exists($fieldName, $updateArray)) {
              $insert_keys .= ',' . $fieldName;
              $insert_values .= ',?';
              $bindings[] = $updateArray[$fieldName]; // INFO: do not escape non selected data
            } else {
              $insert_keys .= ',' . $fieldName;
              $insert_values .= ',?';
              $bindings[] = $value; // INFO: do not escape non selected data
            }
          }
        }

        $insert_keys = ltrim($insert_keys, ',');
        $insert_values = ltrim($insert_values, ',');

        // insert the "copied" row
        $new_query = 'INSERT INTO ' . $db->quote_string($table) . ' (' . $insert_keys . ')
          VALUES (' . $insert_values . ')
        ';
        return $db->query($new_query, $bindings);
      }
    }

    return false;
  }
}
