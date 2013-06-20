<?php

/**
 * @file
 * Contains Alleg\DatabaseConnection.
 */

namespace Alleg;

/**
 * A connection to an Allegiance database.
 */
class DatabaseConnection
{
    protected $conn;
    protected $dsn;
    protected $username;
    protected $password;
    protected $server;
    protected $database;

    /**
     * Constructor.
     *
     * @param string  $dsn A data source name
     * @param string  $username The username for the database
     * @param string  $password The password for the database
     * @param string  $server The server IP address
     * @param string  $database The database name
     */
    public function __construct($dsn, $username, $password, $server = '', $database = '')
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->server = $server;
        $this->database = $database;

        $this->init();
    }

    /*
     * Initialize the database connection.
     */
    public function init()
    {
        // Check operating system, if OSX or Linux use ADO
        if (strtolower(php_uname("s")) == ("darwin") ||
            strtolower(php_uname("s")) == ("linux")) {
            $this->conn = ADONewConnection('mssql') or die("Cannot start ADO");
            $this->conn->Connect($this->dsn,$this->username,$this->password);
            $this->conn->SetFetchMode(ADODB_FETCH_ASSOC);
        }
        // Use SQLOLEDB on a Windows machine
        elseif (strtolower(php_uname("s")) == ("windows nt")) {
            $conn = "PROVIDER=SQLOLEDB;SERVER=".$this->server.";UID=".
                $this->username.";PWD=".$this->password.";DATABASE=".$this->database;
            $this->conn = new COM ("ADODB.Connection") or die("Cannot start ADO");
            $this->conn->open($conn);
        }
    }

    /*
     * Execute a SQL query.
     *
     * @return
     *   A database recordset.
     */
    public function executeQuery($sql)
    {
        $rs = $this->conn->Execute($sql);
        return $rs;
    }

    /*
     * Get the total number of pledges and total dollars from the campaign.
     *
     * @param string $table
     *   The table name, either Campaign or CampaignTrn
     * @param string $drive
     *   The drive ID (e.g. "1305PLEDG")
     * @return
     *   A database recordset.
     */
    public function getCampaignTotals($table, $drive)
    {
        $sql = "SELECT CASE WHEN sum(plamt) IS NULL THEN 0
            ELSE CAST(sum(plamt) as decimal) END AS totaldollars,
            CASE WHEN count(*) IS NULL THEN 0 ELSE CAST(count(*) as int)
            END AS totalpledges
            FROM {$table} WITH (NOLOCK)
            WHERE cmpcod = '{$drive}'";
        $rs = $this->conn->Execute($sql);
        return $rs;
    }
}
