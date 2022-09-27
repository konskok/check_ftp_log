#!/usr/bin/php
<?php

# default values for externally definable parameters 
$cfg['ftp-host'] = "10.0.0.1";				# storage ftp hostname.
$cfg['ftp-path'] = "";								# location of logfiles on the storage ftp
$cfg['ftp-username'] = "username";					# login for storage ftp.
$cfg['ftp-password'] = "secret_password";			# password for storage ftp.
$cfg['log-age'] = 336;								# log age threshold in hours.	
$cfg['logfile-age'] = 16;							# logfile age threshold in days.
$cfg['min-log-entry'] = 120;						# minimal size of logfile entry in bytes.
$cfg['data-source'] = 'log';						# script may get data either from text 'log' or from 'filename'
$cfg['filename-pattern-ok'] = "OK";					# Successfull backup flag for filename mode
$cfg['filename-pattern-warn'] = "WARN";				# Warning flag for filename mode

# initial variables
define( "STATUS_OK", 0 );
define( "STATUS_WARNING", 1 );
define( "STATUS_CRITICAL", 2 );
define( "STATUS_UNKNOWN", 3 );
$i = 0;
$n = 0;
$full_log = "";
$filename = "";


# define timezone and set now_time timespamp
date_default_timezone_set('Europe/Madrid');
$now = date("d.m.Y H:i:s");
$now_time = strtotime($now);

# extract parameters from command line to $cfg
foreach ($argv as &$val) {
	if (preg_match("/\-\-/", $val) && $argv[$i+1] && !preg_match("/\-\-/", $argv[$i+1]) ) {
		$varvalue = $argv[$i+1];
		$varname = str_replace("--", "", $val);
		$cfg[$varname] = $varvalue;
	}
	$i++;
}

# Throw error if filename pattern was not specified in the input
if (!$cfg['bak-file-pattern']) { 
		echo "No filename pattern specified. Can't run.";
		exit(STATUS_UNKNOWN);
}

# open ftp connection
$ftp_conn = ftp_connect($cfg['ftp-host']);
$login_result = ftp_login($ftp_conn, $cfg['ftp-username'], $cfg['ftp-password']);

# Throw error if there is a problem with ftp connection.
if (!$login_result) { 
		echo "FTP connection error.";
		exit(STATUS_UNKNOWN);
}

# set mode, list files
ftp_pasv($ftp_conn, true);
$loglist = ftp_nlist($ftp_conn, "./".$cfg['ftp-path']);

# Throw error if file list was empty.
if (!$loglist) { 
		echo "Logfile directory empty.";
		exit(STATUS_UNKNOWN);
}

$threshold = $cfg['log-age'];
$ptrn = $cfg['bak-file-pattern'];

# set newest as future timestamp.
$newest = $now_time + 14400;
if ( $cfg['data-source'] == 'log'  ) {
	foreach ($loglist as &$fname) {
		if (count(ftp_nlist($ftp_conn, $fname)) == 1) {
			# Filename date extraction pattern.
			# here we define log filename pattern, e.g. ./fzs-2022-09-01.log
			# date in the log filename is used to list and filter logfile by its age 
			# Note that file attributes are ignored.
			# 
			$date_pattern = '/(\d{4}-\d{2}-\d{2})/';
			preg_match_all($date_pattern, $fname, $res, PREG_PATTERN_ORDER);
			$file_time = strtotime($res[1][0]);
			$days_diff = round(($now_time - $file_time) / 86400);
			if ($days_diff < $cfg['logfile-age']) { 
				$logid = fopen('php://temp', 'r+');
				ftp_fget($ftp_conn, $logid, $fname, FTP_BINARY, 0);
				$fstats = fstat($logid);
				fseek($logid, 0);
				if ($fstats['size'] > 0) {
					$logtext = fread($logid, $fstats['size']);
				}
				fclose($logid);
				$full_log = $full_log."/n".$logtext;
			}
		}
	}
	ftp_close($ftp_conn);
	
	# Throw error if log contents is shorter than min-log-entry.
	if (strlen($full_log) < $cfg['min-log-entry']) { 
		echo "Log file is too short.";
		exit(STATUS_UNKNOWN);
	}
	# Log record extraction pattern.
	# Here we are trying to find the specific entry that fits the standard log record for successful STOR operation. 
	# Example:
	# (000051) 01.09.2022 2:22:55 - ftp_user (10.0.1.2)> 226 Successfully transferred "/path/to/file/filename_pattern_2022_09_01_010000_6539791.bak"
	# Note that line endings (\r and \n) should always be added for the lazy search to work properly.

	$pattern = '/\(\d{5,7}\) (.+?) - (.+?) \((.+?)\)\> 226 Successfully transferred \"(.+?)'.$cfg['bak-file-pattern'].'(.+?)\"\r\n/';
	preg_match_all($pattern, $full_log, $lines, PREG_PATTERN_ORDER);
	$logdates = $lines[1];

	foreach ($logdates as &$datetime) {
		
			$backup_time = strtotime($datetime);
			$backup_age = round ( ($now_time - $backup_time) / 3600 );
						
			if ($backup_age < $newest) {
				$newest = $backup_age;
				$ftpuser = $lines[2][$n];
				$filename = $cfg['bak-file-pattern'].$lines[5][$n];
			} 

			$n++;
	}

	if ($newest <= $threshold && $filename) {
		echo "Last backup: $newest hours ago ($datetime)\nSuccessfull STOR: $filename by user $ftpuser\n";
		exit(STATUS_OK);
	} elseif ($newest > $threshold && $filename) {
		echo "Backup expired: newest $newest hours ago ($datetime). Expected $threshold hours. \nSuccessfull STOR: $filename by user $ftpuser.\n";
		exit(STATUS_WARNING);
	} elseif (!$filename) {
		echo "No relevant backup found for pattern $ptrn\n";
		exit(STATUS_CRITICAL);
	}
} elseif ( $cfg['data-source'] == 'filename' ) {
	foreach ($loglist as &$fname) {
		# Filename date extraction pattern for filename mode.
		# here we define file or folder name pattern, e.g. ./FLAG_16.09.2022_22-13-05_OK
		# backup date and status are extracted from the flag folder or filename.
		# 
		$date_pattern = '/(\d{2})\.(\d{2})\.(\d{4})_(\d{2})-(\d{2})-(\d{2})_(.+?)$/';
		preg_match_all($date_pattern, $fname, $res, PREG_PATTERN_ORDER);

		$datetime = $res[3][0]."-". $res[2][0]."-". $res[1][0]." ". $res[4][0].":". $res[5][0].":". $res[6][0];
		$file_time = strtotime($datetime);
		$backup_age = $now_time - $file_time;
				
		if ( preg_match("/".$cfg['bak-file-pattern']."/",$fname) && $backup_age < $newest) { 
			$newest = $backup_age;
			$filename = $fname;
			$newesttime = $datetime;
			$flag = $res[7][0];
		}
	}
	
	$newest = round($newest / 3600);
	
	if ($newest <= $threshold && $flag == $cfg['filename-pattern-ok']) {
		echo "Last backup: $newest hours ago ($newesttime)\n Completed successfully $filename. \n";
		exit(STATUS_OK);
	} elseif ($newest > $threshold && $flag == $cfg['filename-pattern-ok']) {
		echo "Backup expired: newest $newest hours ago ($newesttime). Expected $threshold hours. \nCompleted successfully $filename. \n";
		exit(STATUS_WARNING);
	} elseif ($newest <= $threshold && $flag == $cfg['filename-pattern-warn']) {
		echo "Last backup: $newest hours ago ($newesttime)\nLast backup completed with Warning $filename. \n";
		exit(STATUS_WARNING);
	} elseif ($newest > $threshold && $flag == $cfg['filename-pattern-warn']) {
		echo "Last backup: $newest hours ago ($newesttime). Expected $threshold hours. \nExpired. Last backup completed with Warning $filename. \n";
		exit(STATUS_WARNING);
	} elseif (!$flag) {
		echo "No relevant backup found for pattern $ptrn\n";
		exit(STATUS_CRITICAL);
	}
}

?>
