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
 @author    Remi Collet, Nelly Mahu-Lasson
 @copyright Copyright (c) 2010-2017 Behaviors plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/behaviors
 @link      http://www.glpi-project.org/
 @since     2010

 --------------------------------------------------------------------------
*/

class PluginBehaviorsTicket {

   const LAST_TECH_ASSIGN       = 50;
   const LAST_GROUP_ASSIGN      = 51;
   const LAST_SUPPLIER_ASSIGN   = 52;
   const LAST_WATCHER_ADDED     = 53;



   static function addEvents(NotificationTargetTicket $target) {

      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('add_notif')) {
         Plugin::loadLang('behaviors');
         $target->events['plugin_behaviors_ticketnewtech']  = __('Assign to a technician', 'behaviors');
         $target->events['plugin_behaviors_ticketnewgrp']   = __('Assign to a group', 'behaviors');
         $target->events['plugin_behaviors_ticketnewsupp']  = __('Assign to a supplier', 'behaviors');
         $target->events['plugin_behaviors_ticketnewwatch'] = __('Add a watcher', 'behaviors');
         $target->events['plugin_behaviors_ticketreopen']   = __('Reopen ticket', 'behaviors');
         $target->events['plugin_behaviors_ticketstatus']   = __('Change status', 'behaviors');
         PluginBehaviorsDocument_Item::addEvents($target);
      }
   }


   static function addTargets(NotificationTargetTicket $target) {

      $target->addTarget(self::LAST_TECH_ASSIGN , __('Last technician assigned', 'behaviors'));
      $target->addTarget(self::LAST_GROUP_ASSIGN , __('Last group assigned', 'behaviors'));
      $target->addTarget(self::LAST_SUPPLIER_ASSIGN , __('Last supplier assigned', 'behaviors'));
      $target->addTarget(self::LAST_WATCHER_ADDED , __('Last watcher added', 'behaviors'));
   }


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
      }
   }


   static function getLastLinkedUserByType($type, $target) {
      global $DB, $CFG_GLPI;

      $userlinktable = getTableForItemType($target->obj->userlinkclass);
      $fkfield       = $target->obj->getForeignKeyField();

      $last = "SELECT MAX(`id`) AS lastid
               FROM `$userlinktable`
               WHERE `$userlinktable`.`$fkfield` = '".$target->obj->fields["id"]."'
                      AND `$userlinktable`.`type` = '$type'";
      $result = $DB->query($last);

      $querylast = '';
      if ($data = $DB->fetch_assoc($result)) {
         $object = new $target->obj->userlinkclass();
         if ($object->getFromDB($data['lastid'])) {
            $querylast = " AND `$userlinktable`.`users_id` = '".$object->fields['users_id']."'";
         }
      }

      //Look for the user by his id
      $query =  $target->getDistinctUserSql().",
                      `$userlinktable`.`use_notification` AS notif,
                      `$userlinktable`.`alternative_email` AS altemail
                FROM `$userlinktable`
                LEFT JOIN `glpi_users` ON (`$userlinktable`.`users_id` = `glpi_users`.`id`)".
                $target->getProfileJoinSql()."
                WHERE `$userlinktable`.`$fkfield` = '".$target->obj->fields["id"]."'
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
                && NotificationMail::isUserAddressValid($data['altemail'])) {
               $author_email = $data['altemail'];
            }
            if (empty($author_lang)) {
               $author_lang = $CFG_GLPI["language"];
            }
            if (empty($author_id)) {
               $author_id = -1;
            }
            $target->addToAddressesList(array('email'    => $author_email,
                                              'language' => $author_lang,
                                              'users_id' => $author_id));
         }
      }

      // Anonymous user
      $query = "SELECT `alternative_email`
                FROM `$userlinktable`
                WHERE `$userlinktable`.`$fkfield` = '".$target->obj->fields["id"]."'
                      AND `$userlinktable`.`users_id` = 0
                      AND `$userlinktable`.`use_notification` = 1
                      AND `$userlinktable`.`type` = '$type'";
      foreach ($DB->request($query) as $data) {
         if (NotificationMail::isUserAddressValid($data['alternative_email'])) {
            $target->addToAddressesList(array('email'    => $data['alternative_email'],
                                              'language' => $CFG_GLPI["language"],
                                              'users_id' => -1));
         }
      }
   }


   static function getLastLinkedGroupByType($type, $target) {
      global $DB;

      $grouplinktable = getTableForItemType($target->obj->grouplinkclass);
      $fkfield        = $target->obj->getForeignKeyField();

      $last = "SELECT MAX(`id`) AS lastid
               FROM `$grouplinktable`
               WHERE `$grouplinktable`.`$fkfield` = '".$target->obj->fields["id"]."'
                     AND `$grouplinktable`.`type` = '$type'";
      $result = $DB->query($last);
      $data = $DB->fetch_assoc($result);

      $querylast = '';
      $object = new $target->obj->grouplinkclass();
      if ($object->getFromDB($data['lastid'])) {
         $querylast = " AND `$grouplinktable`.`groups_id` = '".$object->fields['groups_id']."'";
      }

      //Look for the user by his id
      $query = "SELECT `groups_id`
                FROM `$grouplinktable`
                WHERE `$grouplinktable`.`$fkfield` = '".$target->obj->fields["id"]."'
                      AND `$grouplinktable`.`type` = '$type'
                      $querylast";

      foreach ($DB->request($query) as $data) {
         //Add the group in the notified users list
         $target->getAddressesByGroup(0, $data['groups_id']);
      }
   }


   static  function getLastSupplierAddress($target) {
      global $DB;

      if (!$target->options['sendprivate']
          && $target->obj->countSuppliers(CommonITILActor::ASSIGN)) {

         $supplierlinktable = getTableForItemType($target->obj->supplierlinkclass);
         $fkfield           = $target->obj->getForeignKeyField();

         $last = "SELECT MAX(`id`) AS lastid
                  FROM `$supplierlinktable`
                  WHERE `$supplierlinktable`.`$fkfield` = '".$target->obj->fields["id"]."'";

         $result = $DB->query($last);
         $data = $DB->fetch_assoc($result);

         $querylast = '';
         $object = new $target->obj->supplierlinkclass();
         if ($object->getFromDB($data['lastid'])) {
            $querylast = " AND `$supplierlinktable`.`suppliers_id` = '".$object->fields['suppliers_id']."'";
         }
         $query = "SELECT DISTINCT `glpi_suppliers`.`email` AS email,
                                   `glpi_suppliers`.`name` AS name
                   FROM `$supplierlinktable`
                   LEFT JOIN `glpi_suppliers`
                      ON (`$supplierlinktable`.`suppliers_id` = `glpi_suppliers`.`id`)
                   WHERE `$supplierlinktable`.`$fkfield` = '".$target->obj->getID()."'
                         $querylast";

         foreach ($DB->request($query) as $data) {
            $target->addToAddressesList($data);
         }
      }
   }


   static function beforeAdd(Ticket $ticket) {
      global $DB;

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
          && ($_SESSION['glpiactiveprofile']['interface'] == 'central')) {

         if ($config->getField('is_requester_mandatory')
             && !$ticket->input['_users_id_requester']
             && (!isset($ticket->input['_users_id_requester_notif']['alternative_email'])
                 || empty($ticket->input['_users_id_requester_notif']['alternative_email']))) {
            Session::addMessageAfterRedirect(__('Requester is mandatory', 'behaviors'), true, ERROR);
            $ticket->input = array();
            return true;

         }
      }
      if ($config->getField('use_requester_item_group')
          && isset($ticket->input['items_id'])
          && (is_array($ticket->input['items_id']))) {
         foreach ($ticket->input['items_id'] as $type => $items) {
            if (($item = getItemForItemtype($type))
                && (!isset($ticket->input['_groups_id_requester'])
                    || ($ticket->input['_groups_id_requester'] <= 0))) {

               if ($item->isField('groups_id')) {
                  foreach ($items as $itemid) {
                     if ($item->getFromDB($itemid)) {
                        $ticket->input['_groups_id_requester'] = $item->getField('groups_id');
                     }
                  }
               }
            }
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
          && ($ticket->input['_users_id_requester'] > 0)
          && (!isset($ticket->input['_groups_id_requester']) || $ticket->input['_groups_id_requester']<=0)) {

            if ($config->getField('use_requester_user_group') == 1) {
               // First group
               $ticket->input['_groups_id_requester']
                  = PluginBehaviorsUser::getRequesterGroup($ticket->input['entities_id'],
                                                           $ticket->input['_users_id_requester'],
                                                           true);
            } else {
               // All groups
               $g = PluginBehaviorsUser::getRequesterGroup($ticket->input['entities_id'],
                                                           $ticket->input['_users_id_requester'],
                                                           false);
               if (count($g)) {
                  $ticket->input['_groups_id_requester'] = array_shift($g);
               }
               if (count($g)) {
                  $ticket->input['_additional_groups_requesters'] = $g;
               }
            }
      }
      // Toolbox::logDebug("PluginBehaviorsTicket::beforeAdd(), Updated input=", $ticket->input);
   }


   static function afterPrepareAdd(Ticket $ticket) {
      global $DB;

      if (!is_array($ticket->input) || !count($ticket->input)) {
         // Already cancel by another plugin
         return false;
      }

      // Toolbox::logDebug("PluginBehaviorsTicket::afterPrepareAdd(), Ticket=", $ticket);
      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('use_assign_user_group')
          && isset($ticket->input['_users_id_assign'])
          && ($ticket->input['_users_id_assign'] > 0)
          && (!isset($ticket->input['_groups_id_assign'])
              || ($ticket->input['_groups_id_assign'] <= 0))) {

         if ($config->getField('use_assign_user_group')==1) {
            // First group
            $ticket->input['_groups_id_assign']
               = PluginBehaviorsUser::getTechnicianGroup($ticket->input['entities_id'],
                                                         $ticket->input['_users_id_assign'],
                                                         true);
         } else {
            // All groups
            $ticket->input['_additional_groups_assigns']
               = PluginBehaviorsUser::getTechnicianGroup($ticket->input['entities_id'],
                                                         $ticket->input['_users_id_assign'],
                                                         false);
         }
      }
   }


   static function beforeUpdate(Ticket $ticket) {

      if (!is_array($ticket->input) || !count($ticket->input)) {
         // Already cancel by another plugin
         return false;
      }

      //Toolbox::logDebug("PluginBehaviorsTicket::beforeUpdate(), Ticket=", $ticket);
      $config = PluginBehaviorsConfig::getInstance();

      // Check is the connected user is a tech
      if (!is_numeric(Session::getLoginUserID(false))
          || !Session::haveRight('ticket', Ticket::OWN)) {
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
                           getUserName($ticket->fields['users_id_lastupdater']).', '.
                           Html::convDateTime($ticket->fields['date_mod']));

         Session::addMessageAfterRedirect($msg, true, ERROR);
         return $ticket->input = false;
      }

      $soltyp  = (isset($ticket->input['solutiontypes_id'])
                        ? $ticket->input['solutiontypes_id']
                        : $ticket->fields['solutiontypes_id']);
      $dur     = (isset($ticket->input['actiontime'])
                        ? $ticket->input['actiontime']
                        : $ticket->fields['actiontime']);
      $soldesc = (isset($ticket->input['solution'])
                        ? $ticket->input['solution']
                        : $ticket->fields['solution']);
      $cat    = (isset($ticket->input['itilcategories_id'])
                        ? $ticket->input['itilcategories_id']
                        : $ticket->fields['itilcategories_id']);
      $loc    = (isset($ticket->input['locations_id'])
                        ? $ticket->input['locations_id']
                        : $ticket->fields['locations_id']);

      // Wand to solve/close the ticket
      if ((isset($ticket->input['solutiontypes_id']) && $ticket->input['solutiontypes_id'])
          || (isset($ticket->input['solution']) && $ticket->input['solution'])
          || (isset($ticket->input['status'])
              && in_array($ticket->input['status'],
                          array(implode("','",Ticket::getSolvedStatusArray()),
                                implode("','",Ticket::getclosedStatusArray()))))) {

         if ($config->getField('is_ticketrealtime_mandatory')) {
            if (!$dur) {
               unset($ticket->input['status']);
               unset($ticket->input['solution']);
               unset($ticket->input['solutiontypes_id']);
               Session::addMessageAfterRedirect(__("Duration is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }
         if ($config->getField('is_ticketsolutiontype_mandatory')) {
            if (!$soltyp) {
               unset($ticket->input['status']);
               unset($ticket->input['solution']);
               unset($ticket->input['solutiontypes_id']);
               Session::addMessageAfterRedirect(__("Type of solution is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }
         if ($config->getField('is_ticketsolution_mandatory')) {
            if (!$soldesc) {
               unset($ticket->input['status']);
               unset($ticket->input['solution']);
               unset($ticket->input['solutiontypes_id']);
               Session::addMessageAfterRedirect(__("Description of solution is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }
         if ($config->getField('is_ticketcategory_mandatory')) {
            if (!$cat) {
               unset($ticket->input['status']);
               unset($ticket->input['solution']);
               unset($ticket->input['solutiontypes_id']);
               Session::addMessageAfterRedirect(__("Category is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }
         if ($config->getField('is_tickettech_mandatory')) {
            if (($ticket->countUsers(CommonITILActor::ASSIGN) == 0)
                  && !isset($input["_itil_assign"]['users_id'])) {
               unset($ticket->input['status']);
               unset($ticket->input['solution']);
               unset($ticket->input['solutiontypes_id']);
               Session::addMessageAfterRedirect(__("Technician assigned is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }

         if ($config->getField('is_ticketlocation_mandatory')) {
            if (!$loc) {
               unset($ticket->input['status']);
               unset($ticket->input['solution']);
               unset($ticket->input['solutiontypes_id']);
               Session::addMessageAfterRedirect(__("Location is mandatory before ticket is solved/closed",
                                                   'behaviors'), true, ERROR);
            }
         }

         if ($config->getField('use_requester_item_group')
             && isset($ticket->input['items_id'])
             && (is_array($ticket->input['items_id']))) {
            foreach ($ticket->input['items_id'] as $type => $items) {
               foreach ($items as $number => $id) {
                  if (($item = getItemForItemtype($id))
                      && (!isset($ticket->input['_groups_id_requester'])
                          || ($ticket->input['_groups_id_requester'] <= 0))) {

                     if ($item->isField('groups_id')) {
                        foreach ($items as $itemid) {
                           if ($item->getFromDB($itemid)) {
                              $ticket->input['_groups_id_requester'] = $item->getField('groups_id');
                           }
                        }
                     }
                  }
               }
            }
         }

      }
   }


   static function onNewTicket() {

      if (isset($_SESSION['glpiactiveprofile']['interface'])
          && ($_SESSION['glpiactiveprofile']['interface'] == 'central')) {

         if (strstr($_SERVER['PHP_SELF'], "/front/ticket.form.php")
             && isset($_POST['id'])
             && ($_POST['id'] == 0)
             && !isset($_GET['id'])) {

            $config = PluginBehaviorsConfig::getInstance();

            // Only if config to add the "first" group
            if (($config->getField('use_requester_user_group') == 1)
                && isset($_POST['_users_id_requester']) && ($_POST['_users_id_requester'] > 0)
                && (!isset($_POST['_groups_id_requester'])
                    || ($_POST['_groups_id_requester'] <= 0)
                    || (isset($_SESSION['glpi_behaviors_auto_group'])
                        && ($_SESSION['glpi_behaviors_auto_group']
                              == $_POST['_groups_id_requester'])))) {

               // Select first group of this user
               $grp = PluginBehaviorsUser::getRequesterGroup($_POST['entities_id'],
                                                             $_POST['_users_id_requester'],
                                                             true);
               $_SESSION['glpi_behaviors_auto_group'] = $grp;
               $_REQUEST['_groups_id_requester']      = $grp;

            } else if (($config->getField('use_requester_user_group') == 1)
                && isset($_POST['_users_id_requester']) && ($_POST['_users_id_requester'] <= 0)
                && isset($_POST['_groups_id_requester'])
                && isset($_SESSION['glpi_behaviors_auto_group'])
                && ($_SESSION['glpi_behaviors_auto_group'] == $_POST['_groups_id_requester'])) {

               // clear user, so clear group
               $_SESSION['glpi_behaviors_auto_group'] = 0;
               $_REQUEST['_groups_id_requester']      = 0;
            } else {
               unset($_SESSION['glpi_behaviors_auto_group']);
            }
         } else if (strstr($_SERVER['PHP_SELF'], "/front/ticket.form.php")) {
            unset($_SESSION['glpi_behaviors_auto_group']);
         }
      }
   }


   static function afterUpdate(Ticket $ticket) {
      // Toolbox::logDebug("PluginBehaviorsTicket::afterUpdate(), Ticket=", $ticket);

      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('add_notif')
          && in_array('status', $ticket->updates)) {

         if (in_array($ticket->oldvalues['status'],
                      array(implode("','",Ticket::getSolvedStatusArray()),
                            implode("','",Ticket::getClosedStatusArray())))
             && !in_array($ticket->input['status'],
                          array(implode("','",Ticket::getSolvedStatusArray()),
                                implode("','",Ticket::getClosedStatusArray())))) {

            NotificationEvent::raiseEvent('plugin_behaviors_ticketreopen', $ticket);

         } else if ($ticket->oldvalues['status'] <> $ticket->input['status']) {
             NotificationEvent::raiseEvent('plugin_behaviors_ticketstatus', $ticket);
         }
      }
   }
}
