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
require_once "BeeResponse.php";
require_once "BeeDB.php";
require_once "BeeLogger.php";
class BeeController {
	public BeeDB $db;
	public BeeLogger $log;
	public BeeResponse $response;
    public function __construct(string $db = "", bool $useLogging = false) {
		$this->response = new BeeResponse();
		$this->db = new BeeDB($db, $useLogging);
		if($useLogging) { $this->log = new BeeLogger(); }
	}
	protected function AssertRequired(string $str) { if(strlen(trim($str)) === 0) { throw new Exception("Required value not provided."); } }
	protected function AssertLength(string $str, int $len) { if(strlen($str) > $len) { throw new Exception("Value '$str' exceeds maximum length of $len"); } }
	protected function AssertIntArray(array $arr) {
		foreach($arr as $k=>$v) {
			if(!is_int($v)) { throw new Exception("Value '$v' found in a list of numbers."); }
		}
	}
	protected function GetConfigInfo(string $group, string $key):string {
        $ini = parse_ini_file(CONFIG_PATH, true);
        return $ini[$group][$key];
	}
}
?>