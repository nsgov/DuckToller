<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 **/

class Config {
	protected $data, $section_list, $path_root;

	protected function __construct($data, $sections, $path) {
		$this->data = $data;
		$this->section_list = new ArrayObject($sections);
		$this->path_root = $path;
	}

	static function load($ini_file_path, $section_id) {
		$path_root = dirname($ini_file_path);
		$data = new ArrayObject(parse_ini_file($ini_file_path, TRUE));
		return new self($data, array($section_id), $path_root);
	}

	function section($section_id) {
		$sections = $this->section_list->getArrayCopy();
		array_unshift($sections, $section_id);
		return new self($this->data, $sections, $this->path_root);
	}

	function get($key, $fallback=null, $go_deep=TRUE) {
		$val = $fallback;
		foreach ($this->section_list as $sec) {
			if (isset($this->data[$sec][$key])) {
				$val = $this->data[$sec][$key];
				break;
			}
			if (!$go_deep) break;
		}
		return $val;
	}

	function getCachePath() {
		$root = $this->path_root;
		$parts = array();
		for ($i = count($this->section_list); $i--;) {
			if (isset($this->data[$this->section_list[$i]]))
				$conf = $this->data[$this->section_list[$i]];
			else
				continue;
			if (isset($conf['cache_root'])) {
				$root = $conf['cache_root'];
				if ($root{0}!='/')
					$root = $this->path_root . DIRECTORY_SEPARATOR . $root;
			}
			if (isset($conf['cache_path'])) {
				$p = $conf['cache_path'];
				if ($p{0}=='/')
					$parts = array($p);
				else
					$parts[] = $p;
			}
		}
		return realpath($root.'/'.implode('/', $parts));
	}
}
