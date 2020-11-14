<?php declare(strict_types=1);
class BeeAuthException extends Exception {}
class BeeAuth {
    private string $secret;
    public function __construct() {
        $ini = parse_ini_file("config.ini", true);
        $this->secret = $ini["auth"]["secret"];
    }
    public function Register(BeeCredentials $creds):BeeUserToken {
        $db = new BeeDB("auth");
        $userInfo = $db->GetDataRow("SELECT id FROM users WHERE username = :u", ["u" => $creds->username]);
        if($userInfo !== null) { throw new BeeAuthException("A user with this email address aleady exists."); }
        $pwd = password_hash($creds->password, PASSWORD_BCRYPT);
        $userID = $db->InsertAndReturnID("INSERT INTO users (username, password, rolebits) VALUES (:u, :p, 0)", ["u" => $creds->username, "p" => $pwd]);
        return new BeeUserToken($userID, 0);
    }
    public function RegisterLoginOAuth(BeeOAuthAccountInfo $info):BeeUserToken {
        $db = new BeeDB("auth");
        $beeID = $db->GetInt("SELECT id FROM users WHERE source = :s AND username = :i", ["i" => $info->extID, "s" => $info->source]);
        $rolebits = 0;
        if($beeID === 0) { // new user
            $beeID = $db->InsertAndReturnID("INSERT INTO users (username, rolebits, source, accesstoken, accesstokensecret, externalname) VALUES (:i, 0, :s, :t, :ts, :sn)", [
                "i" => $info->extID,
                "t" => $info->accesstoken,
                "ts" => $info->accesstokensecret,
                "sn" => $info->extSecondaryID,
                "s" => $info->source
            ]);
        } else { // existing user
            $db->ExecuteNonQuery("UPDATE users SET accesstoken = :t, accesstokensecret = :ts, externalname = :sn WHERE id = $beeID", [
                "t" => $info->accesstoken,
                "ts" => $info->accesstokensecret,
                "sn" => $info->extSecondaryID
            ]);
            $rolebits = $db->GetInt("SELECT rolebits FROM users WHERE id = $beeID");
        }
        return new BeeUserToken($beeID, $rolebits);
    }
    public function Login(BeeCredentials $creds):BeeUserToken {
        $db = new BeeDB("auth");
        $userInfo = $db->GetDataRow("SELECT password, rolebits, id FROM users WHERE username = :u", ["u" => $creds->username]);
        if($userInfo === null) { throw new BeeAuthException("User not found."); }
        if(!password_verify($creds->password, $userInfo["password"])) {
            // TODO: failed login attempts bullshit
            throw new BeeAuthException("Incorrect password.");
        }
        return new BeeUserToken(intval($userInfo["id"]), intval($userInfo["rolebits"]));
    }
    public function ResetPassword(int $userID, BeePasswordChange $pwd) {
        $db = new BeeDB("auth");
        $oldPassword = $db->GetString("SELECT password FROM users WHERE id = :i", ["i" => $userID]);
        if($oldPassword === null || $oldPassword === "") { throw new BeeAuthException("User $userID not found."); }
        if(!password_verify($pwd->oldPassword, $oldPassword)) { throw new BeeAuthException("Incorrect password."); }
        $newPassword = password_hash($pwd->newPassword, PASSWORD_BCRYPT);
        $db->ExecuteNonQuery("UPDATE users SET password = :p WHERE id = :i", ["i" => $userID, "p" => $newPassword]);
    }

    public function GetToken(string $type, string $token = ""):BeeToken {
        $auth = $token === "" ? $this->GetAuthHeader() : "Bearer $token";
        if($auth === "") { throw new Exception("No auth header."); }
        if(preg_match("/Bearer\s((.*)\.(.*)\.(.*))/", $auth, $matches)) {
            $token = $matches[1];
            $resp = $this->ValidateJWTToken($type, $token);
            if(!$resp->valid) { throw new Exception("Invalid auth header."); }
            if(!$resp->expired) { throw new Exception("Expired auth header."); }
            return $resp->token;
        } else { throw new Exception("No auth bearer."); }
    }
    public function GenerateJWTToken(BeeToken $data):string {
        $header = json_encode(["typ" => "JWT", "alg" => "HS256"]);
        $payload = json_encode($data);
        $base64UrlHeader = $this->Base64UrlEncode($header);
        $base64UrlPayload = $this->Base64UrlEncode($payload);
        $signature = hash_hmac("sha256", "$base64UrlHeader.$base64UrlPayload", $this->secret, true);
        $base64UrlSignature = $this->Base64UrlEncode($signature);
        return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
    }

    private function GetAuthHeader():string {
        if(isset($_SERVER["Authorization"])) { return trim($_SERVER["Authorization"]); }
        if(isset($_SERVER["HTTP_AUTHORIZATION"])) { return trim($_SERVER["HTTP_AUTHORIZATION"]); }
        return "";
    }

    private function Base64URLEncode(string $t):string { return str_replace(["+", "/", "="], ["-", "_", ""], base64_encode($t)); }
    private function ValidateJWTToken(string $tokenClassName, string $token):BeeParsedToken {
        $tokenParts = explode(".", $token);
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];
        $payloadObj = json_decode($payload, true);

        $base64UrlHeader = $this->Base64UrlEncode($header);
        $base64UrlPayload = $this->Base64UrlEncode($payload);
        $signature = hash_hmac("sha256", "$base64UrlHeader.$base64UrlPayload", $this->secret, true);
        $base64UrlSignature = $this->Base64UrlEncode($signature);
        
        $token = AssArrayToObject($tokenClassName, $payloadObj, false);
        $response = new BeeParsedToken($token);
        $response->valid = ($base64UrlSignature === $signatureProvided);
        $response->expired = ((time() - $payloadObj->created) > 28800); // 8 hours
        return $response;
    }
}
?>