    Description
    ----
Roundup (in allusion to a broadband herbicide) is an IMAP-based mail filter,
washer and (re)organizer that works in the background, e.g. as a cron job.
This tool uses IMAP to move rule based mails in a mailbox. The rules for this
are a combination of regular and logical expressions.


    System Requirement
    ----
- PHP 7.x or higher + imap extension


    Installation
    ----
The script must be stored on a server and is called by a cron job.
e.g. /bin/php -f roundup.php


    Configuration
    ----
The application consists of three files (roundup.php, roundup.ini,
roundup.filter). At runtime, the session file roundup.data is created later.
The files can be renamed. Basis is the file name of the application
(roundup.php). All other file names must be based on it.
The program fileroundup.php itself does not need to be configured.

        roundup.ini
There are two sections: COMMON and ACCOUNT.
Please open roundup.ini and read the notices and see the examples.

        roundup.filter
The filters are defined here.
Please open roundup.filter and read the instructions and see the examples.
