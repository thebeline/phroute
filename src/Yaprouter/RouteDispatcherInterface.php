<?php

namespace Yaprouter\Yaprouter;

use Yaprouter\Yaprouter\Exception\HttpMethodNotAllowedException;
use Yaprouter\Yaprouter\Exception\HttpRouteNotFoundException;
use Yaprouter\Yaprouter\Exception\DispatchContinueException;

interface RouteDispatcherInterface {

    public function __construct(RouteCollectionInterface $routeCollection, HandlerResolverInterface $handlerResolver = null);
	
	public function setRequestObject($requestObject);
    
    public function hasRoute($name);
    
    public function route($name, array $parameters = null);
	
	public function newDispatchIterator(HttpRequest $request);
	
    public function dispatch($httpMethod, $uri = null);
	
	public function dispatchRouteID($routeID, HttpRequestInterface $request, array $parameters);
    
}
