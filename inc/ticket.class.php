<?php
/*
 ----------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2008 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org/
 ----------------------------------------------------------------------

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
 ------------------------------------------------------------------------
*/

class PluginBehaviorsTicket {

   static function getUserGroup ($entity, $userid, $filter='') {
      global $DB;

      $config = PluginBehaviorsConfig::getInstance();

      $query = "SELECT glpi_groups.id
                FROM glpi_groups_users
                INNER JOIN glpi_groups ON (glpi_groups.id = glpi_groups_users.groups_id)
                WHERE glpi_groups_users.users_id='$userid'".
                getEntitiesRestrictRequest(' AND ', 'glpi_groups', '', $entity, true);

      $crit = ($filter ? $config->getField($filter) : '');
      if ($crit) {
         $query .= "AND ($crit)";
      }
      foreach ($DB->request($query) as $data) {
         //logDebug("getUserGroup($entity,$userid,$filter):", $data['id']);
         return ($data['id']);
      }
      //logDebug("getUserGroup($entity,$userid,$filter): Not found");
      return 0;
   }

   static function getRequesterGroup ($entity, $userid) {

      return self::getUserGroup($entity, $userid, 'sql_user_group_filter');
   }

   static function getTechnicianGroup ($entity, $userid) {

      return self::getUserGroup($entity, $userid, 'sql_tech_group_filter');
   }

   static function beforeAdd(Ticket $ticket) {
      // logDebug("PluginBehaviorsTicket::beforeAdd(), Ticket=", $ticket);

      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('use_requester_item_group')
          && isset($ticket->input['itemtype'])
          && isset($ticket->input['items_id'])
          && $ticket->input['items_id']>0
          && class_exists($ticket->input['itemtype'])
          && (!isset($ticket->input['groups_id']) || $ticket->input['groups_id']<=0)) {

         $item = new $ticket->input['itemtype']();
         if ($item->isField('groups_id')
             && $item->getFromDB($ticket->input['items_id'])) {
            $ticket->input['groups_id'] = $item->getField('groups_id');
        }
      }

      if ($config->getField('use_requester_user_group')
          && isset($ticket->input['users_id'])
          && $ticket->input['users_id']>0
          && (!isset($ticket->input['groups_id']) || $ticket->input['groups_id']<=0)) {
        $ticket->input['groups_id'] = self::getRequesterGroup($ticket->input['entities_id'],
                                                              $ticket->input['users_id']);
      }

      if ($config->getField('use_assign_user_group')
          && isset($ticket->input['users_id_assign'])
          && $ticket->input['users_id_assign']>0
          && (!isset($ticket->input['groups_id_assign']) || $ticket->input['groups_id_assign']<=0)) {
        $ticket->input['groups_id_assign'] = self::getTechnicianGroup($ticket->input['entities_id'],
                                                                      $ticket->input['users_id_assign']);
      }
      // logDebug("PluginBehaviorsTicket::beforeAdd(), Updated input=", $ticket->input);
   }
}
?>