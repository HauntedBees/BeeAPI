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
const CACHE_PATH = "../../protected/beecache/";
class BeeCache {
	private int $duration = 60;
	private ?BeeDB $db = null;
	function __construct(int $cacheMinutes = 60, BeeDB $db = null) {
		$this->duration = $cacheMinutes;
		$this->db = $db;
	}

	public function GetDBObjects(string $key, string $type, string $sql, array $params = []):array {
		if($this->db === null) { throw new Exception("No database tied to cache."); }
		$fullkey = $key;
		foreach($params as $k=>$v) { $fullkey .= "_$k_$v"; }
		$cachedVal = $this->GetValue($fullkey);
		if($cachedVal === null) {
			$dbVal = $this->db->GetObjects($type, $sql, $params);
			$this->SetValue($fullkey, $dbVal);
			return $dbVal;
		} else {
			return $cachedVal;
		}
	}
	public function GetDBStrings(string $key, string $sql, array $params = []):array {
		if($this->db === null) { throw new Exception("No database tied to cache."); }
		$fullkey = $key;
		foreach($params as $k=>$v) { $fullkey .= "_$k_$v"; }
		$cachedVal = $this->GetValue($fullkey);
		if($cachedVal === null) {
			$dbVal = $this->db->GetStrings($sql, $params);
			$this->SetValue($fullkey, $dbVal);
			return $dbVal;
		} else {
			return $cachedVal;
		}
	}

	public function GetValue(string $key) {
		$path = $this->GetPath($key);
		if(file_exists($path) && (((time() - filemtime($path)) / 60) < $this->duration)) {
			return json_decode(file_get_contents($path), false);
		} else {
			return null;
		}
	}
	public function SetValue(string $key, $obj):void {
		file_put_contents($this->GetPath($key), json_encode($obj));
	}
	private function GetPath(string $key):string {
		$key = str_replace(" ", "", $key);
		if(strlen($key) > 100) { $key = substr($key, 0, 50)."__".substr($key, -50); }
		return CACHE_PATH.$key.".cache";
	}
}
?>