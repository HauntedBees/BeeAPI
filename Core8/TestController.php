<?php declare(strict_types=1);

class TestController extends BeeController {
    public function __construct() {
        parent::__construct("beetag");
    }

	#[Get("fail")]
    public function GetFail(): void { throw new BeeException("FUCK YOU"); }

    #[Get("test")]
    public function GetTest(): string { return "WOGGLE"; }

    #[Get("test2")]
    public function GetTest2(): mixed {
        return $this->db->GetBeeModelList("TestModel", 2, "WHERE numbo > 10");
    }

    #[Get("test3")]
    public function SpeedTest(): mixed {
        $obj = new TestModel();
        $obj->id = 5;
        $obj->SetUserId(5);
        $obj->stringo = "hey";
        $obj->dateo = "2021-01-01";
        $obj->numbo = 5;
        $ignoreColumns = [];
        $start = microtime();
        for($i = 0; $i < 100000; $i++) {
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
        }
        $end = microtime();
        $results = [ "get_object_vars" => ($end - $start) ];
        $start = microtime();
        for($i = 0; $i < 100000; $i++) {
            $params = $this->db->GetPropertyNamesArray(new ReflectionClass($obj::class));
            foreach($params as $key) {
                if($key === "id" || in_array($key, $ignoreColumns)) { continue; }
                $value = $obj->$key;
                if(is_bool($value)) { # PDO shits itself trying to insert into a BIT(1) column without this
                    $bits[] = "$key = ".($value?1:0);
                } else {
                    $bits[] = "$key = :$key";
                    $params[$key] = is_string($value) ? trim($value) : $value;
                }
            }
        }
        $end = microtime();
        $results["ReflectionClass"] = ($end - $start);
        return $results;
    }

    #[Get("test4")]
    public function GetTest4(string $x = "a", int $y = 2, string $z = "c"): mixed {
        return "$z $y $x";
    }



    #[Get("testparam")]
    #[MaxLength("s", 10)]
    #[Required("s")]
    #[IntArray("v")]
    public function GetTestParam(string $s, array $v): string { return "WOOGLE$s$v"; }

    #[Post("posttest")]
    public function TestPost(TestModel $t): string {
        $this->db->PostBeeModel($t);
        return $t->stringo;
    }
}

#[TableName("test")]
class TestModel extends AuthBeeModel {
    #[MaxLength(maxLength: 50)]
    public string $stringo;
    public string $dateo;
    public int $numbo;
}

?>