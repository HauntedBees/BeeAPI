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
class BeeDB {
	private PDO $pdo;
    private ?BeeLogger $log = null;

	public function __construct(string $db, bool $debug = false) {
        if($db === "") {
            throw new BeeException("No database selected");
        }
        $ini = parse_ini_file(CONFIG_PATH, true);
        $creds = $ini["db_$db"];
        $this->pdo = new PDO($creds["dsn"], $creds["username"], $creds["password"]);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if($debug) {
            $this->log = new BeeLogger();
        }
	}

	public function BeginTransaction(): void { $this->pdo->beginTransaction(); }
	public function CommitTransaction(): void { $this->pdo->commit(); }
    public function RollbackTransaction(): void {
        try {
            $this->pdo->rollBack();
        } catch(Exception $e) {
            return;
        }
    }

    public function GetDataTable(string $sql, array $params = []): array {
        $this->TryLog($sql, $params);
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        return $cmd->fetchAll();
    }
    public function GetDataRow(string $sql, array $params = []): ?array {
        $this->TryLog($sql, $params);
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        $row = $cmd->fetch();
        return ($row === false ? null : $row);
    }
    public function ExecuteNonQuery(string $sql, array $params = []): void {
        $this->TryLog($sql, $params);
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
    }
    public function InsertAndReturnID(string $sql, array $params = []): int {
        $this->TryLog($sql, $params);
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        return intval($this->pdo->lastInsertId());
    }
    public function DoMultipleInsert(int $left, array $arr, string $queryStart): void {
        if(count($arr) === 0) {
            return;
        }
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
        if(count($arr) === 0) {
            return;
        }
        $params = ["a" => $left, "ab" => $left2];
        $queryParts = [];
        foreach($arr as $k=>$v) {
            $params["b$k"] = $v;
            $queryParts[] = "(:a, :ab, :b$k)";
        }
        $cmd = $this->pdo->prepare($queryStart.implode(", ", $queryParts));
        $cmd->execute($params);
    }

    public function GetTableName(ReflectionClass $reflection): string {
        $attributes = $reflection->getAttributes("TableName");
        $className = $reflection->getName();
        if(count($attributes) !== 1) {
            throw new Exception("Table Name not set for class $className.");
        }
        return $attributes[0]->getArguments()[0];
    }
    public function GetPropertyName(ReflectionProperty $p): string {
        $attributes = $p->getAttributes("ColumnName");
        if(count($attributes) !== 1) {
            return $p->getName();
        }
        return $attributes[0]->getArguments()[0];
    }
    public function GetPropertyNamesArray(ReflectionClass $reflection): array {
        $props = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        return array_map(function ($p) { return $p->getName(); }, $props);
    }

    public function PostBeeModel(BeeModel $obj, bool $includeCreatedAndModified = true): int {
        $reflection = new ReflectionClass($obj::class);
        $tableName = $this->GetTableName($reflection);
        if($obj instanceof AuthBeeModel && $obj->id !== 0) {
            $user = $obj->GetUserId();
            $count = $this->GetInt("SELECT COUNT(*) FROM $tableName WHERE id = :id AND user = :user", ["id" => $obj->id, "user" => $user]);
            if($count !== 1) {
                throw new Exception("User $user tried to modify $tableName record $obj->id, which is not theirs to access");
            }
        }
        $id = 0;
        $props = array_filter($reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED), function(ReflectionProperty $p) {
            $attrs = $p->getAttributes("NotMapped");
            return count($attrs) === 0;
        });
        //$propNames = array_map(function (ReflectionProperty $p) { return $p->getName() }, $props);
        if(isset($obj->id) && $obj->id !== 0) {
            $id = $obj->id;
            $this->BeeModelUpdate($tableName, $obj, $props, $includeCreatedAndModified);
        } else {
            $id = $this->BeeModelInsert($tableName, $obj, $props, $includeCreatedAndModified);
        }
        return $id;
    }
    private function BeeModelUpdate(string $tableName, BeeModel $obj, array $properties, bool $includeCreatedAndModified): void {
        $sql = "UPDATE $tableName SET ";
        $bits = [];
        $params = [];
        foreach($properties as $prop) { // ReflectionProperty
            $propInternalName = $prop->getName();
            if($propInternalName === "id") { continue; }
            $columnName = $this->GetPropertyName($prop);
            if(isset($obj->$propInternalName)) {
                $value = $obj->$propInternalName;
                if(is_bool($value)) { # PDO shits itself trying to insert into a BIT(1) column without this
                    $bits[] = "$columnName = ".($value?1:0);
                } else {
                    $bits[] = "$columnName = :$columnName";
                    $params[$columnName] = is_string($value) ? trim($value) : $value;
                }
            } else {
                $bits[] = "$columnName = null";
            }
        }
        if($obj->IncludesModifiedColumn() && $includeCreatedAndModified) {
            $bits[] = "modified = :modified";
            $params["modified"] = gmdate("Y-m-d H:i:s");
        }
        $sql .= implode(", ", $bits)." WHERE id = ".$obj->id;
        $this->ExecuteNonQuery($sql, $params);
    }
    private function BeeModelInsert(string $tableName, BeeModel $obj, array $properties, bool $includeCreatedAndModified): int { // ReflectionProperty
        $sql = "INSERT INTO $tableName (";
        $keyBits = [];
        $valueBits = [];
        $params = [];
        foreach($properties as $prop) {
            $propInternalName = $prop->getName();
            if($propInternalName === "id") { continue; }
            $columnName = $this->GetPropertyName($prop);
            if(isset($obj->$propInternalName)) {
                $value = $obj->$propInternalName;
            } else {
                $value = null;
            }
            $keyBits[] = "$columnName";
            if(is_bool($value)) { # PDO shits itself trying to insert into a BIT(1) column without this
                $valueBits[] = ($value?1:0);
            } else {
                $valueBits[] = ":$columnName";
                $params[$columnName] = is_string($value) ? trim($value) : $value;
            }
        }
        if($includeCreatedAndModified) {
            $keyBits[] = "created";
            $keyBits[] = "modified";
            $valueBits[] = ":nowdate";
            $valueBits[] = ":nowdate";
            $params["nowdate"] = gmdate("Y-m-d H:i:s");
        }
        $sql .= implode(", ", $keyBits).") VALUES (".implode(", ", $valueBits).")";
        return $this->InsertAndReturnID($sql, $params);
    }

    public function GetBeeModel(string $objClass, int $id, int $user = 0): mixed {
        $reflection = new ReflectionClass($objClass);
        $tableName = $this->GetTableName($reflection);
        $selectParts = implode(", ", $this->GetPropertyNamesArray($reflection));
        $query = "SELECT $selectParts FROM $tableName WHERE id = $id";
        if($user > 0) {
            $query .= " AND user = $user";
        }
        return $this->GetObject($objClass, $query);
    }

	public function GetObject(string $objClass, string $sql, array $params = []): mixed {
		$this->TryLog($sql, $params);
        $cmd = $this->pdo->prepare($sql);
		$cmd->execute($params);
        $res = $cmd->fetchObject($objClass);
        return $res === false ? null : $res;
    }

    public function GetBeeModelList(string $objClass, int $user = 0, string $whereClause = "", array $params = []): array {
        $reflection = new ReflectionClass($objClass);
        $tableName = $this->GetTableName($reflection);
        $selectParts = implode(", ", $this->GetPropertyNamesArray($reflection));
        $query = "SELECT $selectParts FROM $tableName ";
        if($whereClause != "") {
            $query .= $whereClause;
            if($user > 0) {
                $query .= " AND user = $user";
            }
        } else if($user > 0) {
            $query .= "WHERE user = $user";
        }
        return $this->GetObjects($objClass, $query, $params);
    }

    public function GetObjects(string $objClass, string $sql, array $params = []): array {
		$this->TryLog($sql, $params);
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        return $cmd->fetchAll(PDO::FETCH_CLASS, $objClass);
    }
    public function GetString(string $sql, array $params = []): ?string {
		$this->TryLog($sql, $params);
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        return $cmd->fetch(PDO::FETCH_NUM)[0];
    }
    public function GetStrings(string $sql, array $params = []): array {
		$this->TryLog($sql, $params);
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        $res = [];
        while($row = $cmd->fetch(PDO::FETCH_NUM)) {
            $res[] = $row[0];
        }
        return $res;
    }
    public function GetInt(string $sql, array $params = []): int {
		$this->TryLog($sql, $params);
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        return intval($cmd->fetch(PDO::FETCH_NUM)[0]);
    }
    public function GetInts(string $sql, array $params = []): array {
		$this->TryLog($sql, $params);
        $cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        $res = [];
        while($row = $cmd->fetch(PDO::FETCH_NUM)) {
            $res[] = intval($row[0]);
        }
        return $res;
    }
    public function GetBool(string $sql, array $params = []): bool {
        $this->TryLog($sql, $params);
		$cmd = $this->pdo->prepare($sql);
        $cmd->execute($params);
        $res = intval($cmd->fetch(PDO::FETCH_NUM)[0]);
        return $res > 0;
    }
    public function CreateInClause(array $arr, string $prefix = "l"): array {
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

    private function TryLog(string $query, array $params): void {
        if($this->log === null) {
            return;
        }
        $this->log->LogInfo($query);
        $this->log->LogInfo(json_encode($params));
    }
}