[![Build Status](https://travis-ci.org/voku/simple-mysqli.svg?branch=master)](https://travis-ci.org/voku/simple-mysqli)
[![Coverage Status](https://coveralls.io/repos/voku/simple-mysqli/badge.svg)](https://coveralls.io/r/voku/simple-mysqli)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/f1ad7660-6b85-4e1e-a7a3-8489b96b64f8/mini.png)](https://insight.sensiolabs.com/projects/f1ad7660-6b85-4e1e-a7a3-8489b96b64f8)
[![Dependency Status](https://www.versioneye.com/php/voku:simple-mysqli/dev-master/badge.svg)](https://www.versioneye.com/php/voku:simple-mysqli/dev-master)
[![Total Downloads](https://poser.pugx.org/voku/simple-mysqli/downloads.svg)](https://packagist.org/packages/voku/simple-mysqli)
[![License](https://poser.pugx.org/voku/simple-mysqli/license.svg)](https://packagist.org/packages/voku/simple-mysqli)


Simple MySQLi Class
===================


This is a simple MySQL Abstraction Layer for PHP>5.3 that provides a simple and _secure_ interaction with your database using mysqli_* functions at its core.

This is perfect for small scale applications such as cron jobs, facebook canvas campaigns or micro frameworks or sites.

_This project is under construction, any feedback would be appreciated_

Author: [Jonathan Tavares](https://github.com/entomb)
Author: [Lars Moelleken](http://github.com/voku)


##Get "Simple MySQLi"
You can download it from here, or require it using [composer](https://packagist.org/packages/voku/simple-mysqli).
```json
{
    "require": {
		"voku/simple-mysqli": "dev-master"
	}
}
```

##Install via "composer require"
```shell
composer require voku/simple-mysqli
```


##Starting the driver

```php
    require_once 'composer/autoload.php';

    $db = \voku\db\DB::getInstance('yourDbHost', 'yourDbUser', 'yourDbPassword', 'yourDbName');
    
    // example
    // $db = \voku\db\DB::getInstance('localhost', 'root', '', 'test');

```



##Using the "DB"-Class

there are numerous ways of using this library, here are some examples of the most common methods

###Selecting and retrieving data from a table

```php
  $db = \voku\db\DB::getInstance();

  $result = $db->query("SELECT * FROM users");
  $users  = $result->fetchALL();
```

###Inserting data on a table

to manipulate tables you have the most important methods wrapped,
they all work the same way: parsing arrays of key/value pairs and forming a safe query

the methods are:
```php
  $db->insert( String $table, Array $data );                // generates an INSERT query
  $db->replace( String $table, Array $data );               // generates an REPLACE query
  $db->update( String $table, Array $data, Array $where );  // generates an UPDATE query
  $db->delete( String $table, Array $where );               // generates a DELETE query
```

All methods will return the resulting `mysqli_insert_id()` or true/false depending on context.
The correct approach if to allways check if they executed as success is allways returned

```php
  $ok = $db->delete('users', array( 'user_id' => 9 ) );
  if ($ok) {
    echo "user deleted!";
  } else {
    echo "can't delete user!";
  }
```

**note**: all parameter values are sanitized before execution, you dont have to escape values beforehand.

```php
  $newUserId = $db->insert('users', array(
                                'name'  => "jothn",
                                'email' => "johnsmith@email.com",
                                'group' => 1,
                                'active' => true,
                              )
                          );
  if ($newUserId) {
    echo "new user inserted with the id $new_user_id";
  }
```


###binding parameters on queries

Binding parameters is a good way of preventing mysql injections as the parameters are sanitized before execution.

```php
  $result = $db->query("SELECT * FROM users WHERE id_user = ? AND active = ? LIMIT 1",array(11,1));
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
To iterate a resultset you can use any fetch() method listed above

```php
  $result = $db->query("SELECT * FROM users");

  //using while
  while( $row = $result->fetch() ) {
    echo $row->name;
    echo $row->email;
  }

  //using foreach
  foreach( $result->fetchAll() as $row ) {
    echo $row->name;
    echo $row->email;
  }

```

####Logging and Errors

// TODO

Showing the query log. the log comes with the SQL executed, the execution time and the result row count (if any)
```php

  print_r($db->log());

```

to debug mysql errors:

// TODO

use `$db->errors()` to fetch all errors (returns false if no errors) or `$db->lastError()` for information on the last error.

```php
  if( $db->errors() ){
      echo $db->lastError();
  }
```




