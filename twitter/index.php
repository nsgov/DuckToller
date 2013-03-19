<?php

require_once('../base/ducktoller.phps');
require_once('./tweetcache.phps');
require_once('./twitteravatar.phps');

$ducktoller = new DuckToller("../ducktoller.ini");
$duck = null;

if (isset($_GET['feed'])) {
	$keysfile = 'keys.ini.php';
	$keys = parse_ini_file($keysfile);
	foreach($keys as $name => $key)
		if (!$key)
			throw new Exception("Don't forget your keys! $name needs to be set in $keysfile.");

	$duck = new TweetCache($ducktoller, $keys, $_GET['feed']);
} elseif (isset($_GET['avatar'])) {
	$duck = new TwitterAvatar($ducktoller, $_GET['avatar']);
}
if ($duck)
	$ducktoller->retrieve($duck);
