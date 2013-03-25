<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

require_once(__DIR__.'/cachecontrol.phps');

/**
 * Cachable
 * An abstract base class for all things that can be cached.
 */
abstract class Cachable {
	protected $toller, $config, $loglabel;
	protected $cache_control, $mimetype, $charset, $last_modified;
	protected $content_path_r, $content_path_w, $meta_path_r, $meta_path_w;
	public static $DATE_HTTP = 'D, d M Y H:i:s \G\M\T';

	function __construct(DuckToller $toller, $basename, $ext='.data', $meta_ext='.meta') {
		$this->toller = $toller;
		$this->config = $toller->config;
		$this->loglabel = $basename;
		$this->content_path_r = $basename.$ext;
		$this->meta_path_r = $basename.$meta_ext;
	}

	function init() {
		$this->log = new Log($this->toller, $this->loglabel, $this->toller->log);
		$path = $this->config->getCachePath() . DIRECTORY_SEPARATOR;
		$this->content_path_w = $path.'.'.$this->content_path_r;
		$this->content_path_r = $path.$this->content_path_r;
		$this->meta_path_w = $path.'.'.$this->meta_path_r;
		$this->meta_path_r = $path.$this->meta_path_r;
		$this->cache_control = new CacheControl($this->config->get('cache_control'));
	}

	function lastModified() {
		return $this->last_modified;
	}

	function getContentType() {
		$ct = $this->mimetype;
		if ($this->charset)
			$ct .= '; charset=' . $this->charset;
		return $ct;
	}

	function age() {
		$mtime = (file_exists($this->meta_path_r)) ? filemtime($this->meta_path_r) : 0;
		return $mtime ? time() - $mtime : 0;
	}

	function expired($age) {
		return $age > 86400;
	}

	function getMaxAge($fallback) {
		$cc = $this->cache_control;
		return $cc ? $cc->get('s-maxage', $cc->get('max-age', $fallback)) : $fallback;
	}

	function shouldRevalidate() {
		$reason = FALSE;
		if (!file_exists($this->content_path_r))
			$reason = 'Cache file does not exist.';
		elseif (!file_exists($this->meta_path_r))
			$reason = 'Cache meta data missing';
		else {
			$age = $this->age();
			if (($max_age = $this->getMaxAge(-1)) > -1) {
				if ($age > $max_age)
					$reason = "Cache age > max-age ($age > $max_age)";
				else
					$this->log->info("Cache age <= max-age ($age <= $max_age)");
			}
			if ((!$reason) && ($reason = $this->expired($age))===TRUE)
				$this->log->info('Cache expired');
		}
		if (is_string($reason))
			$this->log->info($reason);
		return $reason != FALSE;
	}

	function toll() {
		if ($this->shouldRevalidate()) {
			$metafile = null;
			if (($cache = @fopen($this->content_path_w, 'xb'))) {
				try {
					if (!($metafile = @fopen($this->meta_path_w, 'wb')))
						throw new Exception('Unable to open meta data write file.');
					$lastmod = $this->last_modified;
					$fetched = $this->fetch($cache, $metafile);
					if (is_string($fetched))
						fwrite($cache, $fetched, strlen($fetched));
					fflush($cache);
					$bytes = ftell($cache);
					fclose($cache);
					fclose($metafile);
					$cache = $metafile = null;
					if (!$fetched) {
						$this->log->info('Fetch received no new content');
						@touch($this->meta_path_r);
					} elseif (!@rename($this->meta_path_w, $this->meta_path_r)) {
						@touch($this->meta_path_r);
						throw new Exception('rename metadata failed in Cachable::toll');
					} elseif (@rename($this->content_path_w, $this->content_path_r)) {
						clearstatcache();
						$this->log->debug('Wrote '.$bytes.' bytes to cache');
					} else
						throw new Exception('rename content failed in Cachable::toll');
					if (!$fetched) {
						unlink($this->content_path_w);
						unlink($this->meta_path_w);
					}
					if (($this->last_modified != $lastmod) && ($this->last_modified > 0))
						@touch($this->content_path_r, $this->last_modified);
					else
						$this->last_modified = time();
				} catch(Exception $ex) {
					if ($cache) fclose($cache);
					if ($metafile) fclose($metafile);
					@unlink($this->content_path_w);
					@unlink($this->meta_path_w);
					$this->log($ex->getMessage());
				}
			} elseif (is_writable($this->content_path_w))
				$this->log->info('Cache write in progress by another process');
			else
				$this->log->error('Unable to create cache file');
		}
		if (!$this->last_modified)
			$this->last_modified = @filemtime($this->content_path_r);
	}

	abstract protected function fetch($cache, $meta);  // load content fresh from the source

	function getLogs($level) {
		return $this->log->getEntries($level);
	}

	function serveHeaders() {
		header('Content-type: '.$this->getContentType());
		$lm = $this->lastModified();
		if ($lm)
			header('Last-Modified: '.gmdate(Cachable::$DATE_HTTP, $lm));
		if ($this->cache_control)
			$this->cache_control->setHeader();
	}

	function serveContent() {
		$bytes = filesize($this->content_path_r);
		if (!headers_sent()) {
			header('Content-Length: '.$bytes);
			ob_end_clean();
		}
		readfile($this->content_path_r);
	}
}
