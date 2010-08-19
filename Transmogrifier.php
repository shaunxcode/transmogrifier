<?php

function arraySplice($array, $pos, $len, $value) {
	array_splice($array, $pos, $len, $value);
	return $array;
}

function arrayAt($array, $key) {
	return $array[$key];
}

class Transmogrifier
{
	private $phpCode;
	private $strings = array();

	public function __construct($code)
	{
		$this->phpCode = $this->expandTildeExpressions($this->stripComments($this->stripStrings($code)));
	}

	public static function includeFile($dir, $filename)
	{
		$tdir = $dir . '/.transmogrified';
		$tfile = $tdir . '/' . $filename;
		if(!file_exists($tdir)) {
			mkdir($tdir);
		}
		
		$T = new Transmogrifier(file_get_contents($dir . '/' . $filename));
		file_put_contents($tfile, $T->asPhp()); 
		
		require_once $tfile;
	}
	
	public function stripStrings($code) 
	{
		foreach(array('"', "'") as $quoteType) {		
			$max = strlen($code);
			while(($start = strpos($code, $quoteType)) !== false) {
				$end = false;
				$pos = $start;
				$skip = false;
	      
				while(!$end || $pos < $max) {
					$char = $code[++$pos];
					if($char == $quoteType && !$skip){
						$sub = substr($code, $start, ($pos - $start) + 1);
						$key = '__STRING__' . count($this->strings);
						$code = str_replace($sub, $key, $code);
						$this->strings[$key] = $sub;
						$end = $pos;
						break;
					}
					$skip = ($char == '\\');
				}
			}
		}
		return $code;
	}

	private function stripComments($code)
	{
		return $code;
	}
	
	public function asPhp()
	{
		return str_replace(array_keys($this->strings), $this->strings, $this->phpCode);
	}

	private function expandTildeExpressions($code)
	{
		$max = strlen($code);
		
		while(($start = strpos($code, '~')) !== false){
			$end = false;
			$pos = $start;
			$open = 0;	      
			$first = false;
			$subIndex = 1;
			
			while(!$end || $pos < $max) {
				$subIndex++;
				$char = $code[++$pos];
				if(!$first && $char != ' ' && $char != "\n" && $char != "\r" && $char != "\t") {
					$first = $char; 
					$parenStart = $subIndex;
				}

								
				if($char == '(') {
					$open++;
					continue;
				}

				if($char == ')') {
					$open--;
					if($open == 0) {
						$sub = substr($code, $start, ($pos-$start)+1);						
						$code = str_replace($sub, $this->process($this->tokenize($this->replaceNativePhp(substr($sub, $parenStart, -1)))), $code);
						$end = $pos;
						break;
					} 
				}

			}
		}
		return $code;
	}

	private function replaceNativePhp($code)
	{
		$max = strlen($code);
		
		while(($start = strpos($code, '{')) !== false){
			$end = false;
			$pos = $start;
			$open = 1;

			while(!$end || $pos < $max) {
				$char = $code[++$pos];
				if($char == '{') {
					$open++;
					continue;
				}

				if($char == '}') {
					$open--;
					if($open == 0) {
						$sub = substr($code, $start, ($pos-$start)+1);
						$key = '__NATIVE__' . count($this->strings);
						$code = str_replace($sub, $key, $code);
						$this->strings[$key] = $this->expandTildeExpressions(substr($sub, 1, -1));
						$end = $pos;
						break;												
					}
				}
			}
		}
		return $code;
	}
	
	private function tokenize($code)
	{
		return explode(
			' ', 
			trim(
				preg_replace(
					'/\s\s+/', 
					' ', 
					str_replace(
						array('(', ')', '~', '@', ',' , ':', "\n", "\t"), 
						array(' ( ', ' ) ', ' ~ ', ' @ ', ' , ', ' : ', ' ', ' '), 
						$code))));
	}

	private function toPrimitive($node)
	{
		return is_null($node) ? 
			'null' : 
			($node['type'] == 'scalar' ? 
				(is_numeric($node['value']) ? $node['value'] : "'{$node['value']}'") : 
				$node['value']);
	}
	
	private function process($tokens, $isArray = false)
	{
		$arrayValue = array();
		$spliceList = array();
		$index = 0;
		foreach($tokens as $i => $char)
		{
			if(!isset($array) && $char == '(') {
				$array = array();
				$parenCount = 1;
				continue;
			}
			
			if(isset($array)) {
				if($char == '(') {
					$parenCount++;
					if($parenCount > 1) {
						$array[] = $char;
					}
					continue;
				}
				
				if($char == ')') {
					$parenCount--;
					if($parenCount < 1) {
						$char = array('type' => 'array', 'value' => $this->process($array, true));
						unset($array);
					} else {
						$array[] = $char;
						continue;
					}
				} else {
					$array[] = $char;
					continue;
				}
			}

			if($char == ',') {
				$unquote = true;
				continue;
			}

			if(isset($unquote) && $char == '@') {
				$splice = true;
				continue;
			}

			if($char == ':') {
				continue;
			}
				
			if(!is_array($char)) {
				if(strpos($char, '__NATIVE__') === 0) {
					$char = array('type' => 'native', 'value' => '(' . $this->strings[$char] . ')');
				} else if(isset($unquote)) {
					$char = array('type' => 'variable', 'value' => $char[0] == '$' ? $char : ('$' . $char));
					unset($unquote);
				} else if(strpos($char, '__STRING__') === 0) {
					$char = array('type' => 'string', 'value' => $this->strings[$char]);
				} else if(!is_null($char)) {
					$char = array('type' => 'scalar', 'value' => $char);
				}
			}
					
			if(isset($splice)) {
				$spliceList[$index] = $char;
				$char = null;
				unset($splice);
			}

			if(isset($tokens[$i + 1]) && $tokens[$i + 1] == ':') {
				$keyword = $char;
				continue;
			}

			if(isset($keyword)) {
				$char = array('type' => 'keyvalue', 'key' => $keyword, 'value' => $char); 
				unset($keyword);
			}

			$arrayValue[$index++] = $char;	
		}

		foreach($arrayValue as $index => $node) {
			if(is_array($node) && $node['type'] == 'keyvalue') {
				$arrayValue[$index] = $this->toPrimitive($node['key']) . ' => ' . $this->toPrimitive($node['value']);
			} else {
				$arrayValue[$index] = $this->toPrimitive($node);
			}
		}

		$arrayValue = 'array(' . implode(', ', $arrayValue) . ')';

		if(!empty($spliceList)) {
			$spliceList = array_reverse($spliceList, true);
			foreach($spliceList as $atIndex => $value) {
				$arrayValue = 'arraySplice(' . $arrayValue . ", $atIndex, 1, " . $this->toPrimitive($value) . ')';
			}
		}

		return '(' . $arrayValue . ')';
	}
}