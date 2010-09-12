#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

$helptext = <<<ENDOFHELP
uncache_users.php <idfile>

Uncache users listed in an ID file, default 'ids.txt'.

ENDOFHELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$id_file = (count($args) > 1) ? $args[0] : 'ids.txt';

common_log(LOG_INFO, 'Updating user inboxes.');

$ids = file($id_file);

$memc = Cache::instance();

foreach ($ids as $id) {

	$user = User::staticGet('id', $id);

	if (!$user) {
		common_log(LOG_WARNING, 'No such user: ' . $id);
		continue;
	}

    $user->decache();

    $memc->delete(Cache::key('user:notices_with_friends:'. $user->id));
    $memc->delete(Cache::key('user:notices_with_friends:'. $user->id . ';last'));
}
