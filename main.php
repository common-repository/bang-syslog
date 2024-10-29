<?php

/*
Plugin Name: Bang System Logging
Plugin URI: http://www.bang-on.net/
Description: Enables system logging from WordPress plugin and theme development.
Version: 1.2
Author: Marcus Downing
Author URI: http://www.bang-on.net/
License: GPLv2
*/

/*
  How to use:
  1. Switch on the plugin, or require the 'include.php' file
  2. Call do_action('log', LOG_INFO, 'message', $param1, $param2);

  The priority level can be omitted; in this case LOG_DEBUG will be assumed.
  Any parameter can be passed, and will be turned into a string for debugging.
  If you include %s in your message, parameters will be interpolated, eg:

    do_action('log', 'Page at %s', $path, $post);

  If the plugin is switched off, do_action('log') will do nothing.

  If you'd like the assistance of system logging early on in WordPress boot,
  before the plugin has a chance to load, put this line into wp-config.php:

    require_once(ABSPATH . 'wp-content/plugins/bang-syslog/include.php');
*/


require_once('bang-syslog-logging.php');
// set_error_handler('bang_syslog__error_handler');
// set_exception_handler('bang_syslog__exception_handler');
// register_shutdown_function('bang_syslog__shutdown_function');

if (defined('BANG_SYSLOG') && BANG_SYSLOG)
  return;
define('BANG_SYSLOG', true);

require_once('bang-syslog-time.php');
require_once('bang-syslog-settings.php');
require_once('bang-syslog-tail.php');

add_filter("plugin_action_links_".plugin_basename(__FILE__), 'bang_syslog_settings_links' );


//  install

register_activation_hook(__FILE__, 'bang_syslog_activate');
register_deactivation_hook(__FILE__, 'bang_syslog_deactivate');

function bang_syslog_activate() {

}

function bang_syslog_deactivate() {

}

