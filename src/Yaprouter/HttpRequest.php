<?php 
namespace Yaprouter\Yaprouter;

class HttpRequest implements HttpRequestInterface {
    
    static protected $httpHeaderMapping = array(
        'HTTP_ACCEPT'                    => 'Accept',
        'HTTP_ACCEPT_CHARSET'            => 'Accept-Charset',
        'HTTP_ACCEPT_ENCODING'           => 'Accept-Encoding',
        'HTTP_ACCEPT_LANGUAGE'           => 'Accept-Language',
        'HTTP_CONNECTION'                => 'Connection',
        'HTTP_CACHE_CONTROL'             => 'Cache-Control',
        'HTTP_UPGRADE_INSECURE_REQUESTS' => 'Upgrade-Insecure-Requests',
        'HTTP_HOST'                      => 'Host',
        'HTTP_REFERER'                   => 'Referer',
        'HTTP_USER_AGENT'                => 'User-Agent',
        'CONTENT_TYPE'                   => 'Content-Type',
        'CONTENT_LENGTH'                 => 'Content-Length'
    );
    
    protected static $defaultBagContainer = '\ArrayObject';
    
    protected static $bagContainerMap = [];
    
    protected static $bagGlobalMap = [
        'post'       => '_POST',
        'get'        => '_GET',
        'parameters' => '_REQUEST',
        'cookie'     => '_COOKIE',
        'session'    => '_SESSION',
        'files'      => '_FILES',
        'server'     => '_SERVER'
    ];
    
    protected $_rawBagData = [];
    
    protected $_dataBags = [];
    
    public function setHttpMethod($httpMethod) {
        $this->server['REQUEST_METHOD'] = $httpMethod;
    }
    
    public function setRequestUri($uri) {
        $this->server['REQUEST_URI'] = $uri;
    }
    
    public function httpMethod() {
        return isset($this->server['REQUEST_METHOD']) ? $this->server['REQUEST_METHOD'] : 'GET';
    }
    
    public function requestUri() {
        return isset($this->server['REQUEST_URI']) ? $this->server['REQUEST_URI'] : '';
    }

    public function offsetSet($name,$value) {
        $this->parameters[$name] = $value;
    }
    
    public function offsetExists($name) {
        return isset($this->parameters[$name]);
    }
    
    public function offsetGet($name) {
        return $this->parameters[$name];
    }
    
    public function offsetUnset($name) {
        unset($this->paramemters[$name]);
    }
    
    public function importParameters(array $parameters) {
        foreach ($parameters as $key => $value)
            $this->paramemters[$key] = $value;
    }

    public function __get($key) {
        if (!isset($this->_dataBags[$key]) && isset(static::$bagGlobalMap[$key])) {
            $container = isset(static::$bagContainerMap[$key]) ? static::$bagContainerMap[$key] : static::$defaultBagContainer;
            $data = (array) (isset($this->_rawBagData[$key]) ? $this->_rawBagData[$key] : null);
            $this->_dataBags[$key] = new $container($data);
        }
        return isset($this->_dataBags[$key]) ? $this->_dataBags[$key] : null;
    }

    /**
     * Converts global $_SERVER variables to header values.
     *
     * @return array
     */
    public static function createHeadersFromServerGlobal(array $server) {
        $headers = array();
        foreach (self::$httpHeaderMapping as $serverKey => $headerKey) {
            if (isset($server[$serverKey])) {
                $headers[$headerKey] = $server[$serverKey];
            }
        }
        // For extra http header fields
        foreach ($server as $key => $value) {
            if (isset(self::$httpHeaderMapping[$key])) {
                continue;
            }
            if ('HTTP_' === substr($key,0,5)) {
                $headerField = join('-',array_map('ucfirst',explode('_', strtolower(substr($key,5)))));
                $headers[$headerField] = $value;
            }
        }
        return $headers;
    }
    
    public function importGlobals(array $globals) {
        foreach (static::$bagGlobalMap as $bag => $global) {
            if (isset($globals[$global])) {
                unset($this->_rawBagData[$bag]);
                unset($this->_dataBags[$bag]);
                $this->_rawBagData[$bag] = $globals[$global];
            }
        }
        if (isset($globals['_SERVER'])) {
            unset($this->_dataBags['headers']);
            $this->_dataBags['headers'] = static::createHeadersFromServerGlobal($globals['_SERVER']);
        }
    }

    /**
     * Create request object from superglobal $GLOBALS
     *
     * @param $globals The $GLOBALS
     * @return HttpRequest
     */
    static public function createFromGlobals(array $globals) {
        $request = new static;
        $request->importGlobals($globals);
        return $request;
    }

}

