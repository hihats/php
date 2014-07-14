<?php 
/**
 * Unimplemented function list under old version PHP envirionment
 */


/**
 * http://jp2.php.net/manual/en/function.array-column.php
 */
if(version_compare(phpversion(), "5.5.0", '<')){
	function array_column($array, $column){
		$ret = array();
		foreach($array as $row) $ret[] = $row[$column];
		return $ret;
	}
}

/**
 * http://jp2.php.net/manual/en/function.json-encode.php
 */
if(version_compare(phpversion(), "5.2.0", '<')){
	function json_encode($val){
		if (is_int($val)||is_float($val)) return $val;
		if (is_bool($val)) return $val?'true':'false';
		if (is_array($val)) {
			$len = count($val);
			for($i=0; $i<$len && isset($val[$i]); ++$i) {}
			$temp = array();
			if($len==$i) {
				for($j=0;$j<$len;$j++) {
					$temp[] = sprintf("%s", json_encode($val[$j]));
				}
				return '['. implode(",",$temp) .']';
			} else {
				foreach($val as $key => $item) {
					$temp[] = sprintf("%s:%s", json_encode($key), json_encode($item));
				}
				return '{'. implode(",", $temp) .'}';
			}
 		}
		// else string
		static $from = array('\\',  "\n", "\r", '"');
		static $to   = array('\\\\','\\n','\\r','\\"');
		static $cmap = array(0x80, 0xFFFF, 0, 0xFFFF);
		return '"'. preg_replace_callback(
		'/&#([0-9]+);/',
		create_function('$match','return sprintf("\\u%04x", $match[1]);'),
		mb_encode_numericentity(str_replace($from, $to, $val), $cmap, 'UTF-8')
		) . '"';
 	}
}

