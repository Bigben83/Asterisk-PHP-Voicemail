<?php
//index.php for AST-PHP-VM by Wolf I. Butler
//See cite.php for copyright and version information.
//See release_notes.html for installation information, revision history, and warranty information.
session_start();
include('library.php');
//Security function outputs a full login page if not logged-in.
if(! security()) exit;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<head><title>Asterisk PHP Web Voicemail Interface</title>
<link href="main.css" rel="stylesheet" type="text/css" />
</head><body class="Normal"><div align="center">
<script language="PHP">

if($_POST['new_folder'])
{
	$movefolder = clean($_POST['new_folder']);
	if($movefolder == "Trash") $_SESSION['folder'] = "tmp";
	else $_SESSION['folder'] = $movefolder;
}

elseif(is_array($_POST['delete']))
{
	//I know there is a better way to do this, but I'm being lazy for now...
	//Using an array to pass the root filename of the message to delete due to HTML submit button naming issues.
	//I'm sure this can be done differently using Javascript.
	foreach($_POST['delete'] as $index => $value) $shortfile = $index;	//Should only be one index anyway.
	$shortfile = clean($shortfile);
	$pre_len = strlen($vminfo['msgprefix']);
	if(substr($shortfile,0,$pre_len) == $vminfo['msgprefix'])
	//Make sure prefix matches as a minor security step.
	{
		//By default- movemsg will put the file in the Trash folder, which is emptied upon next login...
		if(movemsg($shortfile))
		{
			if($folder == 'tmp') echo "Message permanently deleted(!)...<br>";
			else echo "Message moved to Trash...<br>\n";
		}
		else echo "Error: Unable to move message to Trash!<Br>\n";
	}
}

elseif(is_array($_POST['move']))
{
	//Ditto to most of the comments above...
	foreach($_POST['move'] as $index => $value) $move = $index;
	$arr_move = split(":",$move);	//Should be shortfile and destination...
	$shortfile = $arr_move[0];
	$dest = $arr_move[1];
	if(movemsg($shortfile,$dest)) echo "Message moved to $dest folder...<br>\n";
	else echo "<b>Error: Unable to move message!</b><Br>\n";
}

$_SESSION['folder_list'] = getfolders();

//The following makes session variables more easily available to what follows...
foreach($_SESSION as $index => $value) $$index = $value;

//Temporarily add Trash (./tmp) folder to the list for what follows...
$folder_list['tmp'] = $_SESSION['has_trash'];
if(! $folder_list["$folder"])
{
	//Current folder is empty. If not INBOX- switch to INBOX...
	if($folder != "INBOX")
	{
		if($folder == 'tmp') echo "The <b><i>Trash</i></b> folder is now empty. <b>Switched to INBOX folder...</b>";
		else echo "The <b><i>$folder</i></b> folder is now empty. <b>Switched to INBOX folder...</b>";
		$folder = "INBOX";
		$_SESSION['folder'] = "INBOX";
	}
}

//Delete current folder from folder list...
foreach($folder_list as $index => $value)
{
	//echo "$index => $value <br>\n";	//debugging
	if($folder == $index OR $index == 'tmp')
		unset($folder_list["$index"]);
}

//Use the /tmp folder as the "Trash" folder, and call it that...
if($folder == 'tmp') $folder_name = 'Trash';
	else $folder_name = $folder;

</script>
</div>
<form method="POST">
<div align="center">
<table border="1">
<tr bgcolor="#000099" class="BgWtTxt">
	<td colspan="5" align="center">Mailbox <? echo $mbinfo['mailbox']; ?> for
	<? echo $mbinfo['fullname']; ?>, Folder: <i><b><?echo $folder_name; ?></b></i></td>
</tr>
<tr>
	<td>Caller ID:</td>
	<td>Received:</td>
	<td>Duration:</td>
	<td>Move To:</td>
	<td>Delete:</td>
</tr>
<script language="PHP">
$basedir = $vminfo['basedir'];
$ext = $mbinfo['mailbox'];

$dirhandle = opendir("$basedir/$ext/$folder");

//The following loads a table so it can be indexed on origtime to messages can be sorted for display...
while($filename = readdir($dirhandle))
{
	if(substr($filename,-4) == ".wav")
	{
		$tmpfile = substr($filename,0,-4);	//Base file name with no extension.
		$msg_flag = 1;
		$arr_info = parse_ini_file("$basedir/$ext/$folder/$tmpfile.txt");
		$arr_messages[$arr_info['origtime']]['shortfile'] = $tmpfile;
		foreach($arr_info as $index => $value)
		{
			$arr_messages[$arr_info['origtime']][$index] = $value;
		}
	}
}

krsort($arr_messages);	//Sort by time- reverse order.

//Display voicemail message information and links...
foreach($arr_messages as $arr_msg)
{
	echo "<tr>";
	echo "<td><a href=\"http://asterisk.nbm.com/vm/mp3stream.php/".$arr_msg['shortfile'].".mp3\" target=\"_BLANK\" title=\"Click to play.\">\n";
	if(strlen($arr_msg['callerid']) >= 3) echo $arr_msg['callerid'];
	else echo "<i>Unknown</i>";
	echo "</a></td>\n";
	echo "<td><a href=\"http://asterisk.nbm.com/vm/mp3stream.php/".$arr_msg['shortfile'].".mp3\" target=\"_BLANK\" title=\"Click to play.\">\n";
	echo $arr_msg['origdate'];
	echo "</a></td>\n";
	echo "<td align=\"center\"><a href=\"http://asterisk.nbm.com/vm/mp3stream.php/".$arr_msg['shortfile'].".mp3\" target=\"_BLANK\" title=\"Click to play.\">\n";
	if($arr_msg['duration'] > 0) echo nice_time($arr_msg['duration']);
	echo "</a></td>\n";
	echo "<td>\n";
	foreach($folder_list as $index => $value)
	{
		echo "<input type=\"submit\" name=\"move[".$arr_msg['shortfile'].":$index]\" value=\"$index\">";
	}
	echo "</td>";
	echo "<td><input type=\"submit\" name=\"delete[".$arr_msg['shortfile']."]\" value=\"Delete\"></td>";
	echo "</tr>\n";
}

//No messages in folder...
if(! $msg_flag)
{
	echo "<tr> <td colspan=\"5\" align=\"center\"><b>No messages in this folder.</b></td></tr>\n";
}
echo "</table>";

//Display other folders, if they exist...
if(count($folder_list))
{
	echo "<b>Other Folders:</b> <br>\n";
	$scroller = 0;
	foreach($folder_list as $index => $value)
	{
		$scroller++;
		if($scroller > 5)
		{
			//This is for if someone has a LOT of folders. Prints 5 buttons per line.
			echo "<br>\n";
			$scroller = 0;
		}
		$foldername = $index;
		echo "<input type=\"submit\" name=\"new_folder\" value=\"$index ($value)\"";
		if(! $value) echo " disabled=\"disabled\"";
		echo "> ";
	}

	echo "<Br>";
}

//Display Trash folder if it exists...
if($trashcount = $_SESSION['has_trash'])
{
		echo "<Br><input type=\"submit\" name=\"new_folder\" value=\"Trash ($trashcount)\"><Br> ";
		echo "Note: The Trash folder will be emptied automatically when you logout.<br>\n";
}
</script>
<br><input type="submit" name="logout" value="logout">
</div></form>
<script language="PHP">
include('cite.php');
/////Debugging Block...
/*
if(count($arr_debug))
	foreach($arr_debug as $value) echo "$value <br>\n";
echo "<BR><BR>**********************************************************************************************<br>";
echo "_SESSION:<br>";
foreach($_SESSION as $index => $value) echo "$index => $value<br>"; 	//debugging
echo "<br>mbinfo:<br>";
foreach($mbinfo as $index => $value) echo "$index => $value<br>"; 	//debugging
echo "<br>folder_list:<br>";
foreach($folder_list as $index => $value) echo "$index => $value<br>"; 	//debugging
echo "<br>vmInfo:<br>";
foreach($vminfo as $index => $value) echo "$index => $value<br>"; 	//debugging
*/
</script>
</body>
</html>
