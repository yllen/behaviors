<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2011 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 --------------------------------------------------------------------------
*/

class PluginBehaviorsConfig extends CommonDBTM {

   static private $_instance = NULL;

   function canCreate() {
      return haveRight('config', 'w');
   }

   function canView() {
      return haveRight('config', 'r');
   }

   static function getTypeName() {
      global $LANG;

      return $LANG['common'][12];
   }

   function getName($with_comment=0) {
      global $LANG;

      return $LANG['plugin_behaviors'][0];
   }

   /**
    * Singleton for the unique config record
    */
   static function getInstance() {

      if (!isset(self::$_instance)) {
         self::$_instance = new self();
         if (!self::$_instance->getFromDB(1)) {
            self::$_instance->getEmpty();
         }
      }
      return self::$_instance;
   }

   static function install() {
      global $DB, $LANG;

      $table = 'glpi_plugin_behaviors_configs';
      if (!TableExists($table)) { //not installed

         $query = "CREATE TABLE `$table` (
                     `id` int(11) NOT NULL,
                     `use_requester_item_group` tinyint(1) NOT NULL default '0',
                     `use_requester_user_group` tinyint(1) NOT NULL default '0',
                     `is_ticketsolutiontype_mandatory` tinyint(1) NOT NULL default '0',
                     `is_ticketrealtime_mandatory` tinyint(1) NOT NULL default '0',
                     `is_requester_mandatory` tinyint(1) NOT NULL default '0',
                     `is_ticketdate_locked` tinyint(1) NOT NULL default '0',
                     `use_assign_user_group` tinyint(1) NOT NULL default '0',
                     `sql_user_group_filter` varchar(255) default NULL,
                     `sql_tech_group_filter` varchar(255) default NULL,
                     `tickets_id_format` VARCHAR(15) NULL,
                     `remove_from_ocs` tinyint(1) NOT NULL default '0',
                     `date_mod` datetime default NULL,
                     `comment` text,
                     PRIMARY KEY  (`id`)
                   ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
         $DB->query($query) or die($LANG['update'][90] . "&nbsp;:<br>" . $DB->error());

         $query = "INSERT INTO `$table` (id, date_mod) VALUES (1, NOW())";
         $DB->query($query) or die($LANG['update'][90] . "&nbsp;:<br>" . $DB->error());
      } else {
         // Upgrade

         $changes = array();

         if (!FieldExists($table,'tickets_id_format')) {
            $changes[] = "ADD `tickets_id_format` VARCHAR( 15 ) NULL";
         }
         if (!FieldExists($table,'remove_from_ocs')) {
            $changes[] = "ADD `remove_from_ocs` tinyint(1) NOT NULL default '0'";
         }
         if (!FieldExists($table,'is_requester_mandatory')) {
            $changes[] = "ADD `is_requester_mandatory` tinyint(1) NOT NULL default '0'";
         }
         // version 0.1.5 - feature #2801 Forbid change of ticket's creation date
         if (!FieldExists($table,'is_ticketdate_locked')) {
            $changes[] = "ADD `is_ticketdate_locked` tinyint(1) NOT NULL default '0'";
         }
         // Version 0.2.0 - set_use_date_on_state now handle in GLPI
         if (FieldExists($table,'set_use_date_on_state')) {
            $changes[] = "DROP `set_use_date_on_status`";
         }

         if (count($changes)>0) {
            $query="ALTER TABLE `$table` ".implode(",\n", $changes);
            $DB->query($query) or die($LANG['update'][90] . "&nbsp;:<br>" . $DB->error());
         }
      }

      return true;
   }

   static function uninstall() {
      global $DB;

      if (TableExists('glpi_plugin_behaviors_configs')) { //not installed

         $query = "DROP TABLE `glpi_plugin_behaviors_configs`";
         $DB->query($query) or die($DB->error());
      }
      return true;
   }

   static function getHeadings($item, $withtemplate) {
      global $LANG;

      if (get_class($item)=='Config') {
            return array(1 => $LANG['plugin_behaviors'][0]);
      }
      return false;
   }

   static function showHeadings($item) {

      if (get_class($item)=='Config') {
            return array(1 => array('PluginBehaviorsConfig', 'showConfigForm'));
      }
      return false;
   }

   static function showConfigForm($item, $withtemplate) {
      global $LANG;

      $config = self::getInstance();

      $config->showFormHeader();

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='2' class='tab_bg_2 b center'>".$LANG['common'][34]."</td>";   // User
      echo "<td colspan='2' class='tab_bg_2 b center'>".$LANG['Menu'][38]."</td>";     // Inventory
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['plugin_behaviors'][3]."&nbsp;:</td><td>";
      echo "<input type='text' name='sql_user_group_filter' value='".
           htmlentities($config->fields['sql_user_group_filter'],ENT_QUOTES, 'UTF-8')."' size='25'>";
      echo "</td><td colspan='2'>&nbsp;";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['plugin_behaviors'][4]."&nbsp;:</td><td>";
      echo "<input type='text' name='sql_tech_group_filter' value='".
           htmlentities($config->fields['sql_tech_group_filter'],ENT_QUOTES, 'UTF-8')."' size='25'></td>";
      echo "<td>".$LANG['plugin_behaviors'][11]."&nbsp;:</td><td>";
      $plugin = new Plugin();
      if ($plugin->isActivated('uninstall')) {
         Dropdown::showYesNo('remove_from_ocs', $config->fields['remove_from_ocs']);
      } else {
         echo $LANG['plugin_behaviors'][12];
      }
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='2' class='tab_bg_2 b center'>".$LANG['job'][13]."</td>";
      echo "<td rowspan='10' colspan='2' class='top'>".$LANG['common'][25]."&nbsp;:<br>";
      echo "<textarea cols='60' rows='12' name='comment' >".$config->fields['comment']."</textarea>";
      echo "<br>".$LANG['common'][26]."&nbsp;: ";
      echo convDateTime($config->fields["date_mod"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['plugin_behaviors'][10]."&nbsp;:</td><td>";
      $tab = array('NULL' => '-----');
      foreach (array('Y000001', 'Ym0001', 'Ymd01', 'ymd0001') as $fmt) {
         $tab[$fmt] = date($fmt) . '  (' . $fmt . ')';
      }
      Dropdown::showFromArray("tickets_id_format", $tab, array('value' => $config->fields['tickets_id_format']));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['plugin_behaviors'][1]."&nbsp;:</td><td>";
      Dropdown::showYesNo("use_requester_item_group", $config->fields['use_requester_item_group']);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['plugin_behaviors'][2]."&nbsp;:</td><td>";
      Dropdown::showYesNo("use_requester_user_group", $config->fields['use_requester_user_group']);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['plugin_behaviors'][6]."&nbsp;:</td><td>";
      Dropdown::showYesNo("use_assign_user_group", $config->fields['use_assign_user_group']);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['plugin_behaviors'][13]."&nbsp;:</td><td>";
      Dropdown::showYesNo("is_requester_mandatory", $config->fields['is_requester_mandatory']);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>"; // Ticket - Update
      echo "<td colspan='2' class='tab_bg_2 b center'>".$LANG['job'][38].' - '.$LANG['buttons'][14];
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['plugin_behaviors'][7]."&nbsp;:</td><td>";
      Dropdown::showYesNo("is_ticketrealtime_mandatory", $config->fields['is_ticketrealtime_mandatory']);
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['plugin_behaviors'][8]."&nbsp;:</td><td>";
      Dropdown::showYesNo("is_ticketsolutiontype_mandatory", $config->fields['is_ticketsolutiontype_mandatory']);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['plugin_behaviors'][14]."&nbsp;:</td><td>";
      Dropdown::showYesNo("is_ticketdate_locked", $config->fields['is_ticketdate_locked']);
      echo "</td></tr>";

      $config->showFormButtons(array('candel'=>false));

      return false;
   }

   function prepareInputForAdd($input) {
      global $LANG, $DB;

      if (isset($input['sql_user_group_filter']) && !empty($input['sql_user_group_filter'])) {
         $sql = "SELECT id
                 FROM `glpi_groups`
                 WHERE (".stripslashes($input['sql_user_group_filter']).")";
         $res = $DB->query($sql);
         if ($res) {
            $DB->free_result($res);
         } else {
            addMessageAfterRedirect($LANG['plugin_behaviors'][5] .
                                       " (".stripslashes($input['sql_user_group_filter']).")",
                                    false, ERROR);
            addMessageAfterRedirect($DB->error());
            unset($input['sql_user_group_filter']);
         }
      }
      if (isset($input['sql_tech_group_filter']) && !empty($input['sql_tech_group_filter'])) {
         $sql = "SELECT id
                 FROM `glpi_groups`
                 WHERE (".stripslashes($input['sql_tech_group_filter']).")";
         $res = $DB->query($sql);
         if ($res) {
            $DB->free_result($res);
         } else {
            addMessageAfterRedirect($LANG['plugin_behaviors'][5] .
                                       " (".stripslashes($input['sql_tech_group_filter']).")",
                                    false, ERROR);
            addMessageAfterRedirect($DB->error());
            unset($input['sql_tech_group_filter']);
         }
      }
      return $input;
   }

   function prepareInputForUpdate($input) {
      return $this->prepareInputForAdd($input);
   }
}

?>