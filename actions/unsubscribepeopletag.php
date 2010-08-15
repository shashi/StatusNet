<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Unsubscribe to a peopletag
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
 * @category  Peopletag
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Unsubscribe to a peopletag
 *
 * This is the action for subscribing to a peopletag. It works more or less like the join action
 * for groups.
 *
 * @category Peopletag
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class UnsubscribepeopletagAction extends Action
{
    var $peopletag = null;
    var $tagger = null;

    /**
     * Prepare to run
     */

    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            $this->clientError(_('You must be logged in to unsubscribe to a peopletag.'));
            return false;
        }
        // Only allow POST requests

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(_('This action only accepts POST requests.'));
            return false;
        }

        // CSRF protection

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token.'.
                                 ' Try again, please.'));
            return false;
        }

        $tagger_arg = $this->trimmed('tagger');
        $tag_arg = $this->trimmed('tag');

        $id = intval($this->arg('id'));
        if ($id) {
            $this->peopletag = Profile_list::staticGet('id', $id);
        } else {
            $this->clientError(_('No ID given.'), 404);
            return false;
        }

        if (!$this->peopletag || $this->peopletag->private) {
            $this->clientError(_('No such peopletag.'), 404);
            return false;
        }

        $this->tagger = Profile::staticGet('id', $this->peopletag->tagger);

        return true;
    }

    /**
     * Handle the request
     *
     * On POST, add the current user to the group
     *
     * @param array $args unused
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        $cur = common_current_user();

        Profile_tag_subscription::remove($this->peopletag, $cur);

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, sprintf(_('%1$s unsubscribed to peopletag %2$s by %3$s'),
                                                  $cur->nickname,
                                                  $this->peopletag->tag,
                                                  $this->tagger->nickname));
            $this->elementEnd('head');
            $this->elementStart('body');
            $lf = new SubscribePeopletagForm($this, $this->peopletag);
            $lf->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            if (common_get_returnto()) {
                common_redirect(common_get_returnto(), 303);
                return true;
            }
            common_redirect(common_local_url('peopletagsbyuser',
                                array('nickname' => $this->tagger->nickname)),
                            303);
        }
    }
}
