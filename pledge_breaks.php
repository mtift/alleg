<?php

/**
 * A Sample file to demmonstrate how to demonstrate one way to pull pledge break
 * data and move it to a remote MS SQL Server.
 */

// Manage database credentials in this file
include('config.inc');
include('vendor/autoload.php');

date_default_timezone_set('UTC');

use Alleg\DatabaseConnection;

// Determines current drive
$drive_selected = 'MAY2013PLEDGE';

// The name of the table in the remote database that will store pledge totals
$destination_table = 'PledgeBreaks';

$current_date = date("Y-m-d H:i:s");
$total_dollars = 0.00;
$total_pledges = 0;
$all_break_dollars = 0.00;
$all_break_pledges = 0;

$alleg_conn = new DatabaseConnection($alleg_dsn,$alleg_username,$alleg_password,$alleg_server,$alleg_database);
$web_conn = new DatabaseConnection($webdb_dsn,$webdb_username,$webdb_password,$webdb_server,$webdb_database);

// determine relevant source codes for the current pledge drive
$sql = "select distinct cpdsrc from oocampd WITH (NOLOCK) where cpdcod = '{$drive_selected}'";
$rs = $alleg_conn->executeQuery($sql);
$count = 0;
while (!$rs->EOF) {
    // Format the first record differently
    if ($count == 0) {
        $drive_source_codes = "'" . trim($rs->fields('cpdsrc')) . "'";
    }
    $drive_source_codes = $drive_source_codes . ", '" . trim($rs->fields('cpdsrc')) . "'";
    $count += 1;
    $rs->MoveNext();
}

// Determine break info
// NOTE: The CASE/CAST statement is needed below becuase without it SQL Server
// returns breps as a SQL Server variant data type, which the SQL Server
// Driver for PHP does not support. For more information, see, for example,
// bit.ly/m8FUrm and http://msdn.microsoft.com/en-us/library/aa223955(v=sql.80).aspx.
$sql = "SELECT bkcode, bkcall, bkdtyy, bkdtmm, bkdtdd, bkdesc, bktim, bkdur,
    CASE WHEN breps IS NULL THEN '' ELSE CAST(breps as varchar) END AS breps,
    CAST(CAST(bkdtmm AS char) + '/' + CAST(bkdtdd AS char) + '/' + CAST(bkdtyy AS char) AS datetime) AS Date
    FROM oobreak WITH (NOLOCK)
    WHERE bkcall IN ({$drive_source_codes})
    ORDER BY bkdtyy, bkdtmm, bkdtdd, bkcode";
$rs = $alleg_conn->executeQuery($sql);
while (!$rs->EOF) {
    // Fix Allegiance end times to make them useable
    $start_time = $rs->fields('bktim');
    $end_time = $start_time;
    $end_hour = (int) $start_time / 10000;
    $end_hour = $end_hour * 10000;
    $end_minute = $end_time - $end_hour;
    $bk_dur_hour = (int) $rs->fields('bkdur') / 10000;
    $bk_dur_hour = $bk_dur_hour * 10000;
    $bk_dur_minute = $rs->fields('bkdur') - $bk_dur_hour;
    $end_minute = $end_minute + $bk_dur_minute;
    while ($end_minute > 5900) {
        $end_minute = $end_minute - 6000;
        $bk_dur_hour = $bk_dur_hour + 10000;
    }
    $end_time = $end_hour + $bk_dur_hour + $end_minute;
    $real_start_time = $start_time;
    $real_end_time = $end_time;
    if ($end_time >= 240000) {
        $end_time = 240000;
        $real_end_time = $end_time;
    }
    $bkcall = trim($rs->fields('bkcall'));
    $bkdtyy = trim($rs->fields('bkdtyy'));
    $bkdtmm = trim($rs->fields('bkdtmm'));
    $bkdtdd = trim($rs->fields('bkdtdd'));
    $pledge_date = ($bkdtyy * 10000) + ($bkdtmm * 100) + $bkdtdd;
    $break_code = trim($rs->fields('bkcode'));

    // Get pledge data from the Campaign table
    $sql2 = "SELECT CASE WHEN sum(plamt) IS NULL THEN 0
        ELSE CAST(sum(plamt) as decimal) END AS totaldollars,
        CASE WHEN count(*) IS NULL THEN 0 ELSE CAST(count(*) AS int)
        END AS totalpledges
        FROM Campaign WITH (NOLOCK)
        WHERE cmpcod = '{$drive_selected}'
        AND CpdSrc LIKE '%{$bkcall}%'
        AND plgyy = {$bkdtyy}
        AND plgmm = {$bkdtmm}
        AND plgdd = {$bkdtdd}
        AND (([break] = '{$break_code}') OR
        ([break] = '' AND wttime >= {$real_start_time}
        AND wttime < {$real_end_time}))";
    $rs2 = $alleg_conn->executeQuery($sql2);

    // Add pledge & dollar totals for individual break
    $total_dollars += $rs2->fields('totaldollars');
    $total_pledges += $rs2->fields('totalpledges');

    // Get pledge data from the CampaignTrn table
    $sql2 = "SELECT CASE WHEN sum(plamt) IS NULL THEN 0
        ELSE CAST(sum(plamt) as decimal) END AS totaldollars,
        CASE WHEN count(*) IS NULL THEN 0 ELSE CAST(count(*) as int)
        END AS totalpledges
        FROM CampaignTrn WITH (NOLOCK)
        WHERE cpdcod = '{$drive_selected}'
        AND CpdSrc like '%{$bkcall}%'
        AND PledgeDate = $pledge_date
        AND (([break] = '{$break_code}') OR
        ([break] = '' AND wttime >= {$real_start_time}
        AND wttime < {$real_end_time}))";
    $rs2 = $alleg_conn->executeQuery($sql2);

    // Add pledge & dollar totals for individual break
    $total_dollars += $rs2->fields('totaldollars');
    $total_pledges += $rs2->fields('totalpledges');

    // Add to total count of all pledges and total dollars
    $all_break_pledges += $total_pledges;
    $all_break_dollars += $total_dollars;

    // Add break information
    $break_description = $rs->fields('bkdesc');
    $break_description = str_replace("'", "`", $break_description);

    $break_time = $rs->fields('bktim');
    if (strlen($break_time) == 5) {
        $break_time = substr($break_time, 0, 1) . ":" . substr($break_time, 1, 2);
    }
    if (strlen($break_time) == 6) {
        $break_time = substr($break_time, 0, 2) . ":" . substr($break_time, 2, 2);
    }

    if ($rs->fields('Date')) {
        $break_date = $rs->fields('Date');
    }
    else{
        $break_date = nothing;
    }

    if($rs->Fields('breps') != '') {
        // Breps contains both the pledge goal, followed by a semi-colon, followed by the dollar goal
        $pledge_and_dollar_goal = $rs->Fields('breps');
        $semi_colon_position = strpos($rs->Fields('breps'), ';');
        $break_pledge_goal = substr($pledge_and_dollar_goal, 0, $semi_colon_position);
        $break_dollar_goal = substr($pledge_and_dollar_goal, $semi_colon_position+1, strlen($pledge_and_dollar_goal));
    }
    else {
        $break_pledge_goal = 0;
    }

    // Insert data into remote database
    $sql_ext = "insert into {$destination_table} (Drive, BreakNumber, BreakDescription, BreakDate,
        BreakTime, TotalDollars, TotalPledges, LastUpdated, BreakPledgeGoal, BreakDollarGoal)
        values ('{$drive_selected}', '{$break_code}', '{$break_description}',
                '{$break_date}', '{$break_time}', '{$total_dollars}', '{$total_pledges}',
                '{$current_date}', {$break_pledge_goal}, {$break_dollar_goal})";
    $rs_ext = $web_conn->executeQuery($sql_ext);

    // Clear variables
    $break_code = "";
    $break_description = "";
    $break_time = "";

    // Clear pledes totals for the individual break
    $total_dollars = 0;
    $total_pledges= 0;

    $rs->MoveNext();
}

// Use these variables to store total dollars and pledges
$all_dollars = 0;
$all_pledges = 0;

// Get totals from the Campaign table
$camp_rs = $alleg_conn->getCampaignTotals('Campaign', $drive_selected);
$all_dollars += $camp_rs->fields('totaldollars');
$all_pledges += $camp_rs->fields('totalpledges');

// Get totals from the CampaignTrn table
$camptrn_rs = $alleg_conn->getCampaignTotals('CampaignTrn', $drive_selected);
$all_dollars += $camptrn_rs->fields('totaldollars');
$all_pledges += $camptrn_rs->fields('totalpledges');

// At this point $all_break_dollars/pledges are the break totals and
// The "unmarked" totals are basically eveything else.
$unmarked_pledge_total = ($all_dollars - $all_break_dollars);
$unmarked_pledge_count = ($all_pledges - $all_break_pledges);

// add non-break/unmarked totals
$sql_ext = "INSERT INTO {$destination_table} (Drive, BreakNumber,
    BreakDescription, BreakDate, BreakTime, TotalDollars, TotalPledges,
    LastUpdated, BreakPledgeGoal, BreakDollarGoal)
    VALUES ('{$drive_selected}', 999, 'Pre/Post-Drive/Challenges',
        '{$current_date}', 999, {$unmarked_pledge_total},
        {$unmarked_pledge_count}, '{$current_date}', 0, 0)";
$rs_ext = $web_conn->executeQuery($sql_ext);

// clear old pledge breaks
$sql_ext = "DELETE FROM {$destination_table}
    WHERE LastUpdated < '{$current_date}'
    AND Drive = '{$drive_selected}'";
$rs_ext = $web_conn->executeQuery($sql_ext);
