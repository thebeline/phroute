<?php
namespace Yaprouter\Yaprouter;

/**
 * Interface RouteCollectorInterface
 * @package Yaprouter\Yaprouter
 */
interface RouteCollectorInterface {

    /**
     * @param $httpMethod
     * @param $route
     * @param $handler
     * @param array $filters
     * @return $this
     */
    public function addRoute($httpMethod, $route, $handler, array $filters = []);

    /**
     * @param array $filters
     * @param \Closure $callback
     */
    public function group(array $filters, \Closure $callback);


    /**
     * @param $name
     * @param $handler
     */
    public function filter($name, $handler);

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function get($route, $handler, array $filters = []);

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function head($route, $handler, array $filters = []);

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function post($route, $handler, array $filters = []);

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function put($route, $handler, array $filters = []);

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function patch($route, $handler, array $filters = []);

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function delete($route, $handler, array $filters = []);

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function options($route, $handler, array $filters = []);

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function any($route, $handler, array $filters = []);

    /**
     * @param $route
     * @param $classname
     * @param array $filters
     * @return $this
     */
    public function controller($route, $classname, array $filters = []);

}
