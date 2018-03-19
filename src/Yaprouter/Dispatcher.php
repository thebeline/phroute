<?php

namespace Yaprouter\Yaprouter;

use Yaprouter\Yaprouter\Exception\HttpMethodNotAllowedException;
use Yaprouter\Yaprouter\Exception\HttpRouteNotFoundException;
use Yaprouter\Yaprouter\Exception\DispatchContinueException;

class Dispatcher implements RouteDispatcherInterface {

    private $handlerResolver;
	
	private $routeCollection;
	
	private $requestObject;

    /**
     * Create a new route dispatcher.
     *
     * @param RouteDataInterface $data
     * @param HandlerResolverInterface $resolver
     */
    public function __construct(RouteCollectionInterface $routeCollection, HandlerResolverInterface $handlerResolver = null) {
		$this->routeCollection = $routeCollection;
		$this->handlerResolver = $handlerResolver ?: new HandlerResolver();
    }
	
	public function setRequestObject($requestObject) {
		if (!is_string($requestObject)) {
			if (!($requestObject instanceof HttpRequestFactoryInterface))
				throw new \InvalidArgumentException("Instantiated HttpRequest objects must implement HttpRequestFactoryInterface");
		} elseif (!is_a($requestObject, __NAMESPACE__.'\\HttpRequestInterface'))
			throw new \InvalidArgumentException('HttpRequest classes must implement HttpRequestConstructableInterface.');
		
		$this->requestObject = $requestObject;
	}

    /**
     * @param $name
     * @return bool
     */
    public function hasRoute($name) {
        return $this->routeCollection->hasNamed($name);
    }

    protected function newRequestObject($httpMethod, $uri = null) {
		if (!is_string($httpMethod)) {
			if (is_object($httpMethod)) {
				if (!empty($uri))
					throw new \InvalidArgumentException("Parameter 2 must be null when providing HttpRequest object.");
				$requestObject = $httpMethod;
			} else {
				throw new \InvalidArgumentException("Parameter 1 must string or object of type HttpRequest.");
			}
		} elseif (empty($uri)) {
			throw new \InvalidArgumentException("Parameter 2 must not be empty when not providing HttpRequest object.");
		} else {
			$objectSource = isset($this->requestObject) ? $this->requestObject : __NAMESPACE__.'\\HttpRequest';
			if (is_object($objectSource)) {
				$requestObject = $objectSource->factory($httpMethod, $uri);
			} else {
				$requestObject = new $objectSource();
				$requestObject->importGlobals($GLOBALS);
				$requestObject->setHttpMethod($httpMethod);
				$requestObject->setRequestUri($uri);
			}
		}
		
		if (!($requestObject instanceof HttpRequestInterface))
			throw new \InvalidArgumentException('HttpRequest Object must be of implement HttpRequestInterface.');
		
		return $requestObject;
	}
	
	/**
     * @param $name
     * @param array $args
     * @return string
     */
    public function route($name, array $parameters = null) {
		$uri = '';
		if ($this->hasRoute($name)) {
			$route      = $this->routeCollection->getNamed($name);
			$parameters = new \ArrayObject((array) $parameters);
			
			$event = $this->dispatchEvent('route_parameters', $route, $parameters);
			if (!is_null($event) && ($event !== true))
				return $event;
			
			// route parameter event
			$uri = RouteParser::buildUri($route->getRouteParts(), $parameters->getArrayCopy());
		}
		return $uri;
	}
	
	public function newDispatchIterator(HttpRequest $request) {
		return new DispatchIterator($request, $this->routeCollection);
	}
	
    /**
     * Dispatch a route for the given HTTP Method / URI.
     *
     * @param $httpMethod
     * @param $uri
     * @return mixed|null
     */
    public function dispatch($httpMethod, $uri = null) {
		
		$request = $this->newRequestObject($httpMethod, $uri);
		
		$dispatchIterator = $this->newDispatchIterator($request);
		
		foreach ($dispatchIterator as $dispatch_data) {
			
			list($routeID, $parameters) = $dispatch_data;
			
			try {
				return $this->dispatchRouteID($routeID, $request, $parameters);
			} catch (DispatchContinueException $continueException) {
				continue;
			}
		
		}
		
		if (isset($continueException))
			throw $continueException->getPrevious();
		if (isset($dispatch_data))
			throw new HttpMethodNotAllowedException('Allow: ' . implode(', ', $dispatchIterator->getFoundMethods()));
		else
			throw new HttpRouteNotFoundException('Route ' . ($request->requestUri()) . ' not found.');
    }
	
	public function dispatchRouteID($routeID, HttpRequestInterface $request, array $parameters) {
		
		if (!($route = $this->routeCollection[$routeID]))
			throw new DispatcherException("Invalid RouteID.");
		
		try {
			
			return $this->dispatchRoute($route, $request, $parameters);
		
		} catch (\Exception $exception) {
			
			$response = $this->dispatchEvent('exception', $route, $exception);
			
			if (empty($response))
				throw $exception;
			elseif ($response === true)
				throw new DispatchContinueException("Route Dispatch of ID $routeID failed with Exception: ".$exception->getMessage(), 0, $exception);
			else
				return $response;
			
		}
		
	}
	
	protected function dispatchRoute(Route $route, HttpRequestInterface $request, array $parameters) {
		if ($eventResponse = $this->dispatchEvent('init', $route, $request->httpMethod(), $request->requestUri()))
			return $eventResponse;
		
		$parameters = new \ArrayObject($route->mapMatchedParameters($parameters));
		
		$event = $this->dispatchEvent('request_parameters', $route, $parameters);
		if (!is_null($event) && ($event !== true))
			return $event;
		
		$dispatchRequest = clone $request;
		
		$dispatchRequest->importParameters($parameters->getArrayCopy());
		
		$event = $this->dispatchEvent('before', $route, $dispatchRequest);
		if (!is_null($event) && ($event !== true))
			return $event;
		
		$handler = $route->getHandler();
		
		$routeHandler = $this->routeCollection->resolveHandler($handler);
		
		if (!is_callable($routeHandler = $this->handlerResolver->resolve($routeHandler)) && !is_callable($routeHandler = [$routeHandler, 'handleRoute']))
			throw new DispatcherException("Invalid Route Handler.");
		
		$routeResponse = $this->dispatchHandler($routeHandler, $dispatchRequest);
		
		$event = $this->dispatchEvent('after', $route, $routeResponse);
		if (!is_null($event) && ($event !== true))
			return $event;
		
		return $routeResponse;
	}
	
	protected function dispatchHandler($handler, ...$arguments) {
		if (is_array($handler)) {
			$callback = [
				$this->handlerResolver->resolve($handler[0]),
				$handler[1]
			];
			if (isset($handler[2]))
				 $arguments += (array) $handler[2];
		} else {
			$callback = $this->handlerResolver->resolve($handler);
		}
		
		if (!is_callable($callback) && (is_array($callback) || !is_callable($callback = [$callback, $event_name])))
			throw new DispatcherException("Non-callable callback.");
		
		return $callback(...$arguments);
	}
	
	protected function dispatchEvent($event_name, Route $route, ...$arguments) {
        
		$response = null;
		
		$routeHandlers = $route->getEventHandlers($event_name);
        
		if (!empty($routeHandlers)) {
			
			$resolvedHandlers = $this->routeCollection->resolveHandlers($routeHandlers);
			
			foreach ($resolvedHandlers as $priority => $handlers) {
				foreach ($handlers as $handler) {
					$response = $this->dispatchHandler($handler, ...$arguments);
					if (!is_null($response) && $response !== true)
						break 2;
				}
			}
		}
		
        return $response;
		
	}
	
}
