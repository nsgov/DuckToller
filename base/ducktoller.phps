<?php
/** DuckToller
 * ducktoller.php
 */

define("DUCKTOLLER_PATH", dirname(__DIR__).'/');

class DuckToller {
	public static $version = "0.1";
	public $config, $path, $log;

	function __construct($config_ini) {
		$this->path = realpath(dirname($config_ini));
		$this->config = parse_ini_file($config_ini, true);
		$this->log = array("DuckToller (" . self::$version . ')');
		date_default_timezone_set($this->config['ducktoller']['timezone']);
	}

	function path($path) {
		if ($path{0}!='/') {
			$basename = basename($path);
			$path = realpath($this->path.'/'.dirname($path)).'/'.$basename;
		}
		return $path;
	}

	function retrieve($cachable) {
		return $cachable->getContent();
	}

	function bark($msg) {
		$this->log[] = $msg;
	}
}