=== Bang System Logging ===
Tags: syslog, debug
Requires at least: 3.0
Tested up to: 3.5
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enable system logging for WordPress plugin and theme development.

== Description ==

Enable system logging for WordPress plugin and theme development. This can make it easier to know what your code is doing.

Features include:

*  Log to the system log or to a file
*  Easily append or embed any value in log messages
*  Coloured logs indicating strings, numbers, arrays, objects, null values etc.
*  Extract values from arrays of objects
*  Optionally exclude AJAX, Javascripts, CSS and any other pattern of URLs.
*  Intercept PHP errors and log strict warnings
*  Measure memory usage between two points, or the time taken by various parts of your code
*  Easy to switch off for production sites

= How to use =

Using it is as simple as calling the `'log'` action in your templates or plugin.

`<?php do_action('log', 'Some log message'); ?>`

This will produce a line in your system log:

`Jun  4 11:23:08 myserver php/mysite.com[1553]: b8e mysite.com/path-to-page: Some log message`

This includes:

*  `Jun  4 11:23:08` - The date and time of the log message
*  `myserver` - The name of this computer
*  `php` - The program that produced the message
*  `mysite.com` - The domain name of the site
*  `1553` - The process ID of the running PHP process
*  `b8e` - A random 3-digit code identifying each page request
*  `mysite.com/path-to-page` - The URL of the request
*  `Some log message` - Your message

For more detailed instructions, see the **How to use** tab.


== Installation ==

= Basic installation =

1. Upload the `bang-syslog` directory into the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('log', 'Some log message'); ?>` in your templates and plugins

= Early logging for plugin developers =

Normally, this plugin can only begin logging once the plugin has loaded.
If you're using logging to develop your own plugin, you may find it helpful to activate this plugin earlier.
Put this line near the bottom of your `wp-config.php` file:

`require_once(ABSPATH . 'wp-content/plugins/bang-syslog/include.php');`

= Using the plugin =

The plugin does nothing until it's called in PHP code.
See the **How to use** tab for more information.


== How to use ==

To log a message, use the `'log'` action in your templates or plugins:

`<?php do_action('log', 'Some log message'); ?>`

This will output the log message:

`Some log message`


= Embedding values =

If you include extra parameters, they'll be added at the end of the log message:

`<?php do_action('log', 'A number and a string', 17, 'foo'); ?>`

This will produce the log message:

`A number and a string: 17, foo`

The value will be formatted correctly depending on its type: integers, strings, arrays, objects, booleans etc. 
You don't need to check if values are null or empty, they'll still be output safely.

If you put the code `%s` into your log message, then one of the arguments will be dropped into the message:

`<?php do_action('log', 'I have %s numbers', count($numbers), $numbers); ?>`

This will produce the log message:

`I have 4 numbers: [9, 16, 307, 1]`


= Selecting fields =

Logging a complete object - such as a WordPress post - can be very large, and sometimes it's only one or two fields you need. 
If you put a string starting with an exclamation point `"!"` followed by a list of field names, they will be selected from the following object.
The following will only show the `ID` and `post_title` fields of the post:

`<?php do_action('log', 'Loaded the post', '!ID,post_title', $post); ?>`

This will produce the log message:

`Loaded the post: {ID: 1932, post_title: Test page}`

If you do this with an array of objects, those fields will be selected from each of them. The following will output a list of post `ID`s:

`<?php do_action('log', 'Loaded %s posts', count($posts), '!ID', $posts); ?>`

This will produce the log message:

`Loaded 3 posts: [1932, 1594, 1103]`


= Coloured logs =

If you have coloured logging switched on, values will appear in different colours to indicate their type.
This can make for quicker scanning of log files. 
To enable coloured logs, tick the *Coloured logs* checkbox on the settings page.
Then use the `log.sh` script, included with this plugin, to decode and display the coloured log files.


== Frequently Asked Questions ==

= What happens when I switch the plugin off? =

The `'log'` action will have no effect.
No logs will be written, but there should be no errors either.

= Where are my logs? =

By default, the message will be written to the computer's system log.
The exact location of this file varies depending on your operating system.
On linux, the logs will typically go to `/var/log/syslog` or `/var/log/messages`.

The *System Logging* settings page can be used to send the logs instead to the Apache error log, or into a file in your web folder.

= My logs are full of garbage! =

Like this?

`Jun  4 11:23:08 potassium php/app.k[1553]: \033[36m\033[44m97a\033[0m \033[32mapp.k/app-content/public-order/policing-football/www.legislation.gov.uk/ukpga/2000/23/contents\033[0m: \033[37m\033[1mfs: Setting page params: page = \033[0m\033[31mnull\033[0m\033[1m, offset = \033[0m\033[31mnull\033[0m\033[1m, size = \033[0m\033[36m10\033[0m\033[1m\033[0m`

You have coloured logging switched on. The codes are unix terminal colour codes, which don't show properly in a normal text editor. You need to use the correct tool to view them.

You can either switch coloured logging off in the settings page, or you can use the included script `log.sh` to decode the coloured logs.


== Screenshots ==

1. The settings page

2. A coloured log file
