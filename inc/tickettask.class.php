<?php
/**
 * @version $Id:  yllen $
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
 @author    Remi Collet, Nelly Mahu-Lasson
 @copyright Copyright (c) 2018 Behaviors plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/behaviors
 @link      http://www.glpi-project.org/
 @since     2010

 --------------------------------------------------------------------------
*/

class PluginBehaviorsTicketTask {


   static function beforeAdd(TicketTask $taskticket) {

      if (!is_array($taskticket->input) || !count($taskticket->input)) {
         // Already cancel by another plugin
         return false;
      }

      //Toolbox::logDebug("PluginBehaviorsTicket::beforeAdd(), Ticket=", $ticket);
      $config = PluginBehaviorsConfig::getInstance();

      // Check is the connected user is a tech
      if (!is_numeric(Session::getLoginUserID(false))
          || !Session::haveRight('ticket', UPDATE)) {
         return false; // No check
      }

      // Wand to solve/close the ticket
      if ($config->getField('is_tickettaskcategory_mandatory')) {
         if ($taskticket->input['taskcategories_id'] == 0) {
            $taskticket->input = false;
            Session::addMessageAfterRedirect(__("Category of task is mandatory",
                                                'behaviors'), true, ERROR);
            return;
         }
      }
   }


   static function beforeUpdate(TicketTask $taskticket) {

      if (!is_array($taskticket->input) || !count($taskticket->input)) {
         // Already cancel by another plugin
         return false;
      }

      //Toolbox::logDebug("PluginBehaviorsTicket::beforeAdd(), Ticket=", $ticket);
      $config = PluginBehaviorsConfig::getInstance();

      // Check is the connected user is a tech
      if (!is_numeric(Session::getLoginUserID(false))
            || !Session::haveRight('ticket', UPDATE)) {
         return false; // No check
      }

      // Wand to solve/close the ticket
      if ($config->getField('is_tickettaskcategory_mandatory')) {
         if (empty($taskticket->input['taskcategories_id'])) {
            $taskticket->input = false;
            Session::addMessageAfterRedirect(__("Category of task is mandatory",
                                                'behaviors'), true, ERROR);
            return;
         }
      }
      if ($config->getField('is_ticketsolution_mandatory')) {
         if (empty($soluce->input['content'])) {
            $soluce->input['content'] = $soluce->fields['content'];
            $soluce->input['solutiontypes_id'] = $soluce->fields['solutiontypes_id'];
            Session::addMessageAfterRedirect(__("Description of solution is mandatory before ticket is solved/closed",
                                                'behaviors'), true, ERROR);
         }
      }
   }
}
