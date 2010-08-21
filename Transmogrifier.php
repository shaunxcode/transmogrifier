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
	private $macroCharacter = '~';
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

	private function expandTildeExpressions($code, $scope = false)
	{
		$code = trim($code);
		//this is just so square bracket expression can have sub-functions w/o extra ~ 
		if($code[0] == '[') {
			$code = '~' . $code;
		}
		
		$max = strlen($code);
		
		$brackets = array('[' => ']', '{' => '}', '(' => ')');
		while(($start = strpos($code, $this->macroCharacter)) !== false){
			$pos = $start;
			$open = 0;	      
			$first = false;
			$subIndex = 1;
			$openBracket = false;
			$closeBracket = false;
			$macro = false;
			$priorChar = false;
			
			while($pos < $max) {
				$subIndex++;
				if(!isset($code[++$pos])) break;
				$char = $code[$pos];

				//capture macro and process 
				if($macro) {
					if(!$open && in_array($char, array(';', ',', '.', ':', '?', ']', ')', '}'))) {

						$sub = substr($code, $start, ($pos - $start));
						$replace = $this->processMacro($macro, $scope);

				  		$code = str_replace($sub, $replace, $code);
						$max = strlen($code);
						$macro = false;
						continue;
					}
					
					if(!$openBracket && isset($brackets[$char])) {
						$openBracket = $char;
						$closeBracket = $brackets[$char];
						if($priorChar !== '~') {
							$macro .= '~';
						}
					}
					
					if($char == $openBracket) {
						$open++;
					} else 	if($char == $closeBracket) {
						$open--;
						if($open == 0) {
							$openBracket = false;
						}
					}

					if(!in_array($char, array("\n", ' ', "\r", "\t"))) {
						$priorChar = $char;
					}
					
					$macro .= $char;
					continue;
				}
				
				if(!$first && $char != ' ' && $char != "\n" && $char != "\r" && $char != "\t") {
					$first = $char; 
					$parenStart = $subIndex;
					if(isset($brackets[$first])) {
						$openBracket = $first;
						$closeBracket = $brackets[$first];
					} else {
						$macro = $first;
						continue;
					}
				}

				if($char === $openBracket) {
					$open++;
					continue;
				}

				if($char === $closeBracket) {
					$open--;
					if($open == 0) {
						$sub = substr($code, $start, ($pos-$start)+1);
						switch($openBracket) {
							case '[':
								$replace = $this->processSquareLambda(substr($sub, $parenStart, -1), $scope);
							
								//check for immediate application
								$subPos = $pos;
								$subBracketOpen = false;
								while($subPos < $max) {
									if(!isset($code[++$subPos])) break;
									$subChar = $code[$subPos];

									if(!$subBracketOpen && !in_array($subChar, array('(', "\n", "\t", ' '))) {
										//only acceptable chars are in array above
										break;
									}
									
									if(!$subBracketOpen && $subChar == '(') {
										$subBracketStart = $subPos;
										$subBracketOpen = 1;
										continue;
									}
									
									if($subChar == '(') {
										$subBracketOpen++;
										continue;
									}
									
									if($subChar == ')') {
										$subBracketOpen--;
										if($subBracketOpen == 0) {
											$replace = '(call_user_func_array(' . $replace . ', array' . substr($code, $subBracketStart, ($subPos - $subBracketStart) + 1) . '))';
											$sub = substr($code, $start, ($subPos - $start) + 1);
											continue;
										}
									}
								}
							break;
							
							case '(':
								$replace = $this->process($this->tokenize($this->replaceNativePhp(substr($sub, $parenStart, -1))));
							break;
						}
				  		$code = str_replace($sub, $replace, $code);
						$max = strlen($code);
						break;
					} 
				}

			}
		}

		return str_replace(array(';;', '};'), array(';', '}'), $code);
	}

	private function processMacro($code, $scope = false)
	{
		$brackets = array('[' => ']', '{' => '}', '(' => ')');
		
		$args = array();		
		$max = strlen($code); 
		$pos = 0;
		$open = 0;
		$openBracket = false;
		$closeBracket = false;
		$arg = '';
		while($pos < $max) {
			$char = $code[$pos++];

			$arg .= $char;
						
			if(!$open && isset($brackets[$char])) {
				$openBracket = $char;
				$closeBracket = $brackets[$char];
			}
			
			if($char === $openBracket) {
				$open++;
			}
			
			if($char === $closeBracket) {
				$open--;
				if($open == 0) {
					$args[] = $this->expandTildeExpressions($arg, $scope);
					$arg = '';
				}
			}

			if((!$open && !empty($arg) && in_array($char, array(' ', "\n", "\r", "\t"))) || $pos == $max) {
				$arg = str_replace(' ', '', trim($arg));
				if(!empty($arg)) {
					$args[] = $arg;
				}
				$arg = '';
			}
		}

		$macroName = array_shift($args);		
		/* EVENTUALLY use Transmogrifier::includeFile recursively! so macros are transmogrified and ~macro can be used */
		$macroFile = APPROOT . '/macros/' . $macroName . '.php';
print_r($args);
		if(!file_exists($macroFile)) {
			throw new Exception("Macro $macroName does not exist");
		} else {
			require_once $macroFile;
			return call_user_func_array($macroName . 'Macro', $args);
		}
	}

	private function processSquareLambda($code, $scope = false) 
	{
		$tokens = explode(' ',preg_replace('/\s\s+/', ' ', $code));
		$args = array();
		$hasArgs = false;
		$inBody = false;
		foreach($tokens as $i => $token) {
			if(!$inBody && $token[0] == '$') {
				$args[$token] = $token;
				continue;
			} else if(!$inBody && $token == '|') {
				$hasArgs = true;
				break;
			} else {
				$inBody = true;
			}
		}
		
		if($hasArgs) {
			$parts = explode('|', $code);
			array_shift($parts);
			$body = implode('|', $parts);
		} else {
			$body = $code;
			$args = array();
		}

		if(!$scope) { 
			$scope = (object)array('args' => array());
		} 		

		$body = $this->expandTildeExpressions($body, $scope);

		preg_match_all('/\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $body, $matches); 

		$uses = array();
		if(isset($matches[0])) {
			foreach($matches[0] as $var) {
				if(!isset($args[$var]) && !isset($scope->args[$var])) {
					$uses[$var] = '&' . $var;
				}
			}
		}

		if(empty($args) && strpos($body, '$_') !== false) {
			$args['$_'] = '$_ = false';
			if(isset($uses['$_'])) {
				unset($uses['$_']);
			}
		}
		
		$scope->args = array_merge($args, $scope->args);
		
		return $function = '(function(' . implode(',', $args) . ') ' . (empty($uses) ? '' : 'use(' . implode(',', $uses). ')') .' {return ' . $body . ";})";
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
						array('(', ')', $this->macroCharacter, '@', ',' , ':', "\n", "\t", '[', ']', '{', '}', '|'), 
						array(' ( ', ' ) ', ' ' . $this->macroCharacter . ' ', ' @ ', ' , ', ' : ', ' ', ' ', ' [ ', ' ] ', ' { ', ' } ', ' | '), 
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