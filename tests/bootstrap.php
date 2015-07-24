<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/voku/db/DB.php';
require_once dirname(__DIR__) . '/src/voku/db/Result.php';

/*
CREATE DATABASE mysql_test;
USE mysql_test;
CREATE TABLE test_page (
  page_id int(16) NOT NULL auto_increment,
  page_template varchar(255),
  page_type varchar(255),
  PRIMARY KEY (page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
*/