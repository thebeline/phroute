<?php

namespace Yaprouter\Yaprouter;

class DispatchIterator implements \Iterator {
    
    protected $staticRoutes;
    protected $variableRoutes;

    protected $httpMethod;
	protected $httpMethods;
	
    protected $uri;
    
    private $foundMethods = [];
	
	
	
	
    private $regex;
    
    private $methods = [];
    
    
    private $current_route;
    
    private $current_handlers;
    private $route_group_offset;
    private $current_route_group;

    private $endpoints;
	
	private $found_hash = [];
	private $found_key  = 0;
    
    /**
     * Create a new route dispatcher.
     *
     * @param RouteDataInterface $data
     * @param HandlerResolverInterface $resolver
     */
    public function __construct(HttpRequestInterface $request, RouteCollectionInterface $routeCollection) {
        $this->uri = $request->requestUri();
        $this->setHttpMethod($request->httpMethod());
		
		$this->staticRoutes   = $routeCollection->getStaticRouteMap();
		$this->variableRoutes = $routeCollection->getVariableRouteMap();
		
		$this->complete = false;
		$this->variable = false;
		
		$this->_next();
    }
	
	private function setHttpMethod($httpMethod) {
		$this->httpMethod = $httpMethod;
        $httpMethods = [$httpMethod];
        
        if ($httpMethod === Route::HEAD)
            $httpMethods[] = Route::GET;
        
        $httpMethods[] = Route::ANY;
        
        $this->httpMethods = $httpMethods;
	}
	
	public function current() {
		return isset($this->found_hash[$this->found_key]) ? $this->found_hash[$this->found_key] : null;
	}
	
	public function key() {
		return isset($this->found_hash[$this->found_key]) ? $this->found_key : null;
	}
	
	public function rewind() {
		$this->found_key = 0;
	}
	
	public function valid() {
		return isset($this->found_hash[$this->found_key]);
	}
	
	public function next() {
		$this->found_key++;
		if (!isset($this->found_hash[$this->found_key]))
			$this->_next();
	}
	
	private function _next() {
		
		if (!$this->complete) {
			switch ($this->variable) {
				case false:
					$routeID = $this->nextStaticRouteHandler($this->staticRoutes, $this->uri, $this->httpMethods);
					if (isset($routeID))
						break;
					$this->variable = true;
				case true:
					$routeID = $this->nextVariableRouteHandler($this->variableRoutes, $this->uri, $this->httpMethods);
			}
			
			if (isset($routeID)) {
				
				$this->found_hash[$this->found_key] = [
					$routeID,
					$this->current_params
				];
				
				echo "Found Route ID: $routeID";
				
			} else {
				$this->complete = true;
			}
		}
		
		return !$this->complete;
	}
    
    private function addFoundMethods(array $methods) {
        $this->foundMethods += array_flip($methods);
    }
	
	public function getFoundMethods() {
		return array_keys($this->foundMethods);
	}
    
    private function nextMappedOffset(array $source, array $map, $map_offset) {
        
		while (isset($map[++$map_offset]))
            if (isset($source[$map[$map_offset]]))
                return [$source[$map[$map_offset]], $map_offset];
        
        return [null, null];
    }
    
    private function nextOffset(array $source, $offset) {
        
        if (!empty($source[++$offset]))
            return [$source[$offset], $offset];
        
        return [null, null];
    
    }
    
    private function nextHandler($route, array $request_methods, array $current_handlers = null, $handler_offset = null, $method_offset = null) {
		
        if (is_null($current_handlers))
            list($current_handlers, $method_offset) = $this->nextMappedOffset($route, $request_methods, -1);
		
        while (isset($current_handlers)) {
            
            if (is_null($handler_offset))
                $handler_offset = -1;
            
            if (isset($current_handlers[++$handler_offset]))
                return [$current_handlers[$handler_offset], $current_handlers, $handler_offset, $method_offset];
            
            $handler_offset = null;
            
            list($current_handlers, $method_offset) = $this->nextMappedOffset($route, $request_methods, $method_offset);
        }
        
        return [null, null, null, null];
    }
    
    private function nextMatch($route_group, $uri, $previous_route = null) {
        
        $regex = null;
        
        if (is_null($previous_route)) {
            $regex = $route_group[RouteCollector::REGEX];
        } elseif (isset($route_group[RouteCollector::ROUTES][++$previous_route])) {
			//print_r($route_group);
			//die();
            $regex = [];
            foreach (array_slice($route_group[RouteCollector::ROUTES], $previous_route, null, true) as $route)
                $regex[] = $route[RouteCollector::REGEX];
            
            $regex = empty($regex) ? null : ('~^(?|' . implode('|', $regex) . ')$~');
        }
		
        if (!empty($regex) && preg_match($regex, $this->uri, $matches)) {
			
            array_shift($matches);
            
            $map_offset = count($matches) - 1;
			
			if ($map_offset >= -1) {
				
				while (!isset($route_group[RouteCollector::MAP][++$map_offset])) if ($map_offset > 1000) {
					// Trigger Error: Offset out of range error.
					break;
				}
				
				if (isset($route_group[RouteCollector::MAP][$map_offset])) {
					$route_offset = $route_group[RouteCollector::MAP][$map_offset];
					if (is_null($previous_route) && isset($route_group[RouteCollector::ROUTES][$route_offset][RouteCollector::METHODS]))
						$this->addFoundMethods(array_keys($route_group[RouteCollector::ROUTES][$route_offset][RouteCollector::METHODS]));
					return [$route_offset, $matches];
				}
			}
            
        }
        
        return [null, null];
        
    }
	
    private function nextStaticRouteHandler(array $routes, $uri, array $request_methods) {
        
        if (isset($routes[$uri])) {
            
            if (empty($this->current_route)) {
                $this->current_route = $routes[$uri];
                $this->addFoundMethods(array_keys($this->current_route));
            }
            
            list($handler, $this->current_handlers, $this->handler_offset, $this->method_offset) =
                $this->nextHandler($this->current_route, $request_methods, $this->current_handlers, $this->handler_offset, $this->method_offset);
            
            if ($handler)
                return $handler;
        }
		
		$this->current_route = null;
        
		return null;
    }
	
	private $handler_offset;
	private $method_offset;
	
	
    private function nextVariableRouteHandler(array $routes, $uri, array $request_methods) {
        //echo "Variable\n";
		
        if (is_null($this->current_route_group))
            $this->current_route_group = 0;
        
        while (isset($routes[$this->current_route_group])) {
            
            $route_group = $routes[$this->current_route_group];
			
			//print_r($route_group);
            
            if (is_null($this->current_route)) {
                list($this->current_route, $this->current_params) = $this->nextMatch($route_group, $uri);
            }
			
            while (isset($this->current_route) && isset($route_group[RouteCollector::ROUTES][$this->current_route][RouteCollector::METHODS])) {
                
                $route = $route_group[RouteCollector::ROUTES][$this->current_route][RouteCollector::METHODS];
                
                list($handler, $this->current_handlers, $this->handler_offset, $this->method_offset) =
                    $this->nextHandler($route, $request_methods, $this->current_handlers, $this->handler_offset, $this->method_offset);
                
                if (isset($handler))
                    return $handler;
                
                list($this->current_route, $this->current_params) = $this->nextMatch($route_group, $uri, $this->current_route);
            }
            
            $this->current_route_group++;
        }
		
        $this->current_route_group = null;
        
        return null;
        
    }
	
}
