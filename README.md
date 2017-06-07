# Asterisk-PHP-Voicemail

A VERY SIMPLE PHP-based Web interface to Asterisk's standard voice mail system.
It is designed to be an alternative to the Web-based voicemail system that ships with Asterisk
because of the potential security risks of using a setuid root Perl script.

https://sourceforge.net/projects/ast-php-vm/


Uncomment and add the following to voicemail.confthis changes the voicemail recording to be editable with this script.

externnotify = /usr/local/bin/recursive.php


#Updates

Making Webpages fully responsive
