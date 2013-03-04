<?php
/**
 * DuckToller | cachable.phps
 * © 2013 Province of Nova Scotia
 */

/**
 * Cachable
 * An abstract base class for all things that can be cached.
 */
abstract class Cachable {
	protected $toller, $_stat, $path, $max_age, $loglabel, $content;

	function __construct($toller, $cachefile) {
		$this->toller = $toller;
		$this->path = $cachefile;
		$this->loglabel = basename($cachefile);
	}

	function stat($field=null) {
		if (!$this->_stat)
			!$this->_stat = @stat($this->path);
		return ($this->_stat && $field) ? $this->_stat[$field] : $this->_stat;
	}

	function lastModified() {
		return $this->stat('mtime');
	}

	function mimetype() {
		return "text/plain";
	}

	function age() {
		return time() - $this->lastModified();
	}

	function expired() {
		return $this->age() > $this->max_age;
	}

	function getContent() {
		if (!$this->content || $this->expired())
			$this->lockAndLoad();
		return $this->content;
	}

	protected function lockAndLoad() {
		$this->content = '';
		$cache = fopen($this->path, 'c+b');
		if (!$cache)
			throw new Exception("Could not open cache file");
		$age = $this->age();
		$this->log("Cache age: $age seconds");
		if ($this->expired() || !$this->stat('size')) {
			if (flock($cache, LOCK_EX|LOCK_NB)) {
				try {
					$this->fetch($cache);
					$this->writeCache($cache);
				} catch(Exception $ex) {
					$this->log($ex->getMessage());
				}
				flock($cache, LOCK_UN);
			} else
				$this->log("Unable to get exclusive lock");
		}
		if (!$this->content) {
			$this->log("Loading from cache");
			flock($cache, LOCK_SH);
			/*	refresh stat after getting lock,
				just in case flock was waiting for another LOCK_EX to release,
				in which case the file was just updated	*/
			$this->_stat = null;
			$this->stat();
			$this->loadFromCache($cache);
			flock($cache, LOCK_UN);
		}
		fclose($cache);
	}

	protected function writeCache($cache) {
		$len = strlen($this->content);
		if ($len) {
			$this->log("Writing $len bytes to cache (".substr($this->content, 0, 10).")");
			fseek($cache, 0);
			ftruncate($cache, 0);
			$written = fwrite($cache, $this->content, $len);
			$this->log("Wrote $written bytes");
		} else
			$this->log("Received empty content");
	}

	abstract protected function fetch($cache);  // load content fresh from the source into $this->content

	protected function loadFromCache($cache) {
		$bytes = $this->stat('size');
		if ($bytes)
			$this->content = fread($cache, $bytes);
	}

	protected function log($msg) {
		$this->toller->bark($this->loglabel . ": " . $msg);
	}
}
