<?php 

define("SECTION_ACCOUNTS", "ACCOUNTS");
define("SECTION_COMMON", "COMMON");

define("SECTION_COMMON_BLOCK", "BLOCK");
define("SECTION_COMMON_DURATION", "DURATION");
define("SECTION_COMMON_OVERSIZE", "OVERSIZE");

define("URL_SCHEME", "scheme");
define("URL_HOST", "host");
define("URL_PORT", "port");
define("URL_PATH", "path");
define("URL_USER", "user");
define("URL_PASS", "pass");

define("FILTER_ACCOUNT", "account");
define("FILTER_SOURCE", "source");
define("FILTER_TARGET", "target");
define("FILTER_PATTERN", "pattern");
define("FILTER_EXPRESSION", "expression");

define("PROTOCOL_IMAP", "imap");
define("PROTOCOL_IMAP_SECURE", "imaps");

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
            $value = trim(implode("\r\n$key: ", $value));
        $message = trim($message) . "\r\n$key: $value";
    }
     
    $body = imap_body($imap, $number);
    
    $message = trim($message) . "\r\n\r\n" . trim($body);
    $message = message_decode_plain($message);
    
    if (!$message)
        return FALSE;
    return trim($message);
}

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
        trigger_error("Invalid filter section (missing valid statement):\r\n$plain\r\n ", E_USER_WARNING);
        return FALSE;
    }
    $filter[FILTER_ACCOUNT] = $match[1]; 
    $filter[FILTER_SOURCE]  = $match[2];
    $filter[FILTER_TARGET]  = $match[3];
    
    foreach ($section as $line) {
        if (preg_match("/^\s*pat\s*:.*$/i", $line)) {
            if (!preg_match("/^\s*pat\s*:\s*(\w+)\s+(.*?)\s*$/i", $line, $match)) {
                trigger_error("Invalid filter section (invalid " . FILTER_PATTERN . "):\r\n$plain\r\n ", E_USER_WARNING);
                return FALSE;    
            } elseif (@preg_match($match[2], "") === FALSE) {
                trigger_error("Invalid filter section (invalid " . FILTER_PATTERN . " expression):\r\n$plain\r\n ", E_USER_WARNING);
                return FALSE;    
            } else $filter[FILTER_PATTERN][strtoupper($match[1])] = $match[2];
        } elseif (preg_match("/^\s*exp\s*:.*$/i", $line)) {
            if (!preg_match("/^\s*exp\s*:\s*([\w\(\)\|\&\!\s]+)\s*$/i", $line, $match)) {
                trigger_error("Invalid filter section (invalid " . FILTER_EXPRESSION . "):\r\n$plain\r\n ", E_USER_WARNING);
                return FALSE;    
            } else $filter[FILTER_EXPRESSION] = strtoupper($match[1]);              
        }         
    }
    if (count($filter[FILTER_PATTERN])
            && !$filter[FILTER_EXPRESSION]) {
        trigger_error("Invalid filter section (missing valid " . FILTER_EXPRESSION . "):\r\n$plain\r\n ", E_USER_WARNING);
        return FALSE;
    }
    if (!count($filter[FILTER_PATTERN])
            && $filter[FILTER_EXPRESSION]) {
        trigger_error("Invalid filter section (missing valid " . FILTER_PATTERN . "):\r\n$plain\r\n ", E_USER_WARNING);
        return FALSE;
    }
    if (count($filter[FILTER_PATTERN])
            && $filter[FILTER_EXPRESSION]) {
        $expression = $filter[FILTER_EXPRESSION];
        $expression = preg_replace("/[\(\)\|\&\!\s]+/", " ", $expression);
        foreach ($filter[FILTER_PATTERN] as $key => $value)
            $expression = preg_replace("/\b" . preg_quote($key) . "\b/i", " ", $expression);
        if (trim($expression)) {
            trigger_error("Invalid filter section (missing valid " . FILTER_PATTERN . " in " . FILTER_EXPRESSION . "):\r\n$plain\r\n ", E_USER_WARNING);
            return FALSE;
        }
        $expression = $filter[FILTER_EXPRESSION];
        $expression = preg_replace("/\b\w+\b/", "X", $expression);
        if (preg_match("/\w+\s+\w+/", $expression)
                || preg_match("/\&\s+\&/", $expression)) {
            trigger_error("Invalid filter section (missing valid " . FILTER_EXPRESSION . "):\r\n$plain\r\n ", E_USER_WARNING);
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
            trigger_error("Invalid filter section (missing valid " . FILTER_EXPRESSION . "):\r\n$plain\r\n ", E_USER_WARNING);
            return FALSE;
        }
        $expression = $filter[FILTER_EXPRESSION];
        $expression = preg_replace("/[^\(\)]/", "", $expression);
        while (preg_match("/\(\)/", $expression))
            $expression = preg_replace("/\(\)/", "", $expression);
        if ($expression) {
            trigger_error("Invalid filter section (missing valid " . FILTER_EXPRESSION . "):\r\n$plain\r\n ", E_USER_WARNING);
            return FALSE;
        }
    }
    return $filter;
}

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
        $filter[] =  $section;
    }
    return $filter;
}

function account_list() {
    
    $accounts = array();
    $section = configuration_get(SECTION_ACCOUNTS);
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

function output_log($message) {

    if (!$message)
        return;
    
    $message = preg_replace("/(\r\n)|(\n\r)|[\r\n]/", "\r\n", $message);
    $message = preg_replace("/(\r\n\s*){2,}/", "\r\n\t", $message);

    $message = trim($message);
    if (!$message)
        return;
    
    print(date("Y-m-d H:i:s") . " " . $message . "\r\n");    
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

output_log("Start");
foreach (message_filter_list() as $filter) {    
    
    $caption = $filter[FILTER_ACCOUNT] . " " . $filter[FILTER_SOURCE] . " > " . $filter[FILTER_TARGET];
    output_log("Open: $caption");
    
    $target = $filter[FILTER_TARGET];
    $target = preg_replace("/^\/+/", "", $target);
    $target = preg_replace("/\/+$/", "", $target);
    $target = preg_replace("/\/+/", ".", $target);
    
    $imap = @imap_open_url($filter[FILTER_ACCOUNT], $filter[FILTER_SOURCE]);
    if (!$imap) {
        output_log("Connection: Opening failed");
        continue;
    }
    output_log("Connection: Successfully established");
        
    $uids = array();
    $whitelist = array();
    $meta = imap_check($imap);
    $overview = imap_fetch_overview($imap, "1:{$meta->Nmsgs}", 0);
    foreach ($overview as $entry) {
        $hash = hash("whirlpool", $filter[FILTER_ACCOUNT] . " " . $filter[FILTER_SOURCE]);
        $uid = @imap_uid($imap, $entry->msgno);
        if (!$uid)
            continue;
        if (array_key_exists($hash, $whitelist)
                && array_key_exists($uid, $whitelist[$hash]))
            continue;
        $message = imap_fetch_message_plain($imap, $uid);
        if (!$message)
            continue;
        if (!message_filter_eval($filter, $message))
            continue;
        if (preg_match("/NOTHING/i", $filter[FILTER_TARGET])) {
            if (!array_key_exists($hash, $whitelist))
                $whitelist[$hash] = array();
            $whitelist[$hash][] = $uid;
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
    
    output_log("Connection: Successfully closed");
}
output_log("End");
?>