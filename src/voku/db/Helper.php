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
              $bindings[] = $db->escape($updateArray[$fieldName]);
            } else {
              $insert_keys .= ',' . $fieldName;
              $insert_values .= ',?';
              $bindings[] = $db->escape($value);
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
