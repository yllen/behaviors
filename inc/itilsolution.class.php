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
 @author    Remi Collet, Nelly Mahu-Lasson
 @copyright Copyright (c) 2018-2022 Behaviors plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/behaviors
 @link      http://www.glpi-project.org/
 @since     2010

 --------------------------------------------------------------------------
*/

class PluginBehaviorsITILSolution {


   static function beforeAdd(ITILSolution $soluce) {
      global $DB;

      if (!is_array($soluce->input) || !count($soluce->input)) {
         // Already cancel by another plugin
         return false;
      }

      $config = PluginBehaviorsConfig::getInstance();

      // Check is the connected user is a tech
      if (!is_numeric(Session::getLoginUserID(false))
          || !Session::haveRight('ticket', UPDATE)) {
         return false; // No check
      }

      // Want to solve/close the ticket
      $ticket = new Ticket();
      if ($ticket->getFromDB($soluce->input['items_id'])
          && ($soluce->input['itemtype'] == 'Ticket')) {

         if ($config->getField('is_ticketsolutiontype_mandatory')
             && (isset($soluce->input['solutiontypes_id'])
                 && ($soluce->input['solutiontypes_id'] == 0))) {
            $soluce->input = false;
            Session::addMessageAfterRedirect(__("Type of solution is mandatory before ticket is solved/closed",
                                                'behaviors'), true, ERROR);
            return;
         }
         if ($config->getField('is_ticketsolution_mandatory')
             && empty($soluce->input['content'])) {
            $soluce->input = false;
            Session::addMessageAfterRedirect(__("Description of solution is mandatory before ticket is solved/closed",
                                                'behaviors'), true, ERROR);
            return;
         }
         if ($config->getField('is_ticketrealtime_mandatory')
             && ($ticket->fields['actiontime'] == 0)) {
            $soluce->input = false;
            Session::addMessageAfterRedirect(__("Duration is mandatory before ticket is solved/closed",
                                             'behaviors'), true, ERROR);
            return;
         }
         if ($config->getField('is_ticketcategory_mandatory')
             && ($ticket->fields['itilcategories_id'] == 0)) {
            $soluce->input = false;
            Session::addMessageAfterRedirect(__("Category is mandatory before ticket is solved/closed",
                                             'behaviors'), true, ERROR);
            return;
         }
         if ($config->getField('is_tickettech_mandatory')
             && ($ticket->countUsers(CommonITILActor::ASSIGN) == 0)
             && !$config->getField('ticketsolved_updatetech')) {
            $soluce->input = false;
            Session::addMessageAfterRedirect(__("Technician assigned is mandatory before ticket is solved/closed",
                                             'behaviors'), true, ERROR);
            return;
         }
         if ($config->getField('is_tickettechgroup_mandatory')
             && ($ticket->countGroups(CommonITILActor::ASSIGN) == 0)) {
            $soluce->input = false;
            Session::addMessageAfterRedirect(__("Group of technicians assigned is mandatory before ticket is solved/closed",
                                             'behaviors'), true, ERROR);
            return;
         }
         if ($config->getField('is_ticketlocation_mandatory')
             && ($ticket->fields['locations_id'] == 0)) {
            $soluce->input = false;
            Session::addMessageAfterRedirect(__("Location is mandatory before ticket is solved/closed",
                                             'behaviors'), true, ERROR);
            return;
         }
         if ($config->getField('is_tickettasktodo')) {
            foreach($DB->request('glpi_tickettasks',
                                 ['tickets_id' => $ticket->getField('id')]) as $task) {
               if ($task['state'] == 1) {
                  $soluce->input = false;
                  Session::addMessageAfterRedirect(__("You cannot solve/close a ticket with task do to",
                                                   'behaviors'), true, ERROR);
                  return;
               }
            }
         }
      }

      // Want to solve/close the problem
      $problem = new Problem();
      if ($problem->getFromDB($soluce->input['items_id'])
          && ($soluce->input['itemtype'] == 'Problem')) {

         if ($config->getField('is_problemsolutiontype_mandatory')
             && ($soluce->input['solutiontypes_id']
                 && ($soluce->input['solutiontypes_id'] == 0))) {
            $soluce->input = false;
            Session::addMessageAfterRedirect(__("Type of solution is mandatory before problem is solved/closed",
                                                'behaviors'), true, ERROR);
            return;
         }
         if ($config->getField('is_problemtasktodo')) {
            foreach($DB->request('glpi_problemtasks',
                                 ['problems_id' => $problem->getField('id')]) as $task) {
               if ($task['state'] == 1) {
                  $soluce->input = false;
                  Session::addMessageAfterRedirect(__("You cannot solve/close a problem with task do to",
                                                   'behaviors'), true, ERROR);
                  return;
               }
            }
         }
      }

      // Want to solve/close the
      $change = new Change();
      if ($change->getFromDB($soluce->input['items_id'])
          && $soluce->input['itemtype'] == 'Change') {

         if ($config->getField('is_changetasktodo')) {
            foreach($DB->request('glpi_changetasks',
                                 ['changes_id' => $change->getField('id')]) as $task) {
               if ($task['state'] == 1) {
                  $soluce->input = false;
                  Session::addMessageAfterRedirect(__("You cannot solve/close a change with task do to",
                                                   'behaviors'), true, ERROR);
                  return;
               }
            }
         }
      }
   }


   static function beforeUpdate(ITILSolution $soluce) {

      if (!is_array($soluce->input) || !count($soluce->input)) {
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
      if ($config->getField('is_ticketsolutiontype_mandatory')
          && $soluce->input['itemtype'] == 'Ticket') {
         if (empty($soluce->input['solutiontypes_id'])) {
            $soluce->input['content'] = $soluce->fields['content'];
            $soluce->input['solutiontypes_id'] = $soluce->fields['solutiontypes_id'];
            Session::addMessageAfterRedirect(__("Type of solution is mandatory before ticket is solved/closed",
                                                'behaviors'), true, ERROR);
         }
      }
      if ($config->getField('is_ticketsolution_mandatory')
          && $soluce->input['itemtype'] == 'Ticket') {
         if (empty($soluce->input['content'])) {
            $soluce->input['content'] = $soluce->fields['content'];
            $soluce->input['solutiontypes_id'] = $soluce->fields['solutiontypes_id'];
            Session::addMessageAfterRedirect(__("Description of solution is mandatory before ticket is solved/closed",
                                                'behaviors'), true, ERROR);
         }
      }
   }


   static function afterAdd(ITILSolution $soluce) {

      $ticket = new Ticket();
      $config = PluginBehaviorsConfig::getInstance();
      if ($ticket->getFromDB($soluce->input['items_id'])
          && $soluce->input['itemtype'] == 'Ticket') {

         if ($config->getField('ticketsolved_updatetech')) {
            $ticket_user      = new Ticket_User();
            $ticket_user->getFromDBByCrit(['tickets_id' => $ticket->getID(),
                                           'type'       => CommonITILActor::ASSIGN]);

            if (isset($ticket_user->fields['users_id'])
                && ($ticket_user->fields['users_id'] != Session::getLoginUserID())) {
               $ticket_user->add(['tickets_id' => $ticket->getID(),
                                  'users_id'   => Session::getLoginUserID(),
                                  'type'       => CommonITILActor::ASSIGN]);
            }
         }
      }
   }


   /**
    * show warning message
    *
    * @param $params
    *
    * @return string
    **/
   static function checkWarnings($params) {
      global $DB;

      $warnings = [];
      $obj = $params['options']['item'];

      $config = PluginBehaviorsConfig::getInstance();

         // Check is the connected user is a tech
         if (!is_numeric(Session::getLoginUserID(false))
            || (!Session::haveRight('ticket', UPDATE)
                && !Session::haveRight('problem', UPDATE)
                && !Session::haveRight('change', UPDATE))) {
            return false; // No check
         }

         // Want to solve/close the ticket
         $dur = (isset($obj->fields['actiontime']) ? $obj->fields['actiontime'] : 0);
         $cat = (isset($obj->fields['itilcategories_id']) ? $obj->fields['itilcategories_id'] : 0);
         $loc = (isset($obj->fields['locations_id']) ? $obj->fields['locations_id'] : 0);

      if ($obj->getType() == 'Ticket') {
         $mandatory_solution = false;
         if ($config->getField('is_ticketrealtime_mandatory')) {
            // for moreTicket plugin
            $plugin = new Plugin();
            if ($plugin->isActivated('moreticket')) {
               $configmoreticket = new PluginMoreticketConfig();
               $mandatory_solution = $configmoreticket->isMandatorysolution();
            }

            if (($dur == 0) && ($mandatory_solution == false)) {
               $warnings[] = __("Duration is mandatory before ticket is solved/closed", 'behaviors');
            }

         }
         if ($config->getField('is_ticketcategory_mandatory')) {
            if ($cat == 0) {
               $warnings[] = __("Category is mandatory before ticket is solved/closed", 'behaviors');
            }
         }

         if ($config->getField('is_tickettech_mandatory')) {
            if (($obj->countUsers(CommonITILActor::ASSIGN) == 0)
                && !$config->getField('ticketsolved_updatetech')) {

               $warnings[] = __("Technician assigned is mandatory before ticket is solved/closed",
                                'behaviors');
            }
         }

         if ($config->getField('is_tickettechgroup_mandatory')) {
            if (($obj->countGroups(CommonITILActor::ASSIGN) == 0)) {

               $warnings[] = __("Group of technicians assigned is mandatory before ticket is solved/closed",
                                'behaviors');
            }
         }

         if ($config->getField('is_ticketlocation_mandatory')) {
            if ($loc == 0) {
               $warnings[] = __("Location is mandatory before ticket is solved/closed", 'behaviors');
            }
         }

         if ($config->getField('is_tickettasktodo')) {
            foreach ($DB->request('glpi_tickettasks',
                                 ['tickets_id' => $obj->getField('id')]) as $task) {
               if ($task['state'] == 1) {
                  $warnings[] = __("You cannot solve/close a ticket with task do to", 'behaviors');
                  break;
               }
            }
         }
      }

      if ($obj->getType() == 'Problem') {
         if ($config->getField('is_problemtasktodo')) {
            foreach ($DB->request('glpi_problemtasks',
                                 ['problems_id' => $obj->getField('id')]) as $task) {
               if ($task['state'] == 1) {
                  $warnings[] = __("You cannot solve/close a problem with task do to", 'behaviors');
                  break;
               }
            }
         }
      }

      if ($obj->getType() == 'Change') {
         if ($config->getField('is_changetasktodo')) {
            foreach ($DB->request('glpi_changetasks',
                                 ['changes_id' => $obj->getField('id')]) as $task) {
               if ($task['state'] == 1) {
                  $warnings[] = __("You cannot solve/close a change with task do to", 'behaviors');
                  break;
               }
            }
         }
      }
      return $warnings;
   }


   /**
    * Displaying message solution
    *
    * @param $params
   **/
   static function messageWarningSolution($params) {

      if (isset($params['item'])) {
         $item = $params['item'];
         if ($item->getType() == 'ITILSolution') {
            $warnings = self::checkWarnings($params);
            if (is_array($warnings) && count($warnings)) {
               echo "<div class='alert alert-warning'>";

               echo "<div class='d-flex'>";

               echo "<div class='me-2'>";
               echo "<i class='fa fa-exclamation-triangle fa-2x'></i>";
               echo "</div>";

               echo "<div>";
               echo "<h4 class='alert-title'>" . __('You cannot resolve the ticket', 'behaviors') . "</h4>";
               echo "<div class='text-muted'>" . implode('</div><div>', $warnings) . "</div>";
               echo "</div>";

               echo "</div>";

               echo "</div>";
            }
            return $params;
         }
      }
   }


   /**
    * Displaying Add solution button or not
    *
    * @param $params
    *
    * @return array
   **/
   static function deleteAddSolutionButton($params) {

      if (isset($params['item'])) {
         $item = $params['item'];
         if ($item->getType() == 'ITILSolution') {
            $warnings = self::checkWarnings($params);
            if (is_array($warnings) && count($warnings)) {
               echo Html::scriptBlock("$(document).ready(function(){
                        $('.itilsolution').children().find(':submit').hide();
                     });");
            }
         }
      }
   }


}
