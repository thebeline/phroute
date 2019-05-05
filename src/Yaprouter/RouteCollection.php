<?php

namespace Yaprouter\Yaprouter;

class RouteCollection implements RouteCollectionInterface {
    
    private $routeEndpoints = [];
    private $__routeEndpoints = [];
    
    private $staticRouteMap = [];

    private $variableRouteMap = [];

    private $handlers = [];
    
    private $namedRouteMap = [];

    public function __construct(array $routeEndpoints, array $staticRouteMap, array $variableRouteMap, array $namedRouteMap, array $handlers) {
        $this->routeEndpoints   = $routeEndpoints;
        $this->staticRouteMap   = $staticRouteMap;
        $this->variableRouteMap = $variableRouteMap;
        $this->namedRouteMap    = $namedRouteMap;
        $this->handlers         = $handlers;
    }
    
    public function offsetExists($offset) {
        return isset($this->__routeEndpoints[$offset]) || isset($this->routeEndpoints[$offset]);
    }
    
    public function offsetGet($offset) {
        return isset($this->routeEndpoints[$offset])
            ? $this->routeEndpoints[$offset]
            : (isset($this->__routeEndpoints[$offset])
                ? ($this->routeEndpoints[$offset] = unserialize($this->__routeEndpoints[$offset]))
                : null);
    }
    
    public function offsetSet($offset , $value) {
        throw new \Exception('RouteDataArray::offsetSet() forbidden.');
    }
    
    public function offsetUnset($offset) {
        throw new \Exception('RouteDataArray::offsetUnset() forbidden.');
    }
    
    public function getNamed($name) {
        return isset($this->namedRouteMap[$name]) ? $this[$this->namedRouteMap[$name]] : null;
    }
    
    public function hasNamed($name) {
        return isset($this->namedRouteMap[$name]);
    }
    
    
    
    public function resolveHandler($handler, $recurse = false) {
        if (is_string($handler)) {
            if (isset($this->handlers[$handler]))
                $handler = $this->handlers[$handler];
        } elseif ($recurse && is_array($handler))
            $handler[0] = $this->resolveHandler($handler[0]);
        
        return $handler;
        
    }
    
    /**
     * Normalise the array filters attached to the route and merge with any global filters.
     *
     * @param $filters
     * @return array
     */
    public function resolveHandlers(array $handler_collection) {
        foreach ($handler_collection as $priority => &$handlers)
            foreach ($handlers as $position => &$handler)
                $handler = $this->resolveHandler($handler, true);
        
        return $handler_collection;
    }
    
    
    /**
     * @return array
     */
    public function getHandlers() {
        return $this->handlers;
    }
    
    /**
     * @return array
     */
    public function getNamedRouteMap() {
        return $this->namedRouteMap;
    }
    
    /**
     * @return array
     */
    public function getStaticRouteMap() {
        return $this->staticRouteMap;
    }

    /**
     * @return array
     */
    public function getVariableRouteMap() {
        return $this->variableRouteMap;
    }
    
    public function __sleep() {
        // Serialize Endpoints
        $this->__routeEndpoints = [];
        foreach ($this->routeEndpoints as $i => $endpoint)
            $this->__routeEndpoints[$i] = serialize($endpoint);
        
        return ['__routeEndpoints', 'staticRouteMap', 'variableRouteMap', 'namedRouteMap', 'handlers'];
    }
    
}
