<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Miscellaneous settings
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
 * @category  Settings
 * @package   StatusNet
 * @author    Robin Millette <millette@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/accountsettingsaction.php';

/**
 * Miscellaneous settings actions
 *
 * Currently this just manages URL shortening.
 *
 * @category Settings
 * @package  StatusNet
 * @author   Robin Millette <millette@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class UrlsettingsAction extends AccountSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _('URL settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        // TRANS: Instructions for tab "Other" in user profile settings.
        return _('Manage various other options.');
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('urlshorteningservice');
    }

    /**
     * Content area of the page
     *
     * Shows a form for uploading an avatar.
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_other',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('urlsettings')));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        $this->elementStart('ul', 'form_data');

        $shorteners = array(_('[none]') => array('freeService' => false));

        Event::handle('GetUrlShorteners', array(&$shorteners));

        $services = array();
        foreach($shorteners as $name=>$value)
        {
            $services[$name]=$name;
            if($value['freeService']){
                // TRANS: Used as a suffix for free URL shorteners in a dropdown list in the tab "Other" of a
                // TRANS: user's profile settings. This message has one space at the beginning. Use your
                // TRANS: language's word separator here if it has one (most likely a single space).
                $services[$name].=_(' (free service)');
            }
        }
        if($services)
        {
            asort($services);

            $this->elementStart('li');
            // TRANS: Label for dropdown with URL shortener services.
            $this->dropdown('urlshorteningservice', _('Shorten URLs with'),
                            // TRANS: Tooltip for for dropdown with URL shortener services.
                            $services, _('Automatic shortening service to use.'),
                            false, $user->urlshorteningservice);
            $this->elementEnd('li');
        }
        $this->elementStart('li');
        $this->input('maxurllength',
                     _('URL longer than'),
                     (!is_null($this->arg('maxurllength'))) ?
                     $this->arg('maxurllength') : User_urlshortener_prefs::maxUrlLength($user),
                     _('URLs longer than this will be shortened, 0 means always shorten.'));
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->input('maxnoticelength',
                     _('Text longer than'),
                     (!is_null($this->arg('maxnoticelength'))) ?
                     $this->arg('maxnoticelength') : User_urlshortener_prefs::maxNoticeLength($user),
                     _('URLs in notices longer than this will be shortened, 0 means always shorten.'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        // TRANS: Button text for saving "Other settings" in profile.
        $this->submit('save', _m('BUTTON','Save'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Handle a post
     *
     * Saves the changes to url-shortening prefs and shows a success or failure
     * message.
     *
     * @return void
     */

    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        $urlshorteningservice = $this->trimmed('urlshorteningservice');

        if (!is_null($urlshorteningservice) && strlen($urlshorteningservice) > 50) {
            // TRANS: Form validation error for form "Other settings" in user profile.
            $this->showForm(_('URL shortening service is too long (maximum 50 characters).'));
            return;
        }

        $maxurllength = $this->trimmed('maxurllength');

        if (!Validate::number($maxurllength, array('min' => 0))) {
            throw new ClientException(_('Invalid number for max url length.'));
        }

        $maxnoticelength = $this->trimmed('maxnoticelength');

        if (!Validate::number($maxnoticelength, array('min' => 0))) {
            throw new ClientException(_('Invalid number for max notice length.'));
        }

        $user = common_current_user();

        assert(!is_null($user)); // should already be checked

        $user->query('BEGIN');

        $original = clone($user);

        $user->urlshorteningservice = $urlshorteningservice;

        $result = $user->update($original);

        if ($result === false) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            // TRANS: Server error displayed when "Other" settings in user profile could not be updated on the server.
            $this->serverError(_('Couldn\'t update user.'));
            return;
        }

        $prefs = User_urlshortener_prefs::getPrefs($user);
        $orig  = null;

        if (empty($prefs)) {
            $prefs = new User_urlshortener_prefs();

            $prefs->user_id = $user->id;
            $prefs->created = common_sql_now();
        } else {
            $orig = clone($prefs);
        }

        $prefs->urlshorteningservice = $urlshorteningservice;
        $prefs->maxurllength         = $maxurllength;
        $prefs->maxnoticelength      = $maxnoticelength;

        if (!empty($orig)) {
            $result = $prefs->update($orig);
        } else {
            $result = $prefs->insert();
        }

        if (!$result) {
            throw new ServerException(_('Error saving user URL shortening preferences.'));
        }

        $user->query('COMMIT');

        $this->showForm(_('Preferences saved.'), true);
    }
}
