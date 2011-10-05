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

class PluginBehaviorsGroup_Ticket {

   static function afterAdd(Group_Ticket $item) {
      global $DB, $LANG;

      //logDebug("PluginBehaviorsGroup_Ticket::afterAdd()", $item);
      $config = PluginBehaviorsConfig::getInstance();

      if ($config->getField('add_notif')) {
         if ($item->getField('type') == Ticket::ASSIGN) {
            $ticket = new Ticket();
            if ($ticket->getFromDB($item->getField('tickets_id'))) {
               NotificationEvent::raiseEvent('plugin_behaviors_ticketnewgrp', $ticket);
            }
         }
      }
   }
}
