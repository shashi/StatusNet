<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Create a group via the API
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 *
 * @category  API
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';

/**
 * Make a new group. Sets the authenticated user as the administrator of the group.
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiGroupCreateAction extends ApiAuthAction
{
    var $group       = null;
    var $nickname    = null;
    var $fullname    = null;
    var $homepage    = null;
    var $description = null;
    var $location    = null;
    var $aliasstring = null;
    var $aliases     = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */

    function prepare($args)
    {
        parent::prepare($args);

        $this->user  = $this->auth_user;

        $this->nickname    = $this->arg('nickname');
        $this->fullname    = $this->arg('full_name');
        $this->homepage    = $this->arg('homepage');
        $this->description = $this->arg('description');
        $this->location    = $this->arg('location');
        $this->aliasstring = $this->arg('aliases');

        return true;
    }

    /**
     * Handle the request
     *
     * Save the new group
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
             $this->clientError(
                 _('This method requires a POST.'),
                 400,
                 $this->format
             );
             return;
        }

        if (empty($this->user)) {
            $this->clientError(_('No such user.'), 404, $this->format);
            return;
        }

        if ($this->validateParams() == false) {
            return;
        }

        $group = User_group::register(array('nickname' => $this->nickname,
                                            'fullname' => $this->fullname,
                                            'homepage' => $this->homepage,
                                            'description' => $this->description,
                                            'location' => $this->location,
                                            'aliases'  => $this->aliases,
                                            'userid'   => $this->user->id,
                                            'local'    => true));

        switch($this->format) {
        case 'xml':
            $this->showSingleXmlGroup($group);
            break;
        case 'json':
            $this->showSingleJsonGroup($group);
            break;
        default:
            $this->clientError(
                _('API method not found.'),
                404,
                $this->format
            );
            break;
        }

    }

    /**
     * Validate params for the new group
     *
     * @return void
     */

    function validateParams()
    {
        $valid = Validate::string(
            $this->nickname, array(
                'min_length' => 1,
                'max_length' => 64,
                'format' => NICKNAME_FMT
            )
        );

        if (!$valid) {
            $this->clientError(
                _(
                    'Nickname must have only lowercase letters ' .
                    'and numbers and no spaces.'
                ),
                403,
                $this->format
            );
            return false;
        } elseif ($this->groupNicknameExists($this->nickname)) {
            $this->clientError(
                _('Nickname already in use. Try another one.'),
                403,
                $this->format
            );
            return false;
        } else if (!User_group::allowedNickname($this->nickname)) {
            $this->clientError(
                _('Not a valid nickname.'),
                403,
                $this->format
            );
            return false;

        } elseif (
            !is_null($this->homepage)
            && strlen($this->homepage) > 0
            && !Validate::uri(
                $this->homepage, array(
                    'allowed_schemes' =>
                    array('http', 'https')
                )
            )) {
            $this->clientError(
                _('Homepage is not a valid URL.'),
                403,
                $this->format
            );
            return false;
        } elseif (
            !is_null($this->fullname)
            && mb_strlen($this->fullname) > 255) {
                $this->clientError(
                    _('Full name is too long (max 255 chars).'),
                    403,
                    $this->format
                );
            return false;
        } elseif (User_group::descriptionTooLong($this->description)) {
            $this->clientError(
                sprintf(
                    _('Description is too long (max %d chars).'),
                    User_group::maxDescription()
                ),
                403,
                $this->format
            );
            return false;
        } elseif (
            !is_null($this->location)
            && mb_strlen($this->location) > 255) {
                $this->clientError(
                    _('Location is too long (max 255 chars).'),
                    403,
                    $this->format
                );
            return false;
        }

        if (!empty($this->aliasstring)) {
            $this->aliases = array_map(
                'common_canonical_nickname',
                array_unique(preg_split('/[\s,]+/', $this->aliasstring))
            );
        } else {
            $this->aliases = array();
        }

        if (count($this->aliases) > common_config('group', 'maxaliases')) {
            $this->clientError(
                sprintf(
                    _('Too many aliases! Maximum %d.'),
                    common_config('group', 'maxaliases')
                ),
                403,
                $this->format
            );
            return false;
        }

        foreach ($this->aliases as $alias) {

            $valid = Validate::string(
                $alias, array(
                    'min_length' => 1,
                    'max_length' => 64,
                    'format' => NICKNAME_FMT
                )
            );

            if (!$valid) {
                $this->clientError(
                    sprintf(_('Invalid alias: "%s".'), $alias),
                    403,
                    $this->format
                );
                return false;
            }
            if ($this->groupNicknameExists($alias)) {
                $this->clientError(
                    sprintf(
                        _('Alias "%s" already in use. Try another one.'),
                        $alias
                    ),
                    403,
                    $this->format
                );
                return false;
            }

            // XXX assumes alphanum nicknames

            if (strcmp($alias, $this->nickname) == 0) {
                $this->clientError(
                    _('Alias can\'t be the same as nickname.'),
                    403,
                    $this->format
                );
                return false;
            }
        }

        // Evarything looks OK

        return true;
    }

    /**
     * Check to see whether a nickname is already in use by a group
     *
     * @param String $nickname The nickname in question
     *
     * @return boolean true or false
     */

    function groupNicknameExists($nickname)
    {
        $local = Local_group::staticGet('nickname', $nickname);

        if (!empty($local)) {
            return true;
        }

        $alias = Group_alias::staticGet('alias', $nickname);

        if (!empty($alias)) {
            return true;
        }

        return false;
    }

}
