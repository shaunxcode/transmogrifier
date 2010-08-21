<?php

function mapMacro($func, $array) {	
	return implode('', array('array_map(', $func, ',', $array, ')'));
}
