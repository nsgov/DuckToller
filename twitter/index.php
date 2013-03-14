<?php

require_once('../base/ducktoller.phps');
require_once('./tweetcache.phps');
require_once('./twitteravatar.phps');

$ducktoller = new DuckToller("../ducktoller.ini");
$duck = null;

$url = array('http', '://', $_SERVER['HTTP_HOST']);
$port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT']-0 :80;
if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS']=='on')) {
	$url[0] = 'https';
	if ($port != 443)
		$url[] = ':' . $_SERVER['SERVER_PORT'];
} elseif ($port != 80)
		$url[] = ':' . $_SERVER['SERVER_PORT'];
$url[] = $_SERVER['SCRIPT_NAME'] . '?avatar={screen_name}';
$ducktoller->config['twitter']['avatar_url'] = implode($url);

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
	$ducktoller->toll($duck)->retrieve()->deliver();

#echo join("\n", $ducktoller->log);
