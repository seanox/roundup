<?php
/**
 *  LIZENZBEDINGUNGEN - Seanox Software Solutions ist ein Open-Source-Projekt,
 *  im Folgenden Seanox Software Solutions oder kurz Seanox genannt. Diese
 *  Software unterliegt der Version 2 der GNU General Public License.
 *
 *  Roundup, IMAP Background Filters and Washers
 *  Copyright (C) 2018 Seanox Software Solutions
 *
 *  This program is free software; you can redistribute it and/or modify it
 *  under the terms of version 2 of the GNU General Public License as published
 *  by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful, but WITHOUT
 *  ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 *  FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 *  more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with this program; if not, write to the Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 *
 *      DESCRIPTION
 *
 *  Roundup is a background IMAP filter and washer.
 *  This tool uses IMAP to move in a mailbox mails that correspond to specific
 *  regular and logical expressions. The script is intended for background
 *  activities, e.g. as a cron job.
 *
 *  Roundup 1.0.0 20180126
 *  Copyright (C) 2018 Seanox Software Solutions
 *  All rights reserved.
 *
 *  @author  Seanox Software Solutions
 *  @version 1.0.0 20180126
 */

define("SECTION_ACCOUNT", "ACCOUNT");
define("SECTION_COMMON", "COMMON");

define("SECTION_COMMON_BLOCK", "BLOCK");
define("SECTION_COMMON_OVERSIZE", "OVERSIZE");

define("URL_SCHEME", "scheme");
define("URL_HOST", "host");
define("URL_PORT", "port");
define("URL_PATH", "path");
define("URL_USER", "user");
define("URL_PASS", "pass");

define("FILTER_NUMBER", "number");
define("FILTER_ACCOUNT", "account");
define("FILTER_SOURCE", "source");
define("FILTER_TARGET", "target");
define("FILTER_PATTERN", "pattern");
define("FILTER_EXPRESSION", "expression");

define("PROTOCOL_IMAP", "imap");
define("PROTOCOL_IMAP_SECURE", "imaps");

/**
 *  Returns the complete configuration or the given section.
 *  @param  string $section secton
 *  @return mixed  the found section as array, otherwise FALSE
 */ 
function configuration_get($section = FALSE) {
    
    $file = preg_replace("/(\.[^\.]*)$/", "", __FILE__) . ".ini";
    
    $configuration = parse_ini_file($file, TRUE);
    if (!$configuration)
        return FALSE;
    $configuration = array_change_key_case($configuration, CASE_UPPER);
    foreach ($configuration as $key => $value)
        $configuration[$key] = array_change_key_case($value, CASE_UPPER);

    if ($section)
        $section = strtoupper(trim($section));
    if ($section) {
        if (!array_key_exists($section, $configuration))
            return FALSE;
        return $configuration[$section];
    }
    
    return $configuration;
}

/**
 *  Writes a timestamped message to the standard I/O.
 *  Multiple-line messages are indented during output.
 *  @param string $message message
 */
function output_log($message) {

    if (!$message)
        return;
    
    $message = trim(preg_replace("/[\r\n]+\s*/s", "\n", $message));
    $message = preg_replace("/\n/s", "\r\n\t", $message);

    $message = trim($message);
    if (!$message)
        return;
    
    print(date("Y-m-d H:i:s") . " " . $message . "\r\n");    
}

/**
 *  Returns a list of accounts from the configuration file.
 *  @return array accounts as array
 */
function account_list() {
    
    $accounts = array();
    $section = configuration_get(SECTION_ACCOUNT);
    foreach ($section as $key => $value) {
        $index = strpos($value, " ");
        if ($index) {
          $url = trim(substr($value, 0, $index));
          $url = parse_url($url);
          $value = trim(substr($value, $index +1));
          $value = preg_split("/\s+/", $value);
          if (count($value) > 0 && $value[0])
              $url[URL_USER] = $value[0];
          if (count($value) > 1)
              $url[URL_PASS] = $value[1];
        } else $url = parse_url($value);
        if (!$url)
            continue;
        $accounts[$key] = $url;
    }
    return $accounts;
}

/**
 *  Decode and simplify a message with possible multi-parts.
 *  @param  string $message
 *  @return the decoded and simplified message
 */
function message_decode_plain($message) {
    
    $head = "";
    $body = "";
    
    $message = preg_replace("/(\r\n)|(\n\r)|[\r\n]/s", "\n", $message);
    
    $parts = preg_split("/(\n\s*){2,}/s", $message, 2);
    if ($parts && count($parts) > 1) {
        $head = trim($parts[0]);
        $body = trim($parts[1]);
    } else $head = trim($message);
    
    $head = preg_replace("/[\r\n]+/s", "\n", $head);
    $head = preg_replace("/\n+\t+/s", " ", $head);    
    $head = preg_replace("/[\x01-\x09\x0B-\x20]+/", " ", $head);
    $head = preg_replace("/^\s+/m", "", $head);    
    $head = preg_replace("/\s+$/m", "", $head);
    $head = preg_replace("/[\r\n]+/s", "\r\n", $head); 
    $head = trim($head);

    $body = trim($body);
    
    $boundary = preg_match("/^Content-Type:\s*multipart.*\bboundary\s*=\s*\"(.*?)\".*$/im", $head, $match) ? $match[1] : FALSE;
    if ($boundary) {
        $parts = preg_split("/(^|\n)--" . preg_quote($boundary) . "\s*\n/", $body);
        if ($parts && count($parts)) {
            $message = ""; 
            foreach ($parts as $part) {
                $part = trim($part);
                if (!$part)
                    continue;
                $message = trim("$message\r\n\r\n--$boundary\r\n\r\n" . message_decode_plain($part));
            }
            return trim("$head\r\n\r\n$message");
        }
    }

    $type = preg_match("/^Content-Type:\s*([^\s\;]+).*$/im", $head, $match) ? trim($match[1]) : FALSE;
    $encoding = preg_match("/^Content-Transfer-Encoding:\s*(.*)\s*$/im", $head, $match) ? trim($match[1]) : FALSE;
    if (preg_match("/BASE64/i", $encoding)) {
        if (!$type) {
            $body = "DATA: UNKNOWN";
        } elseif (!preg_match("/^text\/.*$/i", $type)) {
            $body = "DATA: " . strtoupper($type);
        } else {
            $decode = @base64_decode($body);
            if ($decode)
                $body = trim($decode);    
        }
    }    
    
    $decode = @quoted_printable_decode($body);
    if ($decode)
        $body = $decode;
    $body = preg_replace("/[\x01-\x20]+/", " ", $body);
    $body = trim($body);
    if ($boundary)    
        $body = "--$boundary\n\n$body";
    $body = preg_replace("/\n/", "\r\n", $body);
    $body = trim($body);
    
    return trim("$head\r\n\r\n$body");
}

/**
 *  Parses a filter section.
 *  Any detected errors are written to the standard I/O.
 *  @param  string $section section
 *  @return mixed  filter as array, otherwise FALSE
 */
function message_filter_parse($section) {
    
    $section = trim($section);
    if (!$section)
        return FALSE;
    
    $section = preg_split("/\s*[\r\n]+\s*/", $section);
    if (!count($section))
        return FALSE;

    $plain = implode("\r\n", $section); 
        
    $filter = array(FILTER_ACCOUNT => FALSE, FILTER_SOURCE => FALSE, FILTER_TARGET => FALSE, FILTER_PATTERN => array(), FILTER_EXPRESSION => FALSE);
        
    $statement = array_shift($section);
    if (!preg_match("/(\w+)\s+(\/[^\s\.]+)\s*>\s*((?:\/[^\s\.]+)|NOTHING)/i", $statement, $match)) {
        output_log("Invalid filter section (missing valid statement):\r\n$plain");
        return FALSE;
    }
    $filter[FILTER_ACCOUNT] = $match[1]; 
    $filter[FILTER_SOURCE]  = $match[2];
    $filter[FILTER_TARGET]  = $match[3];
    
    foreach ($section as $line) {
        if (preg_match("/^\s*pat\s*:.*$/i", $line)) {
            if (!preg_match("/^\s*pat\s*:\s*(\w+)\s+(.*?)\s*$/i", $line, $match)) {
                output_log("Invalid filter section (invalid " . FILTER_PATTERN . "):\r\n$plain\r\n---\r\n$line");
                return FALSE;    
            } elseif (@preg_match($match[2], "") === FALSE) {
                output_log("Invalid filter section (invalid " . FILTER_PATTERN . " regular expression):\r\n$plain\r\n---\r\n$line");
                return FALSE;    
            } else $filter[FILTER_PATTERN][strtoupper($match[1])] = $match[2];
        } elseif (preg_match("/^\s*exp\s*:.*$/i", $line)) {
            if (!preg_match("/^\s*exp\s*:\s*([\w\(\)\|\&\!\s]+)\s*$/i", $line, $match)) {
                output_log("Invalid filter section (invalid " . FILTER_EXPRESSION . "):\r\n$plain\r\n---\r\n$line");
                return FALSE;    
            } else $filter[FILTER_EXPRESSION] = strtoupper($match[1]);              
        }         
    }
    if (count($filter[FILTER_PATTERN])
            && !$filter[FILTER_EXPRESSION]) {
        output_log("Invalid filter section (missing valid " . FILTER_EXPRESSION . "):\r\n$plain");
        return FALSE;
    }
    if (!count($filter[FILTER_PATTERN])
            && $filter[FILTER_EXPRESSION]) {
        output_log("Invalid filter section (missing valid " . FILTER_PATTERN . "):\r\n$plain");
        return FALSE;
    }
    if (count($filter[FILTER_PATTERN])
            && $filter[FILTER_EXPRESSION]) {
        $expression = $filter[FILTER_EXPRESSION];
        $expression = preg_replace("/[\(\)\|\&\!\s]+/", " ", $expression);
        foreach ($filter[FILTER_PATTERN] as $key => $value)
            $expression = preg_replace("/\b" . preg_quote($key) . "\b/i", " ", $expression);
        if (trim($expression)) {
            output_log("Invalid filter section (missing valid " . FILTER_PATTERN . " in " . FILTER_EXPRESSION . "):\r\n$plain");
            return FALSE;
        }
        $expression = $filter[FILTER_EXPRESSION];
        $expression = preg_replace("/\b\w+\b/", "X", $expression);
        if (preg_match("/\w+\s+\w+/", $expression)
                || preg_match("/\&\s+\&/", $expression)) {
            output_log("Invalid filter section (missing valid " . FILTER_EXPRESSION . "):\r\n$plain");
            return FALSE;
        }
        $expression = $filter[FILTER_EXPRESSION];
        $expression = preg_replace("/\s+/", "", $expression);
        if (preg_match("/\(\)/", $expression)
                || preg_match("/\)[\(\!\w]/", $expression)
                || preg_match("/\!\&/", $expression)
                || preg_match("/\&{3,}/", $expression)
                || preg_match("/\(\&/", $expression)
                || preg_match("/[\&\|\!]\)/", $expression)) {
            output_log("Invalid filter section (missing valid " . FILTER_EXPRESSION . "):\r\n$plain");
            return FALSE;
        }
        $expression = $filter[FILTER_EXPRESSION];
        $expression = preg_replace("/[^\(\)]/", "", $expression);
        while (preg_match("/\(\)/", $expression))
            $expression = preg_replace("/\(\)/", "", $expression);
        if ($expression) {
            output_log("Invalid filter section (missing valid " . FILTER_EXPRESSION . "):\r\n$plain");
            return FALSE;
        }
    }
    return $filter;
}

/**
 *  Returns a list of filters from the filter file.
 *  @return array filters as array
 */
function message_filter_list() {
    
    $file = preg_replace("/(\.[^\.]*)$/", "", __FILE__) . ".filter";
    
    $content = file_get_contents($file);
    $content = preg_replace("/(\r\n)|(\n\r)|[\r\n]/", "\n", $content);
    $content = preg_replace("/[\x01-\x09\x0B\x0C\x0E-\x20]+/", " ", $content);
    $content = preg_replace("/^ *#[^\n]*?(\n|$)/m", "", $content);
    $content = trim($content);
    
    $filter = array();
    foreach (preg_split("/((\n\s*){2,})|(\n+(?!\s))/", $content) as $section) {
        $section = message_filter_parse($section);
        if (!$section)
            continue;
        $section[FILTER_NUMBER] = count($filter) +1;
        $filter[] =  $section;
    }
    return $filter;
}

/**
 *  Tests a filter for a message.
 *  @param  array	$filter  filter
 *  @param  string  $message message 
 *  @return boolean TRUE or FALSE
 */
function message_filter_eval($filter, $message) {

    $pattern = @$filter[FILTER_PATTERN];
    if (!$pattern || !count($pattern))
        return FALSE;
    $expression = @$filter[FILTER_EXPRESSION];
    if (!$expression)
        return FALSE;
    foreach ($pattern as $key => $value)  
        $expression = preg_replace("/\b" . preg_quote($key) . "\b/i", preg_match($value, $message) ? "TRUE" : "FALSE", $expression);
    return eval("return $expression;");
}

function imap_open_url($account, $source) {
        
    $account = account_list()[$account];
    
    $scheme = @$account[URL_SCHEME];
    $host   = @$account[URL_HOST];
    $port   = @$account[URL_PORT];
    $path   = @$account[URL_PATH] . "/" . $source;
    
    $path = preg_replace("/^\/+/", "", $path);
    $path = preg_replace("/\/+$/", "", $path);
    $path = preg_replace("/\/+/", ".", $path);

    if (strcasecmp(PROTOCOL_IMAP_SECURE, $scheme) == 0) {
        if (!$port) $port = "993";
        $source = "$host:$port/imap/ssl";
    } elseif (strcasecmp(PROTOCOL_IMAP, $scheme) == 0) {
        if (!$port) $port = "143";
        $source = "$host:$port";
    } else return FALSE;
    
    $source = "{" . $source . "}" . $path;
    
    $user = @$account[URL_USER];
    $pass = @$account[URL_PASS];

    return imap_open($source, $user, $pass);
}

function imap_fetch_message_plain($imap, $uid) {
    
    $number = imap_msgno($imap, $uid);
    if (!$number)
        return FALSE;
    
    $head = imap_fetchheader($imap, $number);
    $head = iconv_mime_decode_headers($head, ICONV_MIME_DECODE_CONTINUE_ON_ERROR);
    if (!$head)
        return FALSE;
     
    $message = ""; 
    foreach ($head as $key => $value) {
        if (is_array($value))
            foreach ($value as $entry)
                $message = trim(trim($message) . "\r\n$key: " . trim($entry));
        else $message = trim(trim($message) . "\r\n$key: $value");
    }
        
    $meta = imap_fetch_overview($imap, $uid, FT_UID);
    $size = $meta[0]->size;
    $size = $size && is_numeric($size) ? intval($size) : FALSE;

    $oversize = configuration_get(SECTION_COMMON);
    $oversize = @$oversize[SECTION_COMMON_OVERSIZE];
    $oversize = preg_match("/(\d+)\s*(K|M)*$/i", $oversize, $match) ? max(intval($oversize), 0) : FALSE;
    if (count($match) > 1) {
        if (strcasecmp($match[2], "K") == 0)
            $oversize *= 1024; 
        else if (strcasecmp($match[2], "M") == 0)
            $oversize *= 1024 *1024; 
    }
        
    $body = $oversize && $size && $size < $oversize ? imap_body($imap, $number) : "OVERSIZE $size";
    
    $message = trim($message) . "\r\n\r\n" . trim($body);
    $message = message_decode_plain($message);
    
    if (!$message)
        return FALSE;
    return trim($message);
}

function imap_mime_decode($text) {
    
    $text = preg_replace("/=[\r\n]+/", "", $text);
    $text = imap_mime_header_decode($text);
    if (!is_array($text))
        return $text;
    $result = "";
    foreach ($text as $entry) 
        $result = trim("$result {$entry->text}");
    return preg_replace("/\s+/", " ", $result);
}

function imap_mailbox_real($imap, $mailbox) {
    
    $meta = imap_check($imap);
    if (!$meta || !$meta->Mailbox)
        return FALSE;
    $real = $meta->Mailbox;
    $real = preg_replace("/^\{.*?\}/", "", $real);
    if (stristr($real, "<no_mailbox>") !== FALSE)
        return FALSE;
    $real = preg_replace("/\./", "/", $real);
    $real = preg_replace("/\/+/", "/", "/$real");
    return $real;
}

function imap_mailbox_account($imap) {
    
    $meta = imap_check($imap);
    if (!$meta || !$meta->Mailbox)
        return FALSE;
    $meta = $meta->Mailbox;
    $host = preg_replace("/^\{([^:]*).*\}.*$/", "$1", $meta);
    $user = preg_replace("/^\{.*?(?:\buser=\"([^\"]*?)\")*\}.*$/", "$1", $meta);
    return trim("$host $user");
}

function imap_hash_mailbox($imap, $mailbox) {

    $account = imap_mailbox_account($imap);
    $real = imap_mailbox_real($imap, $mailbox);
    return strtoupper(bin2hex(trim("$account $real")));
}

function session_open() {
    
    $time = filemtime(__FILE__);
    $file = preg_replace("/(\.[^\.]*)$/", "", __FILE__) . ".filter";
    if (file_exists($file))
        $time = max(filemtime($file), $time); 
    $file = preg_replace("/(\.[^\.]*)$/", "", __FILE__) . ".ini";
    if (file_exists($file))
        $time = max(filemtime($file), $time);     
    $file = preg_replace("/(\.[^\.]*)$/", "", __FILE__) . ".data";
    if (file_exists($file)
            && filemtime($file) > $time) {
        $session = file_get_contents($file);
        $session = unserialize($session);
    } else $session = array();
    return $session;
}

function session_save($session) {
    
    $data = serialize($session);
    $file = preg_replace("/(\.[^\.]*)$/", "", __FILE__) . ".data";
    
    file_put_contents($file, $data);
}

function main() {
    
    output_log("Start");

    $filters = message_filter_list();
    $session = session_open();
    $sequences = array();
    $whitelist = array();

    $block = configuration_get(SECTION_COMMON);
    $block = @$block[SECTION_COMMON_BLOCK];
    $block = $block && is_numeric($block) ? max(intval($block), 0) : FALSE;

    foreach ($filters as $filter) {
          
        $imap = imap_open_url($filter[FILTER_ACCOUNT], $filter[FILTER_SOURCE]);
        $real = imap_mailbox_real($imap, $filter[FILTER_SOURCE]);
        if ($real) {
            $hash = imap_hash_mailbox($imap, $filter[FILTER_SOURCE]);
            $sequence = array_key_exists($hash, $session) ? max($session[$hash] +1, 1) : 1;
            $meta = imap_check($imap);
            if ($meta && imap_check($imap)->Nmsgs) {
                $sequence = "$sequence:" . imap_uid($imap, imap_check($imap)->Nmsgs);
                if ($block) {
                    $count = 0;
                    $overview = imap_fetch_overview($imap, sequence, FT_UID);
                    foreach ($overview as $entry) {
                        if ($count++ > $block)
                            break;
                        $sequence = preg_replace("/\d+$/", imap_uid($imap, $entry->msgno), $sequence);
                    }
                }
                $numbers = preg_split("/:/", $sequence);
                $numbers[0] = intval($numbers[0]);
                $numbers[1] = intval($numbers[1]);
                if ($numbers[0] > $numbers[1])
                    $sequence = FALSE;
            } else $sequence = FALSE;
            $sequences[$hash] = $sequence;
        } else output_log("Invalid source mailbox found in: #{$filter[FILTER_NUMBER]} {$filter[FILTER_ACCOUNT]} {$filter[FILTER_SOURCE]} > {$filter[FILTER_TARGET]}");   
        imap_close($imap);

        $imap = imap_open_url($filter[FILTER_ACCOUNT], $filter[FILTER_TARGET]);
        $real = imap_mailbox_real($imap, $filter[FILTER_SOURCE]);
        if (!$real)
            output_log("Invalid traget mailbox found in: #{$filter[FILTER_NUMBER]} {$filter[FILTER_ACCOUNT]} {$filter[FILTER_SOURCE]} > {$filter[FILTER_TARGET]}");
        
        imap_close($imap);
    }

    output_log("Start analysis");
    foreach ($filters as $filter) {  
        
        output_log("Start #{$filter[FILTER_NUMBER]} {$filter[FILTER_ACCOUNT]} {$filter[FILTER_SOURCE]} > {$filter[FILTER_TARGET]}");
        
        $imap = @imap_open_url($filter[FILTER_ACCOUNT], $filter[FILTER_SOURCE]);
        if (!$imap) {
            output_log("Connection failed");
            continue;
        }
        output_log("Connection successfully established");

        $uids = array();
        
        $target = $filter[FILTER_TARGET];
        $target = preg_replace("/^\/+/", "", $target);
        $target = preg_replace("/\/+$/", "", $target);
        $target = preg_replace("/\/+/", ".", $target);    
            
        $hash = imap_hash_mailbox($imap, $filter[FILTER_SOURCE]);
        $sequence = $sequences[$hash];
        $overview = $sequence ? imap_fetch_overview($imap, $sequence, FT_UID) : array();
        output_log(sprintf("Found %d new message(s)", count($overview)));
        foreach ($overview as $entry) {  
            $uid = @imap_uid($imap, $entry->msgno);
            if (!$uid)
                continue;
            $oid = "$hash:$uid";
            if (array_key_exists($oid, $whitelist))
                continue;
            $message = imap_fetch_message_plain($imap, $uid);
            if (!$entry->seen)
                imap_clearflag_full($imap, $uid, "\\Seen", ST_UID);
            if (!$message)
                continue;
            if (!message_filter_eval($filter, $message))
                continue;
            if (preg_match("/NOTHING/i", $filter[FILTER_TARGET])) {
                $whitelist[] = $oid;
                continue;
            }
            $from = imap_mime_decode($entry->from);
            $subject = imap_mime_decode($entry->subject);
            output_log("Catch: #{$entry->msgno} at {$entry->date} from $from - $subject");
            $uids[] = $uid;    
        }
        
        if ($uids && count($uids)) {
            imap_mail_move($imap, implode(",", $uids), $target, CP_UID);
            imap_expunge($imap);
        }    
        
        imap_close($imap);
    }

    foreach ($sequences as $key => $value)
        $sequences[$key] = preg_replace("/^.*?(\d+)$/", "$1", $value);
    session_save($sequences);

    output_log("End");
}

error_reporting(E_ERROR | E_WARNING);

main();
?>