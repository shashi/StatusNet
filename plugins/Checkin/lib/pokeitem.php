<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for showing / revising an answer
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/form.php';

/**
 * Form for showing a poke
 *
 * @category Form
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class PokeItem extends NoticeListItem
{
    /**
     * The poke to show
     */
    var $poke = null;
    var $poked = null;
    var $poker = null;
    /**
     * Constructor
     *
     * @param HTMLOutputter $out      output channel
     * @param QnA_poke  $poke the poke to show
     */
    function __construct($out = null, Poke $poke)
    {
        parent::__construct($poke->getNotice(), $out);
        $this->poke = $poke;

        $this->poker = $poke->getPoker();
        $this->poked = $poke->getPoked();
    }

    function showContent()
    {
        $this->showAction();
        $this->showPoked();
        parent::showContent();
    }

    function show()
    {
        if (empty($this->notice)) {
            common_log(LOG_WARNING, "Trying to show missing notice; skipping.");
            return;
        } else if (empty($this->profile)) {
            common_log(LOG_WARNING, "Trying to show missing profile (" . $this->notice->profile_id . "); skipping.");
            return;
        }

        $this->elementStart('div', 'poke');
        $this->showNotice();
        $this->showNoticeInfo();
        $this->showNoticeOptions();
    }

    function showAction()
    {
        $this->out->element('span', 'poke_action', _('poked'));
    }

    function showPoked()
    {
        $this->out->elementStart('span', 'poked_avatar');
        $attrs = array('href' => $this->profile->profileurl,
                       'class' => 'url');
        if (!empty($this->profile->fullname)) {
            $attrs['title'] = $this->profile->getFancyName();
        }
        $this->out->elementStart('a', $attrs);
        $this->showAvatar($this->poked, 'poked_photo', AVATAR_MINI_SIZE);
        $this->showNickname();
        $this->out->elementEnd('a');
        $this->out->elementEnd('span');
    }

    function showAvatar($profile=null, $class='', $size=AVATAR_STREAM_SIZE)
    {
        if (empty($profile)) {
            $profile = $this->profile;
        }

        $avatar = $profile->getAvatar($size);

        $this->out->element('img', array('src' => ($avatar) ?
                                         $avatar->displayUrl() :
                                         Avatar::defaultImage($size),
                                         'class' => 'avatar photo',
                                         'width' => $size,
                                         'height' => $size,
                                         'alt' =>
                                         ($profile->fullname) ?
                                         $profile->fullname :
                                         $profile->nickname));
    }

    function showText()
    {
    }

    function showActions()
    {
    }
}
