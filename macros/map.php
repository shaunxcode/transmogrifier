<?php

function mapMacro($func, $array) {	
	return array('array_map(', $func, ',', $array, ')');
}
