<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Subscribe to a peopletag
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
 * Subscribe to a peopletag
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

class SubscribepeopletagAction extends Action
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
            $this->clientError(_('You must be logged in to Subscribe to a peopletag.'));
            return false;
        }

        $tagger_arg = $this->trimmed('tagger');
        $tag_arg = $this->trimmed('tag');

        $id = intval($this->arg('id'));
        if ($id) {
            $this->peopletag = Profile_list::staticGet('id', $id);
        } else if ($tagger_arg && $tag_arg) {
            $tagger = common_canonical_nickname($tagger_arg);
            $tag = common_canonical_tag($tag_arg);

            // Permanent redirect on non-canonical nickname

            if ($tagger_arg != $tagger || $tag_arg != $tag) {
                $args = array('tagger' => $tagger, 'tag' => $tag);
                common_redirect(common_local_url('subscribepeopletag', $args), 301);
                return false;
            }

            $this->peopletag = Profile_list::pkeyGet(array('tagger' => $tagger,
                                                           'tag' => $tag));
        } else {
            $this->clientError(_('No tagger, tag or ID.'), 404);
            return false;
        }


        if (!$this->peopletag) {
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

        try {
            if (Event::handle('StartSubscribePeopletag', array($this->peopletag, $cur))) {
                Profile_tag_subscription::add($this->peopletag->id, $cur->id);
                Event::handle('EndSubscribePeopletag', array($this->peopletag, $cur));
            }
        } catch (Exception $e) {
            $this->serverError(sprintf(_('Could not subscribe user %1$s to peopletag %2$s.'),
                                       $cur->nickname, $this->peopletag->tag));
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, sprintf(_('%1$s subscribed to peopletag %2$s by %3$s'),
                                                  $cur->nickname,
                                                  $this->peopletag->tag,
                                                  $this->tagger->nickname));
            $this->elementEnd('head');
            $this->elementStart('body');
            $lf = new UnsubscribePeopletagForm($this, $this->peopletag);
            $lf->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect(common_local_url('peopletagsubscribers',
                                array('tagger' => $this->tagger->nickname,
                                      'tag' =>$this->peopletag->tag)),
                            303);
        }
    }
}
