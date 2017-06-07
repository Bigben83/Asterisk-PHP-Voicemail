<?php

$dir = '/var/spool/asterisk/voicemail';

exec ("find $dir -type d -exec chmod 0777 {} +");
exec ("find $dir -type f -exec chmod 0775 {} +");
?>