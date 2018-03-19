<?php 
namespace Universal\Http;

interface HttpRequestInterface extends \ArrayAccess {
    
    public function setHttpMethod($httpMethod);
    
    public function setRequestUri($uri);
    
    public function httpMethod();
    
    public function requestUri();

    public function importParameters(array $parameters);
    
    public function importGlobals(array $globals);

}

