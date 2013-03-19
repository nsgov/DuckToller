<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

require_once(__DIR__.'/../base/httpcachable.phps');

class TwitterAvatar extends HttpCachable {
	function __construct(DuckToller $toller, $username) {
		$url_file = 'avatar/.'.$username.'.url';
		if (!file_exists($url_file))
			throw new Exception('Twitter Avatar URL not initialized');
		$url = trim(file_get_contents($url_file));
		parent::__construct($toller, "avatar/$username.img", $url);
		$this->loglabel = 'TwitterAvatar['.$username.']';
	}
}
