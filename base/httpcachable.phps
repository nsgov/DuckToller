<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

class HttpCachable extends Cachable {
	protected $url, $header_path, $header_cache;
	public function __construct($toller, $url, $cachefilename) {
		parent::__construct($toller, $cachefilename);
		$this->url = $url;
		$this->header_path = dirname($cachefilename).'/.'.basename($cachefilename).'.http';

	}

	public function fetch($cache) {
		$this->log('Fetching ' . $this->url);
		$curl = curl_init($this->url);
		$curl_ver = curl_version();
		$ua = 'DuckToller/'.DuckToller::$version.' (curl '.$curl_ver['version'].')';
		curl_setopt_array($curl, array(
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_FAILONERROR => TRUE,
			CURLOPT_FILETIME => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_REFERER => $_SERVER['HTTP_REFERER'],
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_TIMECONDITION => $this->lastModified(),
			CURLOPT_USERAGENT => $ua,
			CURLOPT_WRITEHEADER => $this->header_cache
		));
		$this->content = curl_exec($curl);

		$curl_close($curl);
	}
}
