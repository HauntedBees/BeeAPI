<?php declare(strict_types=1);
/**
 * Bee API
 * @copyright 2023 Haunted Bees Productions
 * @author Sean Finch <fench@hauntedbees.com>
 * @license https://www.gnu.org/licenses/agpl-3.0.en.html GNU Affero General Public License
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * @see https://github.com/HauntedBees/BeeAPI
 */

#[Attribute]
class AuthRequired {
    public function __construct(
        public int $role = 0
    ) {}
}

#[Attribute]
class HttpMethod {
    public function __construct(
        public string $type,
        public string $route
    ) {}
}
 
#[Attribute]
class Get extends HttpMethod { public function __construct(string $route) { parent::__construct("GET", $route); } }

#[Attribute]
class Post extends HttpMethod { public function __construct(string $route) { parent::__construct("POST", $route); } }

class BeeAttributesEnforcer {
    public static function IsRouteMatch(ReflectionMethod $m, string $requestType, string $route): bool {
        $attrs = $m->getAttributes("HttpMethod", ReflectionAttribute::IS_INSTANCEOF);
        if(count($attrs) === 0) {
            return false;
        }
        $httpMethod = strtoupper($attrs[0]->getName());
        $methodRoute = $attrs[0]->getArguments()[0];
        return $requestType === $httpMethod && $methodRoute === $route;
    }
    
    public static function ValidateAuth(ReflectionMethod|ReflectionClass $reflect, BeeController $bc): void {
        $attrs = $reflect->getAttributes("AuthRequired", ReflectionAttribute::IS_INSTANCEOF);
        if(count($attrs) === 0) {
            return;
        }
        if(!isset($bc->userInfo)) {
            throw new BeeAuthException("You must be logged in to access this feature.");
        }
        /** @var AuthRequired $attr */
        $attr = $attrs[0];
        if(!isset($attr->role) || $attr->role === 0) {
            return;
        }
        if(($attr->role & $bc->userInfo->role) === 0) {
            throw new BeeForbiddenException("You don't have access to this feature.");
        }
    }

    public static function ValidateMethod(ReflectionMethod $m, array $args): void {
        $attrs = $m->getAttributes("BeeAttribute", ReflectionAttribute::IS_INSTANCEOF);
        foreach($attrs as $attr) {
            $obj = $attr->newInstance();
            $params = $m->getParameters();
            $found = false;
            for($i = 0; $i < count($params); $i++) {
                if($params[$i]->name == $obj->argumentName) {
                    $obj->Validate($args[$i]);
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                throw new BeeException("Argument $obj->argumentName not found");
            }
        }
    }
    
    public static function ValidateProperty(ReflectionProperty $p, mixed $value): void {
        $attrs = $p->getAttributes("BeeAttribute", ReflectionAttribute::IS_INSTANCEOF);
        foreach($attrs as $attr) {
            $obj = $attr->newInstance();
            $obj->argumentName = $p->getName();
            $obj->Validate($value);
        }
    }
}

#[Attribute]
class TableName {
    public function __construct(
        public string $tableName = ""
    ) {}
}

#[Attribute]
class NotMapped {}

abstract class BeeAttribute {
    public function __construct(
        public string $argumentName = ""
    ) {}
    abstract function Validate(mixed $arg);
}

#[Attribute]
class Required extends BeeAttribute {
    public function Validate(mixed $arg) {
        if($arg === null || $arg === "" || $arg === 0) {
            throw new BeeException("$this->argumentName is required");
        }
    }
}

#[Attribute]
class MaxLength extends BeeAttribute {
    public function __construct(
        public string $argumentName = "",
        public int $maxLength = 100,
        public int $minLength = 0
    ) {}
    public function Validate(mixed $arg) {
        if(!is_string($arg)) {
            throw new BeeException("String expected");
        }
        $length = strlen($arg);
        if($length < $this->minLength || $length > $this->maxLength) {
            throw new BeeException("$this->argumentName is not between the length of $this->minLength and $this->maxLength characters");
        }
    }
}

#[Attribute]
class IntArray extends BeeAttribute {
    public function Validate(mixed $arg) {
        if(!is_array($arg)) {
            throw new BeeException("Array expected");
        }
		foreach($arg as $v) {
			if(!is_int($v)) {
                throw new BeeException("Value '$v' found in a list of numbers");
            }
		}
    }
}

?>