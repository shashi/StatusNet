<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Tags for a profile
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

/*
 * Show a bunch of peopletags
 * provide ajax editing if the current user owns the tags
 *
 * @category Action
 * @pacage   StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 */

class PeopletagsWidget extends Widget
{
    /*
     * the query, current peopletag.
     * or an array of strings (tags)
     */

    var $tags=null;

    var $user=null;
    var $tagger=null;
    var $tagged=null;

    function __construct($out, $tagger, $tagged, $user=null)
    {
        parent::__construct($out);
        $this->tags = Profile_tag::getTags($tagger->id, $tagged->id);

        $this->user   = empty($user) ? common_current_user() : $user;
        $this->tagger = $tagger;
        $this->tagged = $tagged;
    }

    function show()
    {
        if (Event::handle('StartShowPeopletags', array($this->out, $this->tagger, $this->tagged))) {
            if (count($this->tags) > 0) {
                $this->showTags();
            }
            else {
                $this->showEmptyList();
            }
            Event::handle('EndShowPeopletags', array($this->out, $this->tagger, $this->tagged));
        }
    }

    function url($tag)
    {
        return common_local_url('showprofiletag',
                   array('tagger' => $this->tagger->nickname, 'tag' => $tag));
    }

    function label()
    {
        return _('Tags by you');
    }

    function showTags()
    {
        $this->out->elementStart('dl', 'entity_tags user_profile_tags');
        $this->out->element('dt', null, $this->label());
        $this->out->elementStart('dd');
        $this->out->elementStart('ul', 'tags xoxo');
        foreach ($this->tags as $tag) {
            $this->out->elementStart('li');
            // Avoid space by using raw output.
            $pt = '<span class="mark_hash">#</span><a rel="tag" href="' .
              $this->url($tag) .
              '">' . $tag . '</a>';
            $this->out->raw($pt);
            $this->out->elementEnd('li');
        }
        $this->out->elementEnd('ul');

        if (!empty($this->user) && $this->tagger->id == $this->user->id) {
            $this->showEditTagForm();
        }

        $this->out->elementEnd('dd');
        $this->out->elementEnd('dl');
    }

    function showEditTagForm()
    {
        $this->out->elementStart('span', 'form_tag_user_wrap');
        $this->out->elementStart('form', array('method' => 'post',
                                           'id' => 'form_tag_user',
                                           'class' => 'form_settings',
                                           'name' => 'tagprofile',
                                           'action' => common_local_url('tagprofile', array('id' => $this->tagged->id))));

        $this->out->elementStart('fieldset');
        $this->out->element('legend', null, _('Edit tags'));
        $this->out->hidden('token', common_session_token());
        $this->out->hidden('id', $this->tagged->id);

        $this->out->input('tags', $this->label(),
                     ($this->out->arg('tags')) ? $this->out->arg('tags') : implode(' ', $this->tags));
        $this->out->submit('save', _('Save'));

        $this->out->elementEnd('fieldset');
        $this->out->elementEnd('form');
        $this->out->elementEnd('span');
    }

    function showEmptyList()
    {
        $this->out->elementStart('dl', 'entity_tags user_profile_tags');
        $this->out->element('dt', null, $this->label());
        $this->out->elementStart('dd');
        $this->out->element('span', 'tags', _('(None)'));
        $this->showEditTagForm();
        $this->out->elementEnd('dd');
        $this->out->elementEnd('dl');
    }
}

class SelftagsWidget extends PeopletagsWidget
{
    function url($tag)
    {
        // link to self tag page
        return common_local_url('peopletag', array('tag' => $tag));
    }

    function label()
    {
        return _('Tags');
    }
}
