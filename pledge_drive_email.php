<?php

/**
 * A Sample file to demmonstrate how to demonstrate one way to pull names and
 * emails for a pledge-drive-related email
 */

// Manage database credentials in this file
include('config.inc');
include('vendor/autoload.php');

date_default_timezone_set('UTC');

use Alleg\DatabaseConnection;

$today = date("YFd");
$sustainer_file = "SustainerPledgeDriveEmail-{$today}.txt";
$non_sustainer_file = "NonSustainerPledgeDriveEmail-{$today}.txt";
if (strtolower(php_uname("s")) == ("darwin") || strtolower(php_uname("s")) == ("linux")) {
    $dir = "/tmp/";
} elseif (strtolower(php_uname("s")) == ("windows nt")) {
    $dir = ('C:\\');
}
$handle = fopen("{$dir}{$sustainer_file}", "w");
$handle2 = fopen("{$dir}{$non_sustainer_file}", "w");
$previous_email = "";
$email = "";

$alleg_conn = new DatabaseConnection($alleg_dsn,$alleg_username,$alleg_password,$alleg_server,$alleg_database);
print "Gathering emails and salutations. Please wait...\n";

// Create an array of everyone who needs the pledge drive email based on
// complex membership criteria particular to your station
$sql =  "SELECT DISTINCT membr, peeml, oombrmst.lsalu FROM oombrmst WITH (NOLOCK)
    INNER JOIN peoeml on oombrmst.AccEm# = peoeml.peeml#
    WHERE peoeml.peeml LIKE '%@%'
    AND oombrmst.mstat <> 'S'
    AND oombrmst.membr NOT IN (SELECT DISTINCT mcdmbr FROM oombrmcd WHERE mcdfnc IN ('EC'))
    AND oombrmst.membr NOT IN (SELECT DISTINCT pmembr FROM ooplgmst
        WHERE ((plgyy * 10000) + (plgmm * 100) + plgdd) >= 20130415)
    AND oombrmst.membr NOT IN (SELECT DISTINCT wtacct FROM campaigntrn)
    AND renew NOT IN (1, 2, 3, 4)
    AND peoeml.peeml NOT IN (select RTRIM(email)
        FROM oowebtmb WITH (NOLOCK), oowebtrn WITH (NOLOCK), oowebtpa WITH (NOLOCK)
        WHERE mbrtr# = wtwtr# AND mbrtr# = pmttr# and ((wtacct > 1) OR (email <> ' ')))
        ORDER BY peeml";
$rs = $alleg_conn->executeQuery($sql);

$count_all = 0;
$everyone = array();
if ($rs) {
    while (!$rs->EOF) {
        $member_id = trim($rs->fields('membr'));
        $lsalu = "";
        if (is_null($rs->fields('peeml'))) {
            $email = '';
        } else {
            $email = trim(strtolower($rs->fields('peeml')));
        }
        $lsalu = trim($rs->fields('lsalu'));
        $lsalu = strtolower($lsalu);
        $lsalu = ucwords($lsalu);
        $lsalu = str_replace("And", "and", $lsalu);
        if ($previous_email <> $email && !is_null($email)) {
            $everyone[$member_id] = array(
                'email' => $email,
                'salutation' => $lsalu,
            );
            $count_all++;
        }
        $previous_email = $email;
        $rs->MoveNext();
    }
}

print "Gathering sustainer data. Please wait...\n";

// Create an array or sustainers
$sql2 = "SELECT DISTINCT pmembr FROM ooplgmst WITH (NOLOCK)
          WHERE mode IN (52, 53, 62, 72) and pstat = 1 and plsust = 'Y'";
$rs2 = $alleg_conn->executeQuery($sql2);

$sustainers = array();
if ($rs2) {
    while (!$rs2->EOF) {
        $sustainers[] = trim($rs2->fields('pmembr'));
        $rs2->MoveNext();
    }
}

// Create two files: (1) sustainers and (2) non-sustainers
$count_= 0;
foreach($everyone as $all_ids => $member_data) {
    $count++;
    if (in_array($all_ids, $sustainers)) {
        print "Printing record #{$count} of {$count_all}: {$member_data['email']}\t{$member_data['salutation']} (sustainer)\n";
        fwrite($handle, "{$member_data['email']}\t{$member_data['salutation']}\n");
    }
    else {
        print "Printing record #{$count} of {$count_all}: {$member_data['email']}\t{$member_data['salutation']} (non-sustainer)\n";
        fwrite($handle2, "{$member_data['email']}\t{$member_data['salutation']}\n");
    }
}

print "\nThe files are complete and you can check them out here:\n{$dir}{$non_sustainer_file}\n{$dir}{$sustainer_file}\n";
fclose($handle);
fclose($handle2);
fwrite(STDOUT, "\nPlease press ENTER to exit the program -- and have a nice day!\n");
$end = fgets(STDIN);
