Changelog
=========

8.0.5 (2009-07-31)

- "Prepare" -> fix type compatibility with "mysqli_stmt"


8.0.4 (2019-07-25)

- update dependencies
- extend "Debug::logger"
- fix errors reported by phpstan (level 7)


8.0.3 (2019-02-24)

- "DB" -> replace "DateTime" check to "DateTimeInterface"
- update "simple-cache" v3.2 -> v4.0


8.0.2 (2019-02-13)

- fix php warning, if the db config contains multi-array


8.0.1 (2019-01-11)

- fix usage of "Arrayy"


8.0.0 (2018-12-21)

- move "Active Record"-classes into a separate repository 
  -> https://github.com/voku/simple-active-record


7.4.1 (2018-11-23)

- add support for "+" and "-" for DB->update()


7.4.0 (2018-11-11)

- add support for PDO connection as parent driver (via Doctrine/DBAL)


7.3.0 (2018-11-03)

- simple active record -> use "@property" phpdoc type check via Arrayy


7.2.1 (2018-06-19)

- optimize for PHP >= 7.0
- fix doc / examples for the simple active record
- add more tests


7.2.0 (2018-06-04)

- add support for Doctrine/DBAL as parent driver


7.1.4 (2018-04-28)

- DB->_parseQueryParamsByName() is private now (only internal usage)


7.1.3 (2018-04-27)

- optimize the "escape" function
- do not trim the input string


7.1.2 (2018-04-27)

- optimize performance for the query builder


7.1.1 (2018-02-13)

- "DB" -> implement "re_connect" for "DB::getInstance()"


7.1.0 (2018-01-21)

- "define constants for default_result_type"
- add usage of "yield" via "Result->fetchAllYield()"  


7.0.2 (2018-01-07)

- use static cache for the temporary parse-key


7.0.1 (2018-01-02)

- fix for "DB->query()" + '?' (old sql-style)


7.0.0 (2017-12-23)

- update "Portable UTF8" from v4 -> v5

-> this is a breaking change without API-changes - but the requirement from 
   "Portable UTF8" has been changed (it no longer requires all polyfills from Symfony)


6.1.1 (2017-12-21)

- "DB" -> simplify -> !is_array(val) { \[val\] } to val = (array)val


6.1.0 (2017-12-14)

- add "DB->setConfigExtra()"


6.0.3 (2017-12-03)

- fix logging + PHP 7.0


6.0.2 (2017-12-03)

- update "voku/simple-cache"


6.0.1 (2017-12-01)
- fix declaration of voku\db\Prepare::prepare (mysqli_stmt::prepare)
- micro optimization
- update phpunit-config


6.0.0 (2017-11-13)
- "php": ">=7.0" 
  * drop support for PHP < 7.0
  * use "strict_types"


5.4.8 (2017-12-20)

- add "setConfigExtra()" (backport)

5.4.7 (2017-10-15)

- improve "DB->close()" + tests

5.4.6 (2017-10-14)

- fix + test for double connection close

5.4.5 (2017-10-11)

- "ActiveRecord" -> fix return values from DB-class

5.4.4 (2017-09-28)

- fix "insert()", "delete()", etc. with empty string input

5.4.3 (2017-09-28)

- fix -> DB->escape() (same fix as for "DB->secure()")

5.4.2 (2017-09-15)

- fix -> DB->secure()

5.4.1 (2017-09-08)

- update php-docs
- DB->set_convert_null_to_empty_string(false) -> NULL === 'NULL'

5.4.0 (2017-09-03)

- update docs + examples
- fix code-style
- add ActiveRecord::fetchEmpty()

5.3.1 (2017-09-03)

- update docs + examples
- DB->set_convert_null_to_empty_string() -> is deprecated

5.3.0 (2017-09-03)

- "ActiveRecord" -> add more fetch methods
- "ActiveRecord" -> fix "resetDirty()"

5.2.1 (2017-09-03)

- DB->table_exists() && DB->num_rows() -> fix + tests

5.2.0 (2017-09-02)

- add "ActiveRecord"-class + doc + tests

5.1.0 (2017-08-26)

- SSL connection for mysqli
- fix custom-exceptions
- fix transaction-handling
- add new parameter via ":column" (_parseQueryParamsByName)
- foreach for the result-object
- __invoke for the "DB"-class -> e.g.: $result = $db('SELECT ...');
- __invoke for the "Result"-class -> e.g.: $result(function ($result) use (&$foo) { }
- add DB->transact() + doc + tests
- add DB->select_db()
- the "Result"-class now implements "\Countable, \SeekableIterator, \ArrayAccess" interfaces
- add Result->fetchCallable() + doc + tests

5.0.0 (2017-08-10)

- update vendor

5.0.0 (2017-07-22)

- throw custom-exceptions and throw them only if needed

- DBConnectException: will be thrown from DB->connect()
- DBGoneAwayException: will be thrown by "server has gone away"-error
- QueryException: will be thrown by "query"-error

4.4.3 (2017-05-22)

- fix return types of "fetchArray()" / "fetchArrayy()"

4.4.2 (2017-05-21)

- fix return of "DB->ping()" -> if there isn't a link to the db

4.4.1 (2017-05-05)

- add caching for "Helper::phoneticSearch()" + tests

4.4.0 (2017-04-10)

- use a new version of "Arrayy" (vendor)
- use "DB->_parseArrayPair()" in te "Helper"-Class
- use the "phonetic-algorithms" in the database-layer
- only internal re-naming of static variable
- update / fix php-doc

4.3.1 (2017-04-03)

- add the "$databaseName"-parameter to "Helper::copyTableRow()" and "Helper::getDbFields()"

4.3.0 (2017-03-31)

- add "Result->fetchAllColumn()"
- add new parameter for "Result->fetchColumn()"
- fix usage of optional "$database"-parameter for $db->replace()

4.2.6 (2017-03-29)

- fix usage of optional "$database"-parameter for $db->insert() / $db->select() / $db->update()

4.2.5 (2017-03-24)

- fix "DB->quote_string()" -> now we can also process already backtick-quoted strings
- simplify some "if"-statements

4.2.4 (2017-03-15)

- optimize "DB->escape()"

4.2.3 (2017-03-09)

- prepare for PHP7 and "declare(strict_types=1);"
- use new version of "Portable-UTF8"-vendor via composer.json

4.2.2 (2017-01-23)

- fix "Result->cast()" for PHP 5.3 without mysqlnd

4.2.1 (2017-01-10)

- fix "Helper::getDbFields()" for database+table name

4.2.0 (2017-01-09)

- use new version of the "Arrayy"-class (vendor)

4.1.2 (2016-12-22)

- use "UTF8::json_encode()" in the "Result"-object
- add more alias-functions for "Arrayy"-usage
- add more php-docs for the "Result"-object

4.1.0 (2016-12-21)

- add "Prepare->execute_raw()" -> without debugging or logging

4.0.1 (2016-12-19)

- use parameter (array) check for DB->update() / DB->insert() / DB->replace()
- optimize memory usage from Helper->copyTableRow()
- simplify some code

4.0.0 (2016-12-16)

- edit "Prepare->execute()" -> the method will now return an "Result"-object for SELECT queries

WARNING: If you already use "Prepare->execute()" for SELECT-queries, you need to change your code, 
         because the method will now return an "Result"-object instead of true on success.

3.0.4 (2016-11-02)

- fixed "_parseQueryParams()" (e.g. $0 should not replaced by php)

3.0.3 (2016-09-01)

- fixed "copyTableRow()" (do not escape non selected data)

3.0.2 (2016-08-18)

- use "utf8mb4" if it's supported

3.0.1 (2016-08-15)

- fixed usage of (float)

3.0.0 (2016-08-15)
------------------

- merge "secure()" and "escape()" methods
- convert "DateTime"-object to "DateTime"-string via "escape()"
- check magic method "__toString" for "escape()"-input

WARNING: Use "set_convert_null_to_empty_string(true)" to be compatible with the <= 2.0.x tags.

2.0.5/6 (2016-08-12)
------------------

- use new version of "portable-utf8" (3.0)

2.0.4 (2016-07-20)
------------------

- use "assertSame" instead of "assertEquals" (PhpUnit)
- fix "DB->escape()" usage with arrays

2.0.3 (2016-07-11)
------------------

- fix used of "MYSQLI_OPT_INT_AND_FLOAT_NATIVE"
        -> "Type: Notice Message: Use of undefined constant MYSQLI_OPT_INT_AND_FLOAT_NATIVE"


2.0.2 (2016-07-11)
------------------

- fixed return from "DB->qry()"
        -> e.g. if an update-query updated zero rows, then we return "0" instead of "true" now


2.0.1 (2016-07-11)
------------------

- fixed return from "DB->query()" and "Prepare->execute()"
        -> e.g. if an update-query updated zero rows, then we return "0" instead of "true" now


2.0.0 (2016-07-11)
------------------

INFO: There was no breaking API changes, so you can easily upgrade from 1.x.

- use "MYSQLI_OPT_INT_AND_FLOAT_NATIVE" + fallback
- fixed return statements from "DB"-Class e.g. from "query()", "execSQL()"
- don't use "UTF8::html_entity_decode()" by default
- added "Prepare->bind_param_debug()" for debugging and logging prepare statements
