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

    var $tag=null;

    var $user=null;
    var $tagger=null;
    var $tagged=null;

    function __construct($out, $tagger, $tagged, $private=-1)
    {
        parent::__construct($out);

        $this->user   = common_current_user();
        if ($private === -1) {
            $private = empty($this->user) ? false : null;
        }

        $this->tag = Profile_tag::getTags($tagger->id, $tagged->id, $private);
        $this->tagger = $tagger;
        $this->tagged = $tagged;
    }

    function show()
    {
        if (Event::handle('StartShowPeopletags', array($this->out, $this->tagger, $this->tagged))) {
            if ($this->tag->N > 0) {
                $this->showTags();
            }
            else {
                $this->showEmptyList();
            }
            Event::handle('EndShowPeopletags', array($this->out, $this->tagger, $this->tagged));
        }
    }

    function url()
    {
        return $this->tag->homeUrl();
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

        $class = 'tags xoxo';
        if ($this->isEditable()) {
            $class .= ' editable';
        }

        $tags = array();
        $this->out->elementStart('ul', $class);
        while ($this->tag->fetch()) {
            $mode = $this->tag->private ? 'private' : 'public';
            $tags[] = $this->tag->tag;

            $this->out->elementStart('li', 'hashptag mode-' . $mode);
            // Avoid space by using raw output.
            $pt = '<span class="mark_hash">#</span><a rel="tag" href="' .
              $this->url($this->tag->tag) .
              '">' . $this->tag->tag . '</a>';
            $this->out->raw($pt);
            $this->out->elementEnd('li');
        }
        $this->out->elementEnd('ul');

        if ($this->isEditable()) {
            $this->showEditTagForm($tags);
        }

        $this->out->elementEnd('dd');
        $this->out->elementEnd('dl');
    }

    function showEditTagForm($tags=null)
    {
        $this->out->elementStart('span', 'form_tag_user_wrap');
        $this->out->elementStart('form', array('method' => 'post',
                                           'class' => 'form_tag_user',
                                           'name' => 'tagprofile',
                                           'action' => common_local_url('tagprofile', array('id' => $this->tagged->id))));

        $this->out->elementStart('fieldset');
        $this->out->element('legend', null, _('Edit tags'));
        $this->out->hidden('token', common_session_token());
        $this->out->hidden('id', $this->tagged->id);

        if (!$tags) {
            $tags = array();
        }

        $this->out->input('tags', $this->label(),
                     ($this->out->arg('tags')) ? $this->out->arg('tags') : implode(' ', $tags));
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

        $class = 'tags';
        if ($this->isEditable()) {
            $class .= ' editable';
        }

        $this->out->elementStart('ul', $class);
        $this->out->element('li', null, _('(None)'));
        $this->out->elementEnd('ul');

        if ($this->isEditable()) {
            $this->showEditTagForm();
        }
        $this->out->elementEnd('dd');
        $this->out->elementEnd('dl');
    }

    function isEditable()
    {
        return !empty($this->user) && $this->tagger->id == $this->user->id;
    }
}

class SelftagsWidget extends PeopletagsWidget
{
    function url($tag)
    {
        // link to self tag page
        return common_local_url('selftag', array('tag' => $tag));
    }

    function label()
    {
        return _('Tags');
    }
}
