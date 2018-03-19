<?php namespace Yaprouter\Yaprouter;

use ReflectionClass;
use ReflectionMethod;

use Yaprouter\Yaprouter\Exception\BadRouteException;

/**
 * Class RouteCollector
 * @package Yaprouter\Yaprouter
 */
class RouteCollector implements RouteCollectionProviderInterface {

    /**
     *
     */
    const DEFAULT_CONTROLLER_ROUTE = 'index';

    /**
     *
     */
    const APPROX_CHUNK_SIZE = 10;
    
    const METHODS = 'methods';
    const REGEX   = 'regex';
    const MAP     = 'map';
    const ROUTES  = 'routes';

    /**
     * @var array
     */
    private $filters = [];
    /**
     * @var array
     */
    private $staticRouteMap = [];
    /**
     * @var array
     */
    private $variableRouteMap = [];
    
    /**
     * @var array
     */
    private $routeEndpoints = [];
    
    /**
     * @var array
     */
    private $reverseRouteMap = [];

    /**
     * @var array
     */
    private $groupOptions = [];

    /**
     * @var
     */
    private $currentRoutePrefix;

    /**
     * @param $httpMethod
     * @param $route
     * @param $handler
     * @param array $filters
     * @return $this
     */
    public function addRoute($httpMethod, $routeString, $handler, array $options = []) {
        
        if (is_array($routeString))
            list($routeString, $name) = $routeString;
        
        $routeString = $this->addPrefix(Route::trim($routeString));
        
        $options = array_merge_recursive((array) $this->groupOptions, $options);
            
        $route = new Route($httpMethod, $routeString, $handler, $options);
        
        $endpoint_offset = count($this->routeEndpoints);
        
        $this->routeEndpoints[] = $route;
        
        if (!empty($name)) {
            if (isset($this->reverseRouteMap[$name]))
                throw new BadRouteException("Cannot register two routes with matching name '$name'");
    
            $this->reverseRouteMap[$name] = $endpoint_offset;
        }
        
        $routeString = $route->getRouteString();
        
        if ($route->isStatic()) 
            $this->staticRouteMap[$routeString][$httpMethod][]   = $endpoint_offset;
        else
            $this->variableRouteMap[$routeString][$httpMethod][] = $endpoint_offset;
        
        return $this;
    
    }

    /**
     * @param array $filters
     * @param \Closure $callback
     */
    public function group(array $options, \Closure $callback) {
        
        if (!empty($options)) {
            $oldGroupOptions = $this->groupOptions;
            $this->groupOptions = array_merge_recursive($oldGroupOptions, $options);
        }
        
        $newPrefix = isset($options[Route::PREFIX]) ? Route::trim($options[Route::PREFIX]) : null;
        
        if (!empty($newPrefix)) {
            $oldRoutePrefix = $this->currentRoutePrefix;
            $this->currentRoutePrefix = $this->addPrefix($newPrefix);
        }

        $callback($this);
        
        if (isset($oldGroupOptions))
            $this->groupOptions = $oldGroupOptions;

        if (isset($oldRoutePrefix))
            $this->currentRoutePrefix = $oldRoutePrefix;
        
        return $this;
    }

    private function addPrefix($route) {
        return Route::trim(Route::trim($this->currentRoutePrefix) . '/' . $route);
    }

    /**
     * @param $name
     * @param $handler
     */
    public function addHandler($name, $handler) {
        if (isset($this->handlers[$name]))
            throw new \Exception("Filter with name '$name' already Exists.");
        $this->handlers[$name] = $handler;
        return $this;
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function get($route, $handler, array $options = []) {
        return $this->addRoute(Route::GET, $route, $handler, $options);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function head($route, $handler, array $options = []) {
        return $this->addRoute(Route::HEAD, $route, $handler, $options);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function post($route, $handler, array $options = []) {
        return $this->addRoute(Route::POST, $route, $handler, $options);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function put($route, $handler, array $options = []) {
        return $this->addRoute(Route::PUT, $route, $handler, $options);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function patch($route, $handler, array $options = []) {
        return $this->addRoute(Route::PATCH, $route, $handler, $options);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function delete($route, $handler, array $options = []) {
        return $this->addRoute(Route::DELETE, $route, $handler, $options);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function options($route, $handler, array $options = []) {
        return $this->addRoute(Route::OPTIONS, $route, $handler, $options);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function any($route, $handler, array $options = []) {
        return $this->addRoute(Route::ANY, $route, $handler, $options);
    }

    /**
     * @param $route
     * @param $classname
     * @param array $filters
     * @return $this
     * @todo
     */
    public function controller($route, $classname, array $options = []) {
        die('unhandled');
        $reflection = new ReflectionClass($classname);

        $validMethods = Route::validMethods();

        $sep = $route === '/' ? '' : '/';

        foreach($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
        {
            foreach($validMethods as $valid)
            {
                if(stripos($method->name, $valid) === 0)
                {
                    $methodName = $this->camelCaseToDashed(substr($method->name, strlen($valid)));

                    $params = $this->buildControllerParameters($method);

                    if($methodName === self::DEFAULT_CONTROLLER_ROUTE)
                    {
                        $this->addRoute($valid, $route . $params, [$classname, $method->name], $options);
                    }

                    $this->addRoute($valid, $route . $sep . $methodName . $params, [$classname, $method->name], $options);
                    
                    break;
                }
            }
        }
        
        return $this;
    }

    /**
     * @param ReflectionMethod $method
     * @return string
     * @todo
     */
    private function buildControllerParameters(ReflectionMethod $method) {
        die('unhandled');
        $params = '';

        foreach($method->getParameters() as $param)
        {
            $params .= "/{" . $param->getName() . "}" . ($param->isOptional() ? '?' : '');
        }

        return $params;
    }

    /**
     * @param $string
     * @return string
     */
    private function camelCaseToDashed($string) {
        return strtolower(preg_replace('/([A-Z])/', '-$1', lcfirst($string)));
    }

    /**
     * @return RouteDataArray
     */
    public function getCollection() {
        return new RouteCollection($this->routeEndpoints, $this->staticRouteMap, $this->generateVariableRouteData($this->variableRouteMap, $this->routeEndpoints), $this->reverseRouteMap, $this->handlers);
    }

    /**
     * @return array
     */
    private function generateVariableRouteData($variableRouteMap, $routeEndpoints) {
        $variableRouteData = [];
        if (!empty($variableRouteMap)) {
            $chunkSize = $this->computeChunkSize(count($variableRouteMap));
            $chunks = array_chunk($variableRouteMap, $chunkSize, true);
            foreach ($chunks as $chunk) {
                $variableRouteData[] = $this->processChunk($chunk, $routeEndpoints);
            }
        }
        
        return $variableRouteData;
    }

    /**
     * @param $count
     * @return float
     */
    private function computeChunkSize($count) {
        $numParts = max(1, round($count / self::APPROX_CHUNK_SIZE));
        return ceil($count / $numParts);
    }

    /**
     * @param $regexToRoutesMap
     * @return array
     */
    private function processChunk($variableRouteMap, $routeEndpoints) {
        $matchMap = [];
        $regexes = [];
        $numGroups = 0;
        $routeStore = [];
        $routeIndex = 0;
        
        foreach ($variableRouteMap as $regex => $routeMethods) {
            $firstMethod   = reset($routeMethods);
            $firstEndpoint = $routeEndpoints[$firstMethod[0]];
            $numParameters = count($firstEndpoint->getRouteParameters());
            $numGroups     = max($numGroups, $numParameters);
            
            $regex .= str_repeat('()', $numGroups - $numParameters);
            
            $regexes[] = $regex;

            $matchMap[$numGroups++] = $routeIndex;
            $routeStore[$routeIndex++] = [
                self::REGEX => $regex,
                self::METHODS  => $routeMethods
            ];

        }

        $regex = '~^(?|' . implode('|', $regexes) . ')$~';
        return [self::REGEX => $regex, self::MAP => $matchMap, self::ROUTES => $routeStore];
    }
}
