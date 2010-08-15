<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

/**
 * @package OStatusPlugin
 * @author James Walker <james@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class SalmonAction extends Action
{
    var $xml      = null;
    var $activity = null;

    function prepare($args)
    {
        StatusNet::setApi(true); // Send smaller error pages

        parent::prepare($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(_m('This method requires a POST.'));
        }

        if (empty($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] != 'application/magic-envelope+xml') {
            $this->clientError(_m('Salmon requires application/magic-envelope+xml'));
        }

        $xml = file_get_contents('php://input');

        // Check the signature
        $salmon = new Salmon;
        if (!$salmon->verifyMagicEnv($xml)) {
            common_log(LOG_DEBUG, "Salmon signature verification failed.");
            $this->clientError(_m('Salmon signature verification failed.'));
        } else {
            $magic_env = new MagicEnvelope();
            $env = $magic_env->parse($xml);
            $xml = $magic_env->unfold($env);
        }

        $dom = DOMDocument::loadXML($xml);
        if ($dom->documentElement->namespaceURI != Activity::ATOM ||
            $dom->documentElement->localName != 'entry') {
            common_log(LOG_DEBUG, "Got invalid Salmon post: $xml");
            $this->clientError(_m('Salmon post must be an Atom entry.'));
        }

        $this->activity = new Activity($dom->documentElement);
        return true;
    }

    /**
     * Check the posted activity type and break out to appropriate processing.
     */

    function handle($args)
    {
        StatusNet::setApi(true); // Send smaller error pages

        common_log(LOG_DEBUG, "Got a " . $this->activity->verb);
        if (Event::handle('StartHandleSalmon', array($this->activity))) {
            switch ($this->activity->verb)
            {
            case ActivityVerb::POST:
                $this->handlePost();
                break;
            case ActivityVerb::SHARE:
                $this->handleShare();
                break;
            case ActivityVerb::FAVORITE:
                $this->handleFavorite();
                break;
            case ActivityVerb::UNFAVORITE:
                $this->handleUnfavorite();
                break;
            case ActivityVerb::FOLLOW:
            case ActivityVerb::FRIEND:
                $this->handleFollow();
                break;
            case ActivityVerb::UNFOLLOW:
                $this->handleUnfollow();
                break;
            case ActivityVerb::JOIN:
                $this->handleJoin();
                break;
            case ActivityVerb::LEAVE:
                $this->handleLeave();
                break;
            case ActivityVerb::TAG:
                $this->handleTag();
                break;
            case ActivityVerb::UNTAG:
                $this->handleUntag();
                break;
            case ActivityVerb::UPDATE_PROFILE:
                $this->handleUpdateProfile();
                break;
            default:
                throw new ClientException(_m("Unrecognized activity type."));
            }
            Event::handle('EndHandleSalmon', array($this->activity));
        }
    }

    function handlePost()
    {
        throw new ClientException(_m("This target doesn't understand posts."));
    }

    function handleFollow()
    {
        throw new ClientException(_m("This target doesn't understand follows."));
    }

    function handleUnfollow()
    {
        throw new ClientException(_m("This target doesn't understand unfollows."));
    }

    function handleFavorite()
    {
        throw new ClientException(_m("This target doesn't understand favorites."));
    }

    function handleUnfavorite()
    {
        throw new ClientException(_m("This target doesn't understand unfavorites."));
    }

    function handleShare()
    {
        throw new ClientException(_m("This target doesn't understand share events."));
    }

    function handleJoin()
    {
        throw new ClientException(_m("This target doesn't understand joins."));
    }

    function handleLeave()
    {
        throw new ClientException(_m("This target doesn't understand leave events."));
    }

    function handleTag()
    {
        throw new ClientException(_m("This target doesn't understand tag events."));
    }

    function handleUntag()
    {
        throw new ClientException(_m("This target doesn't understand untag events."));
    }

    /**
     * Remote user sent us an update to their profile.
     * If we already know them, accept the updates.
     */
    function handleUpdateProfile()
    {
        $oprofile = Ostatus_profile::getActorProfile($this->activity);
        if ($oprofile) {
            common_log(LOG_INFO, "Got a profile-update ping from $oprofile->uri");
            $oprofile->updateFromActivityObject($this->activity->actor);
        } else {
            common_log(LOG_INFO, "Ignoring profile-update ping from unknown " . $this->activity->actor->id);
        }
    }

    /**
     * @return Ostatus_profile
     */
    function ensureProfile()
    {
        $actor = $this->activity->actor;
        if (empty($actor->id)) {
            common_log(LOG_ERR, "broken actor: " . var_export($actor, true));
            common_log(LOG_ERR, "activity with no actor: " . var_export($this->activity, true));
            throw new Exception("Received a salmon slap from unidentified actor.");
        }

        return Ostatus_profile::ensureActivityObjectProfile($actor);
    }

    function saveNotice()
    {
        $oprofile = $this->ensureProfile();
        return $oprofile->processPost($this->activity, 'salmon');
    }
}
