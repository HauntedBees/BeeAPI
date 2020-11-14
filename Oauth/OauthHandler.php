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
class OauthHandler {
    public function MakeRequest(string $type, string $callbackURL) {
        if($type === "twitter") {
            $r = new OauthTwitterHandler();
            $r->MakeRequest($callbackURL);
        } else {
            throw new BeeException("Invalid OAuth Provider");
        }
    }
    public function HandleResponse(BeeOAuthResponse $response):BeeOAuthAccountInfo {
        $db = new BeeDB("auth");
        $info = $db->GetDataRow("SELECT type, secret FROM apitoken WHERE token = :t AND created > DATE_SUB(NOW(), INTERVAL 5 MINUTE)", ["t" => $response->token]);
        if($info === null) { throw new BeeException("Invalid OAuth Token"); }
        $db->ExecuteNonQuery("DELETE FROM apitoken WHERE token = :t", ["t" => $response->token]);
        $type = $info["type"];
        if($type === "twitter") {
            $r = new OauthTwitterHandler();
            return $r->Validate($response->token, $info["secret"], $response->verifier);
        } else {
            throw new BeeException("Invalid OAuth Provider");
        }
    }
}
?>