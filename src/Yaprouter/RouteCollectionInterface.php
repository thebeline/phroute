<?php

namespace Yaprouter\Yaprouter;

interface RouteCollectionInterface implements \ArrayAccess {
    
    public function __construct(array $routeEndpoints, array $staticRouteMap, array $variableRouteMap, array $namedRouteMap, array $handlers);
    
    public function getNamed($name);
    
    public function hasNamed($name);
    
    public function resolveHandler($handler, $recurse = false);
    
    public function resolveHandlers(array $handler_collection);
    
    public function getHandlers();
    
    public function getNamedRouteMap();
    
    public function getStaticRouteMap();
    
    public function getVariableRouteMap();
    
}
