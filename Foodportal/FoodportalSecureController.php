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
class FoodportalSecureController extends BeeSecureController {
    public function __construct() { parent::__construct("portaladmin", BEEROLE_ADMIN); }
    public function GetAuth() { $this->response->OK(true); }
    
    public function GetWorldJSONExport() {
        $codes = $this->db->GetStrings("SELECT ckey FROM country ORDER BY id ASC");
        $res = [];
        foreach($codes as $code) {
            $res[$code] = $this->GetCountryInner($code);
        }
        return $this->response->OK($res);
    }
    public function GetSpiceJSONExport() {
        $sureWhyNot = new FoodportalController();
        $res = $sureWhyNot->SeasoningsQuery("ORDER BY name ASC", []);
        return $this->response->OK($res);
    }

    /** @return IdNamePair[] */
    public function GetDiets() { return $this->response->OK($this->db->GetObjects("IdNamePair", "SELECT id, name FROM diet ORDER BY name ASC")); }
    /** @return IdNamePair[] */
    public function GetDishes() { return $this->response->OK($this->db->GetObjects("IdNamePair", "SELECT id, name FROM dish WHERE spiceOnly = 0 ORDER BY name ASC")); }
    /** @return Country */
    public function GetCountry(string $countryCode) { return $this->response->OK($this->GetCountryInner($countryCode)); }

    private function GetCountryInner(string $countryCode):Country {
        $country = $this->db->GetObject("Country", "
            SELECT
                id, ckey, name, description, population, popEstimate, area, independence, indFrom, demonym, currency, motto, 
                languages, foodURL, musicURL, realFirstLetter, focusArea, notes
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
    private function GetFoods(string $whereClause, array $params):array {
        $recipes = $this->db->GetObjects("AdminRecipe", "
            SELECT r.id, r.name, r.dish, r.url, r.date, r.img, r.databee, r.description, r.favorite
            FROM recipe r
            $whereClause
            ORDER BY r.name ASC", $params);
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
    private function GetSongs(string $whereClause, array $params):array {
        return $this->db->GetObjects("SongInfo", "
            SELECT c.ckey AS countryCode, s.name, s.url, s.favorite, s.translation
            FROM song s
                INNER JOIN country c ON s.country = c.id
            $whereClause", $params);
    }
    /** @return int */
    public function PostCountry(Country $country) {
        try {
            $this->db->BeginTransaction();
            $skipColumns = ["neighbors", "food", "music"];
            if($country->id === 0) {
                $country->id = $this->db->ObjectInsert("country", $country, $skipColumns);
                // convert shell to not shell
                $shell = $this->db->GetInt("SELECT id FROM shell_country WHERE countryCode = :cc", ["cc" => $country->ckey]);
                if($shell > 0) {
                    $this->db->ExecuteNonQuery("UPDATE country_neighbor SET neighbor = :n WHERE shell = :s", ["n" => $country->id, "s" => $shell]);
                    $this->db->ExecuteNonQuery("DELETE FROM shell_country WHERE id = :id", ["id" => $shell]);
                }
            } else {
                $this->db->ObjectUpdate("country", $country, $skipColumns);
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
    
            if(count($country->music) > 0) {
                $groupSQL = [];
                $groupParams = ["c" => $country->id];
                foreach($country->music as $i=>$song) {
                    $groupSQL[] = "(:c, :n$i, :u$i, ".($song["favorite"]?1:0).", :t$i)";
                    $groupParams["n$i"] = trim($song["name"]);
                    $groupParams["u$i"] = trim($song["url"]);
                    $groupParams["t$i"] = is_string($song["translation"]) ? trim($song["translation"]) : $song["translation"];
                }
                $this->db->ExecuteNonQuery("INSERT INTO song (country, name, url, favorite, translation) VALUES ".implode(", ", $groupSQL), $groupParams);
            }
            if(count($country->food) > 0) {
                $foodSkipColumns = ["diet", "ingredients"];
                $dietSQL = [];
                $dietParams = [];
                $ingSQL = [];
                $ingredientXref = [];
                $idx = 0; 
                foreach($country->food as $food) {
                    if(substr($food["img"], 0, 23) === "data:image/jpeg;base64,") {
                        $newFileName = strtolower($country->ckey."_".preg_replace("/[^A-Za-z_]/", "", preg_replace("/\s/", "_", $food["name"])));
                        $base64data = explode(",", $food["img"])[1];
                        $imageFile = base64_decode($base64data, true);
                        if($imageFile === false) { return $this->response->Error("Invalid base 64 image file."); }
        
                        $ini = parse_ini_file(CONFIG_PATH, true);
                        $paths = $ini["filepaths"];
                        $filepath = $paths["worldfoodimagepath"].$newFileName.".jpg";
                        $filewriter = fopen($filepath, "x");
                        fwrite($filewriter, $imageFile);
                        fclose($filewriter);
                        $food["img"] = $newFileName.".jpg";
                    } else if(substr($food["img"], 0, 5) === "data:image") {
                        return $this->response->Error("JPEGs only!");
                    }
                    if($food["id"] === 0) {
                        $food["country"] = $country->id;
                        $food["id"] = $this->db->ObjectInsert("recipe", $food, $foodSkipColumns);
                    } else {
                        $this->db->ObjectUpdate("recipe", $food, $foodSkipColumns);
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
                        $ingredientId = $ingredientXref[$ingredient];
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
            $this->db->CommitTransaction();
            return $this->response->OK($country->id);
        } catch(Error $e) {
            $this->db->RollbackTransaction();
            return $this->response->Error($e->getMessage());
        } catch(Exception $e) {
            $this->db->RollbackTransaction();
            return $this->response->Exception($e);
        }
    }
    /** @return AdminCountryNeighbor[] */
    public function GetNeighbors(string $query) {
        return $this->response->OK($this->db->GetObjects("AdminCountryNeighbor", "
            SELECT * FROM (
                SELECT id, cKey AS countryCode, name, 0 AS shell FROM country
                UNION ALL
                SELECT id, countryCode, name, 1 AS shell FROM shell_country
            ) T
            WHERE T.name LIKE :query OR T.countryCode LIKE :query", ["query" => "%$query%"]));
    }
}
?>