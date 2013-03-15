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
	protected $toller, $_stat, $max_age, $loglabel, $mimetype, $charset;
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
		$toll = '';
		if (!file_exists($this->path_r)) $toll = 'Cache file does not exist';
		elseif ($this->expired()) $toll = 'Cache expired';
		if ($toll) {
			$this->log('toll: ' . $toll);
			if (($cache = @fopen($this->path_w, 'xb'))!=false) {
				try {
					$fetched = $this->fetch($cache);
					if (is_string($fetched))
						fwrite($cache, $fetched, strlen($fetched));
					$this->log('Wrote '.ftell($cache).' bytes to cache');
					fclose($cache);
					if ($fetched && !rename($this->path_w, $this->path_r))
						$this->log('rename failed in Cachable::toll');
					else
						@unlink($this->path_r);
				} catch(Exception $ex) {
					fclose($cache);
					@unlink($this->path_w);
					$this->log($ex->getMessage());
				}
			} else
				$this->log('Cache write in progress by another process.');
		}
	}

	abstract protected function fetch($cache);  // load content fresh from the source

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
		$bytes = filesize($this->path_r);
		if (!headers_sent()) {
			header('Content-Length: '.$bytes);
			ob_end_clean();
		}
		readfile($this->path_r);
	}
}
