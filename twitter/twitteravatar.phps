<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

require_once(__DIR__.'/../base/httpcachable.phps');

class TwitterAvatar extends HttpCachable {
	protected $url_file;
	function __construct(DuckToller $toller, $username) {
		parent::__construct($toller, null, $username, '.img');
		$this->config = $this->config->section('TwitterAvatar');
		$this->static_url = FALSE;
		$this->url_file = $this->config->getCachePath(".$username.url");
		if (!file_exists($this->url_file))
			throw new Exception('Twitter Avatar URL not initialized.'.$url_file);
		$this->url = trim(file_get_contents($this->url_file));
	}

	function expired($age) {
		$moved = (time() - filemtime($this->url_file) < $age);
		return $moved ? 'updated URL' : parent::expired($age);
	}
}
