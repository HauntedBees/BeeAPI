<?php declare(strict_types=1);
/**
 * Bee API
 * @copyright 2020 Haunted Bees Productions
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
const CONFIG_PATH = "config.ini";
function AssArrayToObject(string $className, array $arr, bool $callConstructor = true) {
    $class = new ReflectionClass($className);
    $obj = ($callConstructor ? $class->newInstance() : $class->newInstanceWithoutConstructor());
    $props = $class->getProperties();
    foreach($props as $k=>$v) {
        $v->setValue($obj, $arr[$v->getName()]);
    }
    return $obj;
}
try {
    require_once "./Core/Module.php";
    spl_autoload_register(function($class) {
        #echo $class;
        $classParts = preg_split('/(?=[A-Z])/', $class);
        $firstPart = $classParts[1];
        require_once "$firstPart/Module.php";
    });

    $controllerName = $_GET["controller"];
    if(empty($controllerName)) { exit(); }

    $controllerName = $controllerName."Controller";
    $controllerClass = new ReflectionClass($controllerName);
    $controller = $controllerClass->newInstance();
    if(!is_subclass_of($controller, "BeeController")) { throw new Exception("Invalid controller."); }

    $methodName = $_GET["method"];
    if(empty($methodName)) { exit(); }

    $requestType = $_SERVER["REQUEST_METHOD"];
    $args = [];
    if($requestType === "POST") {
        $methodName = "Post".$methodName;
        $args = [json_decode(file_get_contents("php://input"), true)];
    } else if($requestType === "GET" || $requestType === "DELETE") {
        $methodName = ($requestType === "DELETE" ? "Delete" : "Get").$methodName;
        if(!empty($_GET["param"])) {
            $param = str_replace('/"', '\"', $_GET["param"]);
            $args = json_decode($param);
        }
    } else { throw new Exception("Unsupported request method."); }

    $method = new ReflectionMethod($controller, $methodName);
    foreach($args as $idx=>&$arg) {
        $isObject = is_array($arg) && array_keys($arg) !== range(0, count($arg) - 1);
        if($isObject) {
            $param = new ReflectionParameter([$controllerName, $methodName], $idx);
            $paramClassName = $param->getClass()->name;
            $arg = AssArrayToObject($paramClassName, $arg);
        }
    }
    $method->invokeArgs($controller, $args);
} catch(BeeException $e) {
    $r = new BeeResponse();
    $r->Error($e->getMessage());
/*} catch(ReflectionException $e) {
    $r = new BeeResponse();
    $r->Error("Invalid request.");
} catch(TypeError $e) {
    $r = new BeeResponse();
    $r->Error("Invalid request format.");
} catch(ArgumentCountError $e) {
    $r = new BeeResponse();
    $r->Error("Invalid parameter count.");*/
} catch(Throwable $e) {
    $r = new BeeResponse();
    $r->Error(get_class($e)."/".$e->getMessage());
}
?>