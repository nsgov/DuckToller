<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

class HttpCachable extends Cachable {
	protected $url, $headers;

	public function __construct(DuckToller $toller, $url, $basename, $ext='.data', $meta_ext='.http') {
		parent::__construct($toller, 'HTTP', $basename, $ext, $meta_ext);
		$this->url = $url;
	}

	protected function loadHeaders() {
		if (!$this->headers) {
			$lines = @file($this->meta_path_r);
			$this->headers = array();
			if ($lines) {
				$this->log('Loading cached http headers');
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
		}
	}

	public function getHeader($key, $fallback=null) {
		$key = strtoupper($key);
		return isset($this->headers[$key]) ? $this->headers[$key] : $fallback;
	}

	function getCacheControl() {
		if (!$this->cache_control) {
			$this->loadHeaders();
			$this->cache_control = new CacheControl($this->getHeader('Cache-Control'));
		}
		return $this->cache_control;
	}

	function expired() {
		$expired = FALSE;
		$this->loadHeaders();
		if ($this->url != $this->getHeader('X-URL'))
			$expired = 'URL has changed';
		else try {
			$expires = $this->getHeader('Expires');
			if ($expires) {
				$dt = new DateTime($expires);
				if ($dt->getTimestamp() < time())
					$expired = $expires;
			}
		} catch(Exception $ex) {
			$expired = 'Expires header: ' . $ex->getMessage();
		}
		return $expired;
	}

	protected function fetch($cache, $header_cache) {
		$this->log('Fetching ' . $this->url);
		$curl = curl_init($this->url);
		$curl_ver = curl_version();
		$ua = 'DuckToller/'.DuckToller::$version.' (curl '.$curl_ver['version'].')';
		$timeout = isset($this->toller->config['http']['timeout']) ? $this->toller->config['http']['timeout']-0 : 0;
		$url = 'X-URL: ' . $this->url . "\n\n";
		fwrite($header_cache, $url, strlen($url));
		curl_setopt_array($curl, array(
			CURLOPT_CONNECTTIMEOUT => $timeout ? $timeout : 9,
			CURLOPT_FAILONERROR => TRUE,
			CURLOPT_FILE => $cache,
			CURLOPT_FILETIME => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_REFERER => $_SERVER['HTTP_REFERER'],
			CURLOPT_TIMECONDITION => $this->lastModified(),
			CURLOPT_USERAGENT => $ua,
			CURLOPT_WRITEHEADER => $header_cache
		));
		$curl_err = '';
		$request_time = time();
		if (!($success = curl_exec($curl)))
			$curl_err = 'curl error #'.curl_errno($curl) . ': '. curl_error($curl);
		$response_time = time();
		$lastmod = curl_getinfo($curl, CURLINFO_FILETIME);
		curl_close($curl);
		if (!$success)
			throw new Exception($curl_err);
		$this->last_modified = $lastmod;
		return $success;
	}

	function serveHeaders() {
		$age = $this->age();
		if ($age)
			header("Age: $age");
		parent::serveHeaders();
	}
}
