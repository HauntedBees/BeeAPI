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
class GeneralController extends BeeController {
    public function GetRecipe(string $searchKey) {
        $searchKey = str_replace(".json", "", $searchKey);
        $json = @file_get_contents("./General/world.json", false);
        $recipeList = json_decode($json, true);
        if(strpos($searchKey, "|") !== false) { // individual recipe
            $recipe = $recipeList[$searchKey];
            if(is_null($recipe)) {
                echo "";
            } else {
                echo json_encode($recipe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        } else { // multiple recipes
            $response = [];
            foreach($recipeList as $k => $v) {
                if(strpos($k, $searchKey) === 0) {
                    $response[] = $v;
                }
            }
            if(count($response) === 0) {
                echo "";
            } else {
                echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }
    }
}
?>