<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

 class HttpCachable extends Cachable {
	protected $url;
	public function __construct($toller, $url, $cachefilename) {
		parent::__construct($toller, $cachefilename);
		$this->url = $url;
	}

	public function fetch($cache) {
		$curl = curl_init($this->url);
		curl_setopt_array($curl, array(
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_FAILONERROR => TRUE,
			CURLOPT_FILE => $cache,
			CURLOPT_FILETIME => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_USERAGENT => 'DuckToller/'.DuckToller::$version
		));
	}
 }
