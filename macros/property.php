<?php

function propertyMacro($name) {
	return implode(";\n", array(
		'public $' . $name, 
		'public function get' . ucfirst($name) . '(){ return $this->' . $name . ';}',
		'public function set' . ucfirst($name) . '($value){ $this->' . $name . ' = $value; return $this;}'));
}