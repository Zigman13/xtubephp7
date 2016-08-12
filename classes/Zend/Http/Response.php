<?php


class Zend_Http_Response
{

    protected static $messages = array(
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',

        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',

        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',  // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',

        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',

        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded'
    );

    protected $version;

    protected $code;

    protected $message;

    protected $headers = array();

    protected $body;

    public function __construct($code, $headers, $body = null, $version = '1.1', $message = null)
    {
        // Make sure the response code is valid and set it
        if (self::responseCodeAsText($code) === null) {
            require_once 'Zend/Http/Exception.php';
            throw new Zend_Http_Exception("{$code} is not a valid HTTP response code");
        }

        $this->code = $code;

        // Make sure we got valid headers and set them
        if (! is_array($headers)) {
            require_once 'Zend/Http/Exception.php';
            throw new Zend_Http_Exception('No valid headers were passed');
    }

        foreach ($headers as $name => $value) {
            if (is_int($name))
                list($name, $value) = explode(": ", $value, 1);

            $this->headers[ucwords(strtolower($name))] = $value;
        }

        // Set the body
        $this->body = $body;

        // Set the HTTP version
        if (! preg_match('|^\d\.\d$|', $version)) {
            require_once 'Zend/Http/Exception.php';
            throw new Zend_Http_Exception("Invalid HTTP response version: $version");
        }

        $this->version = $version;

        // If we got the response message, set it. Else, set it according to
        // the response code
        if (is_string($message)) {
            $this->message = $message;
        } else {
            $this->message = self::responseCodeAsText($code);
        }
    }

    public function isError()
    {
        $restype = floor($this->code / 100);
        if ($restype == 4 || $restype == 5) {
            return true;
        }

        return false;
    }

    public function isSuccessful()
    {
        $restype = floor($this->code / 100);
        if ($restype == 2 || $restype == 1) { // Shouldn't 3xx count as success as well ???
            return true;
        }

        return false;
    }

    public function isRedirect()
    {
        $restype = floor($this->code / 100);
        if ($restype == 3) {
            return true;
        }

        return false;
    }

    public function getBody()
    {
        $body = '';

        // Decode the body if it was transfer-encoded
        switch ($this->getHeader('transfer-encoding')) {

            // Handle chunked body
            case 'chunked':
                $body = self::decodeChunkedBody($this->body);
                break;

            // No transfer encoding, or unknown encoding extension:
            // return body as is
            default:
                $body = $this->body;
                break;
        }

        // Decode any content-encoding (gzip or deflate) if needed
        switch (strtolower($this->getHeader('content-encoding'))) {

            // Handle gzip encoding
            case 'gzip':
                $body = self::decodeGzip($body);
                break;

            // Handle deflate encoding
            case 'deflate':
                $body = self::decodeDeflate($body);
                break;

            default:
                break;
        }

        return $body;
    }

    public function getRawBody()
    {
        return $this->body;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getStatus()
    {
        return $this->code;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getHeader($header)
    {
        $header = ucwords(strtolower($header));
        if (! is_string($header) || ! isset($this->headers[$header])) return null;

        return $this->headers[$header];
    }

    public function getHeadersAsString($status_line = true, $br = "\n")
    {
        $str = '';

        if ($status_line) {
            $str = "HTTP/{$this->version} {$this->code} {$this->message}{$br}";
        }

        // Iterate over the headers and stringify them
        foreach ($this->headers as $name => $value)
        {
            if (is_string($value))
                $str .= "{$name}: {$value}{$br}";

            elseif (is_array($value)) {
                foreach ($value as $subval) {
                    $str .= "{$name}: {$subval}{$br}";
                }
            }
        }

        return $str;
    }

    public function asString($br = "\n")
    {
        return $this->getHeadersAsString(true, $br) . $br . $this->getRawBody();
    }

    public static function responseCodeAsText($code = null, $http11 = true)
    {
        $messages = self::$messages;
        if (! $http11) $messages[302] = 'Moved Temporarily';

        if ($code === null) {
            return $messages;
        } elseif (isset($messages[$code])) {
            return $messages[$code];
        } else {
            return 'Unknown';
        }
    }

    public static function extractCode($response_str)
    {
        preg_match("|^HTTP/[\d\.x]+ (\d+)|", $response_str, $m);

        if (isset($m[1])) {
            return (int) $m[1];
        } else {
            return false;
        }
    }

    public static function extractMessage($response_str)
    {
        preg_match("|^HTTP/[\d\.x]+ \d+ ([^\r\n]+)|", $response_str, $m);

        if (isset($m[1])) {
            return $m[1];
        } else {
            return false;
        }
    }

    public static function extractVersion($response_str)
    {
        preg_match("|^HTTP/([\d\.x]+) \d+|", $response_str, $m);

        if (isset($m[1])) {
            return $m[1];
        } else {
            return false;
        }
    }

    public static function extractHeaders($response_str)
    {
        $headers = array();
        
        // First, split body and headers
        $parts = preg_split('|(?:\r?\n){2}|m', $response_str, 2);
        if (! $parts[0]) return $headers;
        
        // Split headers part to lines
        $lines = explode("\n", $parts[0]);
        unset($parts);
        $last_header = null;

        foreach($lines as $line) {
            $line = trim($line, "\r\n");
            if ($line == "") break;

            if (preg_match("|^([\w-]+):\s+(.+)|", $line, $m)) {
                unset($last_header);
                $h_name = strtolower($m[1]);
                $h_value = $m[2];

                if (isset($headers[$h_name])) {
                    if (! is_array($headers[$h_name])) {
                        $headers[$h_name] = array($headers[$h_name]);
                    }

                    $headers[$h_name][] = $h_value;
                } else {
                    $headers[$h_name] = $h_value;
                }
                $last_header = $h_name;
            } elseif (preg_match("|^\s+(.+)$|", $line, $m) && $last_header !== null) {
                if (is_array($headers[$last_header])) {
                    end($headers[$last_header]);
                    $last_header_key = key($headers[$last_header]);
                    $headers[$last_header][$last_header_key] .= $m[1];
                } else {
                    $headers[$last_header] .= $m[1];
                }
            }
        }

        return $headers;
    }

    public static function extractBody($response_str)
    {
        $parts = preg_split('|(?:\r?\n){2}|m', $response_str, 2);
        if (isset($parts[1])) { 
            return $parts[1];
        }
        return '';
    }

    public static function decodeChunkedBody($body)
    {
        $decBody = '';
        
        while (trim($body)) {
            if (! preg_match("/^([\da-fA-F]+)[^\r\n]*\r\n/sm", $body, $m)) {
                require_once 'Zend/Http/Exception.php';
                throw new Zend_Http_Exception("Error parsing body - doesn't seem to be a chunked message");
            }

            $length = hexdec(trim($m[1]));
            $cut = strlen($m[0]);

            $decBody .= substr($body, $cut, $length);
            $body = substr($body, $cut + $length + 2);
        }

        return $decBody;
    }

    public static function decodeGzip($body)
    {
        if (! function_exists('gzinflate')) {
            require_once 'Zend/Http/Exception.php';
            throw new Zend_Http_Exception('Unable to decode gzipped response ' . 
                'body: perhaps the zlib extension is not loaded?'); 
        }

        return gzinflate(substr($body, 10));
    }

    public static function decodeDeflate($body)
    {
        if (! function_exists('gzuncompress')) {
            require_once 'Zend/Http/Exception.php';
            throw new Zend_Http_Exception('Unable to decode deflated response ' . 
                'body: perhaps the zlib extension is not loaded?'); 
        }

        return gzuncompress($body);
    }

    public static function fromString($response_str)
    {
        $code    = self::extractCode($response_str);
        $headers = self::extractHeaders($response_str);
        $body    = self::extractBody($response_str);
        $version = self::extractVersion($response_str);
        $message = self::extractMessage($response_str);

        return new Zend_Http_Response($code, $headers, $body, $version, $message);
    }
}
