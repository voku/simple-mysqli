<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/Foobar.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

/*
CREATE DATABASE mysql_test;
USE mysql_test;
CREATE TABLE test_page (
  page_id int(16) NOT NULL auto_increment,
  page_template varchar(255),
  page_type varchar(255),
  PRIMARY KEY (page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
