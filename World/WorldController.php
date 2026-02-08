<?php declare(strict_types=1);
/**
 * Bee API
 * @copyright 2026 Haunted Bees Productions
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * 
 * @see https://github.com/HauntedBees/BeeAPI
 */
require_once("Models.php");
class WorldController extends BeeController {
    public function __construct() {
        parent::__construct("portal");
    }
    public function PostBeeLogin(BeeCredentials $credentials) {
        try {
            $auth = new BeeAuth();
            $userInfo = $auth->Login($credentials);
            $token = $auth->GenerateJWTToken($userInfo);
            return $this->response->OK([ "token" => $token ]);
        } catch(BeeAuthException $e) {
            return $this->response->Unauthorized("Invalid email address or password.");
        }
    }

    #[Get("metadata")]
    public function GetMetadata() {
        return [
            "letters" => $this->db->GetStrings("
                SELECT DISTINCT LEFT(IFNULL(realFirstLetter, name), 1)
                FROM country
                WHERE active = 1
                ORDER BY IFNULL(realFirstLetter, name) ASC"),
            "countries" => $this->db->GetDataTable("
                SELECT ckey, name, LEFT(IFNULL(realFirstLetter, name), 1) AS firstLetter, focusArea
                FROM country
                WHERE active = 1
                ORDER BY IFNULL(realFirstLetter, name) ASC"),
            "diets" => $this->db->GetObjects("NamedEmoji", "
                SELECT id, name, emoji, color
                FROM diet
                ORDER BY name ASC"),
            "dishes" => $this->db->GetObjects("NamedEmoji", "
                SELECT id, name, emoji, color
                FROM dish
                WHERE spiceOnly = 0
                ORDER BY name ASC")
        ];
    }
    #[Get("homepage")]
    public function GetHomepage() {
        $currentLetter = $this->db->GetString("
            SELECT MAX(LEFT(IFNULL(realFirstLetter, name), 1))
            FROM country
            WHERE active = 1");
        return [
            "currentLetter" => $currentLetter,
            "countriesDownWithCurrentLetter" => $this->db->GetInt("
                SELECT COUNT(*)
                FROM country
                WHERE LEFT(IFNULL(realFirstLetter, name), 1) = :l
                    AND active = 1", ["l" => $currentLetter]),
            "countriesWithCurrentLetter" => $this->db->GetInt("
                SELECT COUNT(*) FROM (
                    SELECT name FROM country WHERE LEFT(IFNULL(realFirstLetter, name), 1) = :l
                    UNION ALL
                    SELECT name FROM shell_country WHERE LEFT(name, 1) = :l AND name <> 'None'
                ) T", ["l" => $currentLetter]),
            "totalCountries" => $this->db->GetInt("
                SELECT COUNT(*) FROM (
                    SELECT name FROM country
                    UNION ALL
                    SELECT name FROM shell_country WHERE name <> 'None'
                ) T"),
            "countriesDown" => $this->db->GetInt("SELECT COUNT(*) FROM country WHERE active = 1"),
            "latestFoods" => $this->GetFoods("WHERE c.active = 1", [], "ORDER BY date DESC LIMIT 3"),
            "randomSongs" => $this->GetSongs("WHERE c.active = 1", [], "ORDER BY RAND() LIMIT 10")
        ];
    }
    #[Get("page")]
    public function GetPage(int $pageNum) {
        $startIdx = ($pageNum - 1) * 10;
        return $this->GetFoods("WHERE c.active = 1", [], "ORDER BY date DESC LIMIT 10 OFFSET $startIdx");
    }
    #[Get("foodkey")]
    public function GetFoodByKey(string $key) {
        $recipeMatches = $this->GetFoods("WHERE c.active = 1 AND r.url_key = :url_key", ["url_key" => $key], "", false);
        if(count($recipeMatches) == 0) {
            return [
                "food" => null,
                "similarDishes" => []
            ];
        }
        $food = $recipeMatches[0];
        $id = $food->id;
        unset($food->id);
        return [
            "food" => $food,
            "similarDishes" => $this->GetFoods(
                "INNER JOIN recipe r2 ON r2.id <> r.id WHERE r2.id = :id",
                ["id" => $id],
                "ORDER BY
                    CASE
                            WHEN r2.dish_detailed = r.dish_detailed THEN 2
                            ELSE 0
                        END
                        +
                        CASE
                            WHEN r2.dish = r.dish THEN 1
                            ELSE 0
                        END
                    DESC
                    LIMIT 3"
            )
        ];
    }
    #[Get("favorites")]
    public function GetFavorites() {
        return [
            "food" => $this->GetFoods("WHERE r.favorite = 1"),
            "music" => $this->GetSongs("WHERE s.favorite = 1", [], "ORDER BY c.id ASC")
        ];
    }

    #[Get("search")]
    public function GetFullSearchResults(string $q, array $dishes, array $goodDiets, array $badDiets) {
        $joins = "";
        $whereClause = [];
        $havingParts = [];
        $params = [];
        if(strlen($q) === 0 && empty($dishes) && empty($goodDiets) && empty($badDiets)) {
            return [];
        }
        if(strlen($q) > 0) {
            $joins .= "
            LEFT JOIN recipe_ingredient ri ON r.id = ri.recipe
            LEFT JOIN ingredient i ON ri.ingredient = i.id";
            $searchParts = explode(" ", $q);
            $i = 0;
            $searchWhereParts = [];
            foreach($searchParts as $searchWord) {
                $key = ":search$i";
                $params[$key] = "%$searchWord%";
                $searchWhereParts[] = "r.name LIKE $key OR i.name LIKE $key";
                $i++;
            }
            $whereClause[] = "(".implode(" OR ", $searchWhereParts).")";
            // TODO: this is an OR match all the way through, so searches for "corn pepper" will match
            // all things with corn AND all things with pepper... we probably need a HAVING clause or
            // some shit, but it'll probably be more complicated than the diet ones below.
        }
        if(!empty($dishes)) {
            $dishSQL = $this->ExplodeArrayForInClause($params, "dish", $dishes);
            $dishIn = $dishSQL["keys"];
            $whereClause[] = "r.dish IN ($dishIn)";
            $params = $dishSQL["parameters"];
        }
        if(!empty($goodDiets) || !empty($badDiets)) {
            $joins .= "
                LEFT JOIN recipe_diet rd ON r.id = rd.recipe";
            $goodDietSQL = $this->ExplodeArrayForInClause($params, "gooddiet", $goodDiets);
            $params = $goodDietSQL["parameters"];
            $badDietSQL = $this->ExplodeArrayForInClause($params, "baddiet", $badDiets);
            $params = $badDietSQL["parameters"];
            $goodDietParamString = $goodDietSQL["keys"];
            $badDietParamString = $badDietSQL["keys"];
            if(!empty($goodDietParamString)) {
                $whereClause[] = "rd.diet IN ($goodDietParamString)";
            }
            if(!empty($goodDiets)) {
                $goodCount = count($goodDiets);
                $havingParts[] = "COUNT(DISTINCT CASE WHEN rd.diet IN ($goodDietParamString) THEN rd.diet END) = $goodCount";
            }
            if(!empty($badDiets)) {
                $havingParts[] = "COUNT(DISTINCT CASE WHEN rd.diet IN ($badDietParamString) THEN rd.diet END) = 0";
            }
        }
        $collapsedWhereClause = implode(" AND ", $whereClause);
        $collapsedHavingClause = implode(" AND ", $havingParts);
        if(!empty($collapsedHavingClause)) {
            $collapsedHavingClause = "HAVING $collapsedHavingClause";
        }
        $sql = "
            $joins
            WHERE $collapsedWhereClause
            GROUP BY c.ckey, c.name, r.id, r.name, d.name, d.emoji, r.url, r.date, r.img, r.databee, r.description, r.favorite
            $collapsedHavingClause";
        return $this->GetFoods($sql, $params);
    }
    private function ExplodeArrayForInClause(array $parameters, string $objName, array $arrayToExplode) {
        $i = 0;
        $keys = [];
        foreach($arrayToExplode as $item) {
            $key = ":$objName".$i++;
            $parameters[$key] = intval($item);
            $keys[] = $key;
        }
        return [
            "parameters" => $parameters,
            "keys" => implode(", ", $keys)
        ];
    }

    #[Get("countries")]
    public function GetCountries() {
        return $this->db->GetObjects("CountrySummary", "
            SELECT ckey, name, realFirstLetter AS sortName
            FROM country
            ORDER BY IFNULL(realFirstLetter, name) ASC");
    }
    #[Get("country")]
    public function GetCountry(string $countryCode) {
        $country = $this->db->GetObject("Country", "
            SELECT
                id, ckey, name, description, population, popEstimate, area, independence, indFrom, demonym, currency, motto, 
                languages, foodURL, musicURL, realFirstLetter, focusArea
            FROM country
            WHERE ckey = :ckey", ["ckey" => $countryCode]);
        if($country == null) { 
            throw new BeeException("Country not found.");
        }
        $country->neighbors = $this->db->GetObjects("CountryNeighbor", "
            SELECT c.cKey AS realCountryCode, c.name AS realCountryName, sc.name AS shellCountryName
            FROM country_neighbor cn
                LEFT JOIN country c ON cn.neighbor = c.id
                LEFT JOIN shell_country sc ON cn.shell = sc.id
            WHERE cn.country = :id
            ORDER BY IFNULL(c.name, sc.name) ASC", ["id" => $country->id]);
        $country->food = $this->GetFoods("WHERE c.id = :id", ["id" => $country->id]);
        $country->music = $this->GetSongs("WHERE c.id = :id", ["id" => $country->id]);
        unset($country->id);
        return $country;
    }
    /** @return Recipe[] */
    private function GetFoods(string $whereClause, array $params = [], string $orderBy = "ORDER BY r.date DESC", bool $unset_id = true) {
        $recipeSql = "
            SELECT DISTINCT c.ckey AS countryCode, c.name AS countryName, r.id, r.name, d.name AS dish, d.emoji AS dishEmoji, r.url, 
            r.url_key AS urlkey, r.date, r.img, r.databee, r.description, r.favorite
            FROM recipe r
                INNER JOIN country c ON r.country = c.id
                INNER JOIN dish d ON r.dish = d.id
            $whereClause
            $orderBy";
        $recipes = $this->db->GetObjects("Recipe", $recipeSql, $params);
        /*$this->response->extra[] = [
            "sql" => $recipeSql,
            "params" => $params
        ];*/
        foreach($recipes as $recipe) {
            $recipe->diet = $this->db->GetObjects("DietInfo", "
                SELECT d.name, d.emoji, dr.optional, dr.description
                FROM recipe_diet dr
                    INNER JOIN diet d ON dr.diet = d.id
                WHERE dr.recipe = :id", ["id" => $recipe->id]);
            $recipe->ingredients = $this->db->GetStrings("
                SELECT i.name
                FROM ingredient i
                    INNER JOIN recipe_ingredient ri ON i.id = ri.ingredient
                WHERE ri.recipe = :id", ["id" => $recipe->id]);
            $recipe->songs = $this->GetSongs("WHERE s.recipe = :recipe", ["recipe" => $recipe->id]);
            if($unset_id) {
                unset($recipe->id);
            }
        }
        return $recipes;
    }
    /** @return SongInfo[] */
    private function GetSongs(string $whereClause, array $params = [], string $orderBy = "") {
        return $this->db->GetObjects("SongInfo", "
            SELECT c.ckey AS countryCode, c.name AS countryName, s.name, s.url, s.favorite, s.translation
            FROM song s
                INNER JOIN country c ON s.country = c.id
            $whereClause
            $orderBy", $params);
    }
}
?>
