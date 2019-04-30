<?php

require __DIR__."/../vendor/autoload.php";

use Process\Process;

try
{
	$process = new Process($argv);
	$process->run();
}
catch(\Exception $e)
{
	echo $e->getMessage();
	echo "\n";
}

?>