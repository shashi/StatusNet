<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 * @category Actions
 * @package  Actions
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 * @link     http://status.net
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/personalgroupnav.php';
require_once INSTALLDIR.'/lib/profileminilist.php';
require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

class ShowprofiletagAction extends Action
{
    var $notice, $user, $profile_tag;

    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);

        $nickname_clean = common_canonical_nickname($this->trimmed('nickname'));
        $this->user = User::staticGet('nickname', $nickname_clean);
        if(!$this->user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $ptag_args = array(
                                'tagger' => $this->user->id,
                                'tag' => $this->trimmed('profiletag')
                          );

        $this->profile_tag = Profile_list::pkeyGet($ptag_args);

        if(!$this->profile_tag) {
            $this->clientError(_('No such tag.'), 404);
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;
        $this->notice = $this->profile_tag->getNotices(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

        if ($this->page > 1 && $this->notice->N == 0) {
            // TRANS: Server error when page not found (404)
            $this->serverError(_('No such page.'), $code = 404);
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if (!$this->profile_tag) {
            $this->clientError(_('No such user.'));
            return;
        }

        $this->showPage();
    }

    function title()
    {
        if ($this->page > 1) {
            // TRANS: Page title. %1$s is user nickname, %2$d is page number
            return sprintf(_('People tagged %1$s by %2$s, page %3$d'),
                                $this->profile_tag->tag,
                                $this->user->nickname, 
                                $this->page
                          );
        } else {
            // TRANS: Page title. %1$s is user nickname
            return sprintf(_('People tagged %1$s by %2$s'), $this->profile_tag->tag, $this->user->nickname);
        }
    }

    function getFeeds()
    {
        #XXX: make these actually work
        return array(
            new Feed(Feed::RSS1,
                common_local_url(
                    'profiletagrss', array(
                        'nickname' => $this->user->nickname,
                        'profiletag' => $this->profile_tag->tag
                    )
                ),
            // TRANS: %1$s is user nickname
                sprintf(_('Feed for friends of %s (RSS 1.0)'), $this->user->nickname)),
            new Feed(Feed::RSS2,
                common_local_url(
                    'ApiTimelineProfileTag', array(
                        'format' => 'rss',
                        'nickname' => $this->user->nickname,
                        'profiletag' => $this->profile_tag->tag
                    )
                ),
            // TRANS: %1$s is user nickname
                sprintf(_('Feed for friends of %s (RSS 2.0)'), $this->user->nickname)),
            new Feed(Feed::ATOM,
                common_local_url(
                    'ApiTimelineProfileTag', array(
                        'format' => 'atom',
                        'nickname' => $this->user->nickname,
                        'profiletag' => $this->profile_tag->tag
                    )
                ),
                // TRANS: %1$s is user nickname
                sprintf(_('Feed for people tagged %s by %s (Atom)'),
                            $this->profile_tag->tag, $this->user->nickname
                       )
              )
        );
    }

    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
    }

    function showEmptyListMessage()
    {
        // TRANS: %1$s is user nickname
        $message = sprintf(_('This is the timeline for people tagged %s by %s but no one has posted anything yet.'), $this->profile_tag->tag, $this->user->nickname) . ' ';

        if (common_logged_in()) {
            $current_user = common_current_user();
            if ($this->user->id === $current_user->id) {
                $message .= _('Try tagging more people.');
            }
        } else {
            $message .= _('Why not [register an account](%%%%action.register%%%%) and start following this timeline.');
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function showContent()
    {
        if (Event::handle('StartShowProfileTagContent', array($this))) {
            $nl = new NoticeList($this->notice, $this);

            $cnt = $nl->show();

            if (0 == $cnt) {
                $this->showEmptyListMessage();
            }

            $this->pagination(
                $this->page > 1, $cnt > NOTICES_PER_PAGE,
                $this->page, 'all', array('nickname' => $this->user->nickname)
            );

            Event::handle('EndShowProfileTagContent', array($this));
        }
    }

    function showSections()
    {
        $this->showTagged();
        $this->showSubscribers();
        # $this->showStatistics();
    }

    function showPageTitle()
    {
        $user = common_current_user();
        if ($user && ($user->id == $this->user->id)) {
            // TRANS: H1 text
            $this->element('h1', null, sprintf(_("People you tagged %s"), $this->profile_tag->tag));
        } else {
            // TRANS: H1 text. %1$s is user nickname
            $this->element('h1', null, sprintf(_('People tagged %s by %s'), $this->profile_tag->tag, $this->user->nickname));
        }
    }

    function showTagged()
    {
        $profile = $this->profile_tag->getTagged(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_tagged',
                                         'class' => 'section'));
        if (Event::handle('StartShowTaggedProfilesMiniList', array($this))) {
            $this->element('h2', null, $this->title());

            $cnt = 0;

            if (!empty($profile)) {
                $pml = new ProfileMiniList($profile, $this);
                $cnt = $pml->show();
                if ($cnt == 0) {
                    $this->element('p', null, _('(None)'));
                }
            }

            if ($cnt > PROFILES_PER_MINILIST) {
                $this->elementStart('p');
                $this->element('a', array('href' => common_local_url('taggedprofiles',
                                                                     array('nickname' => $this->user->nickname,
                                                                           'profiletag' => $this->profile_tag->tag)),
                                          'class' => 'more'),
                               _('Show all'));
                $this->elementEnd('p');
            }

            Event::handle('EndShowTaggedProfilesMiniList', array($this));
        }
        $this->elementEnd('div');
    }

    function showSubscribers()
    {
        $profile = $this->profile_tag->getSubscribers(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscribers',
                                         'class' => 'section'));
        if (Event::handle('StartShowProfileTagSubscribersMiniList', array($this))) {
            $this->element('h2', null, _('Subscribers'));

            $cnt = 0;

            if (!empty($profile)) {
                $pml = new ProfileMiniList($profile, $this);
                $cnt = $pml->show();
                if ($cnt == 0) {
                    $this->element('p', null, _('(None)'));
                }
            }

            if ($cnt > PROFILES_PER_MINILIST) {
                $this->elementStart('p');
                $this->element('a', array('href' => common_local_url('profiletagsubscribers',
                                                                     array('nickname' => $this->user->nickname,
                                                                           'profiletag' => $this->profile_tag->tag)),
                                          'class' => 'more'),
                               _('All subscribers'));
                $this->elementEnd('p');
            }

            Event::handle('EndShowProfileTagSubscribersMiniList', array($this));
        }
        $this->elementEnd('div');
    }
}
