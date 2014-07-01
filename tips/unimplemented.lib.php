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
		foreach($array as $row) $ret = $row[$column];
		return $ret;
	}
}
