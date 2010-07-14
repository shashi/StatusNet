<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for editing a peopletag
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/form.php';
require_once INSTALLDIR.'/lib/togglepeopletag.php';

/**
 * Form for editing a peopletag
 *
 * @category Form
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      GroupEditForm
 */

class PeopletagEditForm extends Form
{
    /**
     * peopletag to edit
     */

    var $peopletag = null;
    var $tagger    = null;

    /**
     * Constructor
     *
     * @param Action     $out   output channel
     * @param User_group $group group to join
     */

    function __construct($out=null, Profile_list $peopletag=null)
    {
        parent::__construct($out);

        $this->peopletag = $peopletag;
        $this->tagger    = Profile::staticGet('id', $peopletag->tagger);
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */

    function id()
    {
        return 'form_peopletag_edit-' . $this->peopletag->id;
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */

    function formClass()
    {
        return 'form_settings';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('editpeopletag',
                array('tagger' => $this->tagger->nickname, 'tag' => $this->peopletag->tag));
    }

    /**
     * Name of the form
     *
     * @return void
     */

    function formLegend()
    {
        $this->out->element('legend', null, sprintf(_('Edit peopletag %s'), $this->peopletag->tag));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $id = $this->peopletag->id;
        $tag = $this->peopletag->tag;
        $description = $this->peopletag->description;
        $private = $this->peopletag->private;

        $this->out->elementStart('ul', 'form_data');

        $this->out->elementStart('li');
        $this->out->hidden('id', $id);
        $this->out->input('tag', _('Tag'),
                          ($this->out->arg('tag')) ? $this->out->arg('tag') : $tag,
                          _('Change the tag (letters, numbers, -, ., and _ are allowed)'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        $desclimit = Profile_list::maxDescription();
        if ($desclimit == 0) {
            $descinstr = _('Describe the people tag or topic');
        } else {
            $descinstr = sprintf(_('Describe the people tag or topic in %d characters'), $desclimit);
        }
        $this->out->textarea('description', _('Description'),
                             ($this->out->arg('description')) ? $this->out->arg('description') : $description,
                             $descinstr);
        $this->out->checkbox('private', _('Private'), $private);
        $this->out->elementEnd('li');
        $this->out->elementEnd('ul');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _('Save'));
        $this->out->submit('form_action-yes',
                      _m('BUTTON','Delete'),
                      'submit',
                      'delete',
                      _('Delete this people tag'));
    }

    function showProfileList()
    {
        $tagged = $this->peopletag->getTagged();
        $this->out->element('h2', null, 'Add or remove people');

        $this->out->elementStart('div', 'profile_search_wrap');
        $this->out->element('h3', null, 'Add user');
        $search = new SearchProfileForm($this->out, $this->peopletag);
        $search->show();
        $this->out->element('ul', array('id' => 'profile_search_results', 'class' => 'empty'));
        $this->out->elementEnd('div');

        $this->out->elementStart('ul', 'profile-lister');
        while ($tagged->fetch()) {
            $this->out->elementStart('li', 'entity_removable_profile');
            $this->showProfileItem($tagged);
            $this->out->elementStart('span', 'entity_actions');
            $untag = new UntagButton($this->out, $tagged, $this->peopletag);
            $untag->show();
            $this->out->elementEnd('span');
            $this->out->elementEnd('li');
        }
        $this->out->elementEnd('ul');
    }

    function showProfileItem($profile)
    {
        $item = new TaggedProfileItem($this->out, $profile);
        $item->show();
    }
}
