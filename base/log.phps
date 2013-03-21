<?php
/**
 * @package DuckToller
 * @author David Nordlund
 * @copyright © 2013, Province of Nova Scotia
 */

class Log {
	protected $config, $level, $entries;
	public static $DEBUG=0, $INFO=1, $WARN=2, $ERROR=3;

	function __construct($toller, $label, $parent=null) {
		$this->config = $toller->config;
		$this->level = $this->config->get('log_level', self::$DEBUG);
		$this->logfile = $this->config->getPath('log_file');
		$this->label = $label;
		$this->entries = $parent ? $parent->entries : new ArrayObject();
	}

	protected function log($level, $msg) {
		$this->entries->append(array($level, $this->label, $msg));
		if (($this->logfile)&&($f = @fopen($this->logfile, 'a'))) {
			$t = $this->label . ": $msg\n";
			fwrite($f, $t, strlen($t));
			fclose($f);
		}
	}

	function debug($msg) { $this->log(self::$DEBUG, $msg); }
	function info($msg)  { $this->log(self::$INFO,  $msg); }
	function warn($msg)  { $this->log(self::$WARN,  $msg); }
	function error($msg) {
		$this->log(self::$ERROR, $msg);
		error_log($this->label . ": $msg");
	}

	function getEntries($min_level=null) {
		$min_level = is_int($min_level) ? $min_level : $this->level;
		$a = array();
		foreach ($this->entries as $e)
			if ($e[0] >= $min_level)
				$a[] = ($e[1]==$this->label) ? $e[2] : $e[1] . ': ' . $e[2];
		return $a;
	}
}
