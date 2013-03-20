<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

/**
 * CacheControl represents the values in the HTTP Cache-Control header.
 */
class CacheControl {
	protected $fields;

	function __construct($header_value='') {
		$this->fields = array();
		if ($header_value)
			$this->loadHeader($header_value);
	}

	function loadHeader($header_value) {
		$parts = explode(';', strtolower($header_value));
		foreach ($parts as $p) {
			$eq = strpos($p, '=', 1);
			$field = trim($eq ? substr($p, 0, $eq) : $p);
			$this->fields[$field] = $eq ? substr($p, $eq+1)-0 : TRUE;
		}
	}

	function get($field, $fallback=null) {
		return isset($this->fields[$field]) ? $this->fields[$field] : $fallback;
	}

	function toString() {
		$cc = array();
		foreach ($this->fields as $field => $value)
			$cc[] = is_bool($value) ? $field : "$field=$value";
		return implode(';', $cc);
	}

	function setHeader() {
		$cc = $this->toString();
		if ($cc)
			header('Cache-Control: '.$cc);
	}
}
