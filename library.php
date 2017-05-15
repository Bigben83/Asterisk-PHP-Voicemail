<?php
//library.php (function library) for AST-PHP-VM by Wolf I. Butler
//See cite.php for copyright and version information.
//See release_notes.html for installation information, revision history, and warranty information.

function dbopen($vminfo)
{
	//Read MySQL login information from res_mysql.conf
	//(No reason to duplicate this file or data, since we need the same level of access anyway!)
	$arr_mysql_info = parse_ini_file($vminfo['confdir']."/res_config_mysql.conf");
	$mysql_link = mysql_connect($arr_mysql_info['dbhost'],$arr_mysql_info['dbuser'],$arr_mysql_info['dbpass']);
	mysql_select_db($arr_mysql_info['dbname'],$mysql_link);
	if(!mysql_error()) return($mysql_link);
	else return 0;
}

function vminfo()
{
	//Get initial configuration from astphpvm.conf file...
	$arr_phpastvm = parse_ini_file("astphpvm.conf");
	if($arr_phpastvm['confdir']) $vminfo['confdir'] = $arr_phpastvm['confdir'];
	else $vminfo['confdir'] = '/etc/asterisk'; 	//Default
	if($arr_phpastvm['basedir']) $vminfo['basedir'] = $arr_phpastvm['basedir'];
	else $vminfo['basedir'] = '/var/spool/asterisk/voicemail/default'; 	//Default
	if($arr_phpastvm['msgprefix']) $vminfo['msgprefix'] = $arr_phpastvm['msgprefix'];
	else $vminfo['msgprefix'] = 'msg'; 	//Default
	//Get voicemail database information from extconf.conf...
	$arr_extconf = parse_ini_file($vminfo['confdir']."/extconfig.conf");
	$arr_vmdb = split(",",$arr_extconf['voicemail']);	//Should be "mysql,asterisk,(dbname)"
	$vminfo['vmtable'] = $arr_vmdb[2];	//Just need the table name.
	//Get voicemail system information from voicemail.conf...
	//Currently just using the "format" parameter to get valid voicemail file extensions.
	//Unfortunately, because the format string in voicemail.conf uses a "|" character- parse_ini_file won't work...
	//$arr_voicemail = parse_ini_file($vminfo['confdir']."/voicemail.conf");
	//Manually parse voicemail.ini file... (Might try to come up with a better way later...)
	$arr_vmconf = file($vminfo['confdir']."/voicemail.conf");
	foreach($arr_vmconf as $value)
	{
		if(ereg("^format*",$value))
		{
			$arr_temp = split('=',$value);
			$vminfo['fileext'] = trim($arr_temp[1]);
		}
	}
	return($vminfo);
}

function getuserinfo($mailbox)
{
	//Get all user information for passed mailbox from database...
	$mysql_link = dbopen($_SESSION['vminfo']);
	$vminfo = $_SESSION['vminfo'];
	$vmtable = $vminfo['vmtable'];
	$query = "select * from `$vmtable` where mailbox = '$mailbox'";
	if($mysql_result = mysql_query($query,$mysql_link))
	return(mysql_fetch_array($mysql_result));
	else
	{
		echo "Query: $query <br>";
		return 0;
	}
}

function security()
{
	//Handle logout...
	if($_POST['logout'] == "logout")
	{
		empty_trash();
		unset($_SESSION);
		session_destroy();
		session_start();
	}
	//Validate login...
	if(is_array($_SESSION['mbinfo']) and $_SESSION['logged_in'] == 1) return(1);
	else
	{
		if(! $_SESSION['vminfo']) $_SESSION['vminfo'] = vminfo();
		if($_POST['userid'] and $_POST['password'])
		{
			//Validate login...
			if($mbinfo = getuserinfo($_POST['userid']))
			{
				if($_POST['password'] == $mbinfo['password'])
				{
					$_SESSION['mbinfo'] = $mbinfo;
					$_SESSION['logged_in'] = 1;

					//Set global options...
					$_SESSION['folder'] = "INBOX"; //Set default folder
					empty_trash();
					return(1);
				}
				else
				{
					unset($_SESSION['mbinfo']);
					unset($_SESSION['logged_in']);
					echo "Bad login!<br>";
				}
			}
			else echo "Login Database Error!<Br>\n";
		}
		//Show login screen...
		//Need to send full HTML since this function runs before any other main program output.
		?>
			<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
			<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
			<head><title>Asterisk PHP Web Voicemail Login</title>
			<link href="main.css" rel="stylesheet" type="text/css" />
			</head>
			<body>
			<form method="POST"><div align="center">
			<table width="21%" border="0" cellspacing="3" cellpadding="3">
				<tr bgcolor="#000099">
      			<td><div align="center" class="BgWtTxt"><strong>Voicemail Login</strong></div></td>
    			</tr>
    			<tr class="Normal">
      			<td><div align="center">Mailbox:
          		<input type="text" name="userid" size="3" maxlength="10" />
        			</div>
        			</td>
			   </tr>
			   <tr class="Normal">
			   	<td><div align="center">Password:
          	<input type="password" name="password" size="3" maxlength="10" />
        			</div>
        			</td>
    			</tr>
    			<tr class="Normal">
      			<td><div align="center">
          		<input type="submit" name="Login" value="Login" />
        			</div>
        			</td>
    			</tr>
  			</table>
			</form></div>
			<? include('cite.php'); ?>
			</body>
			</html>
		<?php
		exit;
		return(0);
	}
}


function getfolders()
{
	//Get folder list for mailbox...
	$basedir = $_SESSION['vminfo']['basedir'];
	$ext = $_SESSION['mbinfo']['mailbox'];
	$arr_dir = scandir("$basedir/$ext");
	foreach($arr_dir as $filename)
	{
		if(is_dir("$basedir/$ext/$filename"))
		{
			//echo "$basedir/$ext/$filename<Br>";	//debugging
			//All VM folders created by Asterisk start with a capital letter...
			if(ereg("^[A-Z]+[A-Za-z0-9]*",$filename))
			{
				$count = 0;
				if(is_array($arr_msgs = scandir("$basedir/$ext/$filename")))
				{
					foreach($arr_msgs as $msgname)
					{
						//I spent hours trying to get an ereg statement to do this check, but just couldn't get it to work...
						//Will re-visit it someday.
						$pre = $_SESSION['vminfo']['msgprefix'];
						$prelen = strlen($pre);
						if(substr($msgname,0,$prelen) == $pre && substr($msgname,-4) == '.txt')
						$count++;
					}
				}
				$arr_folder["$filename"] = $count;
			}
		}
	}
	if(is_array($arr_folder)) return($arr_folder);
	else return(0);
}

function nice_time($seconds)
{
	$mins = floor($seconds / 60);
	if($mins == 0)
	{
		$mins = "00";
	}
	else
	{
		if($mins < 10)
		{
			$mins = "0".$mins;	//Zero pad.
		}
	}
	$secs = $seconds % 60;
	if($secs < 10) $secs = "0".$secs;	//Zero pad.
	return("$mins:$secs");
}

function cktrash()
{
	$dirhandle = opendir($_SESSION['vminfo']['basedir']."/".$_SESSION['mbinfo']['mailbox']."/tmp");
	$_SESSION['has_trash'] = 0;
	while($filename = readdir($dirhandle))
	{
		if(substr($filename,-4) == ".txt")
		{
			$_SESSION['has_trash'] ++;
		}
	}
	return($_SESSION['has_trash']);
}

function clean($button)
{
	//Just strips the "(xx)" from a submitted button value. Used because we have added the message count after
	//folder names on all of the folder buttons.
	$tmp_array = split("\\(",$button);
	//echo "$button => ".trim($tmp_array[0])."<br>";	//debugging
	return trim($tmp_array[0]);
}

function movemsg($msg, $dest = "tmp")
{
	//*************Not working right!**************
	//Move message from current folder to another.
	//If no destination is specified- message is moved to the ./tmp (trash) folder.
	//Because of the way Asterisk labels messages, it is necessary to determine the next available message
	//number before actually moving each message.
	$from = $_SESSION['vminfo']['basedir']."/".$_SESSION['mbinfo']['mailbox']."/".$_SESSION['folder'];
	$to = $_SESSION['vminfo']['basedir']."/".$_SESSION['mbinfo']['mailbox']."/".$dest;
	if($from == $to)
	{
		//This will only happen if the source file is in the ./tmp folder. Since this is used for trash-
		//We just need to really delete the file, and not bother with anything else...
		$arr_exten = split("\|",$_SESSION['vminfo']['fileext']);
		$arr_exten[] = "txt";	//Add .txt file.
		foreach($arr_exten as $value)
		{
			unlink($from."/$msg.$value");
		}
		cktrash();
		return(1);
	}
	else
	{
		//Need to get next message number to use for the file name...
		for($counter = 0 ; $counter <= 9999 ; $counter++)
		{
			if(! is_file($to."/".$_SESSION['vminfo']['msgprefix'].sprintf('%04d',$counter).".txt"))
			{
				//Touch the .txt file quickly, just to make sure it is reserved...
				touch($to."/".$_SESSION['vminfo']['msgprefix'].sprintf('%04d',$counter).".txt");
				$destfile = $_SESSION['vminfo']['msgprefix'].sprintf('%04d',$counter);
				$counter = 10000;	//Kill loop
			}
		}
		$arr_exten = split("\|",$_SESSION['vminfo']['fileext']);
		$arr_exten[] = "txt";	//Add .txt file.
		$exten_count = count($arr_exten);
		$move_count = 0;
		foreach($arr_exten as $value)
		{
			//Cycles through the file extensions (types) indicated in the system...
			if(rename($from."/$msg.$value",$to."/$destfile.$value"))
			$move_count++;
		}
		if($exten_count == $move_count)
		{
			cktrash();
			return(1);	//Should be equal as long as there wasn't an error.
		}
	}
	cktrash();
	return(0);
}

function empty_trash()
{
	if($_SESSION['vminfo']['basedir'] AND $_SESSION['vminfo']['msgprefix'])	//Safety
	{
		global $arr_debug;
		$basedir = $_SESSION['vminfo']['basedir'];
		$tmp_dir = opendir($basedir."/".$_SESSION['mbinfo']['mailbox']."/tmp");
		while($filename = readdir($tmp_dir))
		{
			//Only delete files starting with designated prefix (default "msg").
			//Not sure if other files will turn up here from system, so better safe than sorry.
			if(ereg("^".$_SESSION['vminfo']['msgprefix']."*",$filename))
			{
				$debug[] = "Deleting $basedir/".$_SESSION['mbinfo']['mailbox']."/tmp/".$filename;
				unlink($basedir."/".$_SESSION['mbinfo']['mailbox']."/tmp/".$filename);
				return(1);
			}
			else $arr_debug[] = "Not deleting $basedir/".$_SESSION['mbinfo']['mailbox']."/tmp/".$filename;
		}
	}
	return(0);
}
?>
