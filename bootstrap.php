<?php

require_once 'Transmogrifier.php';
define('APPROOT', dirname(__FILE__));

function __autoload($className)
{
	Transmogrifier::includeFile(APPROOT . '/classes', $className . '.php');
}
