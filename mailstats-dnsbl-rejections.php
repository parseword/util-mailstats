#!/usr/local/bin/php
<?php
/** 
Log the number of rejected emails (the daily spam reject count) for each 
DNSBL to a database and send a notification email.
*/

/*
 * Copyright 2016 Shaun Cummiskey, <shaun@shaunc.com> <http://shaunc.com>
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

$conn = new mysqli(MAILSTATS_DB_HOST, MAILSTATS_DB_USER, MAILSTATS_DB_PASS, MAILSTATS_DB_NAME);
if ($conn->connect_errno) {
    echo 'Database is not available.';
    exit(1);
}

/**
Build the dates we need to use. The maillog format is "Nov 17" or "Feb  2", note
two spaces when the daypart is a single digit. The script can accept a date in
the form YYYY-MM-DD as a command line argument to force processing a certain day,
e.g.    php mail-reject-stats.php 2015-09-17
*/
if ($argc == 2 && preg_match('|^\d{4}-\d{2}-\d{2}$|', $argv['1'])) {
    //A date was passed from the command line
    list($year, $month, $day) = explode('-', $argv['1']);
    $daypart = sprintf('% 2d', date('d', mktime(0, 0, 0, $month, $day, $year)));
    $date = date('M ', mktime(0, 0, 0, $month, $day, $year)) . $daypart;
    $dbdate = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
}
else {
    //Default to using yesterday's date
    $daypart = sprintf('% 2d', date('d', time()-86400));
    $date = date('M ', time()-86400) . $daypart;
    $dbdate = date('Y-m-d', time()-86400);
}

//If the date has two spaces, cut field 21, otherwise cut field 20
$cutField = substr($daypart, 0, 1) == ' ' ? 21 : 20;
$command = "grep '{$date}' /var/log/maillog* | grep 'Service unavailable' "
    . "| cut -f{$cutField} -d' ' | sort -n | uniq -c | sort -nr";

$return = null;
$output = null;
exec($command, $output, $return);

//grep returns 0 if match, 1 if no match
if ($return === 0 && count($output) > 0) {
    
    /**
    Sample grep output looks like this:
    
      42529 sbl-xbl.spamhaus.org;
       1548 bad.psky.me;
        632 dnsbl.dronebl.org;
        503 dyna.spamrats.com;
        315 noptr.spamrats.com;
        195 psbl.surriel.com;
         24 cbl.abuseat.org;
    **/
    
    $errors = '';
    
    foreach ($output as $line) {
        //Determine the DNSBL and the number of mails it blocked
        list ($count, $dnsbl) = explode(' ', str_replace(';', '', trim($line)));
        
        //Log to the database
        $stmt = $conn->prepare('INSERT dnsbl_reject_stats (date, dnsbl, rejections) VALUES (?,?,?)');
        $stmt->bind_param('ssi', $dbdate, $dnsbl, $count);
        $stmt->execute();
        if ($conn->error != '') {
            $errors .= $conn->error . "\n";
        }
    }

    //Build the email notification
    $data = 'Mail rejections from DNSBLs for ' . $dbdate . " follow:\n\n";

    foreach ($output as $line) {
        $data .= "$line\n";
    }
    if ($errors != '') {
        $data = $errors . "\n\n" . $data;
    }
    
    //Send email notification
    mail(MAILSTATS_RECIPIENT, '[mailstats] DNSBL rejections on ' . gethostname() . ' for ' 
        . $dbdate, $data, 'From: root@localhost');
}
