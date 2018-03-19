<?php namespace Yaprouter\Yaprouter;

interface ParsedRouteDataInterface {

    public function getRouteString();
    
    public function getRouteParameters();
    
    public function getRouteParts();

}
