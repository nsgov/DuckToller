<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright Province of Nova Scotia, 2013
 */

/** The path that other PHP files can use to find DuckToller files */
define("DUCKTOLLER_PATH", dirname(__DIR__).'/');

require_once(DUCKTOLLER_PATH.'base/cachable.phps');

class DuckToller {
	public static $version = "0.1";
	public $config, $path, $log;

	/** The cachable object to operate on */
	protected $duck;
	protected $content;

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

	/** Assign the Cachable object for this DuckToller to operate on.
	 * @return DuckToller return this DuckToller instance.
	 */
	function toll(Cachable $c) {
		$this->duck = $c;
		return $this;
	}

	function retrieve() {
		$this->content = $this->duck->getContent();
		return $this;
	}

	/** Output the assigned cachable object, and set related HTTP headers.
	 * @return DuckToller return this DuckToller instance.
	 */
	function deliver() {
		header('Content-type: ' . $this->duck->mimetype);
		echo $this->content;
	}

	function bark($msg) {
		$this->log[] = $msg;
	}

	/**
	 * @todo Check allow-origin config option against Origin or Referer header
	 */
	function checkOrigin() {
		$allow = $this->config["allow-origin"];
		if (isset($_SERVER['HTTP_ORIGIN']))
			$origin = parse_url($_SERVER['HTTP_ORIGIN']);
	}
}
