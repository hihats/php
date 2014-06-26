<?php
if(isset($argv[1])){
	$file = $argv[1];
}

if(file_exists($file)){
	$contents = file_get_contents($file);
}else{
	echo "File doesn't exist".PHP_EOL;
	exit;
}
$list = array_filter(explode(PHP_EOL, $contents));

natsort($list);

$path = pathinfo($file);
if(!file_put_contents($path['dirname'].'/sorted_'.$path['filename'].'.'.$path['extension'], sprintf('%s', implode(PHP_EOL.PHP_EOL.PHP_EOL, $list)))){
	echo "Failed to put contents in {$path['dirname']}/sorted_{$path['filename']}.{$path['extension']}";
}
//print_r(implode(PHP_EOL,$list));

