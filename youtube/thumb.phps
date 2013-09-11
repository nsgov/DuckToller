<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

require_once(__DIR__.'/../base/httpcachable.phps');

class YoutubeThumbnail extends HttpCachable {
	function __construct(DuckToller $toller, $videoID) {
		parent::__construct($toller, "http://img.youtube.com/vi/$videoID/default.jpg",
		                    $videoID, '-thumb.jpg', '-thumb.http');
		$this->config = $this->config->section('Youtube');
		$this->loglabel = "Youtube[$videoID]";
	}
}
