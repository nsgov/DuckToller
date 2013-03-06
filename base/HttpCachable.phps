<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright  2013, Province of Nova Scotia
 */

 class HttpCachable extends Cachable {
	function __construct($toller, $url) {
		parent::__construct($toller);
	}
 }
