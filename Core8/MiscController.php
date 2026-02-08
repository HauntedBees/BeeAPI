<?php

declare(strict_types=1);

class MiscController extends BeeController
{
    public function __construct()
    {
        parent::__construct("misc");
    }

    #[Get("ping")]
    public function Ping(): string
    {
        return "pong";
    }

    #[Get("list")]
    public function GetInfo(int $page): array
    {
        $offset = $page * 10;
        $res = $this->db->GetDataTable("SELECT * FROM demolog ORDER BY created DESC LIMIT 10 OFFSET $offset");
        foreach ($res as $k => $v) {
            $res[$k]["screenshot"] = base64_encode($res[$k]["screenshot"]);
        }
        return $res;
    }

    #[Post("demo")]
    public function DemoFeedback(DemoRequest $t): void
    {
        $d = new DemoModel();
        $d->source = $t->source;
        $d->message = $t->message;
        $d->sys_info = $t->sys_info == "omitted" ? "" : $t->sys_info;
        if ($t->screenshot != "omitted") {
            $d->screenshot = base64_decode($t->screenshot);
        } else {
            $d->screenshot = "";
        }
        $this->db->PostBeeModel($d);
    }
}

class DemoRequest extends RawBeeModel
{
    public string $source;
    public string $message;
    public string $sys_info;
    public string $screenshot;
}

#[TableName("demolog")]
class DemoModel extends BeeModel
{
    #[MaxLength(maxLength: 100)]
    public string $source;
    #[MaxLength(maxLength: 1000)]
    public string $message;
    #[MaxLength(maxLength: 6000)]
    public string $sys_info;
    public string $screenshot;
}

