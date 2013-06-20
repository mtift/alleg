README
======

What is Alleg?
-----------------

Alleg offers limited functionality for connecting to an Allegiance MS SQL Server
database with ADODB. Alleg should allow developers the ability to write PHP on
a Unix or Linux machine, test locally, and then upload to a Windows machine.

Most of the code is procedural and, therefore, not easily extensible, such as:

  pledge_drive_email.php
  pledge_breaks.php

Some effort has been made to make the code more object-oriented
(src/Alleg/DatabaseConnection.php) and this functionality may be extended.


Requirements
------------

This has been tested on Linux (Debian and Ubuntu) and OSX with PHP version
5.3.26 as well as on Microsoft Windows Server 2003 R2 with PHP version 5.3.9.

In addition to PHP, Unix and Linux machines require that [FreeTDS][1] (or
something similar) be installed. Configuring FreeTDS can be tricky, so be sure
to read the documentation carefully.

[1]: http://freetds.schemamania.org/
