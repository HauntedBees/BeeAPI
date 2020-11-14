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
const BEEROLE_ADMIN = 1;
class BeeSecureController extends BeeController {
    protected BeeUserToken $token;
    public function __construct(string $db = "", int $enforcedRole = 0) {
        $auth = new BeeAuth();
        try {
            $this->token = $auth->GetToken("BeeUserToken");
        } catch(Exception $ex) {
            // TODO: maybe handle expired tokens and shit
            throw $ex;
        }
        if($enforcedRole > 0 && !$this->HasRole($enforcedRole)) { throw new Exception("Access denied."); } // todo: this but better
        parent::__construct($db);
    }
    protected function HasRole(int $roleBit):bool { return ($this->token->role & $roleBit) > 0; }
}
?>