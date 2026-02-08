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

 class BeeCredentials extends RawBeeModel {
    public string $username;
    public string $password;
}
class BeeRegisterCredentials extends BeeCredentials {
   public string $inviteCode;
}
// remember str_contains exists
class AuthController extends BeeController {
    private string $secret;
    public function __construct() {
        parent::__construct("auth");
        $this->secret = $this->GetConfigInfo("auth", "secret");
    }
    #[Get("check")]
    public function IsLoggedIn(): mixed {
        return isset($this->userInfo);
    }
    #[Post("login")]
    public function Login(BeeCredentials $creds): string {
        $userInfo = $this->db->GetDataRow("SELECT password, rolebits, id FROM users WHERE username = :u", ["u" => $creds->username]);
        if($userInfo === null) {
            throw new BeeAuthException("User not found.");
        }
        if(!password_verify($creds->password, $userInfo["password"])) {
            // TODO: failed login attempts bullshit
            throw new BeeAuthException("Incorrect password.");
        }
        return $this->GenerateJWTToken(new BeeUserToken(intval($userInfo["id"]), intval($userInfo["rolebits"])));
    }
    #[Post("register")]
    public function Register(BeeRegisterCredentials $creds): string {
        if($creds->inviteCode != "this is the code hi") {
            throw new BeeException("Account Creation is invite-only right now, sorry. :(");
        }
        $userInfo = $this->db->GetDataRow("SELECT id FROM users WHERE username = :u", ["u" => $creds->username]);
        if($userInfo !== null) {
            throw new BeeAuthException("A user with this user name aleady exists.");
        }
        $pwd = password_hash($creds->password, PASSWORD_BCRYPT);
        $userID = $this->db->InsertAndReturnID("INSERT INTO users (username, password, rolebits) VALUES (:u, :p, 0)", ["u" => $creds->username, "p" => $pwd]);
        if($userID === 0) {
            throw new BeeException("Something went wrong creating your account. Please try again.");
        }
        return $this->GenerateJWTToken(new BeeUserToken($userID, 0));
    }
    
    private function GenerateJWTToken(BeeToken $data): string {
        $header = json_encode(["typ" => "JWT", "alg" => "HS256"]);
        $payload = json_encode($data);
        $base64UrlHeader = $this->Base64UrlEncode($header);
        $base64UrlPayload = $this->Base64UrlEncode($payload);
        $signature = hash_hmac("sha256", "$base64UrlHeader.$base64UrlPayload", $this->secret, true);
        $base64UrlSignature = $this->Base64UrlEncode($signature);
        return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
    }
}
?>