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
 @since     2022

 --------------------------------------------------------------------------
*/

class PluginBehaviorsITILFollowup {

   static function beforeAdd(ITILFollowup $fup) {

      $ticket = new Ticket();
      $config = PluginBehaviorsConfig::getInstance();
      if ($ticket->getFromDB($fup->input['items_id'])
         && $fup->input['itemtype'] == 'Ticket') {
         
         // mailgate situation
         if ($config->getField('addfup_updatetech')
             && isset($fup->input['_mailgate']) && $fup->input['_mailgate']) {
            
            $ticket_user = new Ticket_User();
            $ticket_user->getFromDBByCrit(['tickets_id' => $ticket->getID(),
                                           'type'       => CommonITILActor::ASSIGN]);

            $ticket_requester = new Ticket_User();
            $ticket_requester->getFromDBByCrit(['tickets_id' => $ticket->getID(),
                                           'type'       => CommonITILActor::REQUESTER]);

            # only run if user id is set AND no user has been assigned yet && user is not the requester
            if (isset($fup->input['users_id']) 
                && !isset($ticket_user->fields['users_id'])
                && $ticket_requester->fields['users_id'] <> $fup->input['users_id']) {

               $group_ticket      = new Group_Ticket();
               $group_ticket->getFromDBByCrit(['tickets_id' => $ticket->getID(),
                                               'type'       => CommonITILActor::ASSIGN]);

               $usergroup = Group_User::getGroupUsers($group_ticket->fields['groups_id']);
               $users = [];
               foreach ($usergroup as $user) {
                  $users[$user['id']] = $user['id'];
               }

               if (!in_array( $fup->input['users_id'], $users)) {
                  $ticket_user = new Ticket_User();
                  $ticket_user->add(['tickets_id' => $ticket->getID(),
                                    'users_id'   => $fup->input['users_id'],
                                    'type'       => CommonITILActor::ASSIGN]);
               }
            }

         }

         // regular situation
         else if ($config->getField('addfup_updatetech')
             && Session::haveRight('ticket', UPDATE)) {

            $ticket_user      = new Ticket_User();
            $ticket_user->getFromDBByCrit(['tickets_id' => $ticket->getID(),
                                           'type'       => CommonITILActor::ASSIGN]);

            if ($ticket_user->fields['users_id'] <> Session::getLoginUserID()) {
               $group_ticket      = new Group_Ticket();
               $group_ticket->getFromDBByCrit(['tickets_id' => $ticket->getID(),
                                               'type'       => CommonITILActor::ASSIGN]);

               $usergroup = Group_User::getGroupUsers($group_ticket->fields['groups_id']);
               $users = [];
               foreach ($usergroup as $user) {
                  $users[$user['id']] = $user['id'];
               }

               if (!in_array( Session::getLoginUserID(), $users)) {
                  $ticket_user = new Ticket_User();
                  $ticket_user->add(['tickets_id' => $ticket->getID(),
                                     'users_id'   => Session::getLoginUserID(),
                                     'type'       => CommonITILActor::ASSIGN]);
               }
            }
         }
      }
   }

}
