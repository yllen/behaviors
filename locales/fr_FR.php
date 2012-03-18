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

$LANG['plugin_behaviors'][0] = "Comportements";

// Config
$LANG['plugin_behaviors'][1] = "Prendre le groupe du matériel associé";
$LANG['plugin_behaviors'][2] = "Prendre le groupe du demandeur";
$LANG['plugin_behaviors'][6] = "Prendre le groupe du technicien";
$LANG['plugin_behaviors'][7] = "Durée obligatoire pour résoudre/fermer un ticket";
$LANG['plugin_behaviors'][8] = "Type de solution obligatoire pour résoudre/fermer un ticket";
$LANG['plugin_behaviors'][9] = "Renseigner la date de mise en service lors du passage au statut";
$LANG['plugin_behaviors'][10] = "Format de numérotation des tickets";
$LANG['plugin_behaviors'][11] = "Supprimer la machine dans OCS lors de la purge dans GLPI";
$LANG['plugin_behaviors'][12] = "Le plugin \"Désinstallation d'un matériel\" n'est pas installé";
$LANG['plugin_behaviors'][13] = "Demandeur obligatoire";
$LANG['plugin_behaviors'][14] = "Interdire la modification de la date d'ouverture";
$LANG['plugin_behaviors'][15] = "Notifications supplémentaires";
$LANG['plugin_behaviors'][16] = "Attribution à un technicien";
$LANG['plugin_behaviors'][17] = "Attribution à un groupe";
$LANG['plugin_behaviors'][18] = "Ticket réouvert";

// Message
$LANG['plugin_behaviors'][100] = "Impossible de fermer un ticket sans type de solution";
$LANG['plugin_behaviors'][101] = "Impossible de fermer un ticket sans durée";
