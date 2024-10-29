<?php

function bang_syslog_settings_links($links) {
  do_action('log', 'Settings link', $links);
  array_unshift($links, '<a href="options-general.php?page=bang-syslog-settings.php">Settings</a>');
  return $links; 
}

add_action('plugins_loaded', 'bang_syslog__init', 1);
function bang_syslog__init() {
  if (BANG_SYSLOG_DEBUG >= 2) bang_syslog('SYSLOG: init');
  global $bang_syslog__id;
  $bang_syslog__id = substr(md5(rand()), 3, 3);

  //  channel settings
  $dest = get_option("syslog_dest", "syslog");
  $hostname = bang_syslog__get_hostname();
  $channel = get_option("syslog_channel", $hostname);
  $prefix = get_option("syslog_prefix", "php");
  $prefix = trim($prefix, " /");
  if (!empty($prefix) && !empty($channel)) {
    $prefix = $prefix."/";
  }
  $logmem = (boolean) get_option("syslog_logmem", false);
  $logtime = (boolean) get_option("syslog_logtime", false);
  $colour = (boolean) get_option("syslog_colour", false);
  $escape_newlines = (boolean) get_option("syslog_escape_newlines", false);
  $strict = (boolean) get_option("syslog_strict", false);

  if (!defined('SYSLOG_DEST'))  define('SYSLOG_DEST', $dest);
  if (!defined('SYSLOG_LOGMEM'))  define('SYSLOG_LOGMEM', $logmem);
  if (!defined('SYSLOG_LOGTIME')) define('SYSLOG_LOGTIME', $logtime);
  if (!defined('SYSLOG_ESCAPE_NEWLINES')) define('SYSLOG_ESCAPE_NEWLINES', $escape_newlines);
  if (!defined('SYSLOG_STR_MAX_LENGTH')) define('SYSLOG_STR_MAX_LENGTH', 8*1024);
  if (!defined('SYSLOG_INNER_MAX_LENGTH')) define('SYSLOG_INNER_MAX_LENGTH', (int) (SYSLOG_STR_MAX_LENGTH / 2));
  if (!defined('SYSLOG_COLORS')) define('SYSLOG_COLORS', $colour);
  if (!defined('SYSLOG_STRICT')) define('SYSLOG_STRICT', $strict);

  //  Ignore URLs
  $noajax = (boolean) get_option("syslog_noajax", false);
  if (BANG_SYSLOG_DEBUG) bang_syslog('SYSLOG: noajax', $noajax);
  if ($noajax && defined('DOING_AJAX') && DOING_AJAX) {
    define('SYSLOG_DISABLE', true);
    return;
  }
  $nojscss = (boolean) get_option("syslog_nojscss", false);
  if (BANG_SYSLOG_DEBUG) bang_syslog('SYSLOG: nojscss', $nojscss);
  if ($nojscss) {
    $url = parse_url($_SERVER['REQUEST_URI']);
    if (preg_match('!(\.css|\.js)$!', $url['path'])) {
      define('SYSLOG_DISABLE', true);
      return;
    }
  }

  $ignore = false;
  $ignore_urls = get_option("syslog_ignore_urls", false);
  $ignore_urls = explode("\n", $ignore_urls);

  $url = $_SERVER['REQUEST_URI'];
  if (!is_array($ignore_urls)) $ignore_urls = array();
  foreach ($ignore_urls as $ignore_url) {
    if (empty($ignore_url)) continue;
    if (BANG_SYSLOG_DEBUG) bang_syslog("Checking URL", $url, $ignore_url);
    if (substr($ignore_url, 0, 1) == '/') {
      if (substr($url, 0, strlen($ignore_url)) == $ignore_url)
        $ignore = true;
    } else {
      if (strpos($url, $ignore_url) != false)
        $ignore = true;
    }
    if ($ignore)
      break;
  }

  if ($ignore) {
    if (BANG_SYSLOG_DEBUG) bang_syslog("Ignoring URL", $url);
    remove_action('bang_syslog', 'bang_syslog');
    remove_action('log', 'bang_syslog');
    define('SYSLOG_DISABLE', true);
    return;
  }

  //  direct PHP logs to disk
  ini_set('display_errors', '0');
  ini_set('log_errors', 1);
  ini_set('error_log', 'syslog');

  //  open the logging channel
  openlog($prefix.$channel, LOG_CONS | LOG_PID, LOG_USER);

  if (defined('SYSLOG_DISABLE') && SYSLOG_DISABLE) {
    if (BANG_SYSLOG_DEBUG) bang_syslog('Skipping definition of syslog');
    return;
  }

  add_action('bang_syslog', 'bang_syslog', 10, 100);
  add_action('log', 'bang_syslog', 10, 100);

  //  test message
  //bang_syslog("Open logging");

  if (SYSLOG_LOGMEM) {
    if (BANG_SYSLOG_DEBUG) bang_syslog('SYSLOG: Logging memory');
    bang_log_memory("initial");
    add_action('shutdown', 'bang_syslog__final');
  }

  add_action('admin_print_styles', 'bang_syslog__admin_styles');
}

function bang_syslog__admin_styles() {
  wp_enqueue_style('bang-syslog', plugins_url('admin.css', __FILE__));
}


function bang_syslog__final() {
  bang_log_memory("final");
}

add_action('admin_menu', 'bang_syslog__add_settings');
function bang_syslog__add_settings() {
  add_options_page('Bang System Logging', 'System Logging', 'administrator', basename(__FILE__), 'bang_syslog__show_settings');
  wp_enqueue_script('bang-tabs', plugins_url('scripts/bang-tabs.js', __FILE__), array('jquery'), false, true);
}

function bang_syslog__show_settings() {
  if (isset($_POST['save']) && $_POST['save']) {
    update_option("syslog_dest", stripslashes($_POST['dest']));
    update_option("syslog_channel", stripslashes($_POST['channel']));
    update_option("syslog_prefix", stripslashes($_POST['prefix']));
    update_option("syslog_logmem", isset($_POST['logmem']) && (boolean) $_POST['logmem']);
    update_option("syslog_logtime", isset($_POST['logtime']) && (boolean) $_POST['logtime']);
    update_option("syslog_noajax", isset($_POST['noajax']) && (boolean) $_POST['noajax']);
    update_option("syslog_nojscss", isset($_POST['nojscss']) && (boolean) $_POST['nojscss']);
    update_option("syslog_escape_newlines", isset($_POST['escape_newlines']) && (boolean) $_POST['escape_newlines']);
    update_option("syslog_colour", isset($_POST['colour']) && (boolean) $_POST['colour']);
    update_option("syslog_ignore_urls", stripslashes($_POST['ignore_urls']));
    update_option("syslog_strict", isset($_POST['strict']) && (boolean) $_POST['strict']);

    bang_syslog(LOG_NOTICE, "Bang syslog: settings updated");
  }

  $hostname = bang_syslog__get_hostname();
  $dest = get_option("syslog_dest", "syslog");
  $channel = get_option("syslog_channel", $hostname);
  $prefix = get_option("syslog_prefix", "php");
  $logmem = (boolean) get_option("syslog_logmem", false);
  $logtime = (boolean) get_option("syslog_logtime", false);
  $noajax = (boolean) get_option("syslog_noajax", false); 
  $nojscss = (boolean) get_option("syslog_nojscss", false);
  $escape_newlines = (boolean) get_option("syslog_escape_newlines", true);
  $colour = (boolean) get_option("syslog_colour", false);
  $ignore_urls = get_option("syslog_ignore_urls");
  $strict = (boolean) get_option("syslog_strict");

  ?><div id='bang-leftbar' class='bang-syslog'>
    <a href="http://www.bang-on.net">
      <img src="<?php echo plugins_url('images/bang-black-v.png', __FILE__); ?>" /></a>
    <div><h1>System Logging</h1></div>
  </div>

  <div id='bang-main' class="wrap">

  <?php screen_icon("themes"); ?><h2>Bang System Logging</h2>
  <p>This plugin sends logging information to system log, when accessed with the action <tt>do_action('log', $message, $args)</tt>. You can pass any number and format of arguments to it.</p>

  <p><a href='<?php echo plugins_url('show-log.php', __FILE__); ?>' target='syslog' class='button-primary'>Show log</a></p>

  <div class='tabs-bar'><p>
    <a href='#settings' class='tab current'>Settings</a>
    <a href='#howto-pane' class='tab'>How to use</a>
  </p></div>

  <div class="pane current metabox-holder" id="settings">

  <form method="post">
    <div class='postbox'>
      <h3>Logging</h3>
      <div class='inside'>
        <p><input type='checkbox' name='logmem' id='logmem'<?php if ($logmem) echo " checked"; ?>/>
          <label for='logmem'><b>Log memory use</b>
            <br/>Requires adding special action calls to your code.</label></p>

        <p><input type='checkbox' name='logtime' id='logtime'<?php if ($logtime) echo " checked"; ?>/>
          <label for='logtime'><b>Log time</b>
            <br/>Requires adding special action calls to your code.</label></p>

        <p><input type='checkbox' name='strict' id='strict'<?php if ($strict) echo " checked"; ?>/>
          <label for='strict'><b>Strict mode</b>
            <br/>Log minor warnings throughout PHP.</label></p>
      </div>
    </div>

    <div class='postbox'>
      <h3>Message Formatting</h3>
      <div class='inside'>

        <p><b>Logging destination: </b> &nbsp; 
          <label for='dest_syslog'><input type='radio' name='dest' value='syslog' id='dest_syslog'<?php if ($dest == 'syslog') echo " checked"; ?>/>&nbsp;
          System log</label> &nbsp; &nbsp; 
        <label for='dest_php'><input type='radio' name='dest' value='php' id='dest_php'<?php if ($dest == 'php') echo " checked"; ?>/>&nbsp;
          PHP/Apache error log</label> &nbsp; &nbsp; 
        <label for='dest_file'><input type='radio' name='dest' value='file' id='dest_file'<?php if ($dest == 'file') echo " checked"; ?>/>&nbsp;
          File <tt>syslog.txt</tt> in web root</label></p>

        <table>
          <tr><th style='padding: 0;'>Prefix</th><th style="width: 5px; padding: 0;"></th><th style='padding: 0;'>Channel</th></tr>
          <tr>
            <td style='padding: 0;'><input id='prefix' name='prefix' type='text' size='32' value="<?php echo $prefix; ?>"/></td>
            <td style="width: 5px; padding: 0 5px;">/</td>
            <td style='padding: 0;'><input id='channel' name='channel' type='text' size='60' value="<?php echo $channel; ?>"/></td>
          </tr>
        </table>

        <p><input type='checkbox' name='escape_newlines' id='escape_newlines'<?php if ($escape_newlines) echo " checked"; ?>/>
          <label for='escape_newlines'><b>Escape newlines</b>
            <br/>If newlines are not escaped, a new log message will be generated for each line of the message.</label></p>

        <p><input type='checkbox' name='colour' id='colour'<?php if ($colour) echo " checked"; ?>/>
          <label for='colour'><b>Coloured logs</b>
            <br/>Uses terminal colour codes. You will need a script capable of interpreting the colours.</label></p>
      </div>
    </div>

    <div class='postbox'>
      <h3>Logging Scope</h3>
      <div class='inside'>
        <p><input type='checkbox' name='noajax' id='noajax'<?php if ($noajax) echo " checked"; ?>/>
          <label for='noajax'><b>Ignore AJAX calls</b>
            <br/>Writes no log messages for WordPress AJAX calls.</label></p>

        <p><input type='checkbox' name='nojscss' id='nojscss'<?php if ($nojscss) echo " checked"; ?>/>
          <label for='nojscss'><b>Ignore JS and CSS</b>
            <br/>Writes no log messages for PHP-generated Javascript and CSS.</label></p>

        <p><b>Ignore URLs</b>
          <br/>A list of URL patterns (one per line) on which to ignore logging, eg <tt>/wp-admin</tt> or <tt>.jpg</tt></p>
        <textarea name='ignore_urls' id='ignore_urls' style='width: 100%' rows='10'><?php echo esc_html($ignore_urls); ?></textarea>
      </div>
    </div>

    <input type='hidden' name='save' value='on'/>
    <p><input type='submit' value='Save Settings' class='button-primary'/></p>
  </form>

  </div>

  <div class="pane" id="howto-pane">
    <p>To send a log message, use the log action:</p>
    <pre>
      <span class='cmd'>do_action</span>(<span class='str'>'log'</span>, <span class='str'>'My log message'</span>);</pre>

    <p>If the first parameter is one of the PHP priority levels, it will be used as the level for this log message. Otherwise the default <tt>LOG_NOTICE</tt> will be used.</p>
    <pre>
      <span class='cmd'>do_action</span>(<span class='num'>LOG_WARNING</span>, <span class='str'>'log'</span>, <span class='str'>'My warning message'</span>);</pre>

    <h3>Arguments</h3>
    <p>You can include any combination of arguments after the message. They'll each be turned into text and included in the message. If you have coloured logging turned on, the arguments will use colour to indicate the type of content.</p>
    <pre>
      <span class='cmd'>do_action</span>(<span class='str'>'log'</span>, <span class='str'>'A log message'</span>, <span class='array'>array(<span class='str'>'foo'</span>, <span class='num'>17</span>, <span class='cmd'>(object)</span> <span class='array'>array(<span class='str'>'x'</span> => <span class='num'>19</span>, <span class='str'>'y'</span> => <span class='num'>25.141</span>)</span>)</span>);</pre>

    <p>The arguments can be interpolated into the log message by using the special code '<tt>%s</tt>'.</p>
    <pre>
      <span class='cmd'>do_action</span>(<span class='str'>'log'</span>, <span class='str'>'Post number %s has title %s and post type %s'</span>, <span class='var'>$post->ID</span>, <span class='var'>$post->post_title</span>, <span class='var'>$post->post_type</span>);</pre>

    <h3>Coloured logging</h3>
    <p>Enabling this option will print your logs with X colour codes distinguishing the types of each argument. This can make your logs easier to read, but you'll need to read the logs using a tool that interprets the colours correctly.</p>

    <h3>Strict logging</h3>
    <p>The syslog plugin also captures general PHP notices. Normally it discards low-priority messages reporting bad PHP style, but you can enable these by switching on strict logging.</p>
    <p>This option can result in a deluge of irrelevant messages, so if in doubt leave it switched off.</p>

    <h3>Early loading the plugin</h3>
    <p>Plugin developers may find it helpful to write logs throughout their plugin, and to capture warning messages, but these logs won't be written until the Bang Syslog plugin has been loaded and initialised. To start logging earlier in the process, add this line to your<tt> wp-config.php </tt>file, just above the comment that reads <b>That's all, stop editing!</b>.</p>
    <pre>
      <span class='cmd'>require_once</span>(<span class='num'>ABSPATH</span> . <span class='str'>'wp-content/plugins/bang-syslog/include.php'</span>);</pre>
    <p>This is <b>NOT</b> necessary for most websites. If in doubt, leave this setting switched off.</p>

    <p>Remember that even with logging started early, <tt>do_action('log')</tt> won't work until after WordPress has loaded the <tt>do_action</tt> function. It does this before any plugins are loaded. If you absolutely need to call a log function earlier than that, you can do so with the <tt>bang_syslog</tt> function:</p>
    <pre>
      <span class='cmd'>if</span> (<span class='cmd'>is_callable</span>(<span class='str'>'bang_syslog'</span>))
        <span class='cmd'>bang_syslog</span>(<span class='str'>'Message'</span>, <i>arguments</i>);</pre>

    <h3>Profile time and memory use</h3>
    <p>The best way to examine the performance of your site is with a real profiler. If such a thing isn't available to you, logging can provide a cheap alternative. To profile your code, you'll need to surround various sections with appropriate action messages.</p>
    <p>...</p>

  </div>

  </div><?php
}
