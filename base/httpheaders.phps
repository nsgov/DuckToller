<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

class HttpHeaders {
	protected $http_version, $status_code, $status_msg, $headers;
	public static $CODE = array(
		  0 => '',
		200 => 'OK',
		304 => 'Not Modified',
		403 => 'Forbidden',
		404 => 'Not Found'
	);

	function __construct($status_code=0) {
		$this->http_version = '1.1';
		$this->status($status_code);
		$this->headers = array();
	}

	function status($set=null) {
		if (isset(self::$CODE[$set]))
			$this->status_code = $set;
		return $this->status_code;
	}

	function set($header, $val) {
		if ($header) {
			$key = strtoupper($header);
			$val = trim($val);
			if ($replace || !isset($this->headers[$key]))
				$this->index[$key] = array($header, $val);
			else
				$this->index[$key][1] = $val;
		}
	}

	function append($header, $val, $newline=FALSE) {
		if ($header) {
			$key = strtoupper($header);
			$val = trim($val);
			if (!isset($this->headers[$key]))
				$this->headers[$key] = array($header, $val);
			else
				$this->headers[$key][1] = ($newline ? "\n" : ',').$val;
		}
	}

	function get($key, $fallback=null) {
		$key = strtoupper($key);
		return isset($this->headers[$key]) ? $this->headers[$key][1] : $fallback;
	}

	function load($handle) {
		$this->headers = array();
		$status_line = rtrim(fgets($handle, 80));
		$status = explode(' ', $status_line, 3);
		if (strncmp('HTTP/', $status_line, 5) || (count($status)!=3))
			throw new Exception('Invalid HTTP response');
		$this->http_version = $status[0];
		$this->status_code = $status[1]-0;
		$this->status_msg = $status[2];
		$l = 2;
		for ($key = $val = ''; ($line=rtrim(fgets($handle))) && strlen($line); $l++) {
			if (strpos(" \t", $line{0})!==FALSE)
				$this->append($key, $line, TRUE);
			elseif (($delim = strpos($line, ':', 1)-0)) {
				$key = substr($line, 0, $delim);
				$val = substr($line, $delim+1);
				$this->append($key, $val, FALSE);
			} else
				throw new Exception('Invalid HTTP header, line '.$l);
		}
	}

	function loadFile($path) {
		$fail = null;
		if (($handle = @fopen($path, 'rb'))) try {
			$this->load($handle);
		} catch(Exception $ex) {
			$fail = $ex;
		} else
			$fail = new Exception('Unable to read HttpHeader file');
		if ($handle) fclose($handle);
		if ($fail) throw $fail;
	}
}
