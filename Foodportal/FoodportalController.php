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
class FoodportalController extends BeeController {
    public function __construct() { parent::__construct("portal"); }
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

#region "Areund the World"
    public function GetMetadata() {
        return $this->response->OK([
            "letters" => $this->db->GetStrings("SELECT DISTINCT LEFT(IFNULL(realFirstLetter, name), 1) FROM country ORDER BY IFNULL(realFirstLetter, name) ASC"),
            "countries" => $this->db->GetDataTable("SELECT ckey, name, LEFT(IFNULL(realFirstLetter, name), 1) AS firstLetter, focusArea FROM country ORDER BY IFNULL(realFirstLetter, name) ASC"),
            "diets" => $this->db->GetObjects("NamedEmoji", "SELECT id, name, emoji FROM diet ORDER BY name ASC"),
            "dishes" => $this->db->GetObjects("NamedEmoji", "SELECT id, name, emoji FROM dish WHERE spiceOnly = 0 ORDER BY name ASC")
        ]);
    }
    public function GetHomepage() {
        $currentLetter = $this->db->GetString("SELECT MAX(LEFT(IFNULL(realFirstLetter, name), 1)) FROM country");
        return $this->response->OK([
            "currentLetter" => $currentLetter,
            "countriesDownWithCurrentLetter" => $this->db->GetInt("SELECT COUNT(*) FROM country WHERE LEFT(IFNULL(realFirstLetter, name), 1) = :l", ["l" => $currentLetter]),
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
            "countriesDown" => $this->db->GetInt("SELECT COUNT(*) FROM country"),
            "latestFoods" => $this->GetFoods("", [], "ORDER BY date DESC LIMIT 3"),
            "randomSongs" => $this->GetSongs("", [], "ORDER BY RAND() LIMIT 10")
        ]);
    }
    public function GetFavorites() {
        return $this->response->OK([
            "food" => $this->GetFoods("WHERE r.favorite = 1"),
            "music" => $this->GetSongs("WHERE s.favorite = 1", [], "ORDER BY c.id ASC")
        ]);
    }

    /** @return Recipe[] */
    public function GetSearchResults(string $q) {
        if(substr($q, 0, 4) === "ing:") {
            return $this->response->OK($this->GetFoods("
            LEFT JOIN recipe_ingredient ri ON r.id = ri.recipe
            LEFT JOIN ingredient i ON ri.ingredient = i.id
        WHERE i.name = :q", ["q" => substr($q, 4)]));
        } else {
            return $this->response->OK($this->GetFoods("
            LEFT JOIN recipe_ingredient ri ON r.id = ri.recipe
            LEFT JOIN ingredient i ON ri.ingredient = i.id
        WHERE i.name LIKE :q
            OR r.name LIKE :q
            OR r.description LIKE :q", ["q" => "%$q%"]));
        }
    }
    /** @return Recipe[] */
    public function GetFilterResults(array $goodDiets, array $badDiets, array $goodDishes, array $badDishes) {
        $whereClauseParts = [];
        $whereParams = [];
        if(count($goodDiets) > 0) {
            $info = $this->db->CreateInClause($goodDiets, "gi");
            $len = count($info["paramsObj"]);
            $whereClauseParts[] = "r.id IN (
                SELECT r.id FROM recipe r
                    INNER JOIN recipe_diet ri ON r.id = ri.recipe
                    INNER JOIN diet i ON ri.diet = i.id
                WHERE i.name IN (".$info["inClause"].")
                GROUP BY r.id
                HAVING COUNT(i.id) = $len
            )";
            $whereParams = array_merge($whereParams, $info["paramsObj"]);
        }
        if(count($goodDishes) > 0) {
            $info = $this->db->CreateInClause($goodDishes, "gd");
            $len = count($info["paramsObj"]);
            $whereClauseParts[] = "r.id IN (
                SELECT r.id FROM recipe r
                    INNER JOIN dish d ON r.dish = d.id
                WHERE d.name IN (".$info["inClause"].")
                GROUP BY r.id
                HAVING COUNT(d.id) = $len
            )";
            $whereParams = array_merge($whereParams, $info["paramsObj"]);
        }
        if(count($badDiets) > 0) {
            $info = $this->db->CreateInClause($badDiets, "bi");
            $whereClauseParts[] = "r.id NOT IN (
                SELECT r.id FROM recipe r
                    INNER JOIN recipe_diet ri ON r.id = ri.recipe
                    INNER JOIN diet i ON ri.diet = i.id
                WHERE i.name IN (".$info["inClause"].")
            )";
            $whereParams = array_merge($whereParams, $info["paramsObj"]);
        }
        if(count($badDishes) > 0) {
            $info = $this->db->CreateInClause($badDishes, "bd");
            $whereClauseParts[] = "r.id NOT IN (
                SELECT r.id FROM recipe r
                    INNER JOIN dish d ON r.dish = d.id
                WHERE d.name IN (".$info["inClause"].")
            )";
            $whereParams = array_merge($whereParams, $info["paramsObj"]);
        }
        
        if(count($whereClauseParts) === 0) { return $this->response->Error("Please specify at least one filter."); }
        return $this->response->OK($this->GetFoods("
            INNER JOIN recipe_diet ri ON r.id = ri.recipe
            INNER JOIN diet i ON ri.diet = i.id
        WHERE ".implode(" AND ", $whereClauseParts), $whereParams));
    }

    // these two might not be used anymore
    /** @return NamedEmoji[] */
    public function GetDiets() { return $this->response->OK($this->db->GetObjects("NamedEmoji", "SELECT name, emoji FROM diet ORDER BY name ASC")); }
    /** @return NamedEmoji[] */
    public function GetDishes() { return $this->response->OK($this->db->GetObjects("NamedEmoji", "SELECT name, emoji FROM dish WHERE spiceOnly = 0 ORDER BY name ASC")); }

    /** @return CountrySummary[] */
    public function GetCountries() { // TODO: change realFirstLetter to Sort name or something
        return $this->response->OK($this->db->GetObjects("CountrySummary", "
            SELECT ckey, name, realFirstLetter AS sortName
            FROM country
            ORDER BY IFNULL(realFirstLetter, name) ASC
        "));
    }
    /** @return Country */
    public function GetCountry(string $countryCode) {
        $country = $this->db->GetObject("Country", "
            SELECT
                id, ckey, name, description, population, popEstimate, area, independence, indFrom, demonym, currency, motto, 
                languages, foodURL, musicURL, realFirstLetter, focusArea
            FROM country
            WHERE ckey = :ckey", ["ckey" => $countryCode]);
        if($country == null) { return $this->response->Error("Country not found."); }
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
        return $this->response->OK($country);
    }
    /** @return Recipe[] */
    private function GetFoods(string $whereClause, array $params = [], string $orderBy = "ORDER BY r.date DESC") {
        $recipes = $this->db->GetObjects("Recipe", "
            SELECT DISTINCT c.ckey AS countryCode, c.name AS countryName, r.id, r.name, d.name AS dish, d.emoji AS dishEmoji, r.url, r.date, r.img, r.databee, r.description, r.favorite
            FROM recipe r
                INNER JOIN country c ON r.country = c.id
                INNER JOIN dish d ON r.dish = d.id
            $whereClause
            $orderBy", $params);
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
            unset($recipe->id);
        }
        return $recipes;
    }
    /** @return SongInfo[] */
    private function GetSongs(string $whereClause, array $params = [], string $orderBy = "") {
        return $this->db->GetObjects("SongInfo", "
            SELECT c.ckey AS countryCode, s.name, s.url, s.favorite, s.translation
            FROM song s
                INNER JOIN country c ON s.country = c.id
            $whereClause
            $orderBy", $params);
    }
#endregion
#region "Spiceapedia"
    public function GetSeasoning(string $name) {
        $seasoning = $this->SeasoningsQuery("WHERE s.name = :n", ["n" => $name]);
        if(count($seasoning) === 0) { return $this->response->Error("Seasoning not found."); }
        return $this->response->OK($seasoning[0]);
    }
    private function SeasoningsQuery(string $whereClause, array $whereParams) {
        $seasonings = $this->db->GetObjects("Seasoning", "
            SELECT s.id, s.name, s.origin, s.description, s.emoji,
                CASE
                    WHEN s.type = 0 THEN 'herb'
                    WHEN s.type = 1 THEN 'spice'
                    WHEN s.type = 2 THEN 'blend'
                END AS type, s.species, s.imagedesc, s.imagename, s.imageauthor, s.imageurl, s.authorurl,
                l.code AS license, l.url AS licenseurl
            FROM seasoning s
                INNER JOIN license l ON s.license = l.id
            $whereClause", $whereParams);
        foreach($seasonings as $s) {
            $s->synonyms = $this->db->GetStrings("SELECT synonym FROM seasoning_synonym WHERE seasoning = :i", ["i" => $s->id]);
            $s->dishes = $this->db->GetStrings("
                SELECT d.name
                FROM seasoning_dish sd
                    INNER JOIN dish d ON sd.dish = d.id
                WHERE sd.seasoning = :i", ["i" => $s->id]);
            $s->flavors = $this->db->GetStrings("
                SELECT f.name
                FROM seasoning_flavor sf
                    INNER JOIN flavor f ON sf.flavor = f.id
                WHERE sf.seasoning = :i", ["i" => $s->id]);
            $s->foods = $this->db->GetStrings("
                SELECT i.name
                FROM seasoning_ingredient si
                    INNER JOIN ingredient i ON si.ingredient = i.id
                WHERE si.seasoning = :i", ["i" => $s->id]);
            // TODO: combine seasoning_pairs and seasoning_related with a type column?
            $s->pairsWith = $this->db->GetStrings("
                SELECT s.name
                FROM seasoning_pairs sp
                    INNER JOIN seasoning s ON sp.seasoning2 = s.id
                WHERE sp.seasoning1 = :i", ["i" => $s->id]);
                $s->relatedSpices = $this->db->GetStrings("
                SELECT s.name
                FROM seasoning_related sr
                    INNER JOIN seasoning s ON sr.relatedSeasoning = s.id
                WHERE sr.seasoning = :i", ["i" => $s->id]);
            $s->recipes = $this->db->GetDataTable("
                SELECT sr.name, sr.url, 0 AS local
                FROM seasoning_recipe sr
                WHERE sr.seasoning = :i
                UNION ALL
                SELECT r.name, r.url, 1 AS local
                FROM seasoning_ingredient si
                    INNER JOIN recipe_ingredient ri ON si.ingredient = ri.ingredient
                    INNER JOIN recipe r ON ri.recipe = r.id
                WHERE si.seasoning = :i
                ORDER BY local ASC, name ASC", ["i" => $s->id]);
            unset($s->id);
        }
        return $seasonings;
    }
#endregion
}
?>