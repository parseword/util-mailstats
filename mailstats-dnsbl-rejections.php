#!/usr/local/bin/php
<?php
/*
 * mailstats-dnsbl-rejections.php
 * Parse DNSBL rejection counts from the maillog and store them to a database.
 * See: <https://shaunc.com/go/lgarhnKWdYRv>
 *
 * Copyright 2016 Shaun Cummiskey, <shaun@shaunc.com> <https://shaunc.com>
 * <https://github.com/parseword/util-mailstats>
 *
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

//Import mailstats constants
//Third-party users should comment out the require() and define these yourself
require_once('/etc/config/mailstats.conf');
//define('MAILSTATS_DB_HOST',   'localhost');
//define('MAILSTATS_DB_USER',   'user');
//define('MAILSTATS_DB_PASS',   'pass');
//define('MAILSTATS_DB_NAME',   'mailstats');
//define('MAILSTATS_RECIPIENT', 'root@localhost');

try {
    //Get a database connection
    $conn = new PDO('mysql:host=' . MAILSTATS_DB_HOST . ';dbname='
            . MAILSTATS_DB_NAME . ';charsetlatin1', MAILSTATS_DB_USER,
            MAILSTATS_DB_PASS);
}
catch (PDOException $e) {
    //Send email notification
    mail(MAILSTATS_RECIPIENT, '[mailstats] Database error on ' . gethostname(),
            $e->getMessage(), 'From: ' . MAILSTATS_RECIPIENT);
    exit(1);
}

/*
 * Determine the dates we need to use. The maillog format is e.g. "Nov 17" or
 * "Feb  2", note there are two spaces when the daypart is a single digit.
 *
 * You can pass a date in YYYY-MM-DD format to force processing a certain day:
 * php mailstats-dnsbl-rejections.php 2015-09-17
 */

if ($argc === 2 && preg_match('|^\d{4}-\d{2}-\d{2}$|', $argv['1'])) {
    //A date was passed from the command line
    list($year, $month, $day) = explode('-', $argv['1']);
    //Build the daypart, e.g. "17" or " 2"
    $daypart = sprintf('% 2d', date('d', mktime(0, 0, 0, $month, $day, $year)));
    //Build the maillog date, e.g. "Nov 17" or "Feb  2"
    $logDate = date('M ', mktime(0, 0, 0, $month, $day, $year)) . $daypart;
    //Build the date to use in the database, as YYYY-MM-DD
    $dbDate = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
}
else {
    //Default to using yesterday's date
    $daypart = sprintf('% 2d', date('d', time() - 86400));
    $logDate = date('M ', time() - 86400) . $daypart;
    $dbDate = date('Y-m-d', time() - 86400);
}

//Build a command to grep the stats from the maillog and tally DNSBL rejects.
//The DNSBL hostname is in log field 20 when the daypart has two digits, and
//field 21 when the daypart has one space-padded digit.
$cutField = substr($daypart, 0, 1) == ' ' ? 21 : 20;
$command = "grep '{$logDate}' /var/log/maillog* | grep 'blocked using' "
        . "| cut -f{$cutField} -d' ' | sort -n | uniq -c | sort -nr";

$return = null;
$output = null;
exec($command, $output, $return);

//grep returns 0 if there was a match, 1 if no matches
if ($return === 0 && !empty($output)) {

    /*
     * A successful grep/cut/sort will return results like this:
     *
     * 42529 sbl-xbl.spamhaus.org;
     * 632 dnsbl.dronebl.org;
     * 503 dyna.spamrats.com;
     * 315 noptr.spamrats.com;
     * 195 psbl.surriel.com;
     * 24 cbl.abuseat.org;
     */

    $errors = array();

    foreach ($output as $line) {
        //Determine the DNSBL and the number of mails it blocked
        list ($count, $dnsbl) = explode(' ', str_replace(';', '', trim($line)));

        //Log to the database
        $stmt = $conn->prepare('INSERT dnsbl_reject_stats (date, dnsbl, rejections) VALUES (:dbDate,:dnsbl,:count)');
        $stmt->bindValue(':dbDate', $dbDate, PDO::PARAM_STR);
        $stmt->bindValue(':dnsbl', $dnsbl, PDO::PARAM_STR);
        $stmt->bindValue(':count', $count, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            $errors[] = $stmt->errorInfo();
        }
    }

    //Build the email notification
    $body = 'DNSBL rejection counts for ' . $dbDate . " follow:\n\n";

    foreach ($output as $line) {
        $body .= str_replace(';', '', $line) . "\n";
    }

    if (!empty($errors)) {
        $body .= "\n\nThe following errors were encountered during this run: \n\n"
                . var_export($errors, true);
    }

    //Send email notification
    mail(MAILSTATS_RECIPIENT,
            '[mailstats] DNSBL rejections on ' . gethostname() . ' for '
            . $dbDate, $body, 'From: ' . MAILSTATS_RECIPIENT);
}
