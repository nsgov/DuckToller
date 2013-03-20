<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

class HttpCachable extends Cachable {
	protected $url, $headers, $origin_cache_control, $static_url;
	protected static $ORIGIN_URL_HEADER = 'X-DuckToller-Origin-URL';

	public function __construct(DuckToller $toller, $url, $basename, $ext='.data', $meta_ext='.http') {
		parent::__construct($toller, 'HTTP', $basename, $ext, $meta_ext);
		$this->url = $url;
		$this->headers = $this->origin_cache_control = null;
		$static_url = TRUE;
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
		if (!$this->origin_cache_control) {
			$this->loadHeaders();
			$this->origin_cache_control = new CacheControl($this->getHeader('Cache-Control'));
			if ($this->static_url)
				$this->cache_control = $this->origin_cache_control;
		}
		return $this->cache_control;
	}

	function expired() {
		$expired = FALSE;
		$this->loadHeaders();
		if (!$static_url && ($this->url != $this->getHeader(self::$ORIGIN_URL_HEADER)))
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
		curl_setopt_array($curl, array(
			CURLOPT_CONNECTTIMEOUT => $this->config->get('connection_timeout', 9),
			CURLOPT_FAILONERROR => TRUE,
			CURLOPT_FILE => $cache,
			CURLOPT_FILETIME => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS => $this->config->get('max_redirects', 9),
			CURLOPT_REFERER => $_SERVER['HTTP_REFERER'],
			CURLOPT_TIMECONDITION => $this->lastModified(),
			CURLOPT_USERAGENT => $ua,
			CURLOPT_WRITEHEADER => $header_cache
		));
		$curl_err = '';
		if (!($success = curl_exec($curl)))
			$curl_err = 'curl error #'.curl_errno($curl) . ': '. curl_error($curl);
		$lastmod = curl_getinfo($curl, CURLINFO_FILETIME);
		curl_close($curl);
		if ($success) {
			$url = self::$ORIGIN_URL_HEADER . ': ' . $this->url . "\n\n";
			fwrite($header_cache, $url, strlen($url));
		} else
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
