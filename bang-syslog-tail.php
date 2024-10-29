<?php

if (!defined('BANG_SYSLOG_TAIL_TIME'))
  define('BANG_SYSLOG_TAIL_TIME', 60 * 30); // 30 minutes

if (!defined('BANG_SYSLOG_TAIL_LINES'))
  define('BANG_SYSLOG_TAIL_LINES', 100);

define('BANG_SYSLOG_TAILING', get_transient('bang_syslog_tail'));


//  add a message
function bang_syslog_tail_add_message($msg) {
  if (!BANG_SYSLOG_TAILING) return;
  $messages = get_transient('bang_syslog_messages');
  if (!is_array($messages)) {
    //do_action('log', 'What is this?', $messages);
    syslog(LOG_NOTICE, "app.k - Null messages");
    $messages = array();
  }
  // syslog(LOG_NOTICE, "app.k - Currently has ".count($messages)." messages");
  //$messages[] = $msg;
  array_push($messages, $msg);
  $n = count($messages);
  // syslog(LOG_NOTICE, "app.k - Now has ".$n." messages");
  if ($n > BANG_SYSLOG_TAIL_LINES ) {
    syslog(LOG_NOTICE, "app.k - Trimming");
    $messages = array_values(array_slice($messages, $n - (BANG_SYSLOG_TAIL_LINES / 2)));
  }
  set_transient('bang_syslog_messages', $messages, BANG_SYSLOG_TAIL_TIME);
}


//  show the latest lines
add_action('wp_ajax_bang_syslog_tail', 'bang_syslog_tail');
function bang_syslog_tail() {
  // enable log tail for the next
  set_transient('bang_syslog_tail', true, BANG_SYSLOG_TAIL_TIME);
  $messages = get_transient('bang_syslog_messages');
  // syslog(LOG_NOTICE, "app.k - Writing ".count($messages)." messages");

  if (!defined('SYSLOG_COLORS'))
    define('SYSLOG_COLORS', true);

  bang_syslog_tail_colour2span('reset');
  foreach ($messages as $line) {
    $out = "<div><span class='white black_bg'>".
      preg_replace_callback('/\\\\033\[[0-9]+m/', 'bang_syslog_tail_colour2span', $line).
      "</span></div>";
    //preg_replace("!<span class='[^']*'></span>", '', $out);
    echo $out;
  }
  exit;
}

function bang_syslog_tail_colour2span($code) {
  //syslog(LOG_NOTICE, 'Hoping to translate log message');
  if (is_array($code)) $code = $code[0];

  global $bang_syslog_style;
  $colours = bang_syslog_colors();
  unset($colours['request_color']);
  $flip = array_flip($colours);
  $mod = isset($flip[$code]) ? $flip[$code] : $code;
  // syslog(LOG_NOTICE, 'Translating log message: '.$code.' -> '.$mod);

  if (in_array($mod, array('white', 'black', 'red', 'green', 'blue', 'cyan', 'magenta', 'yellow')))
    $bang_syslog_style->fg = $mod;
  else if (in_array($mod, array('white_bg', 'black_bg', 'red_bg', 'green_bg', 'blue_bg', 'cyan_bg', 'magenta_bg', 'yellow_bg')))
    $bang_syslog_style->bg = $mod;
  else if ($mod == 'blink')       $bang_syslog_style->blink = 'blink';
  else if ($mod == 'bold')        $bang_syslog_style->bold = 'bold';
  else if ($mod == 'invisible')   $bang_syslog_style->invisible = 'invisible';
  else if ($mod == 'underlined')  $bang_syslog_style->underlined = 'underlined';
  else if ($mod == 'reset') {
    $bang_syslog_style = (object) array(
      'fg' => 'white',
      'bg' => 'black_bg',
      'blink' => '',
      'bold' => '',
      'reverse' => '',
      'invisible' => '',
      'underlined' => ''
    );
  }

  $style = (array) $bang_syslog_style;
  return "</span><span class='".implode(' ', array_filter($style))."'>";
}