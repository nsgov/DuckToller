<?php

require_once('../base/ducktoller.phps');
require_once('./tweetcache.phps');

try {
	$ducktoller = new DuckToller("../ducktoller.ini");
	$keysfile = 'keys.ini.php';


	if (isset($_GET['feed'])) {
		$keys = parse_ini_file($keysfile);
		foreach($keys as $name => $key)
			if (!$key)
				throw new Exception("Don't forget your keys! $name needs to be set in $keysfile.");

		$tweetcache = new TweetCache($ducktoller, $keys, $_GET['feed']);
		$ducktoller->retrieve($tweetcache);
	}
} catch(Exception $ex) {
	header("Status: 500 Internal Server Error");
	echo '<p>' . htmlspecialchars($ex->getMessage()) . "</p>\n";
}
?>
<pre>
<?php echo htmlspecialchars(implode("\n", $ducktoller->log)) . "\n"; ?>
</pre>
