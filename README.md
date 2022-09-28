# check_ftp_log
Nagios/Icinga plugin for checking results of the ftp backup by reading and analysing ftp log files.

Max.Fischer <dev@monologic.ru>
Tested on CentOS GNU/Linux 6.5 with Icinga r2.6.3-1

![Icinga Plugin - FTP log checks](/icinga-example.png?raw=true "Icinga Plugin - FTP log checks")

supposed to be placed in nagios plugins directory, i.e.:
```
/usr/lib/nagios/plugins/check_ftp_log.php - CHMOD 755
```

Usage example:
```
./check_ftp_log.php 
--ftp-host 10.0.1.1 --bak-file-pattern web_storage_ --ftp-username username --ftp-password secret_password --log-age 36
```

## Modes
Script may run in one of two modes set trough command line parameter **--data-source [log|filename]**.

**--data-source log [Default]**. Fetching log files from FTP server via ftp protocol, parsing text and searchng for the appropriate 
record indicating successful completion of the upload process:

```
(000051) 01.09.2022 2:22:55 - ftp_user (10.0.1.2)> 
226 Successfully transferred "/path/to/file/filename_pattern_2022_09_01_010000_6539791.bak"
```

**--bak-file-pattern** Backup filename pattern to search for. 
**--log-age** Backup age threshold (in hours), default value is 336. 
**--logfile-age** Log file age limit (in days), default value is 16. 
**--ftp-path** Relative path to ftp directory. Ftp user root folder is used by default.

If the record was found in log, and it is newer than **--log-age** threshold, OK status returned.
If the record was found in log, but it is older then **--log-age** threshold, WARNING status returned.
If there were no appropriate record found, CRITICAL status returned.


**--data-source filename**. Listing filenames from the given folder on ftp and searching for the flag filename. Example:
```
./FLAG_16.09.2022_22-13-05_OK
```

Flag file is supposed to be created by an external application that does not support writing conventional log files.
**--bak-file-pattern** Flag filename pattern to search for. 
**--log-age** Backup age threshold (in hours), default value is 336. 
**--filename-pattern-ok** and **--filename-pattern-warn** are result flag strings, default values are OK and WARN respectively.
**--ftp-path** Relative path to ftp directory. Ftp user root folder is used by default.
 
If OK flag file was found, and it is newer than **--log-age** threshold, OK status returned.
If OK flag file was found, but it is older then **--log-age** threshold, WARNING status returned.
If WARN flag file was found, WARNING status returned.
If there were no appropriate filename found, CRITICAL status returned.






## License

check_ftp_log is licensed under the [MIT](https://www.mit-license.org/) license for all open source applications.

## Bugs and feature requests

If you find a bug, please report it [here on Github](https://github.com/xyhtac/check_ftp_log/issues).

Guidelines for bug reports:

1. Use the GitHub issue search — check if the issue has already been reported.
2. Check if the issue has been fixed — try to reproduce it using the latest master or development branch in the repository.
3. Isolate the problem — create a reduced test case and a live example. 

A good bug report shouldn't leave others needing to chase you up for more information.
Please try to be as detailed as possible in your report.

Feature requests are welcome. Please look for existing ones and use GitHub's "reactions" feature to vote.
