<?php

require_once('../base/ducktoller.phps');
require_once('./tweetcache.phps');

$ducktoller = new DuckToller("../ducktoller.ini");

if (isset($_GET['feed'])) {
	$keysfile = 'keys.ini.php';
	$keys = parse_ini_file($keysfile);
	foreach($keys as $name => $key)
		if (!$key)
			throw new Exception("Don't forget your keys! $name needs to be set in $keysfile.");

	$tweetcache = new TweetCache($ducktoller, $keys, $_GET['feed']);
	$ducktoller->toll($tweetcache)->retrieve()->deliver();
}
