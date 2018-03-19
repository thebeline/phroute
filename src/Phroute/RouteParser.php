<?php namespace Phroute\Phroute;

use Phroute\Phroute\Exception\BadRouteException;
use Phroute\Phroute\Exception\BadValueException;
/**
 * Parses routes of the following form:
 *
 * "/user/{name}/{id:[0-9]+}?"
 */
class RouteParser {

    /**
     * Search through the given route looking for dynamic portions.
     *
     * Using ~ as the regex delimiter.
     *
     * We start by looking for a literal '{' character followed by any amount of whitespace.
     * The next portion inside the parentheses looks for a parameter name containing alphanumeric characters or underscore.
     *
     * After this we look for the ':\d+' and ':[0-9]+' style portion ending with a closing '}' character.
     *
     * Finally we look for an optional '?' which is used to signify an optional route.
     */
    const VARIABLE_REGEX = 
"~\{
    \s* ([a-zA-Z0-9_]*) \s*
    (?:
        : \s* ((?:[^{}]+(?:\{.*?\})?)*)
    )?
\}\??~x";

    /**
     * The default parameter character restriction (One or more characters that is not a '/').
     */
    const DEFAULT_DISPATCH_REGEX = '[^/]+';

    private $parts = [];

    private $reverseParts = [];
    
    private $partsCounter = 0;
    
    private $parameters = [];
    
    private $regexOffset = 0;
	
	const PART_IS_OBJECT   = 'is_object';
	const PART_NAME        = 'name';
	const PART_IS_OPTIONAL = 'optional';
	const PART_VALUE       = 'value';
	const PART_REGEX       = 'regex';
	
	//const PART_NAME = 'name';

    /**
     * Handy parameter type restrictions.
     *
     * @var array
     */
    private static $regexShortcuts = [
        ':i'  => '[0-9]+',
		':a'  => '[0-9A-Za-z]+',
		':h'  => '[0-9A-Fa-f]+',
        ':c'  => '[a-zA-Z0-9+_\-\.]+',
		':o'  => '[a-zA-Z0-9_-]+', // Object Encoding, register
    ];

    /**
     * Parse a route returning the correct data format to pass to the dispatch engine.
     *
     * @param $route
     * @return array
     */
    public static function parseRouteString($route_string) {
		$parser = new self();
		
		return $parser->parse($route_string);
	}
		
	public function parse($route_string) {
		
        if ($matches = self::extractVariableRouteParts($route_string)) {
			
			$this->parseMatches($matches, $route_string);
			
			$route_string  = implode('', $this->parts);
			
		} else {
            $this->reverseParts = [[
                self::PART_VALUE => $route_string
            ]];
		}

        return new ParsedRouteData($route_string, $this->parameters, array_values($this->reverseParts));
    }
	
	private function parseMatches(array $matches, $route_string) {
		
		foreach ($matches as $set) {
			
			$this->staticParts($route_string, $set[0][1]);
			
			$part = self::newPart($set);
			
			$this->regexOffset = $set[0][1] + strlen($set[0][0]);

			$match = '(' . $part[self::PART_REGEX] . ')';

			$this->addParameter($part[self::PART_NAME]);
			$this->addPart($part, $match);
		}
		
		$this->staticParts($route_string, strlen($route_string));
		
	}
	
	protected static function newPart(array $set) {
		$regex = isset($set[2]) ? trim($set[2][0]) : self::DEFAULT_DISPATCH_REGEX;
		
		return [
			self::PART_NAME        => $set[1][0],
			self::PART_REGEX       => (isset(self::$regexShortcuts[$regex]) ? self::$regexShortcuts[$regex] : $regex),
			self::PART_IS_OPTIONAL => (substr($set[0][0], -1) === '?'),
			self::PART_IS_OBJECT   => ($regex === ':o')
		];
	
	}
	
	public static function buildUri(array $reverseParts, $parameters) {
		
		$uri = [];
		$i = 0;

		foreach($reverseParts as $part) {
			
			if (empty($part[self::PART_NAME])) {
				$uri[$i] = $part[self::PART_VALUE];
			} else {
				$name = $part[self::PART_NAME];
				
				$value = isset($parameters[$name]) ? $parameters[$name] : null;
				
				$value = self::validateRouteParameter($value, $part);
				
				if (!empty($value))
					$uri[$i] = $value;
				elseif (isset($uri[--$i]) && $uri[$i] === '/')
					unset($uri[$i]);
			}
			$i++;
		}
		
		return Route::trim(implode('', $uri));
	}

    /**
     * Return any variable route portions from the given route.
     *
     * @param $route
     * @return mixed
     */
    private static function extractVariableRouteParts($route) {
        if(preg_match_all(self::VARIABLE_REGEX, $route, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER))
            return $matches;
    }

    /**
     * @param $route
     * @param $nextOffset
     */
    private function staticParts($route, $nextOffset) {
        $static = preg_split('~(/)~u', substr($route, $this->regexOffset, $nextOffset - $this->regexOffset), 0, PREG_SPLIT_DELIM_CAPTURE);

        foreach($static as $staticPart) {
            if($staticPart) {
				$this->addPart([
                    self::PART_VALUE => $staticPart
                ], self::quote($staticPart));
            }
        }
    }
	
	private function addPart(array $part, $partString) {
		$this->reverseParts[$this->partsCounter] = $part;
		if (!empty($part[self::PART_IS_OPTIONAL])) {
			$prev = $this->partsCounter-1;
			$previous_value = isset($this->reverseParts[$prev][self::PART_VALUE]) ? $this->reverseParts[$prev][self::PART_VALUE] : '';
			if ($previous_value == '/') {
				unset($this->parts[$prev]);
				$partString = '(?:/'.$partString.')';
			}
			$partString .= '?';
		}
		$this->parts[$this->partsCounter] = $partString;
		$this->partsCounter++;
	}
	
    /**
     * @param $varName
     */
    private function addParameter($varName) {
        if (isset($this->parameters[$varName])) {
            throw new BadRouteException("Cannot use the same placeholder '$varName' twice");
        }
        $this->parameters[$varName] = $this->partsCounter;
    }

    /**
     * @param $part
     * @return string
     */
    private static function quote($part) {
        return preg_quote($part, '~');
    }
	
	public static function encodeRouteParameters($parameters, $parts) {
		foreach ($parts as $name => $meta) {
			if (isset($parameters[$name])) { 
				if (!empty($meta[self::PART_IS_OBJECT]))
					$parameters[$name] = self::encodeObject($parameters[$name]);
				//elseif (!empty($meta[self::PART_IS_ENCODED]))
			}
		}
		return $parameters;
	}
	
	public static function decodeRouteParameters($parameters, $parts) {
		foreach ($parts as $name => $meta) {
			if (isset($parameters[$name])) { 
				if (!empty($meta[self::PART_IS_OBJECT]))
					$parameters[$name] = self::decodeObject($parameters[$name]);
				//elseif (!empty($meta[self::PART_IS_ENCODED]))
			}
		}
		return $parameters;
	}
	
	public static function encodeObject($value) {
		return strtr(rtrim(base64_encode(gzdeflate(json_encode($value), 9, ZLIB_ENCODING_RAW)),"="), '+/', '-_');
	}
	
	public static function decodeObject($string) {
		if (is_string($string)) {
			$hold = $string;
			$string = base64_decode(strtr($string, '-_', '+/'), true);
			if (isset($string))
				$string = gzinflate($string);
			if (!empty($string)) {
				$value = json_decode($string, true);
				if (is_null($value) && ($string !== 'null'))
					$value = $hold;
			}
			
		}
		return $value;
	}
	
	public static function validateRouteParameter($value, array $part) {
		if (isset($part[self::PART_REGEX])) {
			
			if (!(is_scalar($value) || is_null($value)))
				throw new BadValueException("Route Variable must be of scalar type.");
		
			$value = (string) $value;
			
			if ($value !== '') {
				$regex = $part[self::PART_REGEX];
			
				if (!preg_match('~^'.$regex.'$~', $value))
					throw new BadValueException("The Variable Value '$value' does not match the variable regex '$regex'.");
				
			} elseif (empty($part[self::PART_IS_OPTIONAL]))
				throw new BadValueException("Route Variable is not optional.");
			
		}
		
		return $value;
	}
}
