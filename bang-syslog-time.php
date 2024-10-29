<?php

add_filter('measure', 'bang_measure_start', 10, 2);
add_action('measure-end', 'bang_measure_end', 10, 2);

function bang_measure_start($label, $parent = false) {
  if (!SYSLOG_LOGTIME) return;
  if (empty($label)) { debug_print_backtrace(); die; }
  global $bang_syslog__measure_stack;
  $bang_syslog__measure_stack[] = $label;
  $code = $parent ? "{$parent[2]}/{$label}" : implode("/", $bang_syslog__measure_stack);
  return array(microtime(true), get_num_queries(), $code, $label);
}

function bang_measure_end($m, $message = false) {
  if (!SYSLOG_LOGTIME) return;

  if ($m == null) { do_action('log', "Measurement error: null"); return; }
  global $bang_syslog__measure_stack;
  $pop = array_pop($bang_syslog__measure_stack);
  if ($pop != $m[3]) {
    do_action('log', "Measurement error: %s != %s", $pop, $m[3]);
    echo "<!-- measurement error at: ";
    debug_print_backtrace();
    echo " -->";
  }

  $time = microtime(true) - $m[0];
  $queries = get_num_queries() - $m[1];

  $label = $m[2];
  if ($message)
    $label = "$label ($message)";

  //do_action('log', $m, $time);
  bang_syslog__write_measure($label, $queries, $time);
}


function bang_measure_part_start($sofar, $label, $parent = false) {
  if (!SYSLOG_LOGTIME) return;
  $m = measure_start('#'.$label, $parent);

  if (empty($sofar) || $sofar[3] != $m[2])
    $sofar = array(0, 0, 0, $m[2]);
  $m[4] = $sofar;
  return $m;
}

function bang_measure_part_end($m) {
  if (!SYSLOG_LOGTIME) return;

  global $bang_syslog__measure_stack;
  $pop = array_pop($bang_syslog__measure_stack);
  if ($pop != $m[3]) { echo "$pop != {$m[3]} ... "; debug_print_backtrace(); die; }

  $sofar = $m[4];
  $time = microtime(true) - $m[0];
  $queries = get_num_queries() - $m[1];

  return array($time + $sofar[0], $queries + $sofar[1], $sofar[2] + 1, $m[2]);
}

function bang_measure_final($sofar, $message = false) {
  if (!SYSLOG_LOGTIME) return;

  $label = $sofar[3];
  $label = "$label [{$sofar[2]}]";
  if ($message)
    $label = "$label ($message)";
  bang_syslog__write_measure($label, $sofar[1], $sofar[0]);
}

function bang_syslog__write_measure($label, $q, $t) {
  //do_action('log', $label, $q, $t);
  $label = preg_replace('!/#!', ' #', $label);
  $label = str_pad($label, 70);
  $q = str_pad($q, 5, " ", STR_PAD_LEFT);

  $t = round($t, 3);
  $t = str_pad($t, 5);
  syslog(LOG_DEBUG, "Time: $label: $q   $t s");
}
