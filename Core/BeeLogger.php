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
require_once "BeeDB.php";
class BeeLogger {
	public BeeDB $db;
	public function __construct() { $this->db = new BeeDB("logging"); }
    public function LogError(string $msg):void { $this->InnerLog("errorlog", $msg); }
    public function LogInfo(string $msg):void { $this->InnerLog("info", $msg); }
    private function InnerLog(string $table, string $msg):void {
        $now = new DateTime();
        $res = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $this->db->ObjectInsert($table, [
            "logtime" => $now->getTimestamp(),
            "sourceclass" => $res[1]["class"],
            "sourcefunc" => $res[1]["function"],
            "log" => $msg
        ]);
    }
}
?>