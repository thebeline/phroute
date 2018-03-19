<?php namespace Yaprouter\Yaprouter;

abstract class AbstractParsedRouteData implements ParsedRouteDataInterface {
    
    protected $routeString;
    protected $routeParameters;
    protected $routeParts;
    
    public function getRouteString() {
        return $this->routeString;
    }
    
    public function getRouteParameters() {
        return $this->routeParameters;
    }
    
    public function getRouteParts() {
        return $this->routeParts;
    }

}
