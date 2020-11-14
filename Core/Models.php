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
class BeeException extends Exception {}
class BeeCredentials {
    public string $username;
    public string $password;
}
class BeeOAuthResponse {
    public string $from;
    public string $token;
    public string $verifier;
}
class BeeOAuthAccountInfo {
    public string $extID;
    public string $extSecondaryID;
    public string $source;
    public string $accesstoken;
    public string $accesstokensecret;
}
class BeeToken {
    public int $created;
    public function __construct() { $this->created = time(); }
}
class BeeParsedToken {
    public bool $valid;
    public bool $expired;
    public BeeToken $token;
    public function __construct(BeeToken $token) { $this->token = $token; }
}
class BeeUserToken extends BeeToken {
    public int $id;
    public int $role;
    public ?array $misc;
    public function __construct(int $id, int $role) {
        parent::__construct();
        $this->id = $id;
        $this->role = $role;
    }
}
class BeeLookup {
    public int $id;
    public string $name;
}
class BeePasswordChange {
    public string $oldPassword;
    public string $newPassword;
}
?>