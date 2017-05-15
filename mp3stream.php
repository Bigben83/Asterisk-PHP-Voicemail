<script language="PHP">
//mp3stream.php for AST-PHP-VM by Wolf I. Butler
//See cite.php for copyright and version information.
//See release_notes.html for installation information, revision history, and warranty information.

//The point of this whole thing is to transparently (to the user) convert the .wav file saved by Asterisk
//into an MP3 and stream it to the browser. This way- files are only converted to MP3 as-needed, saving
//CPU cycles and memory on the server. The alternative is to convert ALL voicemail files to MP3 by a process
//on the server, which would waste CPU cycles and memory and may cause resource problems for Asterisk.
session_start();
if($_SESSION['logged_in'] != 1) exit;
set_time_limit(240);	//Four minutes should be long enough...
//Note: Tried sending this as audio/mpeg but was having problems with Quicktime killing the stream prematurely
//(which was cutting off all of the messages at random points). I'm not sure if this was QT-specific, but it was
//causing a problem on my devel. machine so I decided to just use an audio/mp3 content-type header instead...
header("Content-type: audio/mp3");
$options = parse_ini_file("phpastvm.conf");
$basedir = $options['basedir'];
if(! $basedir) $basedir = "/var/spool/asterisk/voicemail/default/";
$path = split("/",$_SERVER["PATH_INFO"]);
//Doing this because the actual source file is a .wav, but we use .mp3 in the address to avoid confusing the user.
$wav = substr($path[count($path)-1],0,-4).".wav";
$mbinfo = $_SESSION['mbinfo'];
$ext = $mbinfo['mailbox'];
$folder = $_SESSION['folder'];
$wav_path = "$basedir/$ext/$folder/$wav";
//All these lovely sox options do volume leveling... This sends .mp3 output directly to the browser...
passthru("/usr/local/bin/sox -V $wav_path -r32000 -t .mp3 - compand 0.3,0.3 -90,-90,-70,-70,-60,-20,0,0 -5 0 0.2");
</script>
