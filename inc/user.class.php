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

class PluginBehaviorsUser {

   static private function getUserGroup ($entity, $userid, $filter='') {
      global $DB;

      $config = PluginBehaviorsConfig::getInstance();

      $query = "SELECT glpi_groups.id
                FROM glpi_groups_users
                INNER JOIN glpi_groups ON (glpi_groups.id = glpi_groups_users.groups_id)
                WHERE glpi_groups_users.users_id='$userid'".
                getEntitiesRestrictRequest(' AND ', 'glpi_groups', '', $entity, true);

      if ($filter) {
         $query .= "AND ($filter)";
      }
      foreach ($DB->request($query) as $data) {
         return ($data['id']);
      }
      return 0;
   }

   static function getRequesterGroup ($entity, $userid) {

      return self::getUserGroup($entity, $userid, '`is_requester`');
   }

   static function getTechnicianGroup ($entity, $userid) {

      return self::getUserGroup($entity, $userid, '`is_assign`');
   }

}
