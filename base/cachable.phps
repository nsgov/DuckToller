<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

/**
 * Cachable
 * An abstract base class for all things that can be cached.
 */
abstract class Cachable {
	protected $toller, $_stat, $max_age, $loglabel, $mimetype, $charset, $content;
	protected $path_r, $path_w;
	public static $DATE_HTTP = 'D, d M Y H:i:s \G\M\T';

	function __construct($toller, $cachefile) {
		$s = DIRECTORY_SEPARATOR;
		$this->toller = $toller;
		$this->path_r = $cachefile;
		$this->path_w = dirname($cachefile)."$s.".basename($cachefile);
		$this->loglabel = basename($cachefile);
	}

	function stat($field=null) {
		if (!$this->_stat)
			!$this->_stat = @stat($this->path_r);
		return ($this->_stat && $field) ? $this->_stat[$field] : $this->_stat;
	}

	function lastModified() {
		return $this->stat('mtime');
	}

	function getContentType() {
		$ct = $this->mimetype;
		if ($this->charset)
			$ct .= '; charset=' . $this->charset;
		return $ct;
	}

	function age() {
		return time() - $this->lastModified();
	}

	function expired() {
		return $this->age() > $this->max_age;
	}

	function toll() {
		if (!$this->content || $this->expired())
			$this->load();
	}

	protected function load() {
		$this->content = '';
		$age = $this->age();
		$expired = $this->expired();
		$this->log("Cache age: $age seconds" . ($expired?' (expired)':''));
		if ($expired || !$this->stat('size')) {
			$write = @fopen($this->path_w, 'xb');
			if ($write) {
				try {
					$this->content = $this->fetch($cache);
					$this->writeCache($write);
					fflush($write);
					if (!rename($this->path_w, $this->path_r))
						$this->log('rename failed in Cachable::load');
				} catch(Exception $ex) {
					$this->log($ex->getMessage());
				}
				fclose($write);
			} else
				$this->log('write cache already exists, another process must be updating');
		}
		if (!$this->content) {
			$this->log('Loading from cache');
			$this->loadFromCache();
		}
	}

	protected function writeCache($cache) {
		$len = strlen($this->content);
		if ($len) {
			$this->log("Writing $len bytes to cache");
			$written = fwrite($cache, $this->content, $len);
			$this->log("Wrote $written bytes");
		} else
			$this->log('Received empty content');
	}

	abstract protected function fetch($cache);  // load content fresh from the source into $this->content

	protected function loadFromCache() {
		$this->content = @file_get_contents($this->path_r);
	}

	protected function log($msg) {
		$this->toller->bark($this->loglabel . ": " . $msg);
	}

	function serveHeaders() {
		header('Content-type: '.$this->getContentType());
		$lm = $this->lastModified();
		if ($lm)
			header('Last-Modified: '.gmdate(Cachable::$DATE_HTTP, $lm));
		if ($this->max_age)
			header('Cache-Control: max-age='.$this->max_age-0);
	}

	function serveContent() {
		echo $this->content;
	}
}
