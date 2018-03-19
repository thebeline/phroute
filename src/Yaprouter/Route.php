<?php namespace Yaprouter\Yaprouter;

class Route extends AbstractParsedRouteData {
    
    const EVENTS = 'events';
    
    /**
     * Miscellaneous Constants
     */
    const PREFIX = 'prefix';

    /**
     * Constants for common HTTP methods
     */
    const ANY     = 'ANY';

    const GET     = 'GET';

    const HEAD    = 'HEAD';

    const POST    = 'POST';

    const PUT     = 'PUT';

    const PATCH   = 'PATCH';

    const DELETE  = 'DELETE';

    const OPTIONS = 'OPTIONS';
    
    private $httpMethod = '';
    
    private $handler;
    
    private $options = []; // not yet
    
    private $event_handlers = [];
    
    private $isStatic;
    
    protected $routeString;
    protected $routeParameters;
    protected $routeParts;
    
    public function __construct($httpMethod, $routeString, $handler, array $options = []) {
        if (!self::validateHttpMethod($httpMethod))
            throw new \Exception("Invalid HTTP Method $httpMethod.");
        
        $this->httpMethod = $httpMethod;
        
        $this->handler    = $handler;
        
        $routeData = RouteParser::parseRouteString($routeString);
        
        $this->routeString     = $routeData->getRouteString();
        $this->routeParameters = $routeData->getRouteParameters();
        $this->routeParts      = $routeData->getRouteParts();
        
        $this->isStatic = (count($this->routeParts) < 2) && empty($this->routeParts[0][RouteParser::PART_NAME]);
        
        $this->setOptions($options);
    }
    
    public function getHandler() {
        return $this->handler;
    }
    
    public function getEventHandlers($event_name) {
        $handlers = isset($this->event_handlers[$event_name]) ? $this->event_handlers[$event_name] : [];
        
        switch ($event_name) {
            case '':
                // no cases yet
                break;
        }
        
        return $handlers;
    }
    
    public function isStatic() {
        return (bool) $this->isStatic;
    }
    
    protected function setEventHandlers(array $events) {
        foreach ($events as $event_name => $event_handlers) {
            foreach ((array) $event_handlers as $handler) {
                if (is_array($handler)) {
                    $priority = (int) (isset($handler[1]) ? $handler[1] : 0);
                    $this->event_handlers[$event_name][$priority][] = $handler[0];
                } else {
                    $this->event_handlers[$event_name][0][] = $handler;
                }
            }
        }
    }
    
    /**
     * Set Parameters 
     *
     * Set default Values and if Required
     *
     * @param mixed[] $parameters
     * 
     * @return void
     *
     * @access protected
     *
     * @author Michael Mulligan <mike@belineperspectives.com>
     */
    protected function setParameters(array $parameters) {
        // not yet implemented
    }
    
    protected function setOptions(array $options) {
        
        if (isset($options[self::EVENTS])) {
            $this->setEventHandlers($options[self::EVENTS]);
            unset($options[self::EVENTS]);
        }
        
    }
    
    public function mapMatchedParameters(array $matches) {
        $parameters = [];
        
        foreach ($this->routeParameters as $name => $position)
            if (isset($matches[$position]))
                $parameters[$name] = $matches[$position];
        
        return (array) $parameters;
        
    }
    
    /**
     * Filter Request Parameters 
     *
     * Request Parameter sanitation:
     *  * Decode provided value
     * 	* Loads default values
     * 	* Performs Parameter<>Parameter Maps (no)
     * 	* Checks presence of required parameters
     *
     * @param mixed[] $parameters Parameters found from RouteUri of the request.
     * 
     * @return mixed[]
     *
     * @access public
     *
     * @author Michael Mulligan <mike@belineperspectives.com>
     */
    public function applyDefaultParameterValues($parameters) {
        
        foreach ($this->requestParameters as $name => $meta) {
            if (!isset($parameters[$name]) && isset($meta['value']))
                $parameters[$name] = $meta['value'];
            if (empty($parameters[$name]) && !empty($meta['required']))
                throw new \InvalidArgumentException("Missing required parameter.");
        }
        
        return $parameters;
        
    }
    
	/**
     * Filter Route Parameters 
     *
     * Route Parameter sanitation:
     * 	* Loads default values
     * 	* Merges defaults with provided values
     * 	* Performs Parameter<>Parameter Maps // when?
     * 	* Checks presence of required parameters
     * 	* // Should encode // We should pass both of these through the RouteParser
	 *  * The result of this function should be an array of strings
     *
     * @param mixed[] $parameters Parameters to be a part of the RouteUri format.
     * 
     * @return mixed[]
     *
     * @access public
     *
     * @author Michael Mulligan <mike@belineperspectives.com>
     */
    public function filterRoutePrameters(array $parameters) {
        
    }
	
	/**
     * Map Route Parameter 
     *
     * If the function requires a parameter named 'user', but the
     * URI stores it under 'username':
     *
     * 	'username' = $route->mapRouteParameter('user');
     *
     * @param string $from Parameter name the handler would expect
     * 
     * @return string Parameter name the RouteURI would expect 
     *
     * @access public
     *
     * @author Michael Mulligan <mike@belineperspectives.com>
     */
	public function mapRouteParameter($from) {
		return $to;
	}
	
	/**
     * Map Request Parameter 
     *
     * If the function requires a parameter named 'user', but the
     * URI stores it under 'username':
     *
     * 	'user' = $route->mapRequestParameter('username');
     *
     * @param string $from Parameter name the RouteURI would expect
     * 
     * @return string Parameter name the Handler would expect 
     *
     * @access public
     *
     * @author Michael Mulligan <mike@belineperspectives.com>
     */
	public function mapRequestParameter($from) {
		return $to;
	}
    
    public static function validateHttpMethod($method) {
        return in_array($method, self::httpMethods());
    }
    
    /**
     * @return array
     */
    public static function httpMethods() {
        return [
            self::ANY,
            self::GET,
            self::POST,
            self::PUT,
            self::PATCH,
            self::DELETE,
            self::HEAD,
            self::OPTIONS,
        ];
    }
    
    public static function trim($route) {
        return trim($route, '/');
    }
}

