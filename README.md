# Description
Roundup (in allusion to a broadband herbicide) is an IMAP-based mail filter,
washer and (re)organizer that works in the background, e.g. as a cron job.  
This tool uses IMAP to move rule based mails in a mailbox. The rules for this
are a combination of regular and logical expressions.


# Features
- Multiple account support
- IMAP support (incl. SSL)
- simple URL based IMAP and mailbox definition
- Internal dynamic whitelist  
  Exclusion of mails from subsequent filters and rules.
- Filter based on patterns with regular expression  
  The patterns can then be combined in logical expressions.  
  Supported: AND, OR, NOT, round brackets
- Processes the header and body of the messages  
  Message content, including multi-part, is decoded and simplified for the
  filters. The decoding only happens in memory.
- Message attributes such as 'seen / unseen' are retained
- Continuation of filtering from the last analyzed mail (for each mailbox
  separately); complete reanalysis when changing application, configuration or
  filter 
- Logging with detailed error messages
- Physical separation of application, configuration (with accounts) and filter


# Licence Agreement
Seanox Software Solutions ist ein Open-Source-Projekt, im Folgenden
Seanox Software Solutions oder kurz Seanox genannt.

Diese Software unterliegt der Version 2 der GNU General Public License.

Copyright (C) 2018 Seanox Software Solutions

This program is free software; you can redistribute it and/or modify it under
the terms of version 2 of the GNU General Public License as published by the
Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc., 51 Franklin
Street, Fifth Floor, Boston, MA 02110-1301, USA.


# System Requirement
- PHP 7.x or higher + imap extension


# Downloads
[Seanox Roundup 1.0.0](https://github.com/seanox/roundup/raw/master/releases/seanox-roundup-1.0.0.zip)  
[Seanox Roundup Sources 1.0.0](https://github.com/seanox/roundup/raw/master/releases/seanox-roundup-1.0.0-src.zip)  


# Installation
The script must be stored on a server and is called by a cron job.  
```
/bin/php -f roundup.php
```


# Configuration
The application consists of three files (`roundup.php`, `roundup.ini`,
`roundup.filter`). At runtime, the session file `roundup.data` is created later.
The files can be renamed. Basis is the file name of the application
(`roundup.php`). All other file names must be based on it.  
The program file`roundup.php` itself does not need to be configured.

## Configuration _(roundup.ini)_
There are two sections: _COMMON_ and _ACCOUNT_.  
Please open `roundup.ini` and read the notices and see the examples.
  
## Filter _(roundup.filter)_
The filters are defined here.  
Please open `roundup.filter` and read the instructions and see the examples.


# Changes (Change Log)
## 1.0.0 20180127 (summary of the current version)  
NT: Roundup is available as release  

[Read more](https://raw.githubusercontent.com/seanox/roundup/master/CHANGES)


# Contact
[Support](http://seanox.de/contact?support)  
[Development](http://seanox.de/contact?development)  
[Project](http://seanox.de/contact?service)  
[Page](http://seanox.de/contact)  
