<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Superclass for plugins that do "real time" updates of timelines using Ajax
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Superclass for plugin to do realtime updates
 *
 * Based on experience with the Comet and Meteor plugins,
 * this superclass extracts out some of the common functionality
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class RealtimePlugin extends Plugin
{
    protected $replyurl = null;
    protected $favorurl = null;
    protected $deleteurl = null;

    /**
     * When it's time to initialize the plugin, calculate and
     * pass the URLs we need.
     */

    function onInitializePlugin()
    {
        $this->replyurl = common_local_url('newnotice');
        $this->favorurl = common_local_url('favor');
        $this->repeaturl = common_local_url('repeat');
        // FIXME: need to find a better way to pass this pattern in
        $this->deleteurl = common_local_url('deletenotice',
                                            array('notice' => '0000000000'));
        return true;
    }

    function onEndShowScripts($action)
    {
        $timeline = $this->_getTimeline($action);

        // If there's not a timeline on this page,
        // just return true

        if (empty($timeline)) {
            return true;
        }

        $base = $action->selfUrl();
        if (mb_strstr($base, '?')) {
            $url = $base . '&realtime=1';
        } else {
            $url = $base . '?realtime=1';
        }

        $scripts = $this->_getScripts();

        foreach ($scripts as $script) {
            $action->script($script);
        }

        $user = common_current_user();

        if (!empty($user->id)) {
            $user_id = $user->id;
        } else {
            $user_id = 0;
        }

        if ($action->boolean('realtime')) {
            $realtimeUI = ' RealtimeUpdate.initPopupWindow();';
        }
        else {
            $pluginPath = common_path('plugins/Realtime/');
            $realtimeUI = ' RealtimeUpdate.initActions("'.$url.'", "'.$timeline.'", "'. $pluginPath .'");';
        }

        $script = ' $(document).ready(function() { '.
          $realtimeUI.
          $this->_updateInitialize($timeline, $user_id).
          '}); ';
        $action->inlineScript($script);

        return true;
    }

    function onEndShowStatusNetStyles($action)
    {
        $action->cssLink('plugins/Realtime/realtimeupdate.css',
                         null, 'screen, projection, tv');
        return true;
    }

    function onHandleQueuedNotice($notice)
    {
        $paths = array();

        // Add to the author's timeline

        $user = User::staticGet('id', $notice->profile_id);

        if (!empty($user)) {
            $paths[] = array('showstream', $user->nickname);
        }

        // Add to the public timeline

        if ($notice->is_local == Notice::LOCAL_PUBLIC ||
            ($notice->is_local == Notice::REMOTE_OMB && !common_config('public', 'localonly'))) {
            $paths[] = array('public');
        }

        // Add to the tags timeline

        $tags = $this->getNoticeTags($notice);

        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $paths[] = array('tag', $tag);
            }
        }

        // Add to inbox timelines
        // XXX: do a join

        $ni = $notice->whoGets();

        foreach (array_keys($ni) as $user_id) {
            $user = User::staticGet('id', $user_id);
            $paths[] = array('all', $user->nickname);
        }

        // Add to the replies timeline

        $reply = new Reply();
        $reply->notice_id = $notice->id;

        if ($reply->find()) {
            while ($reply->fetch()) {
                $user = User::staticGet('id', $reply->profile_id);
                if (!empty($user)) {
                    $paths[] = array('replies', $user->nickname);
                }
            }
        }

        // Add to the group timeline
        // XXX: join

        $gi = new Group_inbox();
        $gi->notice_id = $notice->id;

        if ($gi->find()) {
            while ($gi->fetch()) {
                $ug = User_group::staticGet('id', $gi->group_id);
                $paths[] = array('showgroup', $ug->nickname);
            }
        }

        if (count($paths) > 0) {

            $json = $this->noticeAsJson($notice);

            $this->_connect();

            foreach ($paths as $path) {
                $timeline = $this->_pathToChannel($path);
                $this->_publish($timeline, $json);
            }

            $this->_disconnect();
        }

        return true;
    }

    function onStartShowBody($action)
    {
        $realtime = $action->boolean('realtime');
        if (!$realtime) {
            return true;
        }

        $action->elementStart('body',
                              (common_current_user()) ? array('id' => $action->trimmed('action'),
                                                              'class' => 'user_in realtime-popup')
                              : array('id' => $action->trimmed('action'),
                                      'class'=> 'realtime-popup'));

        // XXX hack to deal with JS that tries to get the
        // root url from page output

        $action->elementStart('address');
        $action->element('a', array('class' => 'url',
                                  'href' => common_local_url('public')),
                         '');
        $action->elementEnd('address');

        if (common_logged_in()) {
            $action->showNoticeForm();
        }

        $action->showContentBlock();
        $action->showScripts();
        $action->elementEnd('body');
        return false; // No default processing
    }

    function noticeAsJson($notice)
    {
        // FIXME: this code should be abstracted to a neutral third
        // party, like Notice::asJson(). I'm not sure of the ethics
        // of refactoring from within a plugin, so I'm just abusing
        // the ApiAction method. Don't do this unless you're me!

        $act = new ApiAction('/dev/null');

        $arr = $act->twitterStatusArray($notice, true);
        $arr['url'] = $notice->bestUrl();
        $arr['html'] = htmlspecialchars($notice->rendered);
        $arr['source'] = htmlspecialchars($arr['source']);
        $arr['conversation_url'] = $this->getConversationUrl($notice);

        $profile = $notice->getProfile();
        $arr['user']['profile_url'] = $profile->profileurl;

        // Add needed repeat data

        if (!empty($notice->repeat_of)) {
            $original = Notice::staticGet('id', $notice->repeat_of);
            if (!empty($original)) {
                $arr['retweeted_status']['url'] = $original->bestUrl();
                $arr['retweeted_status']['html'] = htmlspecialchars($original->rendered);
                $arr['retweeted_status']['source'] = htmlspecialchars($original->source);
                $originalProfile = $original->getProfile();
                $arr['retweeted_status']['user']['profile_url'] = $originalProfile->profileurl;
                $arr['retweeted_status']['conversation_url'] = $this->getConversationUrl($original);
            }
            $original = null;
        }

        return $arr;
    }

    function getNoticeTags($notice)
    {
        $tags = null;

        $nt = new Notice_tag();
        $nt->notice_id = $notice->id;

        if ($nt->find()) {
            $tags = array();
            while ($nt->fetch()) {
                $tags[] = $nt->tag;
            }
        }

        $nt->free();
        $nt = null;

        return $tags;
    }

    function getConversationUrl($notice)
    {
        $convurl = null;

        if ($notice->hasConversation()) {
            $conv = Conversation::staticGet(
                'id',
                $notice->conversation
            );
            $convurl = $conv->uri;

            if(empty($convurl)) {
                $msg = sprintf(
                    "Couldn't find Conversation ID %d to make 'in context'"
                    . "link for Notice ID %d",
                    $notice->conversation,
                    $notice->id
                );

                common_log(LOG_WARNING, $msg);
            } else {
                $convurl .= '#notice-' . $notice->id;
            }
        }

        return $convurl;
    }

    function _getScripts()
    {
        return array('plugins/Realtime/realtimeupdate.js');
    }

    function _updateInitialize($timeline, $user_id)
    {
        return "RealtimeUpdate.init($user_id, \"$this->replyurl\", \"$this->favorurl\", \"$this->repeaturl\", \"$this->deleteurl\"); ";
    }

    function _connect()
    {
    }

    function _publish($timeline, $json)
    {
    }

    function _disconnect()
    {
    }

    function _pathToChannel($path)
    {
        return '';
    }

    function _getTimeline($action)
    {
        $path = null;
        $timeline = null;

        $action_name = $action->trimmed('action');

        switch ($action_name) {
         case 'public':
            $path = array('public');
            break;
         case 'tag':
            $tag = $action->trimmed('tag');
            if (!empty($tag)) {
                $path = array('tag', $tag);
            }
            break;
         case 'showstream':
         case 'all':
         case 'replies':
         case 'showgroup':
            $nickname = common_canonical_nickname($action->trimmed('nickname'));
            if (!empty($nickname)) {
                $path = array($action_name, $nickname);
            }
            break;
         default:
            break;
        }

        if (!empty($path)) {
            $timeline = $this->_pathToChannel($path);
        }

        return $timeline;
    }
}
