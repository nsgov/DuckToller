<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

require_once(__DIR__.'/httpheaders.phps');

class HttpCachable extends Cachable {
	protected $url, $origin_headers, $static_url;
	protected static $ORIGIN_URL_HEADER = 'X-DuckToller-Origin-URL';
	protected static $config_id = 'HTTP';

	public function __construct(DuckToller $toller, $url, $basename, $ext='.data', $meta_ext='.http') {
		parent::__construct($toller, $basename, $ext, $meta_ext);
		$this->config = $this->config->section('HTTP');
		$this->url = $url;
		$this->origin_headers = new HttpHeaders();
		$static_url = TRUE;
	}

	protected function loadHeaders() {
		if (!$this->origin_headers->status()) {
			$this->log->debug('Loading cached http headers');
			$this->origin_headers->loadFile($this->meta_path_r);
			if (($ct = $this->origin_headers->get('CONTENT-TYPE'))) {
				$ct = preg_split('/\s*;\s*/', $ct);
				$this->mimetype = $ct[0];
			}
		}
	}

	function getMaxAge($fallback) {
		$maxage = parent::getMaxAge(-1);
		$cc = $this->cache_control;
		$this->loadHeaders();
		if (($origin_cc = $this->origin_headers->get('Cache-Control'))) {
			$this->cache_control = new CacheControl($origin_cc);
			$maxage = max($maxage, parent::getMaxAge(-1));
			$this->cache_control = $cc;
		}
		return ($maxage > -1) ? $maxage : $fallback;
	}

	function expired($age) {
		$expired = FALSE;
		$this->loadHeaders();
		if (!$static_url && ($this->url != $this->origin_headers->get(self::$ORIGIN_URL_HEADER)))
			$expired = 'URL has changed';
		else try {
			$expires = $this->origin_headers->get('Expires');
			if ($expires) {
				$dt = new DateTime($expires);
				if ($dt->getTimestamp() < time())
					$expired = "Expired: $expires";
			}
		} catch(Exception $ex) {
			$expired = 'Expires header: ' . $ex->getMessage();
		}
		return $expired;
	}

	protected function fetch($cache, $header_cache) {
		$this->log->info('Fetching ' . $this->url);
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
		$this->loadHeaders();
		$age = $this->age();
		if ($age)
			header("Age: $age");
		parent::serveHeaders();
	}
}
