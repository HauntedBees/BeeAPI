<?php declare(strict_types=1);
require_once "BeeController.php";
class TestController extends BeeController {
	public function GetFail():void { $this->response->Error("Fail"); }
    public function GetTest():void { $this->response->OK(func_get_args()); }
    public function GetLogin(string $u, string $p) {
        $auth = new BeeAuth();
        $creds = new BeeCredentials();
        $creds->username = $u;
        $creds->password = $p;
        return $this->response->OK($auth->Login($creds));
    }
    public function GetVerification(string $token) {
        $auth = new BeeAuth();
        return $this->response->OK($auth->GetToken("BeeUserToken", $token));
    }
}
?>