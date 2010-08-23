<?php

define('__', false); 

function map($fn, $array) {
  return array_map($fn, $array);
}

function filter($fn, $array) {
  return array_filter($fn, $array);
}

function reduce() {
  $args = func_get_args();
  $fn = array_shift($args);
  if(count($args) > 1) {
    $init = array_shift($args);
    $array = array_shift($args);
  } else {
    $init = null;
    $array = array_shift($args);
  }
  return array_reduce($array, $fn, $init);
}

function meditations() {
  foreach((~argHash) as $description => $result) {
    echo $description . ': ' 
      . ($result ? '<span style="color:#00ff00">&#8730;</span>' : '<span style="color:#ff0000;">X</span>') . '<hr>';
  }
}

meditations(
  "The map function relates a sequence to another",
  ~(__ __ __) == map(~[4 * $_], ~(1 2 3)), 

  "You may create that mapping", 
  ~(1 4 9 16 25) == map(~[__], ~(1 2 3 4 5)),

  "Or use the names of existing functions",
  __ == map('is_null', ~(a b {null} c d)),

  "A filter can be strong",
  __ == filter(~[false], ~(anything goes here)),

  "Or very weak",
  ~(anything goes here) == filter(~[__], ~(anything goes here)),

  "Or somewhere in between",
  ~(10 20 30) == filter(~[__], ~(10 20 30 40 50 60 70 80)),

  "Maps and filters may be combined", 
  ~(10 20 30) == map(~[$_], filter(~[$_], ~(1 2 3 4 5 6 7 8))),

  "Reducing can increase the result",
  __ == reduce(~[$a, $b | $a * $b], ~(1 2 3 4)), 

  "You can start somewhere else",
  2400 == reduce(~[$a, $b | $a * $b], __, ~(1 2 3 4)),

  "Numbers are not the only things one can reduce",
  'longest' == reduce(~[$a, $b | __ < __ ? $b : $a], ~(which word is longest))
);
