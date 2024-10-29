<?php

  //  load all of WordPress
  require_once( dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php' );

  //  prod the transient so we get logs
  set_transient('bang_syslog_tail', true, BANG_SYSLOG_TAIL_TIME);

  //  get the location of jQuery
  global $wp_scripts;
  $jquery = $wp_scripts->registered['jquery']->src;

  //  the url we need to get logs from
  $ajax = admin_url('admin-ajax.php').'?action=bang_syslog_tail';

?><html>
<head>
  <style type='text/css'>
  <?php echo file_get_contents('log.css'); ?>

  </style>
  <script type='text/javascript' src='<?= $jquery ?>'></script>
  <script type='text/javascript'>
    var refresh;

    jQuery(function ($) {
      function updateLog() {
        $("#log").load('<?=$ajax?>', function () {
          $("#log").css({ top: ($(window).innerHeight() - $("#log").outerHeight())+"px" });
        });
      }
      updateLog();
      refresh = setInterval(updateLog, 300);

      $(window).resize(function () {
          $("#log").css({ top: ($(window).innerHeight() - $("#log").outerHeight())+"px" });
      });

      $("#stop-button").click(function () {
        clearInterval(refresh);
        $("#stop-button").hide();
        $("#resume-button").show();
      });

      $("#resume-button").click(function () {
        refresh = setInterval(updateLog, 300);
        $("#stop-button").show();
        $("#resume-button").hide();
      });

    });
  </script>
</head>

<body>
<pre id='log'>
<div><span class='yellow bold'>Loading...</span></div>
</pre>

<div id='fade'>
  <a class='button' id='stop-button'>Stop</a>
  <a class='button' id='resume-button'>Resume</a>
</div>

</body>
</html>