<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for repeating a notice
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
 * @category  Form
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class CheckinForm extends Widget
{
    function __construct($out=null, $profile=null)
    {
        parent::__construct($out);

        $this->profile = $profile;
    }
    function show()
    {
        $this->out->elementStart('li', 'entity_checkin');
        $this->showCheckinButton();

        $dialog = new CheckinDialog($this->out, $this->profile);
        $dialog->show();

        $this->out->elementEnd('li');
    }

    function showCheckinButton()
    {
        $this->element('a', array('href' => '#', 'class' => 'checkin_button'),
            _m('BUTTON', 'Checkin'));
    }
}

/**
 * Form for repeating a notice
 *
 * @category Form
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class CheckinDialog extends Form
{
    /**
     * Profile to checkin
     */
    var $profile = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out    output channel
     * @param Profile       $notice profile to checkin
     */
    function __construct($out=null, $profile=null)
    {
        parent::__construct($out);

        $this->profile = $profile;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'checkin-' . $this->profile->id;
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('newcheckin');
    }

    /**
     * Include a session token for CSRF protection
     *
     * @return void
     */
    function sessionToken()
    {
        $this->out->hidden('token',
                           common_session_token());
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: For legend for notice repeat form.
        $this->out->element('legend', null, sprintf(_('Checkin %s?'),
                                    $this->profile->getBestName()));
    }

    /**
     * Data elements
     *
     * @return void
     */
    function formData()
    {
        $this->out->hidden('profile-n'.$this->profile->id,
                           $this->profile->id,
                           'profile');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $this->out->elementStart('span', 'checkbox-wrapper');
        $this->out->checkbox('checkin_private',
                             // TRANS: Checkbox label in widget for selecting potential addressees to mark the notice private.
                             _('Private?'), false);
        $this->out->elementEnd('span');

        $this->out->submit('checkin-submit-' . $this->profile->id,
                           // TRANS: Button text to repeat a notice on notice repeat form.
                           _m('BUTTON','Yes'), 'submit', null,
                           // TRANS: Button title to repeat a notice on notice repeat form.
                           _('Repeat this notice.'));
    }

    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_checkin';
    }
}
