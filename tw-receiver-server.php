<?php
// during initial setup set $debug = true
// accessing tw-receiver-server.php directly (GET Request) will perform some access and configuration tests and report
// if this is off, most error reporting is disabled throughout
$debug = true;

// the backup mechanism is very basic, and only keeps x last saves
// backup directory, default is fine. set $backupdir=false to disable backups
$backupdir = 'twbackups';
// number of backups to keep 
$backupcount = 10;

// enable the use of an ini file to store secret key outside of the web accessible directory (good practice)
// requires placing the twreceiver_config.ini in an external directory and providing the path
// Example: $extSecKeyPath = "../privatedir/tw-receiver-config.ini"
// if you don't have access to a non accessible space, or aren't sure how to do this, do not use this feature
// instead set $extSecKeyPath = false and use the $password variable
// wherever you set the password, have fun with it and make it a long list of words
$extSecKeyPath = false; 
$userpassword = "hello i'm a short friendly password"; 

// there's no way to securely transmit over HTTP. Using HTTP your password and content can be viewed and changed
// use of HTTPS (TLS) is strongly recommended.
// the following setting enables Digest Authentication and Server Challenge Request Mode. 
// Think of it as low budget security. This will prevent a number of attacks, but it is not a replacement for proper HTTPS
// Try out HTTPS, check out https://letsencrypt.org/
$challengeDigestAuthentication = true;

// enable data integrity signing (recommended)
// this creates a unique signature of the wiki text and the secret key being passed in
// checking the validity of this signature helps to prevent tampering of the payload mid stream
$dataIntegritySigning = true;


function processPostParams($poststring, $delim_major = '&', $delim_minor = '='){
	$return_arr = [];
	
	foreach (explode($delim_major,$poststring) as $value) {
		$minorsplit = explode($delim_minor, $value);
		$return_arr[$minorsplit[0]] = $minorsplit[1];
	}
	
	return $return_arr;
}

function failWithMsg($msg){
	global $debug;
	
	if(!$debug) {
		exit('debug is disabled');
	}
	
    exit($msg);
}

function performBackup($currentfile){
	global $backupdir, $backupcount;
	
	// check that backups are enabled
	if(!$backupdir){
		return false;
	}
	
	// verify file exists
	if(!file_exists($currentfile)) {
		return false;
	}
	
	//verify backup dir exists
	if(!is_dir($backupdir)) {
		if(!mkdir($backupdir, 0755)){
			failWithMsg('Server Error: unable to create backup directory');
		}
	}
	
	//move existing file and timestamp it
	$filenameparts = pathinfo($currentfile);
	if($filenameparts['extension'] != 'html' &&  $filenameparts['extension'] != 'htm'){
		failWithMsg('Server Error: bad file extension');
	}
	
	$backupfile = $backupdir . '/' . $filenameparts['filename'] . '.' . time() . '.' . $filenameparts['extension'];
	if(!rename($currentfile, $backupfile)) {
		failWithMsg('Server Error: backup failed, could not move file');
	}
	
	// perform backup pruning duties
	$dircontents = array_diff(scandir($backupdir, 1), array('..', '.')); //removes . and .. dir listings
	// remove dirs from array
	foreach ($dircontents as $key => $val) {
		if(is_dir($backupdir.'/'.$val)){
			unset($dircontents[$key]);
		}
	}

	if(count($dircontents) < $backupcount){
		return true; //no prune required
	}
	
	// really basic pruning mechanism, could be improved with date logic
	foreach($dircontents as $k => $file){
		if($k > $backupcount-1) {
			if(!unlink($backupdir.'/'.$file)){
				failWithMsg('Server Error: backup pruning failure, could not remove file');
			}
		}
	}
	
	return true; // complete backup success
}

function authenticateRequest($submittedkey){
	global $challengeDigestAuthentication;
	$sharedkey= getSharedSecret();
	
	// check for challengeDigestAuthentication mode
	if(!$challengeDigestAuthentication){
		if($sharedkey === $submittedkey) {
			return true;
		}
	}
	elseif($challengeDigestAuthentication) {
		if(testChallengeKey($submittedkey, $sharedkey)){
			return true;
		}
	}
	
	return false;
}

function createChallengeToken(){

	// start a session for this request chain
	session_start();
	$ctoken = hash("sha256", time()+random_int(100,1999));
	//$ctoken = hash("sha256", $iterator . gmdate('YmdHi')); //time based expiry
	// store challenge token to session
	$_SESSION["ctoken"]= $ctoken;
	
	return $ctoken;
}

function getSharedSecret(){
	global $extSecKeyPath, $userpassword;
	$sharedkey="";
	if($extSecKeyPath){
		$config = parse_ini_file($extSecKeyPath);
		$sharedkey = $config['seckey'];
	}
	else {
		$sharedkey = $userpassword;
	}
	
	return $sharedkey;
}

function testChallengeKey($submittedkey, $sharedsecret){
	session_start();
	$ckey = hash("sha256", $sharedsecret . $_SESSION["ctoken"]);
	session_destroy();

	if($ckey === trim($submittedkey)){
		return true;
	}
	return false;
}

function checkDataSignature($submitteddatasig, $datafile) {
	$seckey = getSharedSecret();
	$textdata = file_get_contents($datafile);
	$datasig = hash("sha256", $textdata . $seckey);
	
	if(trim($submitteddatasig) == $datasig) {
		return true;
	}
	
	return false;
}
/*
function testChallengeKey($submittedkey, $sharedsecret){
	$ckey = hash("sha256", $sharedsecret . hash("sha256", getIterator() . gmdate('YmdHi'))); //valid now
	$ckey2 = hash("sha256", $sharedsecret . hash("sha256", getIterator() . gmdate('YmdHi', (time()-60)))); //valid 1 min ago

	if($ckey === $submittedkey || $ckey2 === $submittedkey){
		return true;
	}
	return false;
}
*/

if($_SERVER['REQUEST_METHOD'] == 'GET') {
	
	// logic to return challenge token 
	if(isset($_GET['md']) && $_GET['md'] == 'gct'){
		if($challengeDigestAuthentication){
			echo createChallengeToken();
			exit();
		}
	}
	
	// if debug mode, show setup test page
	if(!$debug) {
		exit('debug is disabled');
	}
?>
<h1>Debug Tests</h1>
<table>
<tr>
	<td>ini setting: file_uploads</td>
	<td><?php echo (ini_get('file_uploads')==1)?'OK':'<span style="color:red">FAIL</span>' ?></td>
</tr>
<tr>
	<td>ini setting: upload_max_filesize</td>
	<td><?php echo ini_get('upload_max_filesize') ?></td>
</tr>
<tr>
	<td>ini setting: post_max_size</td>
	<td><?php echo ini_get('post_max_size') ?></td>
</tr>
<tr>
	<td>backups enabled</td>
	<td><?php echo ($backupdir)?'YES':'NO' ?></td>
</tr>
<tr>
	<td>backups max count</td>
	<td><?php echo $backupcount ?></td>
</tr>
<tr>
	<td>backups directory exists</td>
	<td><?php echo (is_dir($backupdir))?'OK':'<span style="color:red">FAIL</span>' ?></td>
</tr>
<tr>
	<td>backups directory is writable</td>
	<td><?php echo (is_writable($backupdir))?'OK':'<span style="color:red">FAIL</span>' ?></td>
</tr>
<tr>
	<td>this directory is writable</td>
	<td><?php echo (is_writable("."))?'OK':'<span style="color:red">FAIL</span>' ?></td>
</tr>
<tr>
	<td>external key enabled</td>
	<td><?php echo ($extSecKeyPath)?'YES':'NO' ?></td>
</tr>
<tr>
	<td>external key reachable</td>
	<td><?php echo (file_exists($extSecKeyPath))?'YES':'NO' ?></td>
</tr>
<tr>
	<td>secure connection (https)</td>
	<td><?php echo (@$_SERVER['HTTPS'] != "")?'YES':'NO' ?></td>
</tr>
<tr>
	<td>challenge digest auth mode</td>
	<td><?php echo ($challengeDigestAuthentication)?'YES':'NO' ?></td>
</tr>
<tr>
	<td>check data integrity signature</td>
	<td><?php echo ($dataIntegritySigning)?'YES':'NO' ?></td>
</tr>
</table>

<br /><b>Notes:</b>
<br />- Your <i>upload_max_filesize</i> and <i>post_max_size</i> must be at least larger than your wiki filesize
<br />- On NGINX client_max_body_size is another parameter worth looking at if uploads fail with <i>413 Request Entity Too Large</i>
<?php
	exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {

	if(isset($_POST['twreceiverparams'])){
		$postparameters = processPostParams($_POST['twreceiverparams']);
		
		if(isset($postparameters['seckey']) && $postparameters['seckey'] != ""){
			$submittedkey = $postparameters['seckey'];
		}
		else {
			failWithMsg('Server Error: Missing Authentication Parameters');
		}
	}
	else {
		failWithMsg('Server Error: Missing Parameters');
	}

	// check credentials
	if(!authenticateRequest($submittedkey)){
		failWithMsg('Server Error: Authentication Failure');
		exit();
	}
	
	// check data integrity
	if($dataIntegritySigning) {
		if(isset($postparameters['datasig']) && $postparameters['datasig'] != ""){
			$submitteddatasig = $postparameters['datasig'];
			if(!checkDataSignature($submitteddatasig, $_FILES['userfile']['tmp_name'])){
				failWithMsg('Server Error: Data Integrity Failure');
			}
		}
		else {
			failWithMsg('Server Error: Missing Data Signature');
		}
	}

	$destinationfile = "./" . basename($_FILES['userfile']['name']);
	$file_extension = strtolower(pathinfo($destinationfile,PATHINFO_EXTENSION));

	// not interested in file types other than html
	if($file_extension != "html" && $file_extension != "htm") {
		failWithMsg('Server Error: Bad File Extension');
	}
	
	// if file already exists, backup
	if(file_exists($destinationfile)) {
		if(!performBackup($destinationfile)  && $backupdir){
			failWithMsg('Server Error: Backup Failure');
		} 
	}

	// this method ensures we're moving and renaming an uploaded file
	if(move_uploaded_file($_FILES['userfile']['tmp_name'], $destinationfile)) {
		echo "000 - ok";
	}
	else {
		failWithMsg('Upload Failed');
	}
}
?>
