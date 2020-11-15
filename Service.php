<?php declare(strict_types=1);
function PostRequest(string $url, string $params) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLINFO_HEADER_OUT, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [ "Content-Type: application/json", "Content-Length: ".strlen($params) ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
$path = $_GET["path"];
if(preg_match("/[^A-Za-z/]/", $path)) { exit; }
$url = "http://localhost:421/$path";
$params = $_GET["params"];
echo PostRequest($url, $params);
?>