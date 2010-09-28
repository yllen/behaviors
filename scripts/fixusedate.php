<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2010 by the INDEPNET Development Team.

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
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

chdir(dirname($_SERVER["SCRIPT_FILENAME"]));
ini_set("memory_limit","-1");
ini_set("max_execution_time", "0");

// Arguments
$entity = (isset($_SERVER['argv'][1]) ? intval($_SERVER['argv'][1]) : 0);
$modify = (isset($_SERVER['argv'][2]) ? ($_SERVER['argv'][2]=='run') : false);

define('GLPI_ROOT', '../../..');
include (GLPI_ROOT . "/inc/includes.php");

$plug = new Plugin();
if (!$plug->isActivated('behaviors')) {
   die("This plugin is not activated\n");
}
$config = PluginBehaviorsConfig::getInstance();

$stateid = $config->getField('set_use_date_on_state');
$sta = new State();
if ($stateid>0) {
   if ($sta->getFromDB($stateid)) {
      $state = $sta->getField('name');
   } else {
      die ("Unknown state $stateid, check configuration\n");
   }
} else {
   die("This plugin is not configured to set startup date\n");
}
$ent = new Entity();
$inf = new Infocom();

if ($state && $entity && $ent->getFromDB($entity)) {
   echo "\nUse_date computation for : '".$ent->fields['completename']."'\n";
   echo "From history changes, with state='$state'\n";

   $csvname = realpath(GLPI_DOC_DIR).'/_tmp/'.str_replace(' ', '_', $ent->fields['name']).($modify ? '-reel.csv' : '-test.csv');

   $csv = fopen($csvname, "w");
   if (!$csv) {
      die("Can't create '$csvname'\n");
   }
   // En-tete
   fputcsv($csv, array($LANG['state'][6], 'ID', $LANG['financial'][14], $LANG['financial'][76]), ';', '"');

   $sql = "SELECT id, itemtype, items_id, buy_date
           FROM glpi_infocoms
           WHERE entities_id = $entity
             AND buy_date IS NOT NULL
             AND use_date IS NULL";

   $read = $write = 0;
   foreach ($DB->request($sql) as $row) {
      $read++;
      $usedate = null;
      $sqldat  = "SELECT date_mod
                  FROM glpi_logs
                  WHERE itemtype = '".$row['itemtype']."'
                    AND items_id = '".$row['items_id']."'
                    AND linked_action = 0
                    AND id_search_option = 31
                    AND new_value = '$state'
                  ORDER BY id";
      foreach ($DB->request($sqldat) as $event) {
         $usedate = substr($event['date_mod'],0,10);
      }
      printf("%6d : %-20s # %6d : %s / %s\r",
         $read, $row['itemtype'], $row['items_id'],
         convDate($row['buy_date']), convDate($usedate)
      );
      if ($usedate) {
         fputcsv($csv, array($row['itemtype'], $row['items_id'], $row['buy_date'], $usedate), ';', '"');

         $input = array('id'       => $row['id'],
                        'use_date' => $usedate);
         if (!$modify) {
            $write++;
         } else if ($inf->update($input)) {
            $write++;
         }
      }
   }
   fclose($csv);
   echo "Read $read line(s), write $write line(s)";
   echo ", report in '$csvname'\n\n";
} else {
   echo "Usage : php fixusedate <ID entite>  [ run ]\n";
}
?>
