<?php

require_once('../base/ducktoller.phps');

$ducktoller = new DuckToller("../ducktoller.ini");
$duck = null;

if (isset($_GET['thumb'])) {
	require_once(DUCKTOLLER_PATH.'youtube/thumb.phps');
	$duck = new YoutubeThumbnail($ducktoller, $_GET['thumb']);
}

if ($duck)
	$ducktoller->retrieve($duck);
