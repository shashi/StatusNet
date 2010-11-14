<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin that requires the user to have a validated email address before they
 * can post notices
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Plugin for requiring a validated email before posting.
 *
 * Enable this plugin using addPlugin('RequireValidatedEmail');
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Brion Vibber <brion@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class RequireValidatedEmailPlugin extends Plugin
{
    /**
     * Users created before this time will be grandfathered in
     * without the validation requirement.
     */

    public $grandfatherCutoff = null;

    /**
     * If OpenID plugin is installed, users with a verified OpenID
     * association whose provider URL matches one of these regexes
     * will be considered to be sufficiently valid for our needs.
     *
     * For example, to trust WikiHow and Wikipedia OpenID users:
     *
     * addPlugin('RequireValidatedEmailPlugin', array(
     *    'trustedOpenIDs' => array(
     *        '!^http://\w+\.wikihow\.com/!',
     *        '!^http://\w+\.wikipedia\.org/!',
     *    ),
     * ));
     */

    public $trustedOpenIDs = array();

    /**
     * Event handler for notice saves; rejects the notice
     * if user's address isn't validated.
     *
     * @param Notice $notice The notice being saved
     *
     * @return bool hook result code
     */

    function onStartNoticeSave($notice)
    {
        $user = User::staticGet('id', $notice->profile_id);
        if (!empty($user)) { // it's a remote notice
            if (!$this->validated($user)) {
                $msg = _m("You must validate your email address before posting.");
                throw new ClientException($msg);
            }
        }
        return true;
    }

    /**
     * Event handler for registration attempts; rejects the registration
     * if email field is missing.
     *
     * @param Action $action Action being executed
     *
     * @return bool hook result code
     */
    function onStartRegistrationTry($action)
    {
        $email = $action->trimmed('email');

        if (empty($email)) {
            $action->showForm(_m('You must provide an email address to register.'));
            return false;
        }

        // Default form will run address format validation and reject if bad.

        return true;
    }

    /**
     * Check if a user has a validated email address or has been
     * otherwise grandfathered in.
     *
     * @param User $user User to valide
     *
     * @return bool
     */
    protected function validated($user)
    {
        // The email field is only stored after validation...
        // Until then you'll find them in confirm_address.
        $knownGood = !empty($user->email) ||
          $this->grandfathered($user) ||
          $this->hasTrustedOpenID($user);

        // Give other plugins a chance to override, if they can validate
        // that somebody's ok despite a non-validated email.

        // FIXME: This isn't how to do it! Use Start*/End* instead

        Event::handle('RequireValidatedEmailPlugin_Override',
                      array($user, &$knownGood));

        return $knownGood;
    }

    /**
     * Check if a user was created before the grandfathering cutoff.
     * If so, we won't need to check for validation.
     *
     * @param User $user User to check
     *
     * @return bool true if user is grandfathered
     */
    protected function grandfathered($user)
    {
        if ($this->grandfatherCutoff) {
            $created = strtotime($user->created . " GMT");
            $cutoff  = strtotime($this->grandfatherCutoff);
            if ($created < $cutoff) {
                return true;
            }
        }
        return false;
    }

    /**
     * Override for RequireValidatedEmail plugin. If we have a user who's
     * not validated an e-mail, but did come from a trusted provider,
     * we'll consider them ok.
     *
     * @param User $user User to check
     *
     * @return bool true if user has a trusted OpenID.
     */

    function hasTrustedOpenID($user)
    {
        if ($this->trustedOpenIDs && class_exists('User_openid')) {
            foreach ($this->trustedOpenIDs as $regex) {
                $oid = new User_openid();

                $oid->user_id = $user->id;

                $oid->find();
                while ($oid->fetch()) {
                    if (preg_match($regex, $oid->canonical)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Add version information for this plugin.
     *
     * @param array &$versions Array of associative arrays of version data
     *
     * @return boolean hook value
     */

    function onPluginVersion(&$versions)
    {
        $versions[] =
          array('name' => 'Require Validated Email',
                'version' => STATUSNET_VERSION,
                'author' => 'Craig Andrews, '.
                'Evan Prodromou, '.
                'Brion Vibber',
                'homepage' =>
                'http://status.net/wiki/Plugin:RequireValidatedEmail',
                'rawdescription' =>
                _m('Disables posting without a validated email address.'));
        return true;
    }

    /**
     * Hide the notice form if the user isn't able to post.
     *
     * @param Action $action action being shown
     *
     * @return boolean hook value
     */

    function onStartShowNoticeForm($action)
    {
        $user = common_current_user();
        if (!empty($user)) { // it's a remote notice
            if (!$this->validated($user)) {
                return false;
            }
        }
        return true;
    }
}
