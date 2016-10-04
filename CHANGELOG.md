Changelog
=========

3.0.3 (2016-09-01)

* [+]: fixed "copyTableRow()" (do not escape non selected data)

3.0.2 (2016-08-18)

* [+]: use "utf8mb4" if it's supported

3.0.1 (2016-08-15)

* [!]: fixed usage of (float)

3.0.0 (2016-08-15)
------------------

* [~]: merge "secure()" and "escape()" methods
* [+]: convert "DateTime"-object to "DateTime"-string via "escape()"
* [+]: check magic method "__toString" for "escape()"-input

WARNING: Use "set_convert_null_to_empty_string(true)" to be compatible with the <= 2.0.x tags.

2.0.5/6 (2016-08-12)
------------------

* [+]: use new version of "portable-utf8" (3.0)

2.0.4 (2016-07-20)
------------------

* [+]: use "assertSame" instead of "assertEquals" (PhpUnit)
* [+]: fix "DB->escape()" usage with arrays

2.0.3 (2016-07-11)
------------------

* [+]: fix used of "MYSQLI_OPT_INT_AND_FLOAT_NATIVE"
        -> "Type: Notice Message: Use of undefined constant MYSQLI_OPT_INT_AND_FLOAT_NATIVE"


2.0.2 (2016-07-11)
------------------

* [!]: fixed return from "DB->qry()"
        -> e.g. if an update-query updated zero rows, then we return "0" instead of "true" now


2.0.1 (2016-07-11)
------------------

 * [!]: fixed return from "DB->query()" and "Prepare->execute()"
        -> e.g. if an update-query updated zero rows, then we return "0" instead of "true" now


2.0.0 (2016-07-11)
------------------

INFO: There was no breaking API changes, so you can easily upgrade from 1.x.

 * [!]: use "MYSQLI_OPT_INT_AND_FLOAT_NATIVE" + fallback
 * [!]: fixed return statements from "DB"-Class e.g. from "query()", "execSQL()"
 * [!]: don't use "UTF8::html_entity_decode()" by default
 * [+]: added "Prepare->bind_param_debug()" for debugging and logging prepare statements
