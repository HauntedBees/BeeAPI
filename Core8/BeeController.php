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
class BeeController {
	public BeeDB $db;
	public ?BeeLogger $log;
    public int $authTime = 28800; // 8 hours
    public function __construct(
        string $db = "", 
        bool $useLogging = false, 
        public BeeResponse $response = new BeeResponse(),
        public ?BeeUserToken $userInfo = null
    ) {
        if($db !== "") {
		    $this->db = new BeeDB($db, $useLogging);
        }
		if($useLogging) {
            $this->log = new BeeLogger();
        }
	}

	protected function GetConfigInfo(string $group, string $key): string {
        $ini = parse_ini_file(CONFIG_PATH, true);
        return $ini[$group][$key];
	}
    
    public function SetAuthInfo(): void {
        $header = $this->GetAuthHeader();
        if($header === "") {
            return;
        }
        if(preg_match("/Bearer\s((.*)\.(.*)\.(.*))/", $header, $matches)) {
            $token = $matches[1];
            $tokenParts = explode(".", $token);
            $header = base64_decode($tokenParts[0]);
            $payload = base64_decode($tokenParts[1]);
            $signatureProvided = $tokenParts[2];
            $payloadObj = json_decode($payload, true);

            $base64UrlHeader = $this->Base64UrlEncode($header);
            $base64UrlPayload = $this->Base64UrlEncode($payload);
            $signature = hash_hmac("sha256", "$base64UrlHeader.$base64UrlPayload", $this->GetConfigInfo("auth", "secret"), true);
            $base64UrlSignature = $this->Base64UrlEncode($signature);

            $token = new BeeUserToken($payloadObj["id"], $payloadObj["role"]);
            if($base64UrlSignature !== $signatureProvided) {
                throw new BeeAuthException("Invalid authorization.");
            }
            if((time() - $token->created) > $this->authTime) {
                throw new BeeSessionExpiredException("Your session has expired. Please log in again.");
            }
            $this->userInfo = $token;
        }
    }
    private function GetAuthHeader(): string {
        if (isset($_SERVER["Authorization"])) {
            return trim($_SERVER["Authorization"]);
        }
        if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
            return trim($_SERVER["HTTP_AUTHORIZATION"]);
        }
        if (function_exists("apache_request_headers")) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map("ucwords", array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders["Authorization"])) {
                return trim($requestHeaders["Authorization"]);
            }
        }
        return "";
    }

    protected function Base64URLEncode(string $t): string {
        return str_replace(["+", "/", "="], ["-", "_", ""], base64_encode($t));
    }
}
?>