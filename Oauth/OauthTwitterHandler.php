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
require "vendor/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;
class OauthTwitterHandler {
    public function MakeRequest(string $callbackURL):void {
        $ini = parse_ini_file("config.ini", true);
        $creds = $ini["twitter"];
        $connection = new TwitterOAuth($creds["apikey"], $creds["apisecret"]);
        $request_token = $connection->oauth("oauth/request_token", ["oauth_callback" => $callbackURL]);
        if($connection->getLastHttpCode() != 200) {
            throw new Exception("There was a problem redirecting to Twitter.");
        }
        $db = new BeeDB("auth");
        $db->ExecuteNonQuery("INSERT INTO apitoken (type, token, secret, created) VALUES ('twitter', :t, :s, NOW())", [
            "t" => $request_token["oauth_token"],
            "s" => $request_token["oauth_token_secret"]
        ]);
        $url = $connection->url("oauth/authenticate", ["oauth_token" => $request_token["oauth_token"]]);
        header("Location: $url");
    }
    public function Validate(string $token, string $secret, string $verifier):BeeOAuthAccountInfo {
        $ini = parse_ini_file("config.ini", true);
        $creds = $ini["twitter"];
        $connection = new TwitterOAuth($creds["apikey"], $creds["apisecret"], $token, $secret);
        $tokenInfo = $connection->oauth("oauth/access_token", ["oauth_verifier" => $verifier]);
        $response = new BeeOAuthAccountInfo();
        if($connection->getLastHttpCode() === 200) {
            $response->source = "twitter";
            $response->extID = $tokenInfo["user_id"];
            $response->extSecondaryID = $tokenInfo["screen_name"];
            $response->accesstoken = $tokenInfo["oauth_token"];
            $response->accesstokensecret = $tokenInfo["oauth_token_secret"];
        } else {
            throw new BeeException("Something nopey happened!");
        }
        return $response;
    }
}
?>