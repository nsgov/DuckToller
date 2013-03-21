<?php

require_once('../base/ducktoller.phps');
require_once('./twitterfeed.phps');
require_once('./twitteravatar.phps');

$ducktoller = new DuckToller("../ducktoller.ini");
$duck = null;

if (isset($_GET['feed']))
	$duck = new TwitterFeed($ducktoller, $_GET['feed']);
elseif (isset($_GET['avatar']))
	$duck = new TwitterAvatar($ducktoller, $_GET['avatar']);

if ($duck)
	$ducktoller->retrieve($duck);
