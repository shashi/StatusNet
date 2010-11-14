<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'i:n:f:';
$longoptions = array('id=', 'nickname=', 'file=');

$helptext = <<<END_OF_EXPORTACTIVITYSTREAM_HELP
exportactivitystream.php [options]
Export a StatusNet user history to a file

  -i --id       ID of user to export
  -n --nickname nickname of the user to export
  -f --file     file to export to (default STDOUT)

END_OF_EXPORTACTIVITYSTREAM_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

try {
    $user = getUser();
    $actstr = new UserActivityStream($user);
    print $actstr->getString();
} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}
