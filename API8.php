<?php declare(strict_types=1);

require_once "./Core8/Module.php";

const CONFIG_PATH = "../../protected/config.ini";
//const CONFIG_PATH = "config.ini";
const DATE_FORMAT = "Y-m-d H:i:s";

function DoAPIRouting() {
    $module = $_GET["module"];
    $controller = $_GET["controller"];
    $route = $_GET["route"];
    $requestType = strtoupper($_SERVER["REQUEST_METHOD"]);
    
    $controllerClassName = ucfirst($controller)."Controller";
    $controllerPath = "$module/$controllerClassName.php";
    
    try {
        if(!file_exists($controllerPath)) {
            BeeResponse::NewError("No controller $controllerPath exists");
            exit;
        }
        require_once($controllerPath);
        if(!class_exists($controllerClassName)) {
            BeeResponse::NewError("$controllerClassName does not exist");
        }
        $reflection = new ReflectionClass($controllerClassName);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach($methods as $method) {
            if(BeeAttributesEnforcer::IsRouteMatch($method, $requestType, $route)) {
                /** @var BeeController $obj */
                $obj = new $controllerClassName();
                $obj->SetAuthInfo();

                $args = [];
                if($requestType === "POST") {
                    $params = file_get_contents("php://input");
                    if($params !== false) {
                        $methodParams = $method->getParameters();
                        if(count($methodParams) > 0) {
                            $paramType = $methodParams[0]->getType()->getName();
                            $paramObj = new $paramType();
                            $paramObj->MapFromJSON(json_decode(file_get_contents("php://input"), true));
                            if($paramObj instanceof AuthBeeModel) {
                                $paramObj->SetUserId($obj->userInfo->id);
                            }
                            $args = [$paramObj];
                        }
                    }
                } else if(!empty($_GET["param"])) {
                    $param = str_replace('/"', '\"', $_GET["param"]);
                    if($param[0] === "[") {
                        $args = json_decode($param);
                    } else {
                        $args = explode("/", $param);
                    }
                }
                BeeAttributesEnforcer::ValidateMethod($method, $args);

                BeeAttributesEnforcer::ValidateAuth($reflection, $obj);
                BeeAttributesEnforcer::ValidateAuth($method, $obj);

                $res = $method->invokeArgs($obj, $args);
                BeeResponse::NewOK($res);
                exit;
            }
        }   
        BeeResponse::NewError("No $requestType route for $route exists on $controllerClassName");
    } catch(BeeForbiddenException $e) {
        BeeResponse::NewForbidden($e->getMessage());
    } catch(BeeAuthException $e) {
        BeeResponse::NewUnauthorized($e->getMessage());
    } catch(BeeSessionExpiredException $e) {
        BeeResponse::NewUnauthorized($e->getMessage());
    } catch(BeeException $e) {
        BeeResponse::NewError("(BEE):".$e->getMessage());
    } catch(ReflectionException $e) {
        // BeeResponse::NewError("Invalid Request");
        BeeResponse::NewError(get_class($e)."(IR): ".$e->getMessage());
    } catch(TypeError $e) {
        BeeResponse::NewError(get_class($e)."(IRF): ".$e->getMessage());
        // BeeResponse::NewError("Invalid Request Format");
    } catch(ArgumentCountError $e) {
        BeeResponse::NewError(get_class($e)."(IPC): ".$e->getMessage());
        // BeeResponse::NewError("Invalid Parameter Count");
    } catch(Throwable $e) {
        BeeResponse::NewError(get_class($e).": ".$e->getMessage()."-".$e->getFile().":".$e->getLine());
        // BeeResponse::NewError($e->getMessage());
    }
}

DoAPIRouting();
