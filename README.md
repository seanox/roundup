# Description
Roundup is a background IMAP filter and washer.  
This tool uses IMAP to move in a mailbox mails that correspond to specific
regular and logical expressions.  
The script is intended for background activities, e.g. as a cron job.


# Features
- Multi-user support
- IMAP support (secure and not secure)
- Internal dynamic whitelist  
  Protects mails from following filters and rules.
- Filter based on patterns with regular expression
  The patterns can then be combined in logical expressions.  
  Supported: AND, OR, NOT, round brackets
- Processes the header and body of the messages  
  Message content, including multi-part, is decoded and simplified for the
  filters. The decoding only happens in memory.
- Logging with detailed error messages
- Physical separation of application, configuration (with accounts) and filter
- Message attributes such as 'unseen' are retained


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
coming soon


# Installation
The script must be stored on a server and is called by a cron job.  
```
/bin/php -f roundup. php
```


# Changes (Change Log)
[Read more](https://raw.githubusercontent.com/seanox/roundup/master/CHANGES)


# Contact
[Support](http://seanox.de/contact?support)  
[Development](http://seanox.de/contact?development)  
[Project](http://seanox.de/contact?service)  
[Page](http://seanox.de/contact)  
