<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 *
 * Microapp Checkin plugin
 *
 * PHP version 5
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
 * @category  Checkin
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Checkin plugin
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class CheckinPlugin extends MicroAppPlugin
{
    /**
     * Set up the checkin table
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        $schema->ensureTable('checkin', Checkin::schemaDef());
        $schema->

        return true;
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'ShowcheckinAction':
        case 'NewcheckinAction':
        case 'CheckinsAction':
            include_once $dir . '/actions/'
                . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'CheckinForm':
        case 'CheckinList':
        case 'CheckinItem':
        case 'CheckinSection':
            include_once $dir . '/lib/' . strtolower($cls).'.php';
            break;
        case 'Checkin':
            include_once $dir . '/Checkin.php';
            return false;
            break;
        default:
            return true;
        }
    }

    /**
     * Map URLs to actions
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onRouterInitialized($m)
    {
        $UUIDregex = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

        $m->connect(
            'checkin/new',
            array('action' => 'newcheckin')
        );
        $m->connect(
            ':user/checkins',
            array('action' => 'checkins'),
            array('user' => '[A-Za-z0-9_-]+')
        );
        $m->connect(
            'checkin/:id',
            array('action' => 'showcheckin'),
            array('id' => $UUIDregex)
        );

        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array(
            'name'        => 'Checkin',
            'version'     => STATUSNET_VERSION,
            'author'      => 'Shashi Gowda',
            'homepage'    => 'http://status.net/wiki/Plugin:Checkin',
            'description' =>
             _m('Checkin micro-app.')
        );
        return true;
    }

    function appTitle() {
        return _m('Check-in');
    }

    function tag() {
        return 'checkin';
    }

    function types() {
        return array(
            Checkin::OBJECT_TYPE
        );
    }

    /**
     * Given a parsed ActivityStreams activity, save it into a notice
     * and other data structures.
     *
     * @param Activity $activity
     * @param Profile $actor
     * @param array $options=array()
     *
     * @return Notice the resulting notice
     */
    function saveNoticeFromActivity($activity, $actor, $options=array())
    {
        if (count($activity->objects) != 1) {
            throw new Exception('Too many activity objects.');
        }

        $checkinObj = $activity->objects[0];

        if ($checkinObj->type != Checkin::OBJECT_TYPE) {
            throw new Exception('Wrong type for object.');
        }

        $notice = null;

        if ($activity->verb == ActivityVerb::POST) {
            $notice = Checkin::saveNew(
                $actor,
                $checkinObj->target,
                $options
            );
        } else {
            throw new Exception("Unknown verb received by Checkin Plugin");
        }

        return $notice;
    }

    /**
     * Turn a Notice into an activity object
     *
     * @param Notice $notice
     *
     * @return ActivityObject
     */

    function activityObjectFromNotice($notice)
    {
        $question = null;

        if ($notice->object_type == Checkin::OBJECT_TYPE) {
            $checkin = Checkin::fromNotice($notice);
            break;
        }

        if (empty($checkin)) {
            throw new Exception("Unknown object type.");
        }

        $notice = $checkin->getNotice();

        if (empty($notice)) {
            throw new Exception("Unknown checkin notice.");
        }

        $obj = new ActivityObject();

        $obj->id      = $checkin->uri;
        $obj->target  = $checkin->getCheckind();
        $obj->link    = $notice->bestUrl();

        // XXX: probably need other stuff here

        return $obj;
    }

    function onStartShowNoticeItem($nli)
    {
        if (!$this->isMyNotice($nli->notice)) {
            return true;
        }

        $out = $nli->out;
        $notice = $nli->notice;

        $this->showNotice($notice, $out);

        $out->elementEnd('div');

        return false;
    }

    /**
     * Custom HTML output for our notices
     *
     * @param Notice $notice
     * @param HTMLOutputter $out
     */
    function showNotice($notice, $out)
    {
        if ($notice->object_type == Checkin::OBJECT_TYPE) {
            $checkin = Checkin::fromNotice($notice);
            $widget = new CheckinItem($out, $checkin);
            $widget->show();
        } else {
            throw new Exception(
                sprintf(
                    _m('Unexpected type for Checkin plugin: %s.'),
                    $notice->object_type
                )
            );
        }
    }

    /**
     * Form for our app
     *
     * @param HTMLOutputter $out
     * @return Widget
     */

    function entryForm($out)
    {
	$profile = common_current_user()->getProfile();
        $form = new CheckinForm($out, $profile);
	$form->show();
    }

    /**
     * When a notice is deleted, clean up related tables.
     *
     * @param Notice $notice
     */

    function deleteRelated($notice)
    {
        if ($notice->object_type == Checkin::OBJECT_TYPE) {
            Checkin::fromNotice($notice)->delete();
        } else {
            common_log(LOG_DEBUG, "Not deleting related, wtf...");
        }
    }

    function onEndShowScripts($action)
    {
        $action->script($this->path('js/checkin.js'));
        return true;
    }

    function onEndShowStyles($action)
    {
        $action->cssLink($this->path('css/checkin.css'));
        return true;
    }
}
