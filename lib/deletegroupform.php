<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for joining a group
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
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009, 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Form for deleting a group
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      UnsubscribeForm
 * @fixme    merge a bunch of this stuff with similar form types to reduce boilerplate
 */

class DeleteGroupForm extends Form
{
    /**
     * group for user to delete
     */

    var $group = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out   output channel
     * @param group         $group group to join
     */

    function __construct($out=null, $group=null)
    {
        parent::__construct($out);

        $this->group = $group;
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */

    function id()
    {
        return 'group-delete-' . $this->group->id;
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */

    function formClass()
    {
        return 'form_group_delete';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('deletegroup',
                                array('id' => $this->group->id));
    }

    function formData()
    {
        $this->out->hidden($this->id() . '-returnto-action', 'groupbyid', 'returnto-action');
        $this->out->hidden($this->id() . '-returnto-id', $this->group->id, 'returnto-id');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _('Delete'));
    }
}
