<?php namespace Yaprouter\Yaprouter;

class ParsedRouteData extends AbstractParsedRouteData {
    
    public function __construct($routeString, $routeParameters, $routeParts) {
        $this->routeString     = $routeString;
        $this->routeParameters = $routeParameters;
        $this->routeParts      = $routeParts;
    }
    
}
