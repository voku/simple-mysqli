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
   * return all db-fields from a table
   *
   * @param string $table
   * @param bool   $useStaticCache
   * @param DB|null $db
   *
   * @return array
   */
  public static function getDbFields($table, $useStaticCache = true, DB $db = null)
  {
    static $dbFieldsCache = array();

    // use the cache
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

    $sql = "SHOW COLUMNS FROM `" . $db->escape($table) . "`";
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
   * copy row within a DB table and making updates to columns
   *
   * @param string  $table
   * @param array   $whereArray
   * @param array   $updateArray
   * @param array   $ignoreArray
   * @param DB|null $db
   *
   * @return bool|int "int" (insert_id) by "<b>INSERT / REPLACE</b>"-queries<br />
   *                   "false" on error
   */
  public static function copyTableRow($table, array $whereArray, array $updateArray = array(), array $ignoreArray = array(), DB $db = null)
  {
    // init
    $whereSQL = '';
    $return = false;

    if ($db === null) {
      $db = DB::getInstance();
    }

    $table = $db->escape($table);

    foreach ($whereArray as $key => $value) {
      $whereSQL = ' AND ' . $db->escape($key) . ' = ' . $db->escape($value);
    }

    // get the row
    $query = "SELECT * FROM " . $table . "
      WHERE 1 = 1
      " . $whereSQL . "
    ";
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

              if ($updateArray[$fieldName] || $updateArray[$fieldName] == 0) {
                $insert_keys .= ',' . $fieldName;
                $insert_values .= ',?';
                $bindings[] = $updateArray[$fieldName];
              }

            } else {
              $insert_keys .= ',' . $fieldName;
              $insert_values .= ',?';
              $bindings[] = $value;
            }
          }
        }

        // insert the "copied" row
        $new_query = "INSERT INTO `" . $table . "` (" . ltrim($insert_keys, ',') . ")
          VALUES (" . ltrim($insert_values, ',') . ")
        ";
        $return = $db->query($new_query, $bindings);
      }
    }

    return $return;
  }
}
