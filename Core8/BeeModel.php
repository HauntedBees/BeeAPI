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

class RawBeeModel {
    public function MapFromJSON(array $json) {
        $reflection = new ReflectionClass($this);
        $props = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        foreach($props as $prop) {
            $propName = $prop->getName();
            $value = isset($json[$propName]) ? $json[$propName] : null;
            BeeAttributesEnforcer::ValidateProperty($prop, $value);
            if(isset($value)) {
                $this->$propName = $value;
            }
        }
    }
    public function IncludesModifiedColumn(): bool { return true; }
}
class StringRequest extends RawBeeModel {
    public string $value;
}
class IntRequest extends RawBeeModel {
    public int $value;
}
class BeeModel extends RawBeeModel {
    public int $id = 0;
}
class AuthBeeModel extends BeeModel {
    public int $user = 0;
    public function GetUserId(): int {
        if($this->user === 0) {
            throw new Exception("Model with no user.");
        }
        return $this->user;
    }
    public function SetUserId(int $i): void {
        $this->user = $i;
    }
}
?>