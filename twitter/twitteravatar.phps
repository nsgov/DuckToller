<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

require_once(__DIR__.'/../base/httpcachable.phps');

class TwitterAvatar extends HttpCachable {
	function __construct(DuckToller $toller, $username) {
		parent::__construct($toller, null, $username, '.img');
		$this->config = $this->config->section('TwitterAvatar');
		$this->static_url = FALSE;
		$url_file = $this->config->getCachePath(".$username.url");
		if (!file_exists($url_file))
			throw new Exception('Twitter Avatar URL not initialized.'.$url_file);
		$this->url = trim(file_get_contents($url_file));
	}
}
