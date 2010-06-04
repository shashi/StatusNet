<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Profile for a particular user
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
 * @category  Action
 * @package   StatusNet
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/widget.php';
require_once INSTALLDIR.'/lib/profiletagsbycurrentuser.php';

class ProfileTagsByCurrentUserWidget extends Widget
{
    var $tags, $profile, $user=null;

    function __construct($out, $profile, $user=null)
    {
        parent::__construct($out);
        $this->profile = $profile;
        $this->user = empty($user)?common_current_user():$user;
    }

    function show()
    {
        $this->user = common_current_user();
        if(empty($this->user)) {
            return true;
        }

        // Do not show self-tags again
        if($this->user->id == $this->profile->id) {
            return true;
        }

        if (Event::handle('StartProfilePageProfileTagsByCurrentUser', array($this->out, $this->user))) {
            $this->tags = Profile_tag::getTags($this->user->id, $this->profile->id);

            if (count($this->tags) > 0) {
                $this->showTags();
            }
            else {
                $this->showEmptyList();
            }
            Event::handle('StartProfilePageProfileTagsByCurrentUser', array($this->out, $this->profile));
        }
    }

    function showTags()
    {
        $this->out->elementStart('dl', 'entity_tags user_profile_tags');
        $this->out->element('dt', null, _('Tags by you'));
        $this->out->elementStart('dd');
        $this->out->elementStart('ul', 'tags xoxo');
        foreach ($this->tags as $tag) {
            $this->out->elementStart('li');
            // Avoid space by using raw output.
            $pt = '<span class="mark_hash">#</span><a rel="tag" href="' .
              common_local_url('showprofiletag', array('nickname' => $this->user->nickname, 'profiletag' => $tag)) .
              '">' . $tag . '</a>';
            $this->out->raw($pt);
            $this->out->elementEnd('li');
        }
        $this->out->elementEnd('ul');
        $this->showEditTagForm();
        $this->out->elementEnd('dd');
        $this->out->elementEnd('dl');
    }

    function showEditTagForm()
    {
        $this->out->elementStart('ul', 'form_tag_user_wrap');
        $this->out->elementStart('li');
        $this->out->elementStart('form', array('method' => 'post',
                                           'id' => 'form_tag_user',
                                           'class' => 'form_settings',
                                           'name' => 'tagother',
                                           'action' => common_local_url('tagother', array('id' => $this->user->id))));

        $this->out->elementStart('fieldset');
        $this->out->element('legend', null, _('Tag this user'));
        $this->out->hidden('token', common_session_token());
        $this->out->hidden('id', $this->profile->id);

        $this->out->input('tags', _('Tag this user'),
                     ($this->out->arg('tags')) ? $this->out->arg('tags') : implode(' ', $this->tags));
        $this->out->submit('save', _('Save'));
        // $this->out->element('p', 'form_guide', _('Tags for this user (letters, numbers, -, ., and _), comma- or space- separated'));
        $this->out->elementEnd('fieldset');
        $this->out->elementEnd('form');
        $this->out->elementEnd('li');
        $this->out->elementEnd('ul');
    }

    function showEmptyList()
    {
        $this->out->elementStart('dl', 'entity_tags user_profile_tags');
        $this->out->element('dt', null, _('Tags by you'));
        $this->out->elementStart('dd');
        $this->out->element('span', 'tags', _('(None)'));
        $this->showEditTagForm();
        $this->out->elementEnd('dd');
        $this->out->elementEnd('dl');
    }
}
