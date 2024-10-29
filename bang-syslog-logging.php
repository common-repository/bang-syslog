<?php

//  log level 0: no extra logging
//  log level 1: standard debugging for boot etc
//  log level 2: very chatty debugging about each message
//  log level 3: utterly paranoid

if (!defined('BANG_SYSLOG_DEBUG'))
  define('BANG_SYSLOG_DEBUG', false);


function bang_syslog() {
  if (BANG_SYSLOG_DEBUG >= 3) 
  	syslog(LOG_DEBUG, 'Call to bang_syslog');

  extract(bang_syslog_colors());

  //  load arguments
  $n = func_num_args();
  if ($n == 0) {
    if (BANG_SYSLOG_DEBUG >= 2) syslog(LOG_DEBUG, 'Skipping blank message');
    return;
  }

  $args = array();
  for ($i = 0; $i < $n; $i++) {
    $args[] = func_get_arg($i);
  }
  if (BANG_SYSLOG_DEBUG >= 3) {
    syslog(LOG_DEBUG, "SYSLOG: Called with $n arguments");
    bang_syslog__debug_args($args, "Initial arg");
  }

  //  optional priority and message
  $priority = LOG_INFO;
  $msg = "";
  if (is_integer($args[0])) {
    $priority = $args[0];
    $args = array_slice($args, 1);
  }
  if (is_string($args[0])) {
    $msg = $args[0];
    $msg = trim($msg);
    $args = array_slice($args, 1);
  }
  if (BANG_SYSLOG_DEBUG >= 2) syslog(LOG_DEBUG, "SYSLOG: Writing message: $msg");
  if (BANG_SYSLOG_DEBUG >= 2) syslog(LOG_DEBUG, "SYSLOG: Writing message with ".count($args)." arguments");

  if (BANG_SYSLOG_DEBUG >= 3) {
    bang_syslog__debug_args($args, "Raw arg");
  }

  // run commands, make strings, extract parameters etc
  $args = bang_syslog__process_args($args);

  //  format messages inline
  $argmatches = array();
  $argcount = preg_match_all("/%[+-]?( |0|'.)?[-]?(\n+(\.\n+))?[%bcdeEufFgGosxX]/", $msg, $argmatches, PREG_PATTERN_ORDER);
  $argcount = min($argcount, count($args));
  
  if ($argcount > 0) {
    $fmtmsg = preg_replace('/%[+-]?( |0|\'.)?[-]?(\n+(\.\n+))?[%bcdeEufFgGosxX]/', '%s', $msg);
    $argformats = array_values(array_slice($argmatches[0], 0, $argcount));
    $fargs = array_values(array_slice($args, 0, $argcount));
    $args = array_values(array_slice($args, $argcount));

    for ($i = 0; $i < $argcount; $i++) {
      $fmt = $argformats[$i];
      $arg = $fargs[$i];
      if ($fmt == '%s')
        $arg = bang_syslog__print_arg($arg);
      else {
        $ftype = substr($fmt, strlen($fmt) - 1, 1);
        switch ($ftype) {
          case 's': $colour = $yellow;
          default: $colour = $cyan;
        }
        $arg = $colour.vsprintf($fmt, $arg);
      }
      $arg = bang_syslog__msg_surround_arg($arg);
      $args_formatted[] = $arg;
    }
    $msg = vsprintf($fmtmsg, $args_formatted);
  }

  $args = array_map('bang_syslog__print_arg', $args);

  if (BANG_SYSLOG_DEBUG >= 2) syslog(LOG_DEBUG, "SYSLOG: Appending ".count($args)." extra arguments");
  if (BANG_SYSLOG_DEBUG >= 3)
    foreach ($args as $arg)
      syslog(LOG_DEBUG, "SYSLOG: Printed arg: $arg");

  if (!empty($args)) {
    if (!empty($msg)) {
      if (substr($msg, -1) !== ":")
        $msg = "$msg:";
      $msg = "$white$bold$msg$reset ";
    }

    $msg = $msg.implode("$reset, ", $args).$reset;
  } else if (!empty($msg)) {
    $msg = "$white$bold$msg$reset";
  }
  if (BANG_SYSLOG_DEBUG >= 3) syslog(LOG_DEBUG, "SYSLOG: Combined message: $msg");

  //  tag this message
  $host = bang_syslog__get_hostname();
  $uri = $_SERVER['REQUEST_URI'];
  $uri = $host.$uri;
  $id = bang_syslog__id();
  if (BANG_SYSLOG_DEBUG >= 3) syslog(LOG_DEBUG, "SYSLOG: Message request id: $id, uri: $uri, priority: $priority");

  if (bang_syslog__get__escape_newlines()) {
    $msg = preg_replace('/[\n\r]+/', '\\n', $msg);
    
    $msg = "$request_color$id$reset $green$uri$reset: $msg";
    $msg = bang_syslog_limit_length($msg);
    if (BANG_SYSLOG_DEBUG >= 3) syslog(LOG_DEBUG, "SYSLOG: Single line: ".strlen($msg));

    switch (SYSLOG_DEST) {
      case 'php':
        $ok = error_log($msg, 4);
        if (BANG_SYSLOG_DEBUG && !$ok) syslog(LOG_INFO, "SYSLOG: Could not write message to error log!");
        break;

      case 'file':
        $ok = error_log("$msg\n", 3, "syslog.txt");
        if (BANG_SYSLOG_DEBUG && !$ok) syslog(LOG_INFO, "SYSLOG: Could not write message to file!");
        break;

      default:
        $ok = syslog($priority, $msg);
        if (BANG_SYSLOG_DEBUG && !$ok) syslog(LOG_INFO, "SYSLOG: Could not write message to syslog!");
        break;
    }
    if (BANG_SYSLOG_TAILING) bang_syslog_tail_add_message($msg);
    if (BANG_SYSLOG_DEBUG && !$ok) syslog(LOG_INFO, "SYSLOG: Could not write message ($priority): $msg!");
  } else {
    if (BANG_SYSLOG_DEBUG >= 3) syslog(LOG_DEBUG, "SYSLOG: Multiple lines");
    $msg = explode("\n", $msg);
    $msg = array_filter($msg);
    $ok = true;
    foreach ($msg as $m) {
      $m = "$request_color$id$reset $green$uri$reset: $m";
      $m = bang_syslog_limit_length($m);
      switch (SYSLOG_DEST) {
        case 'php':
          $ok = error_log($m, 4); // && $ok;
          if (BANG_SYSLOG_DEBUG && !$ok) syslog(LOG_INFO, "SYSLOG: Could not write message to error log!");
          break;

        case 'file':
          $ok = error_log("$m\n", 3, "syslog.txt"); // && $ok;
          if (BANG_SYSLOG_DEBUG && !$ok) syslog(LOG_INFO, "SYSLOG: Could not write message to file!");
          break;

        default:
          $ok = syslog($priority, $m); // && $ok;
          if (BANG_SYSLOG_DEBUG && !$ok) syslog(LOG_INFO, "SYSLOG: Could not write message to syslog!");
          break;
      }
      if (BANG_SYSLOG_TAILING) bang_syslog_tail_add_message($m);
      if (BANG_SYSLOG_DEBUG && !$ok) syslog(LOG_INFO, "SYSLOG: Could not write message ($priority): $msg!");
    }
  }
  if (BANG_SYSLOG_DEBUG >= 3) syslog(LOG_DEBUG, "SYSLOG: Complete");
}

//  error handlers

function bang_syslog__error_handler($errnum, $errmsg, $file, $line) {
  if (!bang_syslog__show_errnum($errnum))
    return;
	if (!function_exists('bang_syslog')) syslog(LOG_INFO, 'Where the fuck is the bang_syslog function?');
	$type = bang_syslog__error_name($errnum);
	bang_syslog($type.": %s in %s:%s", $errmsg, $file, $line);
}

function bang_syslog__exception_handler($exception) {
	if (!function_exists('bang_syslog')) syslog(LOG_INFO, 'Where the fuck is the bang_syslog function?');
	bang_syslog("Exception: %s", $exception->getMessage());
}

function bang_syslog__shutdown_function() {
	if (BANG_SYSLOG_DEBUG >= 2) syslog(LOG_INFO, 'Shutdown');
	$error = error_get_last();
  if ($error) {
    if (!bang_syslog__show_errnum($error['type']))
      return;
		$type = bang_syslog__error_name($error['type']);
  	bang_syslog($type.": ".$error['message'], $error['file'], $error['line']);
  }
}

function bang_syslog__debug_args($args, $prefix) {
  foreach ($args as $arg) {
    $rawarg = "";
    if (is_string($arg))
      $rawarg = "(string) $arg";
    else if (is_null($arg))
      $rawarg = "(null)";
    else if (is_int($arg))
      $rawarg = "(int) ".((int) $arg);
    else if (is_bool($arg))
      $rawarg = "(bool) ".((bool) $arg);
    else if (is_numeric($arg))
      $rawarg = "(numeric) ".($arg);
    else if (is_array($arg)) {
      $len = count($arg);
      $rawarg = "(array len = $len)";
    } else if (is_object($arg)) {
      $cls = get_class($arg);
      $rawarg = "(object class = $cls)";
    }
    syslog(LOG_DEBUG, "SYSLOG: $prefix: $rawarg");
  }
}

set_error_handler('bang_syslog__error_handler', E_ALL ^ E_USER_DEPRECATED ^ E_STRICT);
set_exception_handler('bang_syslog__exception_handler');
register_shutdown_function('bang_syslog__shutdown_function');

function bang_syslog__error_name($errnum) {
	switch ($errnum) {
    case E_ERROR: return 'Fatal error';
    case E_WARNING: return 'System warning';
    case E_PARSE: return 'System parse error';
    case E_NOTICE: return 'System notice';
    case E_CORE_ERROR: return 'Core error';
    case E_CORE_WARNING: return 'Core warning';
    case E_COMPILE_ERROR: return 'Compile error';
    case E_COMPILE_ERROR: return 'Compile error';
    case E_USER_ERROR: return 'User error';
    case E_USER_WARNING: return 'User warning';
    case E_USER_NOTICE: return 'User notice';
    case E_STRICT: return 'Strict warning';
    case E_RECOVERABLE_ERROR: return 'Recoverable error';
    case E_DEPRECATED: return 'Deprecated';
    case E_USER_DEPRECATED: return 'User deprecated';
    default: return 'Unknown error '.$errnum;
	}
}

function bang_syslog__show_errnum($errnum) {
  switch ($errnum) {
    case E_ERROR:
    case E_PARSE:
    case E_CORE_ERROR:
    case E_CORE_WARNING:
    case E_COMPILE_ERROR:
    case E_COMPILE_ERROR:
    case E_USER_ERROR:
      return true;

    case E_WARNING:
    case E_USER_WARNING:
    case E_USER_NOTICE:
    case E_NOTICE:
    case E_STRICT:
    case E_RECOVERABLE_ERROR:
    case E_DEPRECATED:
    case E_USER_DEPRECATED:
      return defined('SYSLOG_STRICT') ? (boolean) SYSLOG_STRICT : false;

    default: 
      return false;
  }
}


//  parameters

function bang_syslog__get__str_max_length($full = true) { 
	if (!$full) return bang_syslog__get__inner_max_length();
	return defined('SYSLOG_STR_MAX_LENGTH') ? SYSLOG_STR_MAX_LENGTH : 1024; 
}
function bang_syslog__get__inner_max_length() { return defined('SYSLOG_INNER_MAX_LENGTH') ? SYSLOG_INNER_MAX_LENGTH : 512; }
function bang_syslog__get__escape_newlines() { return defined('SYSLOG_ESCAPE_NEWLINES') ? SYSLOG_ESCAPE_NEWLINES : false; }


//  log message processing

function bang_syslog__msg_surround_arg($arg) {
	extract(bang_syslog_colors());
  return "{$reset}{$arg}{$reset}{$bold}";
}

function bang_syslog__process_args($args) {
  $out = array();
  $cmd = array();
  foreach ($args as $arg) {
    if (is_string($arg) && substr($arg, 0, 1) == '!')
      $cmd[] = substr($arg, 1);
    else if (!empty($cmd)) {
      $out[] = bang_syslog__process_commands($arg, $cmd);
      $cmd = array();
    } else
      $out[] = $arg;
  }
  return $out;
}

function bang_syslog__process_commands($arg, $cmd, $insight = true) {
  if (BANG_SYSLOG_DEBUG >= 3) syslog(LOG_DEBUG, "Processing an argument of type ".gettype($arg));

  if (is_null($arg))
    return null;
  if ($arg === false)
    return false;

  if (is_array($arg)) {
    $out = array();
    foreach ($arg as $a) {
      $out[] = bang_syslog__process_commands($a, $cmd, false);
    }
    return $out;
  }

  //  actually process
  $out = array();
  foreach ($cmd as $c) {
    if (BANG_SYSLOG_DEBUG >= 3) syslog(LOG_DEBUG, "Applying command to argument: $c");
    $fields = explode(',', $c);
    if (count($fields) == 1) {
      $field = $fields[0];
      if (isset($arg->$field))
        return $arg->$field;
    }
    foreach ($fields as $field) {
      if (isset($arg->$field))
        $out[$field] = $arg->$field;
    }
  }
  return (object) $out;
}

function bang_syslog_action($params) {
  // arguments
  $n = func_num_args();
  if ($n == 0) return;

  $args = array();
  while($i < $n) {
    $args[] = func_get_arg($i);
    $i++;
  }

  // call syslog
  bang_syslog($params);
}


function bang_syslog__get_hostname() {
  $hostname = $_SERVER['SERVER_NAME'];
  $hostname = trim($hostname);
  if (empty($hostname))
    $hostname = gethostname();
  if (substr($hostname, 0, 4) == "www.")
    $hostname = substr($hostname, 4);
  return $hostname;
}

function bang_syslog__id() {
  global $bang_syslog__id;
  return $bang_syslog__id;
}




//  log the current and peak memory use

function bang_log_memory($label) {
  if (!SYSLOG_LOGMEM) return;
  $mem = bang_syslog__byte_format(memory_get_usage());
  $peak = bang_syslog__byte_format(memory_get_peak_usage());
  bang_syslog(LOG_DEBUG, "Memory use ($label) = $mem, peak = $peak");
}

function bang_syslog__print_arg_strict($arg) {
  $str = bang_syslog__print_arg($arg);
  if (is_bool($arg)) return "boolean ".$str;
  if (is_string($arg)) return "string \"".$str.'"';
  if (is_array($arg)) return "array ".$str;
  if (is_object($arg)) return "object ".$str;
  if (is_long($arg)) return "long ".$str;
  if (is_int($arg)) return "int ".$str;
  if (is_double($arg)) return "double ".$str;
  if (is_float($arg)) return "float ".$str;
  if (is_resource($arg)) return "resource ".$str;
  return gettype($arg)."? ".$str;
}

function bang_syslog__print_arg($arg, $tree = array(), $colour = true) {
  if ($colour)
	 extract(bang_syslog_colors());
  if (is_null($arg)) return "{$red}null";
  if (is_bool($arg)) return $arg ? "{$green}true" : "{$red}false";
  if (bang_syslog__in_array_strict($arg, $tree)) return "{$red}<recursion!>";
  if (count($tree) > 20) return "{$red}<too deep!>";
  if (is_numeric($arg)) {
    if (is_integer($arg)) {
      return $cyan.sprintf('%d', (int) $arg);
    } else if (is_float($arg) || is_double($arg)) {
      $arg = sprintf('%f', (float) $arg);
      if (preg_match('!([0-9]+)(\.[0-9]*)!', $arg, $matches)) {
        $pre = $matches[1];
        $pre = number_format($pre);
        $post = $matches[2];
        $post = rtrim($post, '0');
        $post = rtrim($post, '.');
        $arg = $pre.$post;
      }
      return $cyan.$arg;
    }
    else
      return $cyan.sprintf('%d', (int) $arg);
  }
  if (is_string($arg)) {
    if (empty($arg)) return "{$red}empty";
    return bang_syslog_limit_length($yellow.$arg, bang_syslog__get__str_max_length(empty($tree)));
  }
  if (is_array($arg)) {
    $args = array();
    $tree2 = $tree;
    array_push($tree2, $arg);
    foreach ($arg as $key => $value) {
      $value = bang_syslog__print_arg($value, $tree2);
      if (is_numeric($key)) $args[] = $value;
      else $args[] = $key.': '.$value;
    }
    return bang_syslog_limit_length($blue.'['.implode("$blue, ", $args),false,$blue.']');
  }
  if (is_object($arg)) {
    $cls = get_class($arg);
    if ($cls == "stdClass") $cls = '';
    else $cls = $cls.' ';

    $tree2 = $tree;
    array_push($tree2, $arg);
    $args = array();
    foreach ($arg as $key => $value) {
      $value = bang_syslog__print_arg($value, $tree2);
      $args[] = $key.": ".$value;
    }
    return bang_syslog_limit_length($green.$cls.'{'.implode($green.', ', $args),false,$green.'}');
  }
  $arg = print_r($arg, true);
  $arg = bang_syslog_limit_length($cyan.$arg, bang_syslog__get__str_max_length(empty($tree)));
  return $arg;
}


function bang_syslog_limit_length($arg, $length = false, $affix = false) {
  if (!$length) $length = bang_syslog__get__inner_max_length();
  $affixlen = ($affix ? strlen($affix) : 0) + 3;
  if (strlen($arg) + $affixlen > $length) {
    if (BANG_SYSLOG_DEBUG >= 3) syslog(LOG_DEBUG, "Trimming string from ".strlen($arg)." to ".$length);
    $arg = substr($arg, 0, $length - $affixlen);
    $arg = preg_replace('!(\\033(\[)?)$!', '', $arg);
    $arg = $arg.'...';
    if ($affix) $arg = "$arg$affix";
    if (BANG_SYSLOG_DEBUG >= 3) syslog(LOG_DEBUG, "New length ".strlen($arg));
  }
  return "$arg$affix";
}


function bang_syslog__in_array_strict(&$needle, &$haystack) {
  foreach ($haystack as &$value)
    if ($needle === $value)
      return true;
  return false;
}

/**
 * byte_format
 * Function courtesy of JR
 * http://www.if-not-true-then-false.com/2009/format-bytes-with-php-b-kb-mb-gb-tb-pb-eb-zb-yb-converter/
 */
function bang_syslog__byte_format($bytes, $unit = "", $decimals = 2) {
  $units = array('B' => 0, 'kB' => 1, 'MB' => 2, 'GB' => 3, 'TB' => 4, 'PB' => 5, 'EB' => 6, 'ZB' => 7, 'YB' => 8);
  $value = 0;
  if ($bytes > 0) {
    // Generate automatic prefix by bytes
    // If wrong prefix given
    if (!array_key_exists($unit, $units)) {
      $pow = floor(log($bytes)/log(1024));
      $unit = array_search($pow, $units);
    }

    // Calculate byte value by prefix
    $value = ($bytes/pow(1024,floor($units[$unit])));
  }

  // If decimals is not numeric or decimals is less than 0
  // then set default value
  if (!is_numeric($decimals) || $decimals < 0) {
    $decimals = 2;
  }

  // Format output
  return sprintf('%.' . $decimals . 'f '.$unit, $value);
}

function bang_syslog_colors() {
	$use_colors = defined('SYSLOG_COLORS') ? SYSLOG_COLORS : false;
  if (!$use_colors) {
  	if (!defined('SYSLOG_REQUEST_COLOR'))
    	define('SYSLOG_REQUEST_COLOR', '');

    return array(
    	'reset' => '',
    	'blink' => '',
    	'bold' => '',
    	'invisible' => '',
    	'reverse' => '',
    	'underlined' => '',

    	'white' => '',
    	'black' => '',
    	'red' => '',
    	'green' => '',
    	'blue' => '',
    	'cyan' => '',
    	'magenta' => '',
    	'yellow' => '',

    	'white_bg' => '',
    	'black_bg' => '',
    	'red_bg' => '',
    	'green_bg' => '',
    	'blue_bg' => '',
    	'cyan_bg' => '',
    	'magenta_bg' => '',
    	'yellow_bg' => '',

    	'request_color' => ''
    	);
  }

  $colors = array(
    'reset' => '\033[0m',
    'blink' => '\033[5m',
    'bold' => '\033[1m',
    'invisible' => '\033[8m',
    'reverse' => '\033[7m',
    'underlined' => '\033[4m',

    'white' => '\033[37m',
    'black' => '\033[30m',
    'red' => '\033[31m',
    'green' => '\033[32m',
    'blue' => '\033[34m',
    'cyan' => '\033[36m',
    'magenta' => '\033[35m',
    'yellow' => '\033[33m',

    'white_bg' => '\033[47m',
    'black_bg' => '\033[40m',
    'red_bg' => '\033[41m',
    'green_bg' => '\033[42m',
    'blue_bg' => '\033[44m',
    'cyan_bg' => '\033[46m',
    'magenta_bg' => '\033[45m',
    'yellow_bg' => '\033[43m',
    );

  // random request color
  if (!defined('SYSLOG_REQUEST_COLOR')) {
    $cnames = array('red', 'green', 'blue', 'cyan', 'magenta', 'yellow');
    $fg = array_rand($cnames);

    $bg = '';
    $bgr = rand(1, 100);
    if ($bgr > 50) {
      $bg = array_rand($cnames);
      while ($fg == $bg) $bg = array_rand($cnames); // don't use the same color
      $bg = $colors[$cnames[$bg].'_bg'];
    }

    $fg = $colors[$cnames[$fg]];
    $bold = rand(1, 100);
    $bold = $bold > 50 ? $colors['bold'] : '';

    define('SYSLOG_REQUEST_COLOR', "$fg$bg$bold");
  }

  $colors['request_color'] = SYSLOG_REQUEST_COLOR;
  return $colors;
}