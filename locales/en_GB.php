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

$LANG['plugin_behaviors'][0] = "Behaviours";

// Config
$LANG['plugin_behaviors'][1] = "Use the associated item's group";
$LANG['plugin_behaviors'][2] = "Use the requester's group";
$LANG['plugin_behaviors'][3] = "SQL filter for requester's group";
$LANG['plugin_behaviors'][4] = "SQL filter for technician's group";
$LANG['plugin_behaviors'][5] = "Invalid SQL filter";
$LANG['plugin_behaviors'][6] = "Use the technician's group";
$LANG['plugin_behaviors'][7] = "Duration is mandatory before ticket is solved/closed";
$LANG['plugin_behaviors'][8] = "Type of solution is mandatory before ticket is solved/closed";
$LANG['plugin_behaviors'][9] = "Set the financial startup date when new status is";
$LANG['plugin_behaviors'][10] = "Ticket's number format";
$LANG['plugin_behaviors'][11] = "Delete computer in OCS when purged from GLPI";
$LANG['plugin_behaviors'][12] = "Plugin \"Item's uninstallation\" not installed";
$LANG['plugin_behaviors'][13] = "Requester is mandatory";
$LANG['plugin_behaviors'][14] = "Deny change of ticket's creation date";

// Message
$LANG['plugin_behaviors'][100] = "You cannot close a ticket without solution type";
$LANG['plugin_behaviors'][101] = "You cannot close a ticket without duration";
?>
