[![Build Status](https://travis-ci.org/voku/simple-mysqli.svg?branch=master)](https://travis-ci.org/voku/simple-mysqli)
[![Coverage Status](https://coveralls.io/repos/github/voku/simple-mysqli/badge.svg?branch=master)](https://coveralls.io/github/voku/simple-mysqli?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/voku/simple-mysqli/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/voku/simple-mysqli/?branch=master)
[![Codacy Badge](https://www.codacy.com/project/badge/797ba3ba657d4e0e86f0bade6923fdec)](https://www.codacy.com/app/voku/simple-mysqli)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/f1ad7660-6b85-4e1e-a7a3-8489b96b64f8/mini.png)](https://insight.sensiolabs.com/projects/f1ad7660-6b85-4e1e-a7a3-8489b96b64f8)
[![Dependency Status](https://www.versioneye.com/php/voku:simple-mysqli/dev-master/badge.svg)](https://www.versioneye.com/php/voku:simple-mysqli/dev-master)
[![Latest Stable Version](https://poser.pugx.org/voku/simple-mysqli/v/stable)](https://packagist.org/packages/voku/simple-mysqli) 
[![Total Downloads](https://poser.pugx.org/voku/simple-mysqli/downloads)](https://packagist.org/packages/voku/simple-mysqli) 
[![Latest Unstable Version](https://poser.pugx.org/voku/simple-mysqli/v/unstable)](https://packagist.org/packages/voku/simple-mysqli) 
[![License](https://poser.pugx.org/voku/simple-mysqli/license)](https://packagist.org/packages/voku/simple-mysqli)

Simple MySQLi Class
===================


This is a simple MySQL Abstraction Layer for PHP>=5.3 that provides a simple and _secure_ interaction with your database using mysqli_* functions at its core.

This is perfect for small scale applications such as cron jobs, facebook canvas campaigns or micro frameworks or sites.

_This project is under construction, any feedback would be appreciated_

Author: [Jonathan Tavares](https://github.com/entomb)
Author: [Lars Moelleken](http://github.com/voku)


##Get "Simple MySQLi"
You can download it from here, or require it using [composer](https://packagist.org/packages/voku/simple-mysqli).
```json
{
    "require": {
      "voku/simple-mysqli": "1.*"
    }
}
```

##Install via "composer require"
```shell
composer require voku/simple-mysqli
```


##Starting the driver

```php
    use voku\db\DB;

    require_once 'composer/autoload.php';

    $db = DB::getInstance('yourDbHost', 'yourDbUser', 'yourDbPassword', 'yourDbName');
    
    // example
    // $db = DB::getInstance('localhost', 'root', '', 'test');
```

##Using the "DB"-Class

there are numerous ways of using this library, here are some examples of the most common methods

###Selecting and retrieving data from a table

```php
  use voku\db\DB;

  $db = DB::getInstance();

  $result = $db->query("SELECT * FROM users");
  $users  = $result->fetchAll();
```

But you can also use a method for select-queries:

```php
  $db->select( String $table, Array $where );               // generate an SELECT query
```

Example: SELECT
```php
    $where = array(
        'page_type ='        => 'article',
        'page_type NOT LIKE' => '%öäü123',
        'page_id >='          => 2,
    );
    $resultSelect = $db->select('page', $where);
```

Here is a list of connectors for the "WHERE"-Array:
'NOT', 'IS', 'IS NOT', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'LIKE', 'NOT LIKE', '>', '<', '>=', '<=', '<>'

INFO: use a array as $value for "[NOT] IN" and "[NOT] BETWEEN"

Example: SELECT with "NOT IN"
```php
    $where = array(
        'page_type NOT IN'     => array(
            'foo',
            'bar'
        ),
        'page_id >'            => 2,
    );
    $resultSelect = $db->select('page', $where);
```

Example: SELECT with Cache
```php
    $resultSelect = $db->execSQL("SELECT * FROM users", true, 3600);
```

The result (via $result->fetchAllArray()) is only cached for 3600s when the query was a SELECT statement, otherwise you get the default result from the ```$db->query()``` function. 

###Inserting data on a table

to manipulate tables you have the most important methods wrapped,
they all work the same way: parsing arrays of key/value pairs and forming a safe query

the methods are:
```php
  $db->insert( String $table, Array $data );                // generate an INSERT query
  $db->replace( String $table, Array $data );               // generate an REPLACE query
  $db->update( String $table, Array $data, Array $where );  // generate an UPDATE query
  $db->delete( String $table, Array $where );               // generate a DELETE query
```

All methods will return the resulting `mysqli_insert_id()` or true/false depending on context.
The correct approach if to always check if they executed as success is always returned

Example: DELETE
```php
  $deleteArray = array('user_id' => 9);
  $ok = $db->delete('users', $deleteArray);
  if ($ok) {
    echo "user deleted!";
  } else {
    echo "can't delete user!";
  }
```

**note**: all parameter values are sanitized before execution, you don\'t have to escape values beforehand.

Example: INSERT
```php
  $insertArray = array(
    'name'   => "John",
    'email'  => "johnsmith@email.com",
    'group'  => 1,
    'active' => true,
  );
  $newUserId = $db->insert('users', $insertArray);
  if ($newUserId) {
    echo "new user inserted with the id $new_user_id";
  }
```

Example: REPLACE
```php
  $replaceArray = array(
      'name'   => 'lars',
      'email'  => 'lars@moelleken.org',
      'group'  => 0
  );
  $tmpId = $db->replace('users', $replaceArray);
```

###binding parameters on queries

Binding parameters is a good way of preventing mysql injections as the parameters are sanitized before execution.

```php
  $sql = "SELECT * FROM users 
    WHERE id_user = ? 
    AND active = ? 
    LIMIT 1
  ";
  $result = $db->query($sql, array(11,1));
  if ($result) {
    $user = $result->fetchArray();
    print_r($user);
  } else {
    echo "user not found";
  }
```

###Using the Result-Class

After executing a `SELECT` query you receive a `Result` object that will help you manipulate the resultant data.
there are different ways of accessing this data, check the examples bellow:

####Fetching all data
```php
  $result = $db->query("SELECT * FROM users");
  $allUsers = $result->fetchAll();
```
Fetching all data works as `Object` or `Array` the `fetchAll()` method will return the default based on the `$_default_result_type` config.
Other methods are:

```php
$row = $result->fetch();        // fetch a single result row as defined by the config (Array or Object)
$row = $result->fetchArray();   // fetch a single result row as Array
$row = $result->fetchObject();  // fetch a single result row as Object

$data = $result->fetchAll();        // fetch all result data as defined by the config (Array or Object)
$data = $result->fetchAllArray();   // fetch all result data as Array
$data = $result->fetchAllObject();  // fetch all result data as Object

$data = $result->fetchColumn(String $Column);                  // fetch a single column in a 1 dimention Array
$data = $result->fetchArrayPair(String $key, String $Value);   // fetch data as a key/value pair Array.
```

####Aliases
```php
  $db->get()                  // alias for $db->fetch();
  $db->getAll()               // alias for $db->fetchAll();
  $db->getObject()            // alias for $db->fetchAllObject();
  $db->getArray()             // alias for $db->fetchAllArray();
  $db->getColumn($key)        // alias for $db->fetchColumn($key);
```

####Iterations
To iterate a result-set you can use any fetch() method listed above.

```php
  $result = $db->select('users');

  // using while
  while($row = $result->fetch()) {
    echo $row->name;
    echo $row->email;
  }

  // using foreach
  foreach($result->fetchAll() as $row) {
    echo $row->name;
    echo $row->email;
  }
```

####Logging and Errors

You can hook into the "DB"-Class, so you can use your personal "Logger"-Class. But you have to cover the methods:

```php
$this->trace(String $text, String $name) { ... }
$this->debug(String $text, String $name) { ... }
$this->info(String $text, String $name) { ... }
$this->warn(String $text, String $name) { ... } 
$this->error(String $text, String $name) { ... }
$this->fatal(String $text, String $name) { ... }
```

You can also disable the logging of every sql-query, with the "getInstance()"-parameter "logger_level" from "DB"-Class.
If you set "logger_level" to something other than "trace" or "debug", the "DB"-Class will only log errors anymore.

Showing the query log. the log comes with the SQL executed, the execution time and the result row count
```php
  print_r($db->log());
```

to debug mysql errors:

use `$db->errors()` to fetch all errors (returns false if no errors) or `$db->lastError()` for information on the last error.

```php
  if ($db->errors()) {
      echo $db->lastError();
  }
```




