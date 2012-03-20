<?php
/**
 * @version $Id$
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
 @author    Remi Collet
 @copyright Copyright (c) 2010-2011 Behaviors plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.indepnet.net/projects/behaviors
 @link      http://www.glpi-project.org/
 @since     2010

 --------------------------------------------------------------------------
*/

class PluginBehaviorsTicket {


   static function addEvents(NotificationTargetTicket $target) {
      global $LANG;

      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('add_notif')) {
         Plugin::loadLang('behaviors');
         $target->events['plugin_behaviors_ticketnewtech'] = $LANG['plugin_behaviors'][16];
         $target->events['plugin_behaviors_ticketnewgrp']  = $LANG['plugin_behaviors'][17];
         $target->events['plugin_behaviors_ticketreopen']  = $LANG['plugin_behaviors'][18];
      }
   }


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
          && isset($_SESSION['glpiactiveprofile']['interface'])
          && $_SESSION['glpiactiveprofile']['interface'] == 'central') {
         if ($config->getField('is_requester_mandatory')
             && !$ticket->input['_users_id_requester']
             && (!isset($ticket->input['_users_id_requester_notif']['alternative_email'])
                 || empty($ticket->input['_users_id_requester_notif']['alternative_email']))) {
            Session::addMessageAfterRedirect($LANG['plugin_behaviors'][13], true, ERROR);
            $ticket->input = array();
            return true;

         }
      }

      if ($config->getField('use_requester_item_group')
          && isset($ticket->input['itemtype'])
          && isset($ticket->input['items_id'])
          && $ticket->input['items_id']>0
          && ($item = getItemForItemtype($ticket->input['itemtype']))
          && (!isset($ticket->input['groups_id']) || $ticket->input['groups_id']<=0)) {

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
            if ($config->getField('use_requester_user_group') == 1) {
               // First group
               $ticket->input['_groups_id_requester']
                  = PluginBehaviorsUser::getRequesterGroup($ticket->input['entities_id'],
                                                           $ticket->input['_users_id_requester'],
                                                           true);
            } else {
               // All groups
               $ticket->input['_additional_groups_requesters']
                  = PluginBehaviorsUser::getRequesterGroup($ticket->input['entities_id'],
                                                           $ticket->input['_users_id_requester'],
                                                           false);

            }
      }
      // Toolbox::logDebug("PluginBehaviorsTicket::beforeAdd(), Updated input=", $ticket->input);
   }


   static function afterPrepareAdd(Ticket $ticket) {
      global $DB, $LANG;

      if (!is_array($ticket->input) || !count($ticket->input)) {
         // Already cancel by another plugin
         return false;
      }

      // Toolbox::logDebug("PluginBehaviorsTicket::afterPrepareAdd(), Ticket=", $ticket);
      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('use_assign_user_group')
          && isset($ticket->input['_users_id_assign'])
          && $ticket->input['_users_id_assign']>0
          && (!isset($ticket->input['_groups_id_assign']) || $ticket->input['_groups_id_assign']<=0)) {
         if ($config->getField('use_assign_user_group')==1) {
            // First group
            $ticket->input['_groups_id_assign']
               = PluginBehaviorsUser::getTechnicianGroup($ticket->input['entities_id'],
                                                         $ticket->input['_users_id_assign'],
                                                         true);
         } else {
            // All groups
            $ticket->input['_additional_groups_assigns"']
               = PluginBehaviorsUser::getTechnicianGroup($ticket->input['entities_id'],
                                                         $ticket->input['_users_id_assign'],
                                                         false);
         }
      }
      // Toolbox::logDebug("PluginBehaviorsTicket::afterPrepareAdd(), Updated input=", $ticket->input);
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

      $sol = (isset($ticket->input['solutiontypes_id'])
                    ? $ticket->input['solutiontypes_id']
                    : $ticket->fields['solutiontypes_id']);
      $dur = (isset($ticket->input['actiontime'])
                    ? $ticket->input['actiontime']
                    : $ticket->fields['actiontime']);

      // Wand to solve/close the ticket
      if ((isset($ticket->input['solutiontypes_id'])
             &&  $ticket->input['solutiontypes_id'])
          || (isset($ticket->input['solution'])
              &&    $ticket->input['solution'])
          || (isset($ticket->input['status'])
               && in_array($ticket->input['status'], array('solved','closed')))) {

         if ($config->getField('is_ticketrealtime_mandatory')) {
            if (!$dur) {
               unset($ticket->input['status']);
               unset($ticket->input['solution']);
               unset($ticket->input['solutiontypes_id']);
               Session::addMessageAfterRedirect($LANG['plugin_behaviors'][101], true, ERROR);
            }
         }
         if ($config->getField('is_ticketsolutiontype_mandatory')) {
            if (!$sol) {
               unset($ticket->input['status']);
               unset($ticket->input['solution']);
               unset($ticket->input['solutiontypes_id']);
               Session::addMessageAfterRedirect($LANG['plugin_behaviors'][100], true, ERROR);
            }
         }
      }

      //Toolbox::logDebug("PluginBehaviorsTicket::beforeUpdate(), Updated input=", $ticket->input);
   }


   static function onNewTicket($item) {
      global $DB, $LANG;

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         if (strstr($_SERVER['PHP_SELF'], "/front/ticket.form.php")
                 AND isset($_POST['id'])
                 AND $_POST['id'] == 0
                 AND !isset($_GET['id'])) {

            $config = PluginBehaviorsConfig::getInstance();

            // Only if config to add the "first" group
            if ($config->getField('use_requester_user_group')==1
                && isset($_POST['_users_id_requester'])
                && $_POST['_users_id_requester']>0
                && (!isset($_POST['_groups_id_requester']) || $_POST['_groups_id_requester']<=0)) {

                  // First group
                  $_REQUEST['_groups_id_requester']
                     = PluginBehaviorsUser::getRequesterGroup($_POST['entities_id'],
                                                              $_POST['_users_id_requester'],
                                                              true);
            }
         }
      }
   }


   static function afterUpdate(Ticket $ticket) {
      // Toolbox::logDebug("PluginBehaviorsTicket::afterUpdate(), Ticket=", $ticket);

      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('add_notif')
          && in_array('status', $ticket->updates)
          && in_array($ticket->oldvalues['status'], array('closed', 'solved'))
          && !in_array($ticket->input['status'], array('closed', 'solved'))) {

         NotificationEvent::raiseEvent('plugin_behaviors_ticketreopen', $ticket);
      }
   }
}
