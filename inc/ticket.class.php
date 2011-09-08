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

class PluginBehaviorsTicket {

   static function beforeAdd(Ticket $ticket) {
      global $DB, $LANG;

      if (!is_array($ticket->input) || !count($ticket->input)) {
         // Already cancel by another plugin
         return false;
      }


      //Toolbox::logDebug("PluginBehaviorsTicket::beforeAdd(), Ticket=", $ticket);
      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('tickets_id_format')) {
         $max = 0;
         $sql = 'SELECT MAX( id ) AS max
                 FROM `glpi_tickets`';
         foreach ($DB->request($sql) as $data) {
            $max = $data['max'];
         }
         $want = date($config->getField('tickets_id_format'));
         if ($max < $want) {
            $DB->query("ALTER TABLE `glpi_tickets` AUTO_INCREMENT=$want");
         }
      }

      if (!isset($ticket->input['_auto_import'])
          && $_SESSION['glpiactiveprofile']['interface'] == 'central') {
         if ($config->getField('is_requester_mandatory') && !$ticket->input['_users_id_requester']) {
            Session::addMessageAfterRedirect($LANG['plugin_behaviors'][13], true, ERROR);
            $ticket->input = array();
            return true;

         }
      }

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

      // No Auto set Import for external source -> Duplicate from Ticket->prepareInputForAdd()
      if (!isset($ticket->input['_auto_import'])) {
         if (!isset($ticket->input['_users_id_requester'])) {
            if ($uid = Session::getLoginUserID()) {
               $ticket->input['_users_id_requester'] = $uid;
            }
         }
      }

      if ($config->getField('use_requester_user_group')
          && isset($ticket->input['_users_id_requester'])
          && $ticket->input['_users_id_requester']>0
          && (!isset($ticket->input['_groups_id_requester']) || $ticket->input['_groups_id_requester']<=0)) {
         $ticket->input['_groups_id_requester']
            = PluginBehaviorsUser::getRequesterGroup($ticket->input['entities_id'],
                                                     $ticket->input['_users_id_requester']);
      }

      if ($config->getField('use_assign_user_group')
          && isset($ticket->input['_users_id_assign'])
          && $ticket->input['_users_id_assign']>0
          && (!isset($ticket->input['_groups_id_assign']) || $ticket->input['_groups_id_assign']<=0)) {
         $ticket->input['_groups_id_assign']
            = PluginBehaviorsUser::getTechnicianGroup($ticket->input['entities_id'],
                                                      $ticket->input['_users_id_assign']);
      }
      // Toolbox::logDebug("PluginBehaviorsTicket::beforeAdd(), Updated input=", $ticket->input);
   }


   static function beforeUpdate(Ticket $ticket) {
      global $LANG;

      if (!is_array($ticket->input) || !count($ticket->input)) {
         // Already cancel by another plugin
         return false;
      }

      //Toolbox::logDebug("PluginBehaviorsTicket::beforeUpdate(), Ticket=", $ticket);
      $config = PluginBehaviorsConfig::getInstance();

      // Check is the connected user is a tech
      if (!is_numeric(Session::getLoginUserID(false)) || !Session::haveRight('own_ticket',1)) {
         return false; // No check
      }

      if (isset($ticket->input['date'])) {
         if ($config->getField('is_ticketdate_locked')) {
            unset($ticket->input['date']);
         }
      }

      $sol = (isset($ticket->input['ticketsolutiontypes_id'])
                    ? $ticket->input['ticketsolutiontypes_id']
                    : $ticket->fields['ticketsolutiontypes_id']);
      $dur = (isset($ticket->input['actiontime'])
                    ? $ticket->input['actiontime']
                    : $ticket->fields['actiontime']);

      // Wand to solve/close the ticket
      if ((isset($ticket->input['ticketsolutiontypes_id'])
             &&  $ticket->input['ticketsolutiontypes_id'])
          || (isset($ticket->input['status'])
             && in_array($ticket->input['status'], array('solved','closed')))) {

         if ($config->getField('is_ticketrealtime_mandatory')) {
            if (!$dur) {
               unset($ticket->input['status']);
               unset($ticket->input['ticketsolutiontypes_id']);
               Session::addMessageAfterRedirect($LANG['plugin_behaviors'][101], true, ERROR);
            }
         }
         if ($config->getField('is_ticketsolutiontype_mandatory')) {
            if (!$sol) {
               unset($ticket->input['status']);
               Session::addMessageAfterRedirect($LANG['plugin_behaviors'][100], true, ERROR);
            }
         }
      }

      //Toolbox::logDebug("PluginBehaviorsTicket::beforeUpdate(), Updated input=", $ticket->input);
   }
}
