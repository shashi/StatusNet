<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show, update or delete a list.
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
 * @category  API
 * @package   StatusNet
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';

class ApiListAction extends ApiAuthAction
{
    var $list   = null;
    var $update = false;
    var $delete = false;

    function prepare($args)
    {
        parent::prepare($args);

        // delete list if method is DELETE or if method is POST and an argument
        // _method is set to DELETE
        $this->delete = ($_SERVER['REQUEST_METHOD'] == 'DELETE' ||
                            ($this->trimmed('_method') == 'DELETE' &&
                             $_SERVER['REQUEST_METHOD'] == 'POST'));

        // update list if method is POST or PUT and $this->delete is not true
        $this->update = (!$this->delete &&
                         in_array($_SERVER['REQUEST_METHOD'], array('POST', 'PUT')));

        $this->user = $this->getTargetUser($this->arg('user'));
        $this->list = $this->getTargetList($this->arg('user'), $this->arg('id'));

        if (empty($this->list)) {
            $this->clientError(_('Not found'), 404, $this->format);
            return false;
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if($this->delete) {
            $this->handleDelete();
            return true;
        }

        if($this->update) {
            $this->handlePut();
            return true;
        }

        switch($this->format) {
        case 'xml':
            $this->showSingleXmlList($this->list);
            break;
        case 'json':
            $this->showSingleJsonList($this->list);
            break;
        default:
            $this->clientError(
                _('API method not found.'),
                404,
                $this->format
            );
            break;
        }
    }

    /**
     * Update a list
     *
     * @return boolean success
     */

    function handlePut()
    {
        if($this->auth_user->id != $this->list->tagger) {
            $this->clientError(
                _('You can not update lists that don\'t belong to you.'),
                401,
                $this->format
            );
        }

        $new_list = clone($this->list);
        $new_list->tag = common_canonical_tag($this->arg('name'));
        $new_list->description = common_canonical_tag($this->arg('description'));
        // ignore mode

        $result = $new_list->update($this->list);
        if(!$result) {
            $this->clientError(
                _('An error occured.'),
                503,
                $this->format
            );
        }

        switch($this->format) {
        case 'xml':
            $this->showSingleXmlList($new_list);
            break;
        case 'json':
            $this->showSingleJsonList($new_list);
            break;
        default:
            $this->clientError(
                _('API method not found.'),
                404,
                $this->format
            );
            break;
        }
    }

    function handleDelete()
    {
        if($this->auth_user->id != $this->list->tagger) {
            $this->clientError(
                _('You can not delete lists that don\'t belong to you.'),
                401,
                $this->format
            );
        }

        $record = clone($this->list);
        $this->list->delete();

        switch($this->format) {
        case 'xml':
            $this->showSingleXmlList($record);
            break;
        case 'json':
            $this->showSingleJsonList($record);
            break;
        default:
            $this->clientError(
                _('API method not found.'),
                404,
                $this->format
            );
            break;
        }
    }

    function isReadOnly($args)
    {
        return false;
    }

    function lastModified()
    {
        if(!empty($this->list)) {
            return strtotime($this->list->modified);
        }
        return null;
    }

    /**
     * An entity tag for this list
     *
     * Returns an Etag based on the action name, language, user ID and
     * timestamps of the first and last list the user has joined
     *
     * @return string etag
     */

    function etag()
    {
        if (!empty($this->list)) {

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_language(),
                      $this->user->id,
                      strtotime($this->list->created),
                      strtotime($this->list->modified))
            )
            . '"';
        }

        return null;
    }

}
