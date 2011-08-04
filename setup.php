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

// Init the hooks of the plugins -Needed
function plugin_init_behaviors() {
   global $PLUGIN_HOOKS,$LANG,$CFG_GLPI;

   if (haveRight("config","w")) {
      $PLUGIN_HOOKS['headings']['behaviors']        = array('PluginBehaviorsConfig', 'getHeadings');
      $PLUGIN_HOOKS['headings_action']['behaviors'] = array('PluginBehaviorsConfig', 'showHeadings');

      $PLUGIN_HOOKS['config_page']['behaviors'] = 'front/config.form.php';
   }

   $PLUGIN_HOOKS['item_add']['behaviors'] = array(
      'Computer'           => array('PluginBehaviorsComputer',          'afterAdd'),
      'Monitor'            => array('PluginBehaviorsMonitor',           'afterAdd'),
      'NetworkEquipment'   => array('PluginBehaviorsNetworkEquipment',  'afterAdd'),
      'Peripheral'         => array('PluginBehaviorsPeripheral',        'afterAdd'),
      'Phone'              => array('PluginBehaviorsPhone',             'afterAdd'),
      'Printer'            => array('PluginBehaviorsPrinter',           'afterAdd'),
   );

   $PLUGIN_HOOKS['item_update']['behaviors'] = array(
      'Computer'           => array('PluginBehaviorsComputer',          'afterUpdate'),
      'Monitor'            => array('PluginBehaviorsMonitor',           'afterUpdate'),
      'NetworkEquipment'   => array('PluginBehaviorsNetworkEquipment',  'afterUpdate'),
      'Peripheral'         => array('PluginBehaviorsPeripheral',        'afterUpdate'),
      'Phone'              => array('PluginBehaviorsPhone',             'afterUpdate'),
      'Printer'            => array('PluginBehaviorsPrinter',           'afterUpdate'),
   );

   $PLUGIN_HOOKS['pre_item_add']['behaviors'] = array(
      'Ticket'    => array('PluginBehaviorsTicket',   'beforeAdd')
   );

   $PLUGIN_HOOKS['pre_item_update']['behaviors'] = array(
      'Ticket'    => array('PluginBehaviorsTicket',   'beforeUpdate')
   );

   $PLUGIN_HOOKS['pre_item_purge']['behaviors'] = array(
      'Computer'           => array('PluginBehaviorsComputer',          'beforePurge'),
   );
}

function plugin_version_behaviors() {
   global $LANG;

   return array('name'           => $LANG['plugin_behaviors'][0],
                'version'        => '0.83.0',
                'author'         => 'Remi Collet',
                'homepage'       => 'https://forge.indepnet.net/projects/behaviors',
                'minGlpiVersion' => '0.80');// For compatibility / no install in version < 0.72
}


// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_behaviors_check_prerequisites() {

   // Strict version check (could be less strict, or could allow various version)
   if (version_compare(GLPI_VERSION,'0.83','lt') || version_compare(GLPI_VERSION,'0.84','ge')) {
      echo "This plugin requires GLPI >= 0.83";
      return false;
   }
   return true;
}


// Check configuration process for plugin : need to return true if succeeded
// Can display a message only if failure and $verbose is true
function plugin_behaviors_check_config($verbose=false) {
   global $LANG;

   return true;
}

?>