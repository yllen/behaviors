<?php
/**
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Behaviors plugin for GLPI.

 Behaviors is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 Behaviors is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Behaviors. If not, see <http://www.gnu.org/licenses/>.

 @package   behaviors
 @author    Nelly Mahu-Lasson
 @copyright Copyright (c) 2022 Behaviors plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/behaviors
 @link      http://www.glpi-project.org/
 @since     version 0.83.4

 --------------------------------------------------------------------------
 */
if (!defined('GLPI_ROOT')) {
   include ('../../../inc/includes.php');
}


// for big database
ini_set("max_execution_time", "0");
ini_set("memory_limit", "-1");

global $DB;

$sql = "SELECT `table_name`
        FROM information_schema.tables
        WHERE `table_schema` = '".$DB->dbdefault."'
        AND `table_name` LIKE 'glpi_%'";
$tables = $DB->request($sql);

foreach ($tables as $table) {
   if ($DB->FieldExists($table['table_name'], 'tickets_id')) {
         $query = "ALTER TABLE ".$table['table_name'] ." CHANGE `tickets_id` `tickets_id` INT(11) UNSIGNED NOT NULL;";
         $DB->queryOrDie($query);
   }
   if ($DB->FieldExists($table['table_name'], 'changes_id')) {
      $query = "ALTER TABLE ".$table['table_name'] ." CHANGE `changes_id` `changes_id` INT(11) UNSIGNED NOT NULL;";
      $DB->queryOrDie($query);
   }
   if ($DB->FieldExists($table['table_name'], 'items_id')) {
         $query = "ALTER TABLE ".$table['table_name'] ." CHANGE `items_id` `items_id` INT(11) UNSIGNED NOT NULL;";
         $DB->queryOrDie($query);
   }
}

$query = "ALTER TABLE `glpi_tickets` CHANGE `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;";
$DB->queryOrDie($query);

$query = "ALTER TABLE `glpi_changes` CHANGE `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;";
$DB->queryOrDie($query);