<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

require_once(__DIR__.'/cachecontrol.phps');

/**
 * StaticFile
 * Serve a file directly from the filesystem.
 */
class StaticFile extends Cachable {
	protected $filepath;

	function __construct(DuckToller $toller, $path, $basename, $mimetype, $charset=null) {
		if (strchr($basename, ':') || strchr($basename, DIRECTORY_SEPARATOR) || strchr($basename, '..'))
			throw new Exception('illegal basename for static file.');
		elseif (strstr($path, ':') || strstr($path, '//') || strstr($path, '\\'))
			throw new Exception('illegal path for static file.');
		parent::__construct($toller, $basename);
		$this->filepath = $path . DIRECTORY_SEPARATOR . $basename;
		$this->mimetype = $mimetype;
		$this->charset = $charset;
	}

	function init() {
		parent::init();
		$this->content_path_r = $this->meta_path_r = $this->filepath;
	}

	function toll() {
		$this->last_modified = @filemtime($this->filepath);
	}

	function fetch($a, $b) {
	}
}
