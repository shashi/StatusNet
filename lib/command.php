<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, 2010 StatusNet, Inc.
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/channel.php');

class Command
{

    var $user = null;

    function __construct($user=null)
    {
        $this->user = $user;
    }

    /**
     * Execute the command and send success or error results
     * back via the given communications channel.
     *
     * @param Channel
     */
    public function execute($channel)
    {
        try {
            $this->handle($channel);
        } catch (CommandException $e) {
            $channel->error($this->user, $e->getMessage());
        } catch (Exception $e) {
            common_log(LOG_ERR, "Error handling " . get_class($this) . ": " . $e->getMessage());
            $channel->error($this->user, $e->getMessage());
        }
    }


    /**
     * Override this with the meat!
     *
     * An error to send back to the user may be sent by throwing
     * a CommandException with a formatted message.
     *
     * @param Channel
     * @throws CommandException
     */
    function handle($channel)
    {
        return false;
    }

    /**
     * Look up a notice from an argument, by poster's name to get last post
     * or notice_id prefixed with #.
     *
     * @return Notice
     * @throws CommandException
     */
    function getNotice($arg)
    {
        $notice = null;
        if (Event::handle('StartCommandGetNotice', array($this, $arg, &$notice))) {
            if(substr($this->other,0,1)=='#'){
                // A specific notice_id #123

                $notice = Notice::staticGet(substr($arg,1));
                if (!$notice) {
                    // TRANS: Command exception text shown when a notice ID is requested that does not exist.
                    throw new CommandException(_('Notice with that id does not exist.'));
                }
            }

            if (Validate::uri($this->other)) {
                // A specific notice by URI lookup
                $notice = Notice::staticGet('uri', $arg);
            }

            if (!$notice) {
                // Local or remote profile name to get their last notice.
                // May throw an exception and report 'no such user'
                $recipient = $this->getProfile($arg);

                $notice = $recipient->getCurrentNotice();
                if (!$notice) {
                    // TRANS: Command exception text shown when a last user notice is requested and it does not exist.
                    throw new CommandException(_('User has no last notice.'));
                }
            }
        }
        Event::handle('EndCommandGetNotice', array($this, $arg, &$notice));
        if (!$notice) {
            // TRANS: Command exception text shown when a notice ID is requested that does not exist.
            throw new CommandException(_('Notice with that id does not exist.'));
        }
        return $notice;
    }

    /**
     * Look up a local or remote profile by nickname.
     *
     * @return Profile
     * @throws CommandException
     */
    function getProfile($arg)
    {
        $profile = null;
        if (Event::handle('StartCommandGetProfile', array($this, $arg, &$profile))) {
            $profile =
              common_relative_profile($this->user, common_canonical_nickname($arg));
        }
        Event::handle('EndCommandGetProfile', array($this, $arg, &$profile));
        if (!$profile) {
            // TRANS: Message given requesting a profile for a non-existing user.
            // TRANS: %s is the nickname of the user for which the profile could not be found.
            throw new CommandException(sprintf(_('Could not find a user with nickname %s.'), $arg));
        }
        return $profile;
    }

    /**
     * Get a local user by name
     * @return User
     * @throws CommandException
     */
    function getUser($arg)
    {
        $user = null;
        if (Event::handle('StartCommandGetUser', array($this, $arg, &$user))) {
            $user = User::staticGet('nickname', $arg);
        }
        Event::handle('EndCommandGetUser', array($this, $arg, &$user));
        if (!$user){
            // TRANS: Message given getting a non-existing user.
            // TRANS: %s is the nickname of the user that could not be found.
            throw new CommandException(sprintf(_('Could not find a local user with nickname %s.'),
                               $arg));
        }
        return $user;
    }

    /**
     * Get a local or remote group by name.
     * @return User_group
     * @throws CommandException
     */
    function getGroup($arg)
    {
        $group = null;
        if (Event::handle('StartCommandGetGroup', array($this, $arg, &$group))) {
            $group = User_group::getForNickname($arg, $this->user->getProfile());
        }
        Event::handle('EndCommandGetGroup', array($this, $arg, &$group));
        if (!$group) {
            // TRANS: Command exception text shown when a group is requested that does not exist.
            throw new CommandException(_('No such group.'));
        }
        return $group;
    }
}

class CommandException extends Exception
{
}

class UnimplementedCommand extends Command
{
    function handle($channel)
    {
        // TRANS: Error text shown when an unimplemented command is given.
        $channel->error($this->user, _("Sorry, this command is not yet implemented."));
    }
}

class TrackingCommand extends UnimplementedCommand
{
}

class TrackOffCommand extends UnimplementedCommand
{
}

class TrackCommand extends UnimplementedCommand
{
    var $word = null;
    function __construct($user, $word)
    {
        parent::__construct($user);
        $this->word = $word;
    }
}

class UntrackCommand extends UnimplementedCommand
{
    var $word = null;
    function __construct($user, $word)
    {
        parent::__construct($user);
        $this->word = $word;
    }
}

class NudgeCommand extends Command
{
    var $other = null;
    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function handle($channel)
    {
        $recipient = $this->getUser($this->other);
        if ($recipient->id == $this->user->id) {
            // TRANS: Command exception text shown when a user tries to nudge themselves.
            throw new CommandException(_('It does not make a lot of sense to nudge yourself!'));
        } else {
            if ($recipient->email && $recipient->emailnotifynudge) {
                mail_notify_nudge($this->user, $recipient);
            }
            // XXX: notify by IM
            // XXX: notify by SMS
            // TRANS: Message given having nudged another user.
            // TRANS: %s is the nickname of the user that was nudged.
            $channel->output($this->user, sprintf(_('Nudge sent to %s.'),
                           $recipient->nickname));
        }
    }
}

class InviteCommand extends UnimplementedCommand
{
    var $other = null;
    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }
}

class StatsCommand extends Command
{
    function handle($channel)
    {
        $profile = $this->user->getProfile();

        $subs_count   = $profile->subscriptionCount();
        $subbed_count = $profile->subscriberCount();
        $notice_count = $profile->noticeCount();

        // TRANS: User statistics text.
        // TRANS: %1$s is the number of other user the user is subscribed to.
        // TRANS: %2$s is the number of users that are subscribed to the user.
        // TRANS: %3$s is the number of notices the user has sent.
        $channel->output($this->user, sprintf(_("Subscriptions: %1\$s\n".
                                   "Subscribers: %2\$s\n".
                                   "Notices: %3\$s"),
                                 $subs_count,
                                 $subbed_count,
                                 $notice_count));
    }
}

class FavCommand extends Command
{
    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function handle($channel)
    {
        $notice = $this->getNotice($this->other);
        $fave = Fave::addNew($this->user->getProfile(), $notice);

        if (!$fave) {
            // TRANS: Error message text shown when a favorite could not be set.
            $channel->error($this->user, _('Could not create favorite.'));
            return;
        }

        // @fixme favorite notification should be triggered
        // at a lower level

        $other = User::staticGet('id', $notice->profile_id);

        if ($other && $other->id != $user->id) {
            if ($other->email && $other->emailnotifyfav) {
                mail_notify_fave($other, $this->user, $notice);
            }
        }

        $this->user->blowFavesCache();

        // TRANS: Text shown when a notice has been marked as favourite successfully.
        $channel->output($this->user, _('Notice marked as fave.'));
    }

}

class JoinCommand extends Command
{
    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function handle($channel)
    {
        $group = $this->getGroup($this->other);
        $cur   = $this->user;

        if ($cur->isMember($group)) {
            // TRANS: Error text shown a user tries to join a group they already are a member of.
            $channel->error($cur, _('You are already a member of that group.'));
            return;
        }
        if (Group_block::isBlocked($group, $cur->getProfile())) {
            // TRANS: Error text shown when a user tries to join a group they are blocked from joining.
          $channel->error($cur, _('You have been blocked from that group by the admin.'));
            return;
        }

        try {
            if (Event::handle('StartJoinGroup', array($group, $cur))) {
                Group_member::join($group->id, $cur->id);
                Event::handle('EndJoinGroup', array($group, $cur));
            }
        } catch (Exception $e) {
            // TRANS: Message given having failed to add a user to a group.
            // TRANS: %1$s is the nickname of the user, %2$s is the nickname of the group.
            $channel->error($cur, sprintf(_('Could not join user %1$s to group %2$s.'),
                                          $cur->nickname, $group->nickname));
            return;
        }

        // TRANS: Message given having added a user to a group.
        // TRANS: %1$s is the nickname of the user, %2$s is the nickname of the group.
        $channel->output($cur, sprintf(_('%1$s joined group %2$s.'),
                                              $cur->nickname,
                                              $group->nickname));
    }

}
class DropCommand extends Command
{
    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function handle($channel)
    {
        $group = $this->getGroup($this->other);
        $cur   = $this->user;

        if (!$group) {
            // TRANS: Error text shown when trying to leave a group that does not exist.
            $channel->error($cur, _('No such group.'));
            return;
        }

        if (!$cur->isMember($group)) {
            // TRANS: Error text shown when trying to leave an existing group the user is not a member of.
            $channel->error($cur, _('You are not a member of that group.'));
            return;
        }

        try {
            if (Event::handle('StartLeaveGroup', array($group, $cur))) {
                Group_member::leave($group->id, $cur->id);
                Event::handle('EndLeaveGroup', array($group, $cur));
            }
        } catch (Exception $e) {
            // TRANS: Message given having failed to remove a user from a group.
            // TRANS: %1$s is the nickname of the user, %2$s is the nickname of the group.
            $channel->error($cur, sprintf(_('Could not remove user %1$s from group %2$s.'),
                                          $cur->nickname, $group->nickname));
            return;
        }

        // TRANS: Message given having removed a user from a group.
        // TRANS: %1$s is the nickname of the user, %2$s is the nickname of the group.
        $channel->output($cur, sprintf(_('%1$s left group %2$s.'),
                                              $cur->nickname,
                                              $group->nickname));
    }

}

class TagCommand extends Command
{
    var $other = null;
    var $tags = null;
    function __construct($user, $other, $tags)
    {
        parent::__construct($user);
        $this->other = $other;
        $this->tags = $tags;
    }

    function handle($channel)
    {
        $profile = $this->getProfile($this->other);
        $cur     = $this->user->getProfile();

        if (!$profile) {
            $channel->error($cur, _('No such profile.'));
            return;
        }
        if (!$cur->canTag($profile)) {
            $channel->error($cur, _('You cannot tag this user.'));
            return;
        }

        $privs = array();
        $tags = preg_split('/[\s,]+/', $this->tags);
        $clean_tags = array();

        foreach ($tags as $tag) {
            $private = @$tag[0] === '.';
            $tag = $clean_tags[] = common_canonical_tag($tag);

            if (!common_valid_profile_tag($tag)) {
                $channel->error($cur, sprintf(_('Invalid tag: "%s"'), $tag));
                return;
            }
            $privs[$tag] = $private;
        }

        try {
            foreach ($clean_tags as $tag) {
                Profile_tag::setTag($cur->id, $profile->id, $tag, null, $privs[$tag]);
            }
        } catch (Exception $e) {
            $channel->error($cur, sprintf(_('Error tagging %s: %s'),
                                          $profile->nickname, $e->getMessage()));
            return;
        }

        $channel->output($cur, sprintf(_('%1$s was tagged %2$s'),
                                              $profile->nickname,
                                              implode(', ', $clean_tags)));
    }
}


class UntagCommand extends TagCommand
{
    function handle($channel)
    {
        $profile = $this->getProfile($this->other);
        $cur     = $this->user->getProfile();

        if (!$profile) {
            $channel->error($cur, _('No such profile.'));
            return;
        }
        if (!$cur->canTag($profile)) {
            $channel->error($cur, _('You cannot tag this user.'));
            return;
        }

        $tags = array_map('common_canonical_tag', preg_split('/[\s,]+/', $this->tags));

        foreach ($tags as $tag) {
            if (!common_valid_profile_tag($tag)) {
                $channel->error($cur, sprintf(_('Invalid tag: "%s"'), $tag));
                return;
            }
        }

        try {
            foreach ($tags as $tag) {
                Profile_tag::unTag($cur->id, $profile->id, $tag);
            }
        } catch (Exception $e) {
            $channel->error($cur, sprintf(_('Error untagging %s: %s'),
                                          $profile->nickname, $e->getMessage()));
            return;
        }

        $channel->output($cur, sprintf(_('The following tag(s) were removed from user %1$s: %2$s.'),
                                              $profile->nickname,
                                              implode(', ', $tags)));
    }
}

class WhoisCommand extends Command
{
    var $other = null;
    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function handle($channel)
    {
        $recipient = $this->getProfile($this->other);

        // TRANS: Whois output.
        // TRANS: %1$s nickname of the queried user, %2$s is their profile URL.
        $whois = sprintf(_("%1\$s (%2\$s)"), $recipient->nickname,
                         $recipient->profileurl);
        if ($recipient->fullname) {
            // TRANS: Whois output. %s is the full name of the queried user.
            $whois .= "\n" . sprintf(_('Fullname: %s'), $recipient->fullname);
        }
        if ($recipient->location) {
            // TRANS: Whois output. %s is the location of the queried user.
            $whois .= "\n" . sprintf(_('Location: %s'), $recipient->location);
        }
        if ($recipient->homepage) {
            // TRANS: Whois output. %s is the homepage of the queried user.
            $whois .= "\n" . sprintf(_('Homepage: %s'), $recipient->homepage);
        }
        if ($recipient->bio) {
            // TRANS: Whois output. %s is the bio information of the queried user.
            $whois .= "\n" . sprintf(_('About: %s'), $recipient->bio);
        }
        $channel->output($this->user, $whois);
    }
}

class MessageCommand extends Command
{
    var $other = null;
    var $text = null;
    function __construct($user, $other, $text)
    {
        parent::__construct($user);
        $this->other = $other;
        $this->text = $text;
    }

    function handle($channel)
    {
        try {
            $other = $this->getUser($this->other);
        } catch (CommandException $e) {
            try {
                $profile = $this->getProfile($this->other);
            } catch (CommandException $f) {
                throw $e;
            }
            // TRANS: Command exception text shown when trying to send a direct message to a remote user (a user not registered at the current server).
            throw new CommandException(sprintf(_('%s is a remote profile; you can only send direct messages to users on the same server.'), $this->other));
        }

        $len = mb_strlen($this->text);

        if ($len == 0) {
            // TRANS: Command exception text shown when trying to send a direct message to another user without content.
            $channel->error($this->user, _('No content!'));
            return;
        }

        $this->text = common_shorten_links($this->text);

        if (Message::contentTooLong($this->text)) {
            // XXX: i18n. Needs plural support.
            // TRANS: Message given if content is too long.
            // TRANS: %1$d is the maximum number of characters, %2$d is the number of submitted characters.
            $channel->error($this->user, sprintf(_('Message too long - maximum is %1$d characters, you sent %2$d.'),
                                                 Message::maxContent(), mb_strlen($this->text)));
            return;
        }

        if (!$other) {
            // TRANS: Error text shown when trying to send a direct message to a user that does not exist.
            $channel->error($this->user, _('No such user.'));
            return;
        } else if (!$this->user->mutuallySubscribed($other)) {
            // TRANS: Error text shown when trying to send a direct message to a user without a mutual subscription (each user must be subscribed to the other).
            $channel->error($this->user, _('You can\'t send a message to this user.'));
            return;
        } else if ($this->user->id == $other->id) {
            // TRANS: Error text shown when trying to send a direct message to self.
            $channel->error($this->user, _('Don\'t send a message to yourself; just say it to yourself quietly instead.'));
            return;
        }
        $message = Message::saveNew($this->user->id, $other->id, $this->text, $channel->source());
        if ($message) {
            $message->notify();
            // TRANS: Message given have sent a direct message to another user.
            // TRANS: %s is the name of the other user.
            $channel->output($this->user, sprintf(_('Direct message to %s sent.'), $this->other));
        } else {
            // TRANS: Error text shown sending a direct message fails with an unknown reason.
            $channel->error($this->user, _('Error sending direct message.'));
        }
    }
}

class RepeatCommand extends Command
{
    var $other = null;
    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function handle($channel)
    {
        $notice = $this->getNotice($this->other);

        if($this->user->id == $notice->profile_id)
        {
            // TRANS: Error text shown when trying to repeat an own notice.
            $channel->error($this->user, _('Cannot repeat your own notice.'));
            return;
        }

        if ($this->user->getProfile()->hasRepeated($notice->id)) {
            // TRANS: Error text shown when trying to repeat an notice that was already repeated by the user.
            $channel->error($this->user, _('Already repeated that notice.'));
            return;
        }

        $repeat = $notice->repeat($this->user->id, $channel->source);

        if ($repeat) {

            // TRANS: Message given having repeated a notice from another user.
            // TRANS: %s is the name of the user for which the notice was repeated.
            $channel->output($this->user, sprintf(_('Notice from %s repeated.'), $recipient->nickname));
        } else {
            // TRANS: Error text shown when repeating a notice fails with an unknown reason.
            $channel->error($this->user, _('Error repeating notice.'));
        }
    }
}

class ReplyCommand extends Command
{
    var $other = null;
    var $text = null;
    function __construct($user, $other, $text)
    {
        parent::__construct($user);
        $this->other = $other;
        $this->text = $text;
    }

    function handle($channel)
    {
        $notice = $this->getNotice($this->other);
        $recipient = $notice->getProfile();

        $len = mb_strlen($this->text);

        if ($len == 0) {
            // TRANS: Command exception text shown when trying to reply to a notice without providing content for the reply.
            $channel->error($this->user, _('No content!'));
            return;
        }

        $this->text = common_shorten_links($this->text);

        if (Notice::contentTooLong($this->text)) {
            // XXX: i18n. Needs plural support.
            // TRANS: Message given if content of a notice for a reply is too long.
            // TRANS: %1$d is the maximum number of characters, %2$d is the number of submitted characters.
            $channel->error($this->user, sprintf(_('Notice too long - maximum is %1$d characters, you sent %2$d.'),
                                                 Notice::maxContent(), mb_strlen($this->text)));
            return;
        }

        $notice = Notice::saveNew($this->user->id, $this->text, $channel->source(),
                                  array('reply_to' => $notice->id));

        if ($notice) {
            // TRANS: Text shown having sent a reply to a notice successfully.
            // TRANS: %s is the nickname of the user of the notice the reply was sent to.
            $channel->output($this->user, sprintf(_('Reply to %s sent.'), $recipient->nickname));
        } else {
            // TRANS: Error text shown when a reply to a notice fails with an unknown reason.
            $channel->error($this->user, _('Error saving notice.'));
        }

    }
}

class GetCommand extends Command
{

    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function handle($channel)
    {
        $target = $this->getProfile($this->other);

        $notice = $target->getCurrentNotice();
        if (!$notice) {
            // TRANS: Error text shown when a last user notice is requested and it does not exist.
            $channel->error($this->user, _('User has no last notice.'));
            return;
        }
        $notice_content = $notice->content;

        $channel->output($this->user, $target->nickname . ": " . $notice_content);
    }
}

class SubCommand extends Command
{

    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function handle($channel)
    {

        if (!$this->other) {
            // TRANS: Error text shown when no username was provided when issuing a subscribe command.
            $channel->error($this->user, _('Specify the name of the user to subscribe to.'));
            return;
        }

        $target = $this->getProfile($this->other);

        $remote = Remote_profile::staticGet('id', $target->id);
        if ($remote) {
            // TRANS: Command exception text shown when trying to subscribe to an OMB profile using the subscribe command.
            throw new CommandException(_("Can't subscribe to OMB profiles by command."));
        }

        try {
            Subscription::start($this->user->getProfile(),
                                $target);
            // TRANS: Text shown after having subscribed to another user successfully.
            // TRANS: %s is the name of the user the subscription was requested for.
            $channel->output($this->user, sprintf(_('Subscribed to %s.'), $this->other));
        } catch (Exception $e) {
            $channel->error($this->user, $e->getMessage());
        }
    }
}

class UnsubCommand extends Command
{

    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function handle($channel)
    {
        if(!$this->other) {
            // TRANS: Error text shown when no username was provided when issuing an unsubscribe command.
            $channel->error($this->user, _('Specify the name of the user to unsubscribe from.'));
            return;
        }

        $target = $this->getProfile($this->other);

        try {
            Subscription::cancel($this->user->getProfile(),
                                 $target);
            // TRANS: Text shown after having unsubscribed from another user successfully.
            // TRANS: %s is the name of the user the unsubscription was requested for.
            $channel->output($this->user, sprintf(_('Unsubscribed from %s.'), $this->other));
        } catch (Exception $e) {
            $channel->error($this->user, $e->getMessage());
        }
    }
}

class OffCommand extends Command
{
    var $other = null;
    function __construct($user, $other=null)
    {
        parent::__construct($user);
        $this->other = $other;
    }
    function handle($channel)
    {
        if ($this->other) {
            // TRANS: Error text shown when issuing the command "off" with a setting which has not yet been implemented.
            $channel->error($this->user, _("Command not yet implemented."));
        } else {
            if ($channel->off($this->user)) {
                // TRANS: Text shown when issuing the command "off" successfully.
                $channel->output($this->user, _('Notification off.'));
            } else {
                // TRANS: Error text shown when the command "off" fails for an unknown reason.
                $channel->error($this->user, _('Can\'t turn off notification.'));
            }
        }
    }
}

class OnCommand extends Command
{
    var $other = null;
    function __construct($user, $other=null)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function handle($channel)
    {
        if ($this->other) {
            // TRANS: Error text shown when issuing the command "on" with a setting which has not yet been implemented.
            $channel->error($this->user, _("Command not yet implemented."));
        } else {
            if ($channel->on($this->user)) {
                // TRANS: Text shown when issuing the command "on" successfully.
                $channel->output($this->user, _('Notification on.'));
            } else {
                // TRANS: Error text shown when the command "on" fails for an unknown reason.
                $channel->error($this->user, _('Can\'t turn on notification.'));
            }
        }
    }
}

class LoginCommand extends Command
{
    function handle($channel)
    {
        $disabled = common_config('logincommand','disabled');
        $disabled = isset($disabled) && $disabled;
        if($disabled) {
            // TRANS: Error text shown when issuing the login command while login is disabled.
            $channel->error($this->user, _('Login command is disabled.'));
            return;
        }

        try {
            $login_token = Login_token::makeNew($this->user);
        } catch (Exception $e) {
            $channel->error($this->user, $e->getMessage());
        }

        $channel->output($this->user,
            // TRANS: Text shown after issuing the login command successfully.
            // TRANS: %s is a logon link..
            sprintf(_('This link is useable only once and is valid for only 2 minutes: %s.'),
                    common_local_url('otp',
                        array('user_id' => $login_token->user_id, 'token' => $login_token->token))));
    }
}

class LoseCommand extends Command
{
    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function execute($channel)
    {
        if(!$this->other) {
            // TRANS: Error text shown when no username was provided when issuing the command.
            $channel->error($this->user, _('Specify the name of the user to unsubscribe from.'));
            return;
        }

        $result = Subscription::cancel($this->getProfile($this->other), $this->user->getProfile());

        if ($result) {
            // TRANS: Text shown after issuing the lose command successfully (stop another user from following the current user).
            // TRANS: %s is the name of the user the unsubscription was requested for.
            $channel->output($this->user, sprintf(_('Unsubscribed %s.'), $this->other));
        } else {
            $channel->error($this->user, $result);
        }
    }
}

class SubscriptionsCommand extends Command
{
    function handle($channel)
    {
        $profile = $this->user->getSubscriptions(0);
        $nicknames=array();
        while ($profile->fetch()) {
            $nicknames[]=$profile->nickname;
        }
        if(count($nicknames)==0){
            // TRANS: Text shown after requesting other users a user is subscribed to without having any subscriptions.
            $out=_('You are not subscribed to anyone.');
        }else{
            // TRANS: Text shown after requesting other users a user is subscribed to.
            // TRANS: This message support plural forms. This message is followed by a
            // TRANS: hard coded space and a comma separated list of subscribed users.
            $out = ngettext('You are subscribed to this person:',
                'You are subscribed to these people:',
                count($nicknames));
            $out .= ' ';
            $out .= implode(', ',$nicknames);
        }
        $channel->output($this->user,$out);
    }
}

class SubscribersCommand extends Command
{
    function handle($channel)
    {
        $profile = $this->user->getSubscribers();
        $nicknames=array();
        while ($profile->fetch()) {
            $nicknames[]=$profile->nickname;
        }
        if(count($nicknames)==0){
            // TRANS: Text shown after requesting other users that are subscribed to a user
            // TRANS: (followers) without having any subscribers.
            $out=_('No one is subscribed to you.');
        }else{
            // TRANS: Text shown after requesting other users that are subscribed to a user (followers).
            // TRANS: This message support plural forms. This message is followed by a
            // TRANS: hard coded space and a comma separated list of subscribing users.
            $out = ngettext('This person is subscribed to you:',
                'These people are subscribed to you:',
                count($nicknames));
            $out .= ' ';
            $out .= implode(', ',$nicknames);
        }
        $channel->output($this->user,$out);
    }
}

class GroupsCommand extends Command
{
    function handle($channel)
    {
        $group = $this->user->getGroups();
        $groups=array();
        while ($group->fetch()) {
            $groups[]=$group->nickname;
        }
        if(count($groups)==0){
            // TRANS: Text shown after requesting groups a user is subscribed to without having
            // TRANS: any group subscriptions.
            $out=_('You are not a member of any groups.');
        }else{
            // TRANS: Text shown after requesting groups a user is subscribed to.
            // TRANS: This message support plural forms. This message is followed by a
            // TRANS: hard coded space and a comma separated list of subscribed groups.
            $out = ngettext('You are a member of this group:',
                'You are a member of these groups:',
                count($nicknames));
            $out.=implode(', ',$groups);
        }
        $channel->output($this->user,$out);
    }
}

class HelpCommand extends Command
{
    function handle($channel)
    {
    	// TRANS: Help text for commands.
        $channel->output($this->user,
                         _("Commands:\n".
                           "on - turn on notifications\n".
                           "off - turn off notifications\n".
                           "help - show this help\n".
                           "follow <nickname> - subscribe to user\n".
                           "groups - lists the groups you have joined\n".
                           "tag <nickname> <tags> - tag a user\n".
                           "untag <nickname> <tags> - untag a user\n".
                           "subscriptions - list the people you follow\n".
                           "subscribers - list the people that follow you\n".
                           "leave <nickname> - unsubscribe from user\n".
                           "d <nickname> <text> - direct message to user\n".
                           "get <nickname> - get last notice from user\n".
                           "whois <nickname> - get profile info on user\n".
                           "lose <nickname> - force user to stop following you\n".
                           "fav <nickname> - add user's last notice as a 'fave'\n".
                           "fav #<notice_id> - add notice with the given id as a 'fave'\n".
                           "repeat #<notice_id> - repeat a notice with a given id\n".
                           "repeat <nickname> - repeat the last notice from user\n".
                           "reply #<notice_id> - reply to notice with a given id\n".
                           "reply <nickname> - reply to the last notice from user\n".
                           "join <group> - join group\n".
                           "login - Get a link to login to the web interface\n".
                           "drop <group> - leave group\n".
                           "stats - get your stats\n".
                           "stop - same as 'off'\n".
                           "quit - same as 'off'\n".
                           "sub <nickname> - same as 'follow'\n".
                           "unsub <nickname> - same as 'leave'\n".
                           "last <nickname> - same as 'get'\n".
                           "on <nickname> - not yet implemented.\n".
                           "off <nickname> - not yet implemented.\n".
                           "nudge <nickname> - remind a user to update.\n".
                           "invite <phone number> - not yet implemented.\n".
                           "track <word> - not yet implemented.\n".
                           "untrack <word> - not yet implemented.\n".
                           "track off - not yet implemented.\n".
                           "untrack all - not yet implemented.\n".
                           "tracks - not yet implemented.\n".
                           "tracking - not yet implemented.\n"));
    }
}
