<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright Province of Nova Scotia, 2013
 */

/** The path that other PHP files can use to find DuckToller files */
define("DUCKTOLLER_PATH", dirname(__DIR__).'/');

require_once(DUCKTOLLER_PATH.'base/config.phps');
require_once(DUCKTOLLER_PATH.'base/cachable.phps');
require_once(DUCKTOLLER_PATH.'base/log.phps');

class DuckToller {
	public static $version = "0.2";
	public $config, $log, $timezone;

	function __construct($config_ini) {
		$this->config = Config::load($config_ini, 'DuckToller');
		$this->log = new Log($this, 'DuckToller');
		$this->log->info(self::$version);
		$timezone = $this->config->get('timezone', 'UTC');
		date_default_timezone_set($timezone);
		$this->timezone = new DateTimeZone($timezone);
	}

	function retrieve(Cachable $duck) {
		$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
		$showcontent = ($method == 'GET');
		$duck->init();
		$duck->toll();
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) try {
			$lm = $duck->lastModified() - 0;
			$since = new DateTime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
			if ($lm && ($since->getTimestamp() >= $lm)) {
				header('HTTP/1.1 304 Not Modified', true, 304);
				$showcontent = false;
			}
		} catch (Exception $ex) {
			$this->log->warn('Checking If-Modified-Since: '.$ex->getMessage());
		}
		if (headers_sent()) {  # something bad happened
			echo "<pre>Log:\n";
			print_r($this->log);
			echo "</pre>";
		} else {
			$duck->serveHeaders();
			header('X-DuckToller-Log: '.implode(",\n ", $duck->getLogs(Log::$INFO)));
			if ($showcontent)
				$duck->serveContent();
		}
	}

	/**
	 * Check allow-origin config option against Origin or Referer header.
	 * @throws Exception when allow-origin setting doesn't match origin/referrer.
	 */
	function checkOrigin() {
		$allow = false;
		$allowable = strtolower(trim($this->config->get('allow-origin')));
		if ($allowable == '*')
			$allow = $origin = '*';
		else {
			if (isset($_SERVER['HTTP_ORIGIN']))
				$origin = parse_url($_SERVER['HTTP_ORIGIN']);
			else
				$origin = parse_url($_SERVER['HTTP_REFERER']);
			if (isset($origin['host'])) {
				$origin = $origin['host'];
				foreach (explode(' ', $allowable) as $a) {
					if (fnmatch($a, $origin)) {
						$allow = $origin;
						break;
					}
				}
			}
		}
		if ($allow)
			header('Access-Control-Allow-Origin: ' . $origin);
		#else {
		#	header('Status: 403 Forbidden', true, 403);
		#	throw new Exception('DuckToller::checkOrigin denied access.');
		#}
	}
}
