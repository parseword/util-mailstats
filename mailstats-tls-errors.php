#!/usr/local/bin/php
<?php
/** 
Check for 'SSL_accept error' entries in the Postfix maillog. If any such errors 
are found, send a notification email.
*/

/*
 * Copyright 2017 Shaun Cummiskey, <shaun@shaunc.com> <http://shaunc.com>
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and 
 * limitations under the License.
 */

//Import MAILSTATS_DB_HOST, MAILSTATS_DB_USER, MAILSTATS_DB_PASS, MAILSTATS_DB_NAME, MAILSTATS_RECIPIENT
require_once('/etc/config/mailstats.conf');

//Should we ignore (expected) errors from SSL testing services?
define('DISREGARD_SSL_TESTERS', true);
$sslTesterIps = array(
    '192.175.111.254',      // https://htbridge.com/ssl
    '185.55.116.145',       // https://ssl-tools.net
);

//Get the last run timestamp
$conn = new mysqli(MAILSTATS_DB_HOST, MAILSTATS_DB_USER, MAILSTATS_DB_PASS, MAILSTATS_DB_NAME);
if ($conn->connect_errno) {
    echo 'Database is not available.';
    exit(1);
}
$lastrun = $conn->query('SELECT timestamp FROM tls_errors_lastrun')->fetch_array()['timestamp'];
if (!is_numeric($lastrun) || $lastrun == 0) {
    echo 'Value of tls_errors_lastrun.timestamp was invalid.';
    exit(1);
}

//grep the maillog files for new entries
$return = null;
$output = null;
exec('/bin/grep -E "SSL_accept error from" /var/log/maillog*', $output, $return);

//grep returns 0 if match, 1 if no match
if ($return === 0) {
    $errors = '';
    $mtaIps = array();
    $loglines = array();
    
    foreach ($output as $line) {
        //Attempt to only process lines newer than $lastrun. This is iffy because timestamps in
        //maillog don't include a year. We'll get false positives for awhile after a new year;
        //we prevent recording these by using ON DUPLICATE KEY in the INSERT statement later.
        list ($filename, $line) = explode(':', trim($line), 2);
        $timestamp = strtotime(substr($line, 0, 15));
        
        if ($timestamp > $lastrun) {
            //Attempt to parse the origin
            if (preg_match('|from (.*?)\[(.*?)\]:(.*?)|mi', $line, $matches)) {
                $mta_host  = $matches['1'];
                $mta_ip    = $matches['2'];
                
                if (DISREGARD_SSL_TESTERS && in_array($mta_ip, $sslTesterIps)) {
                    continue;
                }
                
                //Log to the database
                $stmt = $conn->prepare('INSERT tls_errors (mta_host, mta_ip, timestamp) '
                    . 'VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=id');
                $stmt->bind_param('ssi', $mta_host, $mta_ip, $timestamp);
                $stmt->execute();
                if ($conn->error != '') {
                    $errors .= $conn->error . "\n";
                }
                
                //If we logged a row, track the origin and log line for the notification mail
                if ($stmt->affected_rows > 0) {
                    $loglines[$timestamp] = $line;
                    //Ternary operator won't work here; not sure why
                    if (@isset($mtaIps[$mta_ip])) {
                        $mtaIps[$mta_ip]++;
                    }
                    else {
                        $mtaIps[$mta_ip] = 1;
                    }
                }
//               echo "$line \n Affected rows: " . $stmt->affected_rows . "\n";
            }
        }
    }
    
    //If we did anything, let's brag about it
    if (count($loglines) > 0) {
        //Build the email notification
        $data = 'TLS errors since ' 
            . date('Y-m-d H:i:s', $lastrun) . " follow:\n\n";
            
        arsort($mtaIps);
        foreach ($mtaIps as $key=>$value) {
            $data .= '(' . $value . ') ' . $key . "\n"; 
        }
        $data .= "\nRaw log lines follow:\n\n";
        ksort($loglines);
        foreach ($loglines as $line) {
            $data .= "$line\n";
        }
        if ($errors != '') {
            $data = $errors . "\n" . $data;
        }
        
        //Send email notification
        mail(MAILSTATS_RECIPIENT, '[mailstats] Postfix TLS errors on ' . gethostname(), $data, 'From: root@localhost');
    }
    
    //Update the last run timestamp
    $conn->query('UPDATE tls_errors_lastrun SET timestamp=' . time());
}
