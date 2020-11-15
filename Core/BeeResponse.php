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
class BeeResponse {
	public bool $addSessionExpiredMessage = false;
	public function __construct() {
		$origin = $_SERVER["HTTP_ORIGIN"];
		header("Access-Control-Allow-Origin: $origin");
		header("Access-Control-Allow-Credentials: true");
		header("Access-Control-Allow-Headers: Authorization");
		header("Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS");
		header("Content-Type: application/json");
    }
    private function Response($data):void {
		if($this->addSessionExpiredMessage) { 
			$data["sessionexpired"] = true;
		}
		echo json_encode($data);
	}

	public function OK($data):void {
		header("HTTP/1.0 200 OK", true, 200);
        $this->response(["success" => true, "result" => $data]);
	}
	public function PageSet(array $tbl, int $count) {
		header("HTTP/1.0 200 OK", true, 200);
        $this->response(["success" => true, "result" => $tbl, "count" => $count]);
	}
	public function Custom(array $data):void {
		header("HTTP/1.0 200 OK", true, 200);
        $data["success"] = true;
        $this->Response($data);
    }
	public function Message(string $message):void {
		header("HTTP/1.0 200 OK", true, 200);
        $this->Response(["success" => true, "message" => $message]);
    }
    
	public function Unauthorized(string $message):void { $this->ErrorGeneral("401 Unauthorized", 401, $message); }
	public function Forbidden(string $message):void { $this->ErrorGeneral("403 Forbidden", 403, $message); }
	public function NotFound(string $message):void { $this->ErrorGeneral("404 Not Found", 404, $message); }
	public function Exception(Exception $e):void { $this->ErrorGeneral("500 Internal Server Error", 500, $e->getMessage()); }
	public function Error(string $message):void { $this->ErrorGeneral("500 Internal Server Error", 500, $message); }
	public function ErrorGeneral(string $header, int $code, string $message):void {
		header($header, true, $code);
        $this->Response(["success" => false, "message" => $message]);
	}
}