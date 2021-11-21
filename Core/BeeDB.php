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
class BeeDB {
	public PDO $pdo;

	public function __construct($db) {
        if($db === "") { return; }
        $ini = parse_ini_file(CONFIG_PATH, true);
        $creds = $ini["db_$db"];
        $this->pdo = new PDO($creds["dsn"], $creds["username"], $creds["password"]);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	}

	public function BeginTransaction():void { $this->pdo->beginTransaction(); }
	public function CommitTransaction():void { $this->pdo->commit(); }
    public function RollbackTransaction():void { $this->pdo->rollBack(); }

    public function GetDataTable(string $sql, array $params = []):array {
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        return $cmd->fetchAll();
    }
    public function GetDataRow(string $sql, array $params = []):?array {
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        $row = $cmd->fetch();
        return ($row === false ? null : $row);
    }
    public function ExecuteNonQuery(string $sql, array $params = []):void {
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
    }
    public function InsertAndReturnID(string $sql, array $params = []):int {
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        return intval($this->pdo->lastInsertId());
    }
    public function DoMultipleInsert(int $left, array $arr, string $queryStart):void {
        if(count($arr) === 0) { return; }
        $params = ["a" => $left];
        $queryParts = [];
        foreach($arr as $k=>$v) {
            $params["b$k"] = $v;
            $queryParts[] = "(:a, :b$k)";
        }
        $cmd = $this->pdo->prepare($queryStart.implode(", ", $queryParts));
        $cmd->execute($params);
    }
    public function DoMultipleInsertTwoPoint(int $left, int $left2, array $arr, string $queryStart) {
        if(count($arr) === 0) { return; }
        $params = ["a" => $left, "ab" => $left2];
        $queryParts = [];
        foreach($arr as $k=>$v) {
            $params["b$k"] = $v;
            $queryParts[] = "(:a, :ab, :b$k)";
        }
        $cmd = $this->pdo->prepare($queryStart.implode(", ", $queryParts));
        $cmd->execute($params);
    }

	public function GetObject(string $objClass, string $sql, array $params = []) {
		$cmd = $this->pdo->prepare($sql);
		$cmd->execute($params);
        $res = $cmd->fetchObject($objClass);
        return $res === false ? null : $res;
    }
    public function GetObjects(string $objClass, string $sql, array $params = []):array {
		$cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        return $cmd->fetchAll(PDO::FETCH_CLASS, $objClass);
    }
    public function GetString(string $sql, array $params = []):?string {
		$cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        return $cmd->fetch(PDO::FETCH_NUM)[0];
    }
    public function GetStrings(string $sql, array $params = []):array {
		$cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        $res = [];
        while($row = $cmd->fetch(PDO::FETCH_NUM)) {
            $res[] = $row[0];
        }
        return $res;
    }
    public function GetInt(string $sql, array $params = []):int {
		$cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        return intval($cmd->fetch(PDO::FETCH_NUM)[0]);
    }
    public function GetInts(string $sql, array $params = []):array {
		$cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        $res = [];
        while($row = $cmd->fetch(PDO::FETCH_NUM)) {
            $res[] = intval($row[0]);
        }
        return $res;
    }
    public function GetBool(string $sql, array $params = []):bool {
		$cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        $res = intval($cmd->fetch(PDO::FETCH_NUM)[0]);
        return $res > 0;
    }
    public function CreateInClause(array $arr, string $prefix = "l"):array {
        $params = [];
        $sql = [];
        foreach($arr as $k=>$v) {
            $params["$prefix$k"] = $v;
            $sql[] = ":$prefix$k";
        }
        return [
            "paramsObj" => $params,
            "inClause" => implode(", ", $sql)
        ];
    }

    public function ObjectUpdate(string $tableName, $obj, array $ignoreColumns = []):void {
        $sql = "UPDATE $tableName SET ";
        $bits = [];
        $params = [];
        $array = is_array($obj) ? $obj : get_object_vars($obj);
        foreach($array as $key => $value) {
            if($key === "id" || in_array($key, $ignoreColumns)) { continue; }
            if(is_bool($value)) { # PDO shits itself trying to insert into a BIT(1) column without this
                $bits[] = "$key = ".($value?1:0);
            } else {
                $bits[] = "$key = :$key";
                $params[$key] = is_string($value) ? trim($value) : $value;
            }
        }
        $sql .= implode(", ", $bits)." WHERE id = ".$array["id"];
        $this->ExecuteNonQuery($sql, $params);
    }
    public function ObjectInsert(string $tableName, $obj, array $ignoreColumns = []):int {
        $sql = "INSERT INTO $tableName (";
        $keyBits = [];
        $valueBits = [];
        $params = [];
        $array = is_array($obj) ? $obj : get_object_vars($obj);
        foreach($array as $key => $value) {
            if($key === "id" || in_array($key, $ignoreColumns)) { continue; }
            $keyBits[] = "$key";
            if(is_bool($value)) { # PDO shits itself trying to insert into a BIT(1) column without this
                $valueBits[] = ($value?1:0);
            } else {
                $valueBits[] = ":$key";
                $params[$key] = is_string($value) ? trim($value) : $value;
            }
        }
        $sql .= implode(", ", $keyBits).") VALUES (".implode(", ", $valueBits).")";
        return $this->InsertAndReturnID($sql, $params);
    } 
}