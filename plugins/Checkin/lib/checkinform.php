<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Form for checking in
 *
 * PHP version 5
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
 *
 * @category  Checkin
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Form to add a new checkin
 *
 * @category  Checkin
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class CheckinForm extends Form
{
    protected $title;
    protected $description;

    /**
     * Construct a new checkin form
     *
     * @param HTMLOutputter $out output channel
     *
     * @return void
     */
    function __construct($out = null, $title = null, $description = null, $options = null)
    {
        parent::__construct($out);
        $this->title       = $title;
        $this->description = $description;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'newcheckin-form';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_settings ajax-notice';
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
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('fieldset', array('id' => 'newcheckin-data'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->element('span', array('class' => 'status'));
	$this->unli();

        $this->li();
	if (common_current_user()->shareLocation()) {
	    $this->out->hidden('checkin_data-lat', empty($this->lat) ? (empty($this->profile->lat) ? null : $this->profile->lat) : $this->lat, 'lat');
	    $this->out->hidden('checkin_data-lon', empty($this->lon) ? (empty($this->profile->lon) ? null : $this->profile->lon) : $this->lon, 'lon');

	    $this->out->hidden('checkin_data-location_id', empty($this->location_id) ? (empty($this->profile->location_id) ? null : $this->profile->location_id) : $this->location_id, 'location_id');
	    $this->out->hidden('checkin_data-location_ns', empty($this->location_ns) ? (empty($this->profile->location_ns) ? null : $this->profile->location_ns) : $this->location_ns, 'location_ns');

	    $this->out->elementEnd('ul');
	    $toWidget = new ToSelector(
	        $this->out,
	            common_current_user(),
		    null
		);
		$toWidget->show();

	} else {
	    $this->out->element('span', null, _('You have disabled sharing your location'));
	}
        $this->unli();

        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $label = _m('BUTTON', 'Check in');
        // TRANS: Button text for saving a new checkin.
        $this->out->element('input', array('type' => 'submit',
                                      'id' => 'checkin_submit',
				      'disabled' => 'true',
                                      'class' => 'submit',
                                      'value' => $label));
    }
}
