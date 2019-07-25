<?php

declare(strict_types=1);

namespace voku\db;

use voku\cache\Cache;
use voku\helper\Phonetic;

/**
 * Helper: This class can handle extra functions that use the "Simple-MySQLi"-classes.
 */
class Helper
{
    /**
     * @param DB $dbConnection
     *
     * @return string
     */
    private static function generateCacheKey(DB $dbConnection): string
    {
        // init
        $configTmp = [];

        $configOrig = $dbConnection->getConfig();
        \array_walk_recursive(
            $configOrig,
            static function ($k, $v) use (&$configTmp) {
                $configTmp[] = $v;
                $configTmp[] = $k;
            }
        );

        return \implode('--', $configTmp);
    }

    /**
     * Optimize tables
     *
     * @param array   $tables       database table names
     * @param DB|null $dbConnection <p>Use <strong>null</strong> to get your first singleton instance.</p>
     *
     * @return int
     */
    public static function optimizeTables(array $tables = [], DB $dbConnection = null): int
    {
        if ($dbConnection === null) {
            $dbConnection = DB::getInstance();
        }

        $optimized = 0;
        if (!empty($tables)) {
            foreach ($tables as $table) {
                $optimize = 'OPTIMIZE TABLE ' . $dbConnection->quote_string($table);
                $result = $dbConnection->query($optimize);
                if ($result) {
                    $optimized++;
                }
            }
        }

        return $optimized;
    }

    /**
     * Repair tables
     *
     * @param array   $tables       database table names
     * @param DB|null $dbConnection <p>Use <strong>null</strong> to get your first singleton instance.</p>
     *
     * @return int
     */
    public static function repairTables(array $tables = [], DB $dbConnection = null): int
    {
        if ($dbConnection === null) {
            $dbConnection = DB::getInstance();
        }

        $optimized = 0;
        if (!empty($tables)) {
            foreach ($tables as $table) {
                $optimize = 'REPAIR TABLE ' . $dbConnection->quote_string($table);
                $result = $dbConnection->query($optimize);
                if ($result) {
                    $optimized++;
                }
            }
        }

        return $optimized;
    }

    /**
     * Check if "mysqlnd"-driver is used.
     *
     * @return bool
     */
    public static function isMysqlndIsUsed(): bool
    {
        static $_mysqlnd_is_used = null;

        if ($_mysqlnd_is_used === null) {
            $_mysqlnd_is_used = (
                \extension_loaded('mysqlnd')
                &&
                \function_exists('mysqli_fetch_all')
            );
        }

        return $_mysqlnd_is_used;
    }

    /**
     * Check if the current environment supports "utf8mb4".
     *
     * @param DB $dbConnection
     *
     * @return bool
     */
    public static function isUtf8mb4Supported(DB $dbConnection = null): bool
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
        if ($dbConnection === null) {
            $dbConnection = DB::getInstance();
        }

        $server_version = self::get_mysql_server_version($dbConnection);
        $client_version = self::get_mysql_client_version($dbConnection);

        if (
            $server_version >= 50503
            &&
            (
                (
                    self::isMysqlndIsUsed()
                    &&
                    $client_version >= 50009
                )
                ||
                (
                    !self::isMysqlndIsUsed()
                    &&
                    $client_version >= 50503
                )
            )

        ) {
            return true;
        }

        return false;
    }

    /**
     * A phonetic search algorithms for different languages.
     *
     * INFO: if you need better performance, please save the "voku\helper\Phonetic"-output into the DB and search for it
     *
     * @param string      $searchString
     * @param string      $searchFieldName
     * @param string      $idFieldName
     * @param string      $language     <p>en, de, fr</p>
     * @param string      $table
     * @param array       $whereArray
     * @param DB|null     $dbConnection <p>use <strong>null</strong> if you will use the current database-connection</p>
     * @param string|null $databaseName <p>use <strong>null</strong> if you will use the current database</p>
     * @param bool        $useCache     use cache?
     * @param int         $cacheTTL     cache-ttl in seconds
     *
     * @return array
     */
    public static function phoneticSearch(
        string $searchString,
        string $searchFieldName,
        string $idFieldName = null,
        string $language = 'de',
        string $table = '',
        array $whereArray = null,
        DB $dbConnection = null,
        string $databaseName = null,
        bool $useCache = false,
        int $cacheTTL = 3600
    ): array {
        // init
        $cacheKey = null;

        if ($dbConnection === null) {
            $dbConnection = DB::getInstance();
        }

        if ($table === '') {
            $debug = new Debug($dbConnection);
            $debug->displayError('Invalid table name, table name in empty.', false);

            return [];
        }

        if ($idFieldName === null) {
            $idFieldName = 'id';
        }

        if ($whereArray === null) {
            $whereArray = [];
        }

        $whereSQL = $dbConnection->_parseArrayPair($whereArray, 'AND');
        if ($whereSQL) {
            $whereSQL = 'AND ' . $whereSQL;
        }

        if ($databaseName) {
            $databaseName = $dbConnection->quote_string(\trim($databaseName)) . '.';
        }

        // get the row
        $query = 'SELECT ' . $dbConnection->quote_string($searchFieldName) . ', ' . $dbConnection->quote_string($idFieldName) . ' 
          FROM ' . $databaseName . $dbConnection->quote_string($table) . '
          WHERE 1 = 1
          ' . $whereSQL . '
        ';

        if ($useCache) {
            $cache = new Cache(null, null, false, $useCache);
            $cacheKey = 'sql-phonetic-search-' . \md5($query);

            if (
                $cache->getCacheIsReady()
                &&
                $cache->existsItem($cacheKey)
            ) {
                return $cache->getItem($cacheKey);
            }
        } else {
            $cache = false;
        }

        $result = $dbConnection->query($query);

        if (!$result instanceof Result) {
            return [];
        }

        // make sure the row exists
        if ($result->num_rows <= 0) {
            return [];
        }

        $dataToSearchIn = [];
        /** @noinspection LoopWhichDoesNotLoopInspection */
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($tmpArray = $result->fetchArray()) {
            $dataToSearchIn[$tmpArray[$idFieldName]] = $tmpArray[$searchFieldName];
        }

        $phonetic = new Phonetic($language);
        $return = $phonetic->phonetic_matches($searchString, $dataToSearchIn);

        // save into the cache
        if (
            $cacheKey !== null
            &&
            $useCache
            &&
            $cache instanceof Cache
            &&
            $cache->getCacheIsReady()
        ) {
            $cache->setItem($cacheKey, $return, $cacheTTL);
        }

        return $return;
    }

    /**
     * A string that represents the MySQL client library version.
     *
     * @param DB $dbConnection
     *
     * @return string
     */
    public static function get_mysql_client_version(DB $dbConnection = null): string
    {
        static $MYSQL_CLIENT_VERSION_CACHE = [];

        if ($dbConnection === null) {
            $dbConnection = DB::getInstance();
        }

        $cacheKey = self::generateCacheKey($dbConnection);

        if (isset($MYSQL_CLIENT_VERSION_CACHE[$cacheKey])) {
            return $MYSQL_CLIENT_VERSION_CACHE[$cacheKey];
        }

        $doctrineConnection = $dbConnection->getDoctrineConnection();
        if ($doctrineConnection) {
            $doctrineWrappedConnection = $doctrineConnection->getWrappedConnection();
            if ($doctrineWrappedConnection instanceof \Doctrine\DBAL\Driver\PDOConnection) {
                return $MYSQL_CLIENT_VERSION_CACHE[$cacheKey] = $doctrineWrappedConnection->getAttribute(5); // 5 = PDO::ATTR_CLIENT_VERSION
            }
        }

        $mysqli_link = $dbConnection->getLink();
        if (!$mysqli_link) {
            return '';
        }

        return $MYSQL_CLIENT_VERSION_CACHE[$cacheKey] = (string) \mysqli_get_client_version($mysqli_link);
    }

    /**
     * Returns a string representing the version of the MySQL server that the MySQLi extension is connected to.
     *
     * @param DB $dbConnection
     *
     * @return string
     */
    public static function get_mysql_server_version(DB $dbConnection = null): string
    {
        static $MYSQL_SERVER_VERSION_CACHE = [];

        if ($dbConnection === null) {
            $dbConnection = DB::getInstance();
        }

        $cacheKey = self::generateCacheKey($dbConnection);

        if (isset($MYSQL_SERVER_VERSION_CACHE[$cacheKey])) {
            return $MYSQL_SERVER_VERSION_CACHE[$cacheKey];
        }

        $doctrineConnection = $dbConnection->getDoctrineConnection();
        if ($doctrineConnection) {
            $doctrineWrappedConnection = $doctrineConnection->getWrappedConnection();
            if ($doctrineWrappedConnection instanceof \Doctrine\DBAL\Driver\PDOConnection) {
                return $MYSQL_SERVER_VERSION_CACHE[$cacheKey] = (string) $doctrineWrappedConnection->getServerVersion();
            }
        }

        $mysqli_link = $dbConnection->getLink();
        if (!$mysqli_link) {
            return '';
        }

        return $MYSQL_SERVER_VERSION_CACHE[$cacheKey] = (string) \mysqli_get_server_version($mysqli_link);
    }

    /**
     * Return all db-fields from a table.
     *
     * @param string      $table
     * @param bool        $useStaticCache
     * @param DB|null     $dbConnection <p>use <strong>null</strong> if you will use the current database-connection</p>
     * @param string|null $databaseName <p>use <strong>null</strong> if you will use the current database</p>
     *
     * @return array
     */
    public static function getDbFields(string $table, bool $useStaticCache = true, DB $dbConnection = null, string $databaseName = null): array
    {
        static $DB_FIELDS_CACHE = [];

        // use the static cache
        if (
            $useStaticCache
            &&
            isset($DB_FIELDS_CACHE[$table])
        ) {
            return $DB_FIELDS_CACHE[$table];
        }

        // init
        $dbFields = [];

        if ($dbConnection === null) {
            $dbConnection = DB::getInstance();
        }

        if ($table === '') {
            $debug = new Debug($dbConnection);
            $debug->displayError('Invalid table name, table name in empty.', false);

            return [];
        }

        if ($databaseName) {
            $databaseName = $dbConnection->quote_string(\trim($databaseName)) . '.';
        }

        $sql = 'SHOW COLUMNS FROM ' . $databaseName . $dbConnection->escape($table);
        $result = $dbConnection->query($sql);

        if ($result instanceof Result && $result->num_rows > 0) {
            foreach ($result->fetchAllArray() as $tmpResult) {
                $dbFields[] = $tmpResult['Field'];
            }
        }

        // add to static cache
        $DB_FIELDS_CACHE[$table] = $dbFields;

        return $dbFields;
    }

    /**
     * Copy row within a DB table and making updates to the columns.
     *
     * @param string      $table
     * @param array       $whereArray
     * @param array       $updateArray
     * @param array       $ignoreArray
     * @param DB|null     $dbConnection <p>Use <strong>null</strong> to get your first singleton instance.</p>
     * @param string|null $databaseName <p>use <strong>null</strong> if you will use the current database</p>
     *
     * @return false|int|string "int|string" (insert_id) by "<b>INSERT / REPLACE</b>"-queries<br />
     *                   "false" on error
     */
    public static function copyTableRow(
        string $table,
        array $whereArray,
        array $updateArray = [],
        array $ignoreArray = [],
        DB $dbConnection = null,
        string $databaseName = null
    ) {
        // init
        $table = \trim($table);

        if ($dbConnection === null) {
            $dbConnection = DB::getInstance();
        }

        if ($table === '') {
            $debug = new Debug($dbConnection);
            $debug->displayError('Invalid table name, table name in empty.', false);

            return false;
        }

        $whereSQL = $dbConnection->_parseArrayPair($whereArray, 'AND');
        if ($whereSQL) {
            $whereSQL = 'AND ' . $whereSQL;
        }

        if ($databaseName) {
            $databaseName = $dbConnection->quote_string(\trim($databaseName)) . '.';
        }

        // get the row
        $query = 'SELECT * FROM ' . $databaseName . $dbConnection->quote_string($table) . '
          WHERE 1 = 1
          ' . $whereSQL . '
        ';
        $result = $dbConnection->query($query);

        // make sure the row exists
        if ($result instanceof Result && $result->num_rows > 0) {

            /** @noinspection LoopWhichDoesNotLoopInspection */
            /** @noinspection PhpAssignmentInConditionInspection */
            while ($tmpArray = $result->fetchArray()) {

                // re-build a new DB query and ignore some field-names
                $bindings = [];
                $insert_keys = '';
                $insert_values = '';

                foreach ($tmpArray as $fieldName => $value) {
                    if (!\in_array($fieldName, $ignoreArray, true)) {
                        if (\array_key_exists($fieldName, $updateArray)) {
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

                $insert_keys = \ltrim($insert_keys, ',');
                $insert_values = \ltrim($insert_values, ',');

                // insert the "copied" row
                $new_query = 'INSERT INTO ' . $databaseName . $dbConnection->quote_string($table) . ' 
                  (' . $insert_keys . ')
                  VALUES 
                  (' . $insert_values . ')
                ';

                $return = $dbConnection->query($new_query, $bindings);
                \assert(\is_int($return) || \is_string($return) || $return === false);

                return $return;
            }
        }

        return false;
    }
}
