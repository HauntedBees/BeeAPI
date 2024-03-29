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
#region "Areund the World"
class NamedEmoji {
    public int $id;
    public string $name;
    public string $emoji;
}
class IdNamePair {
    public int $id;
    public string $name;
}
class CountrySummary {
    public string $ckey;
    public string $name;
    public ?string $sortName;
}
class Country {
    public int $id;
    public string $ckey;
    public string $name;
    public string $description;
    public int $population;
    public int $popEstimate;
    public int $area;
    public ?string $independence;
    public ?string $indFrom;
    public string $demonym;
    public string $currency;
    public ?string $motto;
    public string $foodURL;
    public string $musicURL;
    public string $languages;
    public ?string $realFirstLetter;
    public ?string $focusArea;
    public array $neighbors; // CountryNeighbor[]
    public array $food; // Recipe[]
    public array $music; // SongInfo[]
    public ?string $notes;
}
class Recipe {
    public int $id;
    public string $countryCode;
    public string $countryName;
    public string $name;
    public string $dish;
    public string $dishEmoji;
    public string $url;
    public string $date;
    public string $img;
    public string $description;
    public bool $favorite;
    public array $diet; // DietInfo[]
    public array $ingredients; // string[]
    public ?string $databee;
}
class SongInfo {
    public string $countryCode;
    public string $countryName;
    public string $name;
    public string $url;
    public bool $favorite;
    public ?string $translation;
}
class DietInfo {
    public string $name;
    public string $emoji;
    public string $description;
    public bool $optional;
}

class CountryNeighbor {
    public ?string $realCountryCode;
    public ?string $realCountryName;
    public ?string $shellCountryName;
}
class AdminCountryNeighbor {
    public int $id;
    public string $countryCode;
    public string $name;
    public bool $shell;
}
class AdminRecipe {
    public int $id;
    public string $name;
    public int $dish;
    public string $url;
    public string $date;
    public string $img;
    public string $description;
    public bool $favorite;
    public array $diet; // AdminDietInfo[]
    public array $ingredients; // string[]
    public ?string $databee;
}
class AdminDietInfo {
    public int $diet;
    public string $description;
    public bool $optional;
}
#endregion
#region "Spiceapedia"
class Seasoning {
    public int $id;
    public string $name;
    public array $synonyms; // string[]
    public string $origin;
    public string $description;
    public string $emoji;
    public string $species;
    public string $type;
    public array $dishes; // string[]
    public array $flavors; // string[]
    public array $foods; // string[]
    public array $pairsWith; // Seasoning[]
    public array $relatedSpices; // Seasoning[]
    public array $components; // Seasoning[]
    public array $recipes; // { name: string, url: string, local: bool }[]
    public string $imagedesc;
    public string $imagename;
    public string $imageauthor;
    public string $imageurl;
    public string $authorurl;
    public string $license;
    public string $licenseurl;
}
class ListSeasoning {
    public int $type;
    public string $name;
}
#endregion
?>