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
require_once("Models.php");
class AdminController extends BeeSecureController {
    public function __construct() { parent::__construct("portaladmin", BEEROLE_ADMIN); }
//class AdminController extends BeeController {
//    public function __construct() { parent::__construct("portaladmin"); }
    
    #[Get("export")]
    public function GetWorldJSONExport(): array {
        $codes = $this->db->GetStrings("SELECT ckey FROM country ORDER BY id ASC");
        $res = [];
        foreach($codes as $code) {
            $res[$code] = $this->GetCountryInner($code);
        }
        return $res;
    }

    #[Get("deadLinks")]
    public function GetDeadLinks(): array {
        $links = $this->db->GetObjects("LinkScanRecord", "
            SELECT CONCAT(c.name, ' (Song): ', s.name) AS name,  s.url
            FROM song s
                INNER JOIN country c ON s.country = c.id
            UNION
            SELECT CONCAT(c.name, ' (Recipe): ', r.name) AS name, r.url
            FROM recipe r
                INNER JOIN country c ON r.country  = c.id");
        $invalidLinks = [];
        foreach($links as $link) {
            if(!$this->IsLinkValid($link->url)) {
                $invalidLinks[] = $link;
            }
        }
        return $invalidLinks;
    }
    private function IsLinkValid(string $url): bool {
        if(str_contains($url, "youtube.com")) {
            $ytUrl = "http://www.youtube.com/oembed?url=$url&format=json";
            $headers = get_headers($ytUrl);
            return substr($headers[0], 9, 3) !== "404";
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64; rv:160.0) Gecko/20100101 Firefox/160.0");
        curl_exec($ch);
        if(curl_errno($ch)) {
            curl_close($ch);
            return false;
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code >= 200 && $code <= 403) || $code == 429;
    }

    #[Get("asOfNotes")]
    public function GetAsOfNotes(): array {
        return $this->db->GetObjects("Country", "
            SELECT id, name, asOfNotes, asOfDate
            FROM country
            WHERE asOfNotes IS NOT NULL
                AND TRIM(asOfNotes) <> ''
        ");
    }

    #[Post("validateInfo")]
    public function ValidateAsOfNotes(UpdateNoteRequest $req): bool {
        $this->db->ExecuteNonQuery("UPDATE country SET asofDate = CURDATE() WHERE id = :id", ["id" => $req->countryId]);
        return true;
    }

    #[Get("diets")]
    public function GetDiets(): array { return $this->db->GetObjects("IdNamePair", "SELECT id, name FROM diet ORDER BY name ASC"); }
    #[Get("dishes")]
    public function GetDishes(): array { return $this->db->GetObjects("IdNamePair", "SELECT id, name FROM dish WHERE spiceOnly = 0 ORDER BY name ASC"); }
    #[Get("country")]
    public function GetCountry(string $countryCode): Country { return $this->GetCountryInner($countryCode); }

    private function GetCountryInner(string $countryCode): Country {
        $country = $this->db->GetObject("Country", "
            SELECT
                id, ckey, name, description, population, popEstimate, area, independence, indFrom, demonym, currency, motto, 
                languages, foodURL, musicURL, realFirstLetter, focusArea, notes, active, asOfNotes
            FROM country
            WHERE ckey = :ckey", ["ckey" => $countryCode]);
        if($country == null) { return $this->response->Error("Country not found."); }
        $country->neighbors = $this->db->GetObjects("AdminCountryNeighbor", "
            SELECT IFNULL(c.id, sc.id) AS id, IFNULL(c.ckey, sc.countryCode) AS countryCode, IFNULL(c.name, sc.name) AS name,
                CASE
                    WHEN c.id IS NULL THEN 1
                    ELSE 0
                END AS shell
            FROM country_neighbor cn
                LEFT JOIN country c ON cn.neighbor = c.id
                LEFT JOIN shell_country sc ON cn.shell = sc.id
            WHERE cn.country = :id
            ORDER BY IFNULL(c.name, sc.name) ASC", ["id" => $country->id]);
        $country->food = $this->GetFoods("WHERE r.country = :id", ["id" => $country->id]);
        $country->music = $this->GetSongs("WHERE c.id = :id", ["id" => $country->id]);
        return $country;
    }
    /** @return Recipe[] */
    private function GetFoods(string $whereClause, array $params, string $orderBy = "ORDER BY r.name ASC"): array {
        $recipes = $this->db->GetObjects("AdminRecipe", "
            SELECT r.id, r.name, r.dish, r.url, r.url_key AS urlkey, r.date, r.img, r.databee, r.description, r.favorite
            FROM recipe r
            $whereClause
            $orderBy", $params);
        foreach($recipes as $recipe) {
            $recipe->diet = $this->db->GetObjects("AdminDietInfo", "
                SELECT dr.diet, dr.optional, dr.description
                FROM recipe_diet dr
                WHERE dr.recipe = :id", ["id" => $recipe->id]);
            $recipe->ingredients = $this->db->GetStrings("
                SELECT i.name
                FROM ingredient i
                    INNER JOIN recipe_ingredient ri ON i.id = ri.ingredient
                WHERE ri.recipe = :id", ["id" => $recipe->id]);
        }
        return $recipes;
    }
    /** @return SongInfo[] */
    private function GetSongs(string $whereClause, array $params): array {
        return $this->db->GetObjects("SongInfo", "
            SELECT c.ckey AS countryCode, s.recipe, s.name, s.url, s.favorite, s.translation
            FROM song s
                INNER JOIN country c ON s.country = c.id
            $whereClause", $params);
    }
    #[Post("country")]
    public function PostCountry(Country $country): array {
        try {
            $this->db->BeginTransaction();
            if($country->id === 0) {
                $this->db->PostBeeModel($country);
                // convert shell to not shell
                $shell = $this->db->GetInt("SELECT id FROM shell_country WHERE countryCode = :cc", ["cc" => $country->ckey]);
                if($shell > 0) {
                    $this->db->ExecuteNonQuery("UPDATE country_neighbor SET shell = NULL, neighbor = :n WHERE shell = :s", ["n" => $country->id, "s" => $shell]);
                    $this->db->ExecuteNonQuery("DELETE FROM shell_country WHERE id = :id", ["id" => $shell]);
                }
            } else {
                $this->db->PostBeeModel($country);
                if($country->asOfNotesChanged) {
                    $this->db->ExecuteNonQuery("UPDATE country SET asofDate = CURDATE() WHERE id = :id", ["id" => $country->id]);
                }
                $this->db->ExecuteNonQuery("DELETE FROM country_neighbor WHERE country = :id", ["id" => $country->id]);
                $this->db->ExecuteNonQuery("DELETE FROM song WHERE country = :id", ["id" => $country->id]);
            }
            
            if(count($country->neighbors) > 0) {
                $groupSQL = [];
                foreach($country->neighbors as $neighbor) {
                    if($neighbor["shell"]) {
                        $groupSQL[] = "(".$country->id.", NULL, ".$neighbor["id"].")";
                    } else {
                        $groupSQL[] = "(".$country->id.", ".$neighbor["id"].", NULL)";
                    }
                }
                $this->db->ExecuteNonQuery("INSERT INTO country_neighbor (country, neighbor, shell) VALUES ".implode(", ", $groupSQL));
            }
    
            if(count($country->food) > 0) {
                $dietSQL = [];
                $dietParams = [];
                $ingSQL = [];
                $ingredientXref = [];
                $idx = 0; 
                foreach($country->food as $food) {
                    $cleanFoodName = $this->GetAsciiName($food["name"]);
                    if(substr($food["img"], 0, 23) === "data:image/jpeg;base64,") {
                        $newFileName = strtolower($country->ckey."_".$cleanFoodName);
                        $base64data = explode(",", $food["img"])[1];
                        $imageFile = base64_decode($base64data, true);
                        if($imageFile === false) { throw new BeeException("Invalid base 64 image file."); }
        
                        $ini = parse_ini_file(CONFIG_PATH, true);
                        $paths = $ini["filepaths"];
                        $filepath = $paths["worldfoodimagepath"].$newFileName.".jpg";
                        $filewriter = fopen($filepath, "w");
                        if($filewriter === false) { throw new BeeException("Could not open filepath at: ".realpath($paths["worldfoodimagepath"])); }
                        fwrite($filewriter, $imageFile);
                        fclose($filewriter);
                        $food["img"] = $newFileName.".jpg";
                    } else if(substr($food["img"], 0, 5) === "data:") {
                        throw new BeeException("JPEGs only!");
                    }
                    $foodAsObj = new AdminRecipe();
                    $foodAsObj->MapFromJSON($food);
                    $foodAsObj->country = $country->id;
                    if($food["id"] === 0) {
                        $foodAsObj->urlkey = $cleanFoodName;
                        $food["id"] = $this->db->PostBeeModel($foodAsObj, false);
                    } else {
                        $this->db->PostBeeModel($foodAsObj, false);
                        $this->db->ExecuteNonQuery("DELETE FROM recipe_diet WHERE recipe = :id", ["id" => $food["id"]]);
                        $this->db->ExecuteNonQuery("DELETE FROM recipe_ingredient WHERE recipe = :id", ["id" => $food["id"]]);
                    }
                    foreach($food["diet"] as $diet) {
                        $dietSQL[] = "(".$food["id"].", :d$idx, ".($diet["optional"]?1:0).", :desc$idx)";
                        $dietParams["d$idx"] = $diet["diet"];
                        $dietParams["desc$idx"] = trim($diet["description"]);
                        $idx++;
                    }
                    foreach($food["ingredients"] as $ingredient) {
                        $ingredient = trim($ingredient);
                        $ingredientId = array_key_exists($ingredient, $ingredientXref) ? $ingredientXref[$ingredient] : null;
                        if($ingredientId === null) {
                            $ingredientId = $this->db->GetInt("SELECT id FROM ingredient WHERE name = :n", ["n" => $ingredient]);
                            if($ingredientId === 0) {
                                $ingredientId = $this->db->InsertAndReturnID("INSERT INTO ingredient (name) VALUES (:n)", ["n" => $ingredient]);
                            }
                            $ingredientXref[$ingredient] = $ingredientId;
                        }
                        $ingSQL[] = "(".$food["id"].", $ingredientId)";
                    }
                }
                if(count($dietSQL) > 0) {
                    $this->db->ExecuteNonQuery("INSERT INTO recipe_diet (recipe, diet, optional, description) VALUES ".implode(", ", $dietSQL), $dietParams);
                }
                if(count($ingSQL) > 0) {
                    $this->db->ExecuteNonQuery("INSERT INTO recipe_ingredient (recipe, ingredient) VALUES ".implode(", ", $ingSQL));
                }
            }
            if(count($country->music) > 0) {
                $groupSQL = [];
                $groupParams = ["c" => $country->id];
                foreach($country->music as $i=>$song) {
                    $groupSQL[] = "(:c, :r$i, :n$i, :u$i, ".($song["favorite"]?1:0).", :t$i)";
                    $groupParams["r$i"] = $song["recipe"];
                    $groupParams["n$i"] = trim($song["name"]);
                    $groupParams["u$i"] = trim($song["url"]);
                    $groupParams["t$i"] = is_string($song["translation"]) ? trim($song["translation"]) : $song["translation"];
                }
                $this->db->ExecuteNonQuery("INSERT INTO song (country, recipe, name, url, favorite, translation) VALUES ".implode(", ", $groupSQL), $groupParams);
            }
            $this->db->CommitTransaction();
            $updatedRss = $this->GenerateXML();
            return [
                "id" => $country->id,
                "rssUpdated" => $updatedRss
            ];
        } catch(Error $e) {
            $this->db->RollbackTransaction();
            throw $e;
        } catch(Exception $e) {
            $this->db->RollbackTransaction();
            throw $e;
        }
    }
    /** @return AdminCountryNeighbor[] */
    #[Get("neighbors")]
    public function GetNeighbors(string $query): array {
        return $this->db->GetObjects("AdminCountryNeighbor", "
            SELECT * FROM (
                SELECT id, cKey AS countryCode, name, 0 AS shell FROM country
                UNION ALL
                SELECT id, countryCode, name, 1 AS shell FROM shell_country
            ) T
            WHERE T.name LIKE :query OR T.countryCode LIKE :query", ["query" => "%$query%"]);
    }

    #[Get("feed")]
    public function GetFeedTest(): string {
        $this->GenerateXML();
        return __DIR__;
    }

    private function GetAsciiName(string $name): string {
        return strtolower(preg_replace("/[^A-Za-z_]/", "", preg_replace("/\s/", "_", $name)));
    }

    private function GenerateXML(): bool {
        $rss = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>Areund the World</title>
		<link>https://www.hauntedbees.com/world/</link>
		<language>en-us</language>
		<copyright>&#169; 2018-2026 Haunted Bees Productions</copyright>
		<description>A journey of cooking food and listening to music from around the world, with the eventual goal of doing so for every country.</description>
        <atom:link href="https://www.hauntedbees.com/feeds/world.rss" rel="self" type="application/rss+xml" />
EOD;
        $recipes = $this->GetFoods("
                INNER JOIN country c ON r.country = c.id
            WHERE c.active = 1", [], "ORDER BY date DESC LIMIT 40");
        foreach($recipes as $recipe) {
            $rss .= "
            <item>
                <title>".$recipe->name."</title>
                <link>https://www.hauntedbees.com/world/recipe/".$recipe->urlkey."</link>
                <description></description>
                <pubDate>".date("r", strtotime($recipe->date))."</pubDate>
            </item>";
        }
        $rss .= "
    </channel>
</rss>";
        $ini = parse_ini_file(CONFIG_PATH, true);
        $paths = $ini["filepaths"];
        $filepath = $paths["worldrsspath"];
        $filewriter = fopen($filepath, "w");
        if($filewriter === false) { return false; }
        fwrite($filewriter, $rss);
        fclose($filewriter);
        return true;
    }
}
?>