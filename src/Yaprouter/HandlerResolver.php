<?php namespace Yaprouter\Yaprouter;

class HandlerResolver implements HandlerResolverInterface {
	
	private $container = [];
	
	public function __construct($container = null) {
		
		if (!empty($container)) {
			if (!(is_array($container) || ($container instanceof \ArrayAccess)))
				throw new \InvalidArgumentException('Internal HandlerResolver Container must be of type Array or implement \ArrayAccess');
			$this->container = $container;
		}
		
	}
	
	/**
	 * Create an instance of the given handler.
	 *
	 * @param $handler
	 * @return array
	 */
	public function resolve($handler) {
		if (is_string($handler)) {
			if (isset($this->container[$handler]))
				$handler = $this->container[$handler];
			elseif (class_exists($handler))
				$handler = new $handler;
		} elseif(is_array($handler)) {
			if (is_string($handler[0])) {
				$class = $handler[0];
				$parameters = (array) (isset($handler[1]) ? $handler[1] : null);
				$handler = new $class(...$parameters);
			}
		}
		
		return $handler;
	}
	
}
