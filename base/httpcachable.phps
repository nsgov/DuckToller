<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

class HttpCachable extends Cachable {
	protected $url, $header_path_r, $header_path_w, $headers;
	protected $_expired, $_lastmod, $cache_control;

	public function __construct(DuckToller $toller, $cachefile, $url) {
		parent::__construct($toller, $cachefile);
		$this->url = $url;
		$this->min_age = max($toller->config['http']['min_age']-0, 1);
		$this->max_age = max($toller->config['http']['max_age']-0, $this->min_age);
		$this->header_path_r = dirname($cachefile).'/.'.basename($cachefile).'.http';
		$this->header_path_w = $this->header_path_r . '2';
		$this->loadHeaders();
	}

	protected function loadHeaders() {
		$lines = @file($this->header_path_r);
		$this->headers = array();
		if ($lines) {
			$this->load('Loading http headers');
			foreach ($lines as $line) {
				$m = array();
				if (preg_match('/^([-\w]+):\s*(.+)\s*$/', $line, $m))
					$this->headers[strtoupper($m[1])] = $m[2];
			}
		}
		$ct = $this->getHeader('CONTENT-TYPE');
		if ($ct) {
			$ct = preg_split('/\s*;\s*/', $ct);
			$this->mimetype = $ct[0];
		}
		$this->cache_control = $this->loadCacheControl($this->getHeader('CACHE-CONTROL',''));
		$this->calcLastMod();
		$this->calcExpiry();
	}

	public function getHeader($key, $fallback=null) {
		$key = strtoupper($key);
		return isset($this->headers[$key]) ? $this->headers[$key] : $fallback;
	}

	protected function loadCacheControl($header) {
		$parts = preg_split('/\s*;\s*/', strtolower($header));
		$cc = array();
		foreach ($parts as $p) {
			$m = array();
			if (preg_match('/^(\w+)\s*=\s*(.*)$/', $p, $m))
				$cc[$m[1]] = $m[2];
			else
				$cc[$p] = true;
		}
		return $cc;
	}

	protected function checkCacheControl($key, $fallback=false, $src=null) {
		$cc = $src ? $src : $this->cache_control;
		return isset($cc[$key]) ? $cc[$key] : $fallback;
	}

	protected function calcLastMod() {
		$lm = $this->getHeader('Last-Modified');
		if ($lm) try {
			$dt = new DateTime($lm);
			$lm = $dt->getTimestamp();
		} catch(Exception $ex) {
			$this->log('Invalid Last-Modified value in header cache');
			$lm = 0;
		}
		if (!$lm)
		    $lm = $this->stat('mtime');
		$this->_lastmod = $lm ? $lm : 0;
	}
	public function lastModified() {
		return $this->_lastmod;
	}

	protected function calcExpiry() {
		$reason = null;
		$now = time();
		$mtime = $this->stat('mtime') - 0;
		$http_age = $now - $mtime;
		$max_age = $this->checkCacheControl('s-maxage', $this->checkCacheControl('max-age', $this->max_age));
		if ($http_age > $this->min_age) {
			if ($this->url != $this->getHeader('X-URL'))
				$reason = 'URL has changed';
			elseif ($http_age > $max_age)
				$reason = "max_age exceeded ($http_age > $max_age)";
			else try {
				$expiry = $this->getHeader('Expiry');
				if ($expiry) {
					$dt = new DateTime($expiry);
					if ($dt->getTimestamp() < $now)
						$reason = "Expiry date reached ($expiry)";
				}
			} catch(Exception $ex) {
				$reason = $ex->getMessage();
			}
		}
		if ($reason)
			$this->log('Expired: ' . $reason);
		$this->_expired = ($reason != null);
	}
	public function expired() {
		return $this->_expired;
	}

	protected function fetch($cache) {
		$this->log('Fetching ' . $this->url);
		$curl = curl_init($this->url);
		$curl_ver = curl_version();
		$ua = 'DuckToller/'.DuckToller::$version.' (curl '.$curl_ver['version'].')';
		$header_cache = @fopen($this->header_path_w, 'wb');
		if (!header_cache)
			throw new Exception('Could not open header cache file for writing');
		$url = 'X-URL: ' . $this->url . "\n\n";
		$timeout = isset($this->toller->config['http']['timeout']) ? $this->toller->config['http']['timeout']-0 : 0;
		fwrite($header_cache, $url, strlen($url));
		curl_setopt_array($curl, array(
			CURLOPT_CONNECTTIMEOUT => $timeout ? $timeout : 9,
			CURLOPT_FAILONERROR => TRUE,
			CURLOPT_FILETIME => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_REFERER => $_SERVER['HTTP_REFERER'],
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_TIMECONDITION => $this->lastModified(),
			CURLOPT_USERAGENT => $ua,
			CURLOPT_WRITEHEADER => $header_cache
		));
		$content = curl_exec($curl);
		fclose($header_cache);
		curl_close($curl);
		return $content;
	}

	protected function writeCache($cache) {
		if (@rename($this->header_path_w, $this->header_path_r)) {
			parent::writeCache($cache);
			$this->loadHeaders();
		} else {
			@unlink($this->header_path_w);
			throw new Exception('Could not rename new http header file.  All is lost. :(');
		}
	}
}
