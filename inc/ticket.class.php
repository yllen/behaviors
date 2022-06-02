<?php
/**
 * @version $Id$
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Behaviors plugin for GLPI.
 *
 * Behaviors is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Behaviors is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Behaviors. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   behaviors
 * @author    Remi Collet, Nelly Mahu-Lasson
 * @copyright Copyright (c) 2010-2022 Behaviors plugin team
 * @license   AGPL License 3.0 or (at your option) any later version
 * http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @link      https://forge.glpi-project.org/projects/behaviors
 * @link      http://www.glpi-project.org/
 * @since     2010
 *
 * --------------------------------------------------------------------------
 */

class PluginBehaviorsTicket {

   const LAST_TECH_ASSIGN                     = 50;
   const LAST_GROUP_ASSIGN                    = 51;
   const LAST_SUPPLIER_ASSIGN                 = 52;
   const LAST_WATCHER_ADDED                   = 53;
   const SUPERVISOR_LAST_GROUP_ASSIGN         = 54;
   const LAST_GROUP_ASSIGN_WITHOUT_SUPERVISOR = 55;


   /**
    * @param \NotificationTargetTicket $target
    *
    * @return void
    */
   static function addEvents(NotificationTargetTicket $target) {

      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('add_notif')) {
         Plugin::loadLang('behaviors');
         $target->events['plugin_behaviors_ticketreopen']
            = sprintf(__('%1$s - %2$s'), __('Behaviours', 'behaviors'),
                      __('Reopen ticket', 'behaviors'));

         $target->events['plugin_behaviors_ticketstatus']
            = sprintf(__('%1$s - %2$s'), __('Behaviours', 'behaviors'),
                      __('Change status', 'behaviors'));

         $target->events['plugin_behaviors_ticketwaiting']
            = sprintf(__('%1$s - %2$s'), __('Behaviours', 'behaviors'),
                      __('Ticket waiting', 'behaviors'));

         PluginBehaviorsDocument_Item::addEvents($target);
      }
   }


   /**
    * @param \NotificationTargetTicket $target
    *
    * @return void
    */
   static function addTargets(NotificationTargetTicket $target) {

      // No new recipients for globals notifications
      $alert = ['alertnotclosed', 'recall', 'recall_ola'];
      if (!in_array($target->raiseevent, $alert)) {
         $target->addTarget(self::LAST_TECH_ASSIGN,
                            sprintf(__('%1$s (%2$s)'), __('Last technician assigned', 'behaviors'),
                                    __('Behaviours', 'behaviors')));
         $target->addTarget(self::LAST_GROUP_ASSIGN,
                            sprintf(__('%1$s (%2$s)'), __('Last group assigned', 'behaviors'),
                                    __('Behaviours', 'behaviors')));
         $target->addTarget(self::LAST_SUPPLIER_ASSIGN,
                            sprintf(__('%1$s (%2$s)'), __('Last supplier assigned', 'behaviors'),
                                    __('Behaviours', 'behaviors')));
         $target->addTarget(self::LAST_WATCHER_ADDED,
                            sprintf(__('%1$s (%2$s)'), __('Last watcher added', 'behaviors'),
                                    __('Behaviours', 'behaviors')));
         $target->addTarget(self::SUPERVISOR_LAST_GROUP_ASSIGN,
                            sprintf(__('%1$s (%2$s)'), __('Supervisor of last group assigned', 'behaviors'),
                                    __('Behaviours', 'behaviors')));
         $target->addTarget(self::LAST_GROUP_ASSIGN_WITHOUT_SUPERVISOR,
                            sprintf(__('%1$s (%2$s)'),
                                    __('Last group assigned without supersivor', 'behaviors'),
                                    __('Behaviours', 'behaviors')));
      }
   }


   /**
    * @param \NotificationTargetTicket $target
    *
    * @return void
    */
   static function addActionTargets(NotificationTargetTicket $target) {

      switch ($target->data['items_id']) {
         case self::LAST_TECH_ASSIGN :
            self::getLastLinkedUserByType(CommonITILActor::ASSIGN, $target);
            break;

         case self::LAST_GROUP_ASSIGN :
            self::getLastLinkedGroupByType(CommonITILActor::ASSIGN, $target);
            break;

         case self::LAST_SUPPLIER_ASSIGN :
            self::getLastSupplierAddress($target);
            break;

         case self::LAST_WATCHER_ADDED :
            self::getLastLinkedUserByType(CommonITILActor::OBSERVER, $target);
            break;

         case self::SUPERVISOR_LAST_GROUP_ASSIGN :
            self::getLastLinkedGroupByType(CommonITILActor::ASSIGN, $target, 1);
            break;

         case self::LAST_GROUP_ASSIGN_WITHOUT_SUPERVISOR :
            self::getLastLinkedGroupByType(CommonITILActor::ASSIGN, $target, 2);
            break;
      }
   }


   /**
    * @param $type
    * @param $target
    *
    * @return void
    */
   static function getLastLinkedUserByType($type, $target) {
      global $DB, $CFG_GLPI;

      $dbu           = new DbUtils();
      $userlinktable = $dbu->getTableForItemType($target->obj->userlinkclass);
      $fkfield       = $target->obj->getForeignKeyField();

      $last   = "SELECT MAX(`id`) AS lastid
               FROM `$userlinktable`
               WHERE `$userlinktable`.`$fkfield` = '" . $target->obj->fields["id"] . "'
                     AND `$userlinktable`.`type` = '$type'";
      $result = $DB->request($last);

      $querylast = '';
      if ($data = $result->next()) {
         $object = new $target->obj->userlinkclass();
         if ($object->getFromDB($data['lastid'])) {
            $querylast = " AND `$userlinktable`.`users_id` = '" . $object->fields['users_id'] . "'";
         }
      }

      //Look for the user by his id
      $query = "SELECT DISTINCT `glpi_users`.`id` AS users_id,
                                 `glpi_users`.`language` AS language,
                                 `$userlinktable`.`use_notification` AS notif,
                                 `$userlinktable`.`alternative_email` AS altemail
                 FROM `$userlinktable`
                 LEFT JOIN `glpi_users` ON (`$userlinktable`.`users_id` = `glpi_users`.`id`)
                 INNER JOIN `glpi_profiles_users`
                    ON (`glpi_profiles_users`.`users_id` = `glpi_users`.`id` " .
               $dbu->getEntitiesRestrictRequest("AND", "glpi_profiles_users", "entities_id",
                                                $target->getEntity(), true) . ")
                 WHERE `$userlinktable`.`$fkfield` = '" . $target->obj->fields["id"] . "'
                       AND `$userlinktable`.`type` = '$type'
                       $querylast";

      foreach ($DB->request($query) as $data) {
         //Add the user email and language in the notified users list
         if ($data['notif']) {
            $author_email = UserEmail::getDefaultForUser($data['users_id']);
            $author_lang  = $data["language"];
            $author_id    = $data['users_id'];

            if (!empty($data['altemail'])
                && ($data['altemail'] != $author_email)
                && NotificationMailing::isUserAddressValid($data['altemail'])) {
               $author_email = $data['altemail'];
            }
            if (empty($author_lang)) {
               $author_lang = $CFG_GLPI["language"];
            }
            if (empty($author_id)) {
               $author_id = -1;
            }
            $target->addToRecipientsList(['email'    => $author_email,
                                          'language' => $author_lang,
                                          'users_id' => $author_id]);
         }
      }

      // Anonymous user
      foreach ($DB->request(['SELECT' => 'alternative_email',
                             'FROM'   => $userlinktable,
                             'WHERE'  => [$fkfield           => $target->obj->fields["id"],
                                          'users_id'         => 0,
                                          'use_notification' => 1,
                                          'type'             => $type]]) as $data) {
         if (NotificationMailing::isUserAddressValid($data['alternative_email'])) {
            $target->addToRecipientsList(['email'    => $data['alternative_email'],
                                          'language' => $CFG_GLPI["language"],
                                          'users_id' => -1]);
         }
      }
   }


   /**
    * @param $type
    * @param $target
    * @param $supervisor
    *
    * @return void
    */
   static function getLastLinkedGroupByType($type, $target, $supervisor = 0) {
      global $DB;

      $dbu            = new DbUtils();
      $grouplinktable = $dbu->getTableForItemType($target->obj->grouplinkclass);
      $fkfield        = $target->obj->getForeignKeyField();

      $last   = ['SELECT' => ['MAX' => 'id AS lastid'],
                 'FROM'   => $grouplinktable,
                 'WHERE'  => [$grouplinktable . '.' . $fkfield => $target->obj->fields["id"],
                              $grouplinktable . '.type'        => $type]];
      $result = $DB->request($last);

      //Look for the user by his id
      $query = ['SELECT' => 'groups_id',
                'FROM'   => $grouplinktable,
                'WHERE'  => [$grouplinktable . '.' . $fkfield => $target->obj->fields["id"],
                             $grouplinktable . '.type'        => $type]];

      if ($data = $result->next()) {
         $object = new $target->obj->grouplinkclass();
         if ($object->getFromDB($data['lastid'])) {
            $query['WHERE']['groups_id'] = $object->fields['groups_id'];
         }
      }
      foreach ($DB->request($query) as $data) {
         //Add the group in the notified users list
         self::addForGroup($supervisor, $object->fields['groups_id'], $target);
      }
   }


   /**
    * @param $target
    *
    * @return void
    */
   static function getLastSupplierAddress($target) {
      global $DB;

      if (!$target->options['sendprivate']
          && $target->obj->countSuppliers(CommonITILActor::ASSIGN)) {

         $dbu               = new DbUtils();
         $supplierlinktable = $dbu->getTableForItemType($target->obj->supplierlinkclass);
         $fkfield           = $target->obj->getForeignKeyField();

         $last = ['SELECT' => ['MAX' => 'id AS lastid'],
                  'FROM'   => $supplierlinktable,
                  'WHERE'  => [$supplierlinktable . '.' . $fkfield => $target->obj->fields["id"]]];

         $result = $DB->request($last);
         $data   = $result->next();

         $query = ['SELECT'    => 'glpi_suppliers.email AS email',
                   'DISTINCT'  => true,
                   'FIELDS'    => 'glpi_suppliers.name AS name',
                   'FROM'      => $supplierlinktable,
                   'LEFT JOIN' => ['glpi_suppliers'
                                   => ['FKEY' => [$supplierlinktable => 'suppliers_id',
                                                  'glpi_suppliers'   => 'id']]],
                   'WHERE'     => [$supplierlinktable . '.' . $fkfield => $target->obj->getID()]];

         $object = new $target->obj->supplierlinkclass();
         if ($object->getFromDB($data['lastid'])) {
            $query['WHERE'][$supplierlinktable . '.suppliers_id'] = $object->fields['suppliers_id'];
         }
         foreach ($DB->request($query) as $data) {
            $target->addToRecipientsList($data);
         }
      }
   }

   /**
    * @return void
    */
   static function onNewTicket() {

      if (isset($_SESSION['glpiactiveprofile']['interface'])
          && ($_SESSION['glpiactiveprofile']['interface'] == 'central')) {

         if (strstr($_SERVER['PHP_SELF'], "/front/ticket.form.php")
             && isset($_POST['id'])
             && ($_POST['id'] == 0)) {

            $config = PluginBehaviorsConfig::getInstance();

            if ($config->getField('use_requester_user_group') > 0
                && isset($_POST['_actors'])) {
               $actors = json_decode($_POST['_actors'], true);
               if (isset($actors['requester'])) {
                  $requesters = $actors['requester'];
                  // Select first group of this user
                  $group_requester_actors = [];
                  foreach ($requesters as $requester) {
                     if ($requester['itemtype'] == 'Group') {
                        $group_requester_actors[] = $requester['items_id'];
                     }
                     if ($requester['itemtype'] == 'User') {
                        if ($config->getField('use_requester_user_group') == 1) {
                           // First group
                           $grp = PluginBehaviorsUser::getRequesterGroup($_POST['entities_id'],
                                                                         $requester['items_id'],
                                                                         true);
                           if ($grp > 0 && !isset($_SESSION['glpi_behaviors_auto_group_request'])
                               || (isset($_SESSION['glpi_behaviors_auto_group_request'])
                                   && is_array($_SESSION['glpi_behaviors_auto_group_request'])
                                   && !in_array($grp, $_SESSION['glpi_behaviors_auto_group_request']))
                                  && !in_array($grp, $group_requester_actors)) {
                              $actors['requester'][]                           = ['itemtype'          => 'Group',
                                                                                  'items_id'          => $grp,
                                                                                  'use_notification'  => "1",
                                                                                  'alternative_email' => ""];
                              $_SESSION['glpi_behaviors_auto_group_request'][] = $grp;
                           }
                           $new_actors       = json_encode($actors);
                           $_POST['_actors'] = $new_actors;
                        } else if ($config->getField('use_requester_user_group') == 3) {
                           // Default group
                           $user = new User();
                           if ($user->getFromDB($requester['itemtype']['items_id'])) {
                              $grp = $user->fields['groups_id'];
                           }
                           if ($grp > 0 && !isset($_SESSION['glpi_behaviors_auto_group_request'])
                               || (isset($_SESSION['glpi_behaviors_auto_group_request'])
                                   && is_array($_SESSION['glpi_behaviors_auto_group_request'])
                                   && !in_array($grp, $_SESSION['glpi_behaviors_auto_group_request']))
                                  && !in_array($grp, $group_requester_actors)) {
                              $actors['requester'][]                           = ['itemtype'          => 'Group',
                                                                                  'items_id'          => $grp,
                                                                                  'use_notification'  => "1",
                                                                                  'alternative_email' => ""];
                              $_SESSION['glpi_behaviors_auto_group_request'][] = $grp;
                           }
                           $new_actors       = json_encode($actors);
                           $_POST['_actors'] = $new_actors;

                        } else {
                           // All groups
                           $grps = PluginBehaviorsUser::getRequesterGroup($_POST['entities_id'],
                                                                          $requester['items_id'],
                                                                          false);
                           foreach ($grps as $grp) {
                              if (!isset($_SESSION['glpi_behaviors_auto_group_request'])
                                  || (isset($_SESSION['glpi_behaviors_auto_group_request'])
                                      && is_array($_SESSION['glpi_behaviors_auto_group_request'])
                                      && !in_array($grp, $_SESSION['glpi_behaviors_auto_group_request']))
                                     && !in_array($grp, $group_requester_actors)) {
                                 $actors['requester'][]                           = ['itemtype'          => 'Group',
                                                                                     'items_id'          => $grp,
                                                                                     'use_notification'  => "1",
                                                                                     'alternative_email' => ""];
                                 $_SESSION['glpi_behaviors_auto_group_request'][] = $grp;
                              }
                           }
                        }
                        $new_actors       = json_encode($actors);
                        $_POST['_actors'] = $new_actors;
                     }
                  }
               }
            } else {
               unset($_SESSION['glpi_behaviors_auto_group_request']);
            }

            if ($config->getField('use_assign_user_group') > 0
                && isset($_POST['_actors'])) {
               if (isset($actors['assign'])) {
                  $assigneds = $actors['assign'];
                  // Select first group of this user
                  $group_assign_actors = [];
                  foreach ($assigneds as $assigned) {
                     if ($assigned['itemtype'] == 'Group') {
                        $group_assign_actors[] = $assigned['items_id'];
                     }
                     if ($assigned['itemtype'] == 'User') {
                        if ($config->getField('use_assign_user_group') == 1) {
                           // First group
                           $grp = PluginBehaviorsUser::getTechnicianGroup($_POST['entities_id'],
                                                                          $assigned['items_id'],
                                                                          true);
                           if ($grp > 0 && !isset($_SESSION['glpi_behaviors_auto_group_assign'])
                               || (isset($_SESSION['glpi_behaviors_auto_group_assign'])
                                   && is_array($_SESSION['glpi_behaviors_auto_group_assign'])
                                   && !in_array($grp, $_SESSION['glpi_behaviors_auto_group_assign']))
                                  && !in_array($grp, $group_assign_actors)) {
                              $actors['assign'][]                             = ['itemtype'          => 'Group',
                                                                                 'items_id'          => $grp,
                                                                                 'use_notification'  => "1",
                                                                                 'alternative_email' => ""];
                              $_SESSION['glpi_behaviors_auto_group_assign'][] = $grp;
                           }
                           $new_actors       = json_encode($actors);
                           $_POST['_actors'] = $new_actors;
                        } else {
                           // All groups
                           $grps = PluginBehaviorsUser::getTechnicianGroup($_POST['entities_id'],
                                                                           $assigned['items_id'],
                                                                           false);
                           foreach ($grps as $grp) {
                              if (!isset($_SESSION['glpi_behaviors_auto_group_assign'])
                                  || (isset($_SESSION['glpi_behaviors_auto_group_assign'])
                                      && is_array($_SESSION['glpi_behaviors_auto_group_assign'])
                                      && !in_array($grp, $_SESSION['glpi_behaviors_auto_group_assign']))
                                     && !in_array($grp, $group_assign_actors)) {
                                 $actors['assign'][]                             = ['itemtype'          => 'Group',
                                                                                    'items_id'          => $grp,
                                                                                    'use_notification'  => "1",
                                                                                    'alternative_email' => ""];
                                 $_SESSION['glpi_behaviors_auto_group_assign'][] = $grp;
                              }
                           }
                        }
                        $new_actors       = json_encode($actors);
                        $_POST['_actors'] = $new_actors;
                     }
                  }
               }
            } else {
               unset($_SESSION['glpi_behaviors_auto_group_assign']);
            }

         } else if (strstr($_SERVER['PHP_SELF'], "/front/ticket.form.php")) {
            unset($_SESSION['glpi_behaviors_auto_group_request']);
            unset($_SESSION['glpi_behaviors_auto_group_assign']);
         }
      }
   }


   /**
    * @param \Ticket $ticket
    *
    * @return false|void
    */
   static function beforeAdd(Ticket $ticket) {
      global $DB;

      if (!is_array($ticket->input) || !count($ticket->input)) {
         // Already cancel by another plugin
         return false;
      }

      $dbu = new DbUtils();

      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('tickets_id_format')) {
         $max = 0;
         $sql = ['SELECT' => ['MAX' => 'id AS max'],
                 'FROM'   => 'glpi_tickets'];
         foreach ($DB->request($sql) as $data) {
            $max = $data['max'];
         }
         $want = date($config->getField('tickets_id_format'));
         if ($max < $want) {
            $DB->query("ALTER TABLE `glpi_tickets` AUTO_INCREMENT=$want");
         }
      }
      //Replaced by ticket template
      //      if (!isset($ticket->input['_auto_import'])
      //          && isset($_SESSION['glpiactiveprofile']['interface'])
      //          && ($_SESSION['glpiactiveprofile']['interface'] == 'central')) {
      //
      //         if ($config->getField('is_requester_mandatory')
      //             && (!isset($ticket->input['_users_id_requester'])
      //                 || ((is_array($ticket->input['_users_id_requester'])
      //                      && empty($ticket->input['_users_id_requester']))
      //                     || (!is_array($ticket->input['_users_id_requester'])
      //                         && !$ticket->input['_users_id_requester']))
      //                    && (!isset($ticket->input['_users_id_requester_notif']['alternative_email'])
      //                        || empty($ticket->input['_users_id_requester_notif']['alternative_email'])))) {
      //            Session::addMessageAfterRedirect(__('Requester is mandatory', 'behaviors'), true, ERROR);
      //            $ticket->input = [];
      //            return true;
      //
      //         }
      //      }

      if ($config->getField('use_requester_item_group')
          && isset($ticket->input['items_id'])
          && (is_array($ticket->input['items_id']))) {
         foreach ($ticket->input['items_id'] as $type => $items) {
            if (($item = $dbu->getItemForItemtype($type))
                && isset($ticket->input['_actors'])) {
               $actors = $ticket->input['_actors'];

               //for simplified interface
               if (!isset($actors['requester']) && isset($ticket->input['_users_id_requester'])) {
                  $actors['requester'][] = ['itemtype'          => 'User',
                                            'items_id'          => $ticket->input['_users_id_requester'],
                                            'use_notification'  => "1",
                                            'alternative_email' => ""];
               }

               if (isset($actors['requester'])) {
                  $requesters = $actors['requester'];
                  $ko         = 0;
                  foreach ($requesters as $requester) {
                     if ($requester['itemtype'] == 'Group') {
                        $ko++;
                     }
                  }
                  if ($ko == 0 && $item->isField('groups_id')) {
                     foreach ($items as $itemid) {
                        if ($item->getFromDB($itemid)) {
                           $actors['requester'][] = ['itemtype'          => 'Group',
                                                     'items_id'          => $item->getField('groups_id'),
                                                     'use_notification'  => "1",
                                                     'alternative_email' => ""];
                        }
                     }
                     $new_actors               = $actors;
                     $ticket->input['_actors'] = $new_actors;
                  }
               }
            }
         }
      }

      // No Auto set Import for external source -> Duplicate from Ticket->prepareInputForAdd()
      //      if (!isset($ticket->input['_auto_import'])) {
      //         if (!isset($ticket->input['_users_id_requester'])) {
      //            if ($uid = Session::getLoginUserID()) {
      //               $ticket->input['_users_id_requester'] = $uid;
      //            }
      //         }
      //      }

      if ($config->getField('use_requester_user_group') > 0
          && isset($ticket->input['_actors'])) {
         $actors = $ticket->input['_actors'];

         //for simplified interface
         if (!isset($actors['requester']) && isset($ticket->input['_users_id_requester'])) {
            $actors['requester'][] = ['itemtype'          => 'User',
                                      'items_id'          => $ticket->input['_users_id_requester'],
                                      'use_notification'  => "1",
                                      'alternative_email' => ""];
         }
         if (isset($actors['requester'])) {
            $requesters = $actors['requester'];
            // Select first group of this user
            $ko   = 0;
            $grp  = 0;
            $grps = [];
            foreach ($requesters as $requester) {
               if ($config->getField('use_requester_user_group') == 1) {
                  // First group
                  if ($requester['itemtype'] == 'User') {
                     $grp = PluginBehaviorsUser::getRequesterGroup($ticket->input['entities_id'],
                                                                   $requester['items_id'],
                                                                   true);
                  }
                  if ($grp > 0 && $requester['itemtype'] == 'Group'
                      && $requester['items_id'] == $grp) {
                     $ko++;
                  }
                  if ($grp > 0 && $ko == 0) {
                     $actors['requester'][] = ['itemtype'          => 'Group',
                                               'items_id'          => $grp,
                                               'use_notification'  => "1",
                                               'alternative_email' => ""];
                  }
               } else if ($config->getField('use_requester_user_group') == 3) {
                  // Default group
                  $user = new User();
                  if ($requester['itemtype'] == 'User') {
                     if ($user->getFromDB($requester['itemtype']['items_id'])) {
                        $grp = $user->fields['groups_id'];
                     }
                  }
                  if ($grp > 0 && $requester['itemtype'] == 'Group'
                      && $requester['items_id'] == $grp) {
                     $ko++;
                  }
                  if ($grp > 0 && $ko == 0) {
                     $actors['requester'][] = ['itemtype'          => 'Group',
                                               'items_id'          => $grp,
                                               'use_notification'  => "1",
                                               'alternative_email' => ""];
                  }

               } else {
                  // All groups
                  if ($requester['itemtype'] == 'User') {
                     $grps = PluginBehaviorsUser::getRequesterGroup($ticket->input['entities_id'],
                                                                    $requester['items_id'],
                                                                    false);
                  }
                  if ($requester['itemtype'] == 'Group'
                      && in_array($requester['items_id'], $grps)) {
                     unset($grps[$requester['items_id']]);
                  }
                  if (count($grps) > 0) {
                     foreach ($grps as $grp) {
                        $actors['requester'][] = ['itemtype'          => 'Group',
                                                  'items_id'          => $grp,
                                                  'use_notification'  => "1",
                                                  'alternative_email' => ""];
                     }
                  }
               }
            }
         }
         $ticket->input['_actors'] = $actors;
      }

      if ($config->getField('ticketsolved_updatetech')
          && isset($ticket->input['status'])
          && in_array($ticket->input['status'], array_merge(Ticket::getSolvedStatusArray(),
                                                            Ticket::getClosedStatusArray()))) {

         if (isset($ticket->input['_actors'])) {
            $actors = $ticket->input['_actors'];
            if (isset($actors['assign'])) {
               unset($actors['assign']);
            }
         }
         $actors['assign'][]       = ['itemtype'          => 'User',
                                      'items_id'          => Session::getLoginUserID(),
                                      'use_notification'  => "1",
                                      'alternative_email' => ""];
         $ticket->input['_actors'] = $actors;
      }
      //for simplified interface
      if (isset($_SESSION["glpiactiveprofile"]["interface"])
          && $_SESSION["glpiactiveprofile"]["interface"] == "helpdesk"
          && isset($ticket->input['_actors']['requester'])) {

         $requesters = $ticket->input['_actors']['requester'];
         foreach ($requesters as $k => $requester) {
            if ($requester['itemtype'] == 'User') {
               unset($ticket->input['_actors']['requester'][$k]);
            }
         }
      }
   }

   static function afterPrepareAdd(Ticket $ticket) {


      if (!is_array($ticket->input) || !count($ticket->input)) {
         // Already cancel by another plugin
         return false;
      }

      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('use_assign_user_group') > 0
          && isset($ticket->input['_actors'])) {
         $actors = $ticket->input['_actors'];
         if (isset($actors['assign'])) {
            $assigneds = $actors['assign'];
            // Select first group of this user
            $ko   = 0;
            $grp  = 0;
            $grps = [];
            foreach ($assigneds as $assigned) {
               if ($config->getField('use_assign_user_group') == 1) {
                  // First group
                  if ($assigned['itemtype'] == 'User') {
                     $grp = PluginBehaviorsUser::getTechnicianGroup($ticket->input['entities_id'],
                                                                    $assigned['items_id'],
                                                                    true);
                  }
                  if ($grp > 0 && $assigned['itemtype'] == 'Group'
                      && $assigned['items_id'] == $grp) {
                     $ko++;
                  }
                  if ($ko == 0) {
                     $actors['assign'][] = ['itemtype'          => 'Group',
                                            'items_id'          => $grp,
                                            'use_notification'  => "1",
                                            'alternative_email' => ""];
                  }
               } else {
                  // All groups
                  if ($assigned['itemtype'] == 'User') {
                     $grps = PluginBehaviorsUser::getTechnicianGroup($ticket->input['entities_id'],
                                                                     $assigned['items_id'],
                                                                     false);
                  }
                  if ($assigned['itemtype'] == 'Group'
                      && in_array($assigned['items_id'], $grps)) {
                     unset($grps[$assigned['items_id']]);
                  }
                  if (count($grps) > 0) {
                     foreach ($grps as $grp) {
                        $actors['assign'][] = ['itemtype'          => 'Group',
                                               'items_id'          => $grp,
                                               'use_notification'  => "1",
                                               'alternative_email' => ""];
                     }
                  }
               }
            }
         }
         $ticket->input['_actors'] = $actors;
      }
   }


   /**
    * @param \Ticket $ticket
    *
    * @return false|void
    */
   static function beforeUpdate(Ticket $ticket) {
      global $DB;

      if (!is_array($ticket->input) || !count($ticket->input)) {
         // Already cancel by another plugin
         return false;
      }

      $dbu    = new DbUtils();
      $config = PluginBehaviorsConfig::getInstance();

      // Check is the connected user is a tech
      if (!is_numeric(Session::getLoginUserID(false))
          || !Session::haveRight('ticket', UPDATE)) {
         return false; // No check
      }

      if (isset($ticket->input['date'])) {
         if ($config->getField('is_ticketdate_locked')) {
            unset($ticket->input['date']);
         }
      }

      if (isset($ticket->input['_read_date_mod'])
          && $config->getField('use_lock')
          && ($ticket->input['_read_date_mod'] != $ticket->fields['date_mod'])) {

         $msg = sprintf(__('%1$s (%2$s)'), __("Can't save, item have been updated", "behaviors"),
                        $dbu->getUserName($ticket->fields['users_id_lastupdater']) . ', ' .
                        Html::convDateTime($ticket->fields['date_mod']));

         Session::addMessageAfterRedirect($msg, true, ERROR);
         return $ticket->input = false;
      }

      if (isset($ticket->input['status'])
          && in_array($ticket->input['status'], array_merge(Ticket::getSolvedStatusArray(),
                                                            Ticket::getClosedStatusArray()))) {

         $soluce = $DB->request('glpi_itilsolutions',
                                ['itemtype' => 'Ticket',
                                 'items_id' => $ticket->input['id']]);

         if ($config->getField('is_ticketsolutiontype_mandatory')
             && !count($soluce)) {
            unset($ticket->input['status']);
            Session::addMessageAfterRedirect(__("Type of solution is mandatory before ticket is solved/closed",
                                                'behaviors'), true, ERROR);
         }
         if ($config->getField('is_ticketsolution_mandatory')
             && !count($soluce)) {
            unset($ticket->input['status']);
            Session::addMessageAfterRedirect(__("Description of solution is mandatory before ticket is solved/closed",
                                                'behaviors'), true, ERROR);
         }

         $dur = (isset($ticket->input['actiontime'])
            ? $ticket->input['actiontime']
            : $ticket->fields['actiontime']);
         $cat = (isset($ticket->input['itilcategories_id'])
            ? $ticket->input['itilcategories_id']
            : $ticket->fields['itilcategories_id']);
         $loc = (isset($ticket->input['locations_id'])
            ? $ticket->input['locations_id']
            : $ticket->fields['locations_id']);

         // Wand to solve/close the ticket
         if ($config->getField('is_ticketrealtime_mandatory')) {
            if (!$dur) {
               unset($ticket->input['status']);
               Session::addMessageAfterRedirect(__("Duration is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }
         if ($config->getField('is_ticketcategory_mandatory')) {
            if (!$cat) {
               unset($ticket->input['status']);
               Session::addMessageAfterRedirect(__("Category is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }

         if ($config->getField('is_tickettech_mandatory')) {
            if (($ticket->countUsers(CommonITILActor::ASSIGN) == 0)
                //                && !isset($input["_itil_assign"]['users_id'])
                && !$config->getField('ticketsolved_updatetech')) {
               unset($ticket->input['status']);
               Session::addMessageAfterRedirect(__("Technician assigned is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }

         if ($config->getField('is_tickettechgroup_mandatory')) {
            if (($ticket->countGroups(CommonITILActor::ASSIGN) == 0)
               //                && !isset($input["_itil_assign"]['groups_id'])
            ) {
               unset($ticket->input['status']);
               Session::addMessageAfterRedirect(__("Group of technicians assigned is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }
         if ($config->getField('is_ticketlocation_mandatory')) {
            if (!$loc) {
               unset($ticket->input['status']);
               Session::addMessageAfterRedirect(__("Location is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }
         $state = 0;
         if ($config->getField('is_tickettasktodo')) {
            foreach ($DB->request('glpi_tickettasks',
                                  ['tickets_id' => $ticket->getField('id')]) as $task) {
               if ($task['state'] == 1) {
                  $state++;
               }
               if ($state > 0) {
                  Session::addMessageAfterRedirect(__("You cannot solve/close a ticket with task do to",
                                                      'behaviors'), true, ERROR);
                  unset($ticket->input['status']);
               }
            }
         }
      }
      $cat = (isset($ticket->input['itilcategories_id'])
         ? $ticket->input['itilcategories_id']
         : $ticket->fields['itilcategories_id']);

      if ($config->getField('is_ticketcategory_mandatory_on_assign') && isset($ticket->input['_actors'])) {
         $actors = $ticket->input['_actors'];
         if (isset($actors['assign'])) {
            $assigneds = $actors['assign'];
            $ko        = 0;
            foreach ($assigneds as $assigned) {
               if (!$cat
                   && isset($assigned['itemtype'])
                   && ($assigned['itemtype'] == 'Group'
                       || $assigned['itemtype'] = 'User')) {
                  $ko++;
               }
            }
            if ($ko > 0) {
               $ticket->input = [];
               Session::addMessageAfterRedirect(__("Category is mandatory when you assign a ticket",
                                                   'behaviors'), true, ERROR);
            }
         }
      }

      //disabled : only in add new ticket
      //      if ($config->getField('use_requester_item_group')
      //          && isset($ticket->input['items_id'])
      //          && (is_array($ticket->input['items_id']))) {
      //         foreach ($ticket->input['items_id'] as $type => $items) {
      //            foreach ($items as $number => $id) {
      //               if (($item = $dbu->getItemForItemtype($type))
      //                   && !isset($ticket->input['_itil_requester']['groups_id'])) {
      //                  if ($item->isField('groups_id')) {
      //                     foreach ($items as $itemid) {
      //                        if ($item->getFromDB($itemid)) {
      //                           $ticket->input['_itil_requester']
      //                              = ['_type'     => 'group',
      //                                 'groups_id' => $item->getField('groups_id')];
      //                        }
      //                     }
      //                  }
      //               }
      //            }
      //         }
      //      }

      if ($config->getField('use_requester_user_group_update') > 0
          && isset($ticket->input['_actors'])) {
         $actors = $ticket->input['_actors'];
         if (isset($actors['requester'])) {
            $requesters = $actors['requester'];
            // Select first group of this user
            $ko   = 0;
            $grp  = 0;
            $grps = [];
            foreach ($requesters as $requester) {
               if ($config->getField('use_requester_user_group_update') == 1) {
                  // First group
                  if ($requester['itemtype'] == 'User') {
                     $grp = PluginBehaviorsUser::getRequesterGroup($ticket->fields['entities_id'],
                                                                   $requester['items_id'],
                                                                   true);
                  }
                  if ($grp > 0 && $requester['itemtype'] == 'Group'
                      && $requester['items_id'] == $grp) {
                     $ko++;
                  }
                  if ($ko == 0) {
                     $actors['requester'][] = ['itemtype'          => 'Group',
                                               'items_id'          => $grp,
                                               'use_notification'  => "1",
                                               'alternative_email' => ""];
                  }
               } else if ($config->getField('use_requester_user_group_update') == 3) {
                  // Default group
                  $user = new User();
                  if ($requester['itemtype'] == 'User') {
                     if ($user->getFromDB($requester['itemtype']['items_id'])) {
                        $grp = $user->fields['groups_id'];
                     }
                  }
                  if ($grp > 0 && $requester['itemtype'] == 'Group'
                      && $requester['items_id'] == $grp) {
                     $ko++;
                  }
                  if ($grp > 0 && $ko == 0) {
                     $actors['requester'][] = ['itemtype'          => 'Group',
                                               'items_id'          => $grp,
                                               'use_notification'  => "1",
                                               'alternative_email' => ""];
                  }

               } else {
                  // All groups
                  if ($requester['itemtype'] == 'User') {
                     $grps = PluginBehaviorsUser::getRequesterGroup($ticket->fields['entities_id'],
                                                                    $requester['items_id'],
                                                                    false);
                  }
                  if ($requester['itemtype'] == 'Group'
                      && in_array($requester['items_id'], $grps)) {
                     unset($grps[$requester['items_id']]);
                  }
                  if (count($grps) > 0) {
                     foreach ($grps as $grp) {
                        $actors['requester'][] = ['itemtype'          => 'Group',
                                                  'items_id'          => $grp,
                                                  'use_notification'  => "1",
                                                  'alternative_email' => ""];
                     }
                  }
               }
            }
         }
         $ticket->input['_actors'] = $actors;
      }

      if ($config->getField('use_assign_user_group_update') > 0
          && isset($ticket->input['_actors'])) {
         $actors = $ticket->input['_actors'];
         if (isset($actors['assign'])) {
            $assigneds = $actors['assign'];
            // Select first group of this user
            $ko   = 0;
            $grp  = 0;
            $grps = [];
            foreach ($assigneds as $assigned) {
               if ($config->getField('use_assign_user_group_update') == 1) {
                  // First group
                  if ($assigned['itemtype'] == 'User') {
                     $grp = PluginBehaviorsUser::getTechnicianGroup($ticket->fields['entities_id'],
                                                                    $assigned['items_id'],
                                                                    true);
                  }
                  if ($grp > 0 && $assigned['itemtype'] == 'Group'
                      && $assigned['items_id'] == $grp) {
                     $ko++;
                  }
                  if ($ko == 0) {
                     $actors['assign'][] = ['itemtype'          => 'Group',
                                            'items_id'          => $grp,
                                            'use_notification'  => "1",
                                            'alternative_email' => ""];
                  }
               } else {
                  // All groups
                  if ($assigned['itemtype'] == 'User') {
                     $grps = PluginBehaviorsUser::getTechnicianGroup($ticket->fields['entities_id'],
                                                                     $assigned['items_id'],
                                                                     false);
                  }
                  if ($assigned['itemtype'] == 'Group'
                      && in_array($assigned['items_id'], $grps)) {
                     unset($grps[$assigned['items_id']]);
                  }
                  if (count($grps) > 0) {
                     foreach ($grps as $grp) {
                        $actors['assign'][] = ['itemtype'          => 'Group',
                                               'items_id'          => $grp,
                                               'use_notification'  => "1",
                                               'alternative_email' => ""];
                     }
                  }
               }
            }
         }
         $ticket->input['_actors'] = $actors;
      }

      if ($config->getField('ticketsolved_updatetech')
          && isset($ticket->input['status'])
          && in_array($ticket->input['status'], array_merge(Ticket::getSolvedStatusArray(),
                                                            Ticket::getClosedStatusArray()))) {

         $ticket_user = new Ticket_User();
         if ((!$ticket_user->getFromDBByCrit(['tickets_id' => $ticket->fields['id'],
                                              'type'       => CommonITILActor::ASSIGN])
              || (isset($ticket_user->fields['users_id'])
                  && ($ticket_user->fields['users_id'] != Session::getLoginUserID())))
             && (((in_array($ticket->fields['status'], Ticket::getSolvedStatusArray()))
                  && (in_array($ticket->input['status'], Ticket::getClosedStatusArray())))
                 || !in_array($ticket->fields['status'], array_merge(Ticket::getSolvedStatusArray(),
                                                                     Ticket::getClosedStatusArray())))) {

            $ticket_user->add(['tickets_id' => $ticket->getID(),
                               'users_id'   => Session::getLoginUserID(),
                               'type'       => CommonITILActor::ASSIGN]);
         }
      }
   }


   /**
    * @param \Ticket $ticket
    *
    * @return void
    */
   static function afterUpdate(Ticket $ticket) {

      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('add_notif')
          && in_array('status', $ticket->updates)) {

         if (in_array($ticket->oldvalues['status'],
                      array_merge(Ticket::getSolvedStatusArray(),
                                  Ticket::getClosedStatusArray()))
             && !in_array($ticket->input['status'],
                          array_merge(Ticket::getSolvedStatusArray(),
                                      Ticket::getClosedStatusArray()))) {

            NotificationEvent::raiseEvent('plugin_behaviors_ticketreopen', $ticket);

         } else if ($ticket->oldvalues['status'] <> $ticket->input['status']) {
            if ($ticket->input['status'] == CommonITILObject::WAITING) {
               NotificationEvent::raiseEvent('plugin_behaviors_ticketwaiting', $ticket);
            } else {
               NotificationEvent::raiseEvent('plugin_behaviors_ticketstatus', $ticket);
            }
         }
      }
   }


   /**
    * @param \Ticket $srce
    * @param array   $input
    *
    * @return array
    */
   //Disabled : integrated in glpi10
   //   static function preClone(Ticket $srce, array $input) {
   //      global $DB;
   //
   //      $user_reques                  = $srce->getUsers(CommonITILActor::REQUESTER);
   //      $input['_users_id_requester'] = [];
   //      foreach ($user_reques as $users) {
   //         $input['_users_id_requester'][] = $users['users_id'];
   //      }
   //      $user_observ                 = $srce->getUsers(CommonITILActor::OBSERVER);
   //      $input['_users_id_observer'] = [];
   //      foreach ($user_observ as $users) {
   //         $input['_users_id_observer'][] = $users['users_id'];
   //      }
   //      $user_assign               = $srce->getUsers(CommonITILActor::ASSIGN);
   //      $input['_users_id_assign'] = [];
   //      foreach ($user_assign as $users) {
   //         $input['_users_id_assign'][] = $users['users_id'];
   //      }
   //
   //      $group_reques                  = $srce->getGroups(CommonITILActor::REQUESTER);
   //      $input['_groups_id_requester'] = [];
   //      foreach ($group_reques as $groups) {
   //         $input['_groups_id_requester'][] = $groups['groups_id'];
   //      }
   //      $group_observ                 = $srce->getGroups(CommonITILActor::OBSERVER);
   //      $input['_groups_id_observer'] = [];
   //      foreach ($group_observ as $groups) {
   //         $input['_groups_id_observer'][] = $groups['groups_id'];
   //      }
   //      $group_assign               = $srce->getGroups(CommonITILActor::ASSIGN);
   //      $input['_groups_id_assign'] = [];
   //      foreach ($group_assign as $groups) {
   //         $input['_groups_id_assign'][] = $groups['groups_id'];
   //      }
   //
   //      $suppliers                     = $srce->getSuppliers(CommonITILActor::ASSIGN);
   //      $input['_suppliers_id_assign'] = [];
   //      foreach ($suppliers as $suppliers) {
   //         $input['_suppliers_id_assign'][] = $suppliers['groups_id'];
   //      }
   //
   //      return $input;
   //
   //   }


   /**
    * @param \Ticket $clone
    * @param         $oldid
    *
    * @return void
    */
   //Disabled : integrated in glpi10
   //   static function postClone(Ticket $clone, $oldid) {
   //      global $DB;
   //
   //      $dbu  = new DbUtils();
   //      $fkey = $dbu->getForeignKeyFieldForTable($clone->getTable());
   //      $crit = [$fkey => $oldid];
   //
   //      // add items of tickets source
   //      $item = new Item_Ticket();
   //      foreach ($DB->request($item->getTable(), $crit) as $dataitem) {
   //         $input = ['itemtype'   => $dataitem['itemtype'],
   //                   'items_id'   => $dataitem['items_id'],
   //                   'tickets_id' => $clone->getField('id')];
   //         $item->add($input);
   //      }
   //
   //      // link new ticket to ticket source
   //      $link      = new Ticket_Ticket();
   //      $inputlink = ['tickets_id_1' => $clone->getField('id'),
   //                    'tickets_id_2' => $oldid,
   //                    'link'         => 1];
   //      $link->add($inputlink);
   //
   //      if ($dbu->countElementsInTable("glpi_documents_items",
   //                                     ['itemtype' => 'Ticket',
   //                                      'items_id' => $oldid])) {
   //         $docitem = new Document_Item();
   //         foreach ($DB->request("glpi_documents_items", ['itemtype' => 'Ticket',
   //                                                        'items_id' => $oldid]) as $doc) {
   //            $inputdoc = ['documents_id' => $doc['documents_id'],
   //                         'items_id'     => $clone->getField('id'),
   //                         'itemtype'     => 'Ticket',
   //                         'entities_id'  => $doc['entities_id'],
   //                         'is_recursive' => $doc['is_recursive']];
   //            $docitem->add($inputdoc);
   //         }
   //      }
   //   }


   /**
    * @param $manager
    * @param $group_id
    * @param $target
    *
    * @return void
    */
   static function addForGroup($manager, $group_id, $target) {
      global $DB;

      $dbu = new DbUtils();

      // members/managers of the group allowed on object entity
      // filter group with 'is_assign' (attribute can be unset after notification)
      $query = "SELECT DISTINCT `glpi_users`.`id` AS users_id,
                               `glpi_users`.`language` AS language
               FROM `glpi_groups_users`
               INNER JOIN `glpi_users` ON (`glpi_groups_users`.`users_id` = `glpi_users`.`id`)
               INNER JOIN `glpi_profiles_users`
                     ON (`glpi_profiles_users`.`users_id` = `glpi_users`.`id` " .
               $dbu->getEntitiesRestrictRequest("AND", "glpi_profiles_users", "entities_id",
                                                $target->getEntity(), true) . ")
                           INNER JOIN `glpi_groups` ON (`glpi_groups_users`.`groups_id` = `glpi_groups`.`id`)
                           WHERE `glpi_groups_users`.`groups_id` = '$group_id'
                           AND `glpi_groups`.`is_notify`";

      if ($manager == 1) {
         $query .= " AND `glpi_groups_users`.`is_manager` ";
      } else if ($manager == 2) {
         $query .= " AND NOT `glpi_groups_users`.`is_manager` ";
      }

      foreach ($DB->request($query, '', true) as $data) {
         $target->addToRecipientsList($data);
      }
   }
}
