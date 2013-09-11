<?php

require_once('../base/ducktoller.phps');

$ducktoller = new DuckToller("../ducktoller.ini");
$duck = null;

if (isset($_GET['feed'])) {
	require_once('./twitterfeed.phps');
	$duck = new TwitterFeed($ducktoller, $_GET['feed']);
} elseif (isset($_GET['avatar'])) {
	require_once('./twitteravatar.phps');
	$duck = new TwitterAvatar($ducktoller, $_GET['avatar']);
} elseif (isset($_GET['xslt'])) {
	require_once(DUCKTOLLER_PATH.'base/staticfile.phps');
	$duck = new StaticFile($ducktoller, './', $_GET['xslt'].'.xslt', 'application/xslt+xml', 'utf-8');
}

if ($duck)
	$ducktoller->retrieve($duck);
