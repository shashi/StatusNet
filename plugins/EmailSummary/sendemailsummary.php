#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

$shortoptions = 'i:n:a';
$longoptions = array('id=', 'nickname=', 'all');

$helptext = <<<END_OF_SENDEMAILSUMMARY_HELP
sendemailsummary.php [options]
Send an email summary of the inbox to users

  -i --id       ID of user to send summary to
  -n --nickname nickname of the user to send summary to
  -a --all      send summary to all users

END_OF_SENDEMAILSUMMARY_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$qm = QueueManager::get();

// enqueue summary for user or all users

try {
    $user = getUser();
    $qm->enqueue($user->id, 'usersum');
} catch (NoUserArgumentException $nuae) {
    $qm->enqueue(null, 'sitesum');
}
