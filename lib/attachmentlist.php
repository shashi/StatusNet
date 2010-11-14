<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * widget for displaying a list of notice attachments
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
 * @category  UI
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * widget for displaying a list of notice attachments
 *
 * There are a number of actions that display a list of notices, in
 * reverse chronological order. This widget abstracts out most of the
 * code for UI for notice lists. It's overridden to hide some
 * data for e.g. the profile page.
 *
 * @category UI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      Notice
 * @see      NoticeListItem
 * @see      ProfileNoticeList
 */
class AttachmentList extends Widget
{
    /** the current stream of notices being displayed. */

    var $notice = null;

    /**
     * constructor
     *
     * @param Notice $notice stream of notices from DB_DataObject
     */
    function __construct($notice, $out=null)
    {
        parent::__construct($out);
        $this->notice = $notice;
    }

    /**
     * show the list of notices
     *
     * "Uses up" the stream by looping through it. So, probably can't
     * be called twice on the same list.
     *
     * @return int count of notices listed.
     */
    function show()
    {
        $atts = new File;
        $att = $atts->getAttachments($this->notice->id);
        if (empty($att)) return 0;
        $this->out->elementStart('dl', array('id' =>'attachments',
                                             'class' => 'entry-content'));
        // TRANS: DT element label in attachment list.
        $this->out->element('dt', null, _('Attachments'));
        $this->out->elementStart('dd');
        $this->out->elementStart('ol', array('class' => 'attachments'));

        foreach ($att as $n=>$attachment) {
            $item = $this->newListItem($attachment);
            $item->show();
        }

        $this->out->elementEnd('dd');
        $this->out->elementEnd('ol');
        $this->out->elementEnd('dl');

        return count($att);
    }

    /**
     * returns a new list item for the current notice
     *
     * Recipe (factory?) method; overridden by sub-classes to give
     * a different list item class.
     *
     * @param Notice $notice the current notice
     *
     * @return NoticeListItem a list item for displaying the notice
     */
    function newListItem($attachment)
    {
        return new AttachmentListItem($attachment, $this->out);
    }
}

/**
 * widget for displaying a single notice
 *
 * This widget has the core smarts for showing a single notice: what to display,
 * where, and under which circumstances. Its key method is show(); this is a recipe
 * that calls all the other show*() methods to build up a single notice. The
 * ProfileNoticeListItem subclass, for example, overrides showAuthor() to skip
 * author info (since that's implicit by the data in the page).
 *
 * @category UI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      NoticeList
 * @see      ProfileNoticeListItem
 */
class AttachmentListItem extends Widget
{
    /** The attachment this item will show. */

    var $attachment = null;

    var $oembed = null;

    /**
     * constructor
     *
     * Also initializes the profile attribute.
     *
     * @param Notice $notice The notice we'll display
     */
    function __construct($attachment, $out=null)
    {
        parent::__construct($out);
        $this->attachment  = $attachment;
        $this->oembed = File_oembed::staticGet('file_id', $this->attachment->id);
    }

    function title() {
        if (empty($this->attachment->title)) {
            if (empty($this->oembed->title)) {
                $title = $this->attachment->url;
            } else {
                $title = $this->oembed->title;
            }
        } else {
            $title = $this->attachment->title;
        }

        return $title;
    }

    function linkTitle() {
        return $this->title();
    }

    /**
     * recipe function for displaying a single notice.
     *
     * This uses all the other methods to correctly display a notice. Override
     * it or one of the others to fine-tune the output.
     *
     * @return void
     */
    function show()
    {
        $this->showStart();
        $this->showNoticeAttachment();
        $this->showEnd();
    }

    function linkAttr() {
        return array('class' => 'attachment', 'href' => $this->attachment->url, 'id' => 'attachment-' . $this->attachment->id);
    }

    function showLink() {
        $this->out->elementStart('a', $this->linkAttr());
        $this->out->element('span', null, $this->linkTitle());
        $this->showRepresentation();
        $this->out->elementEnd('a');
    }

    function showNoticeAttachment()
    {
        $this->showLink();
    }

    function showRepresentation() {
        $thumbnail = File_thumbnail::staticGet('file_id', $this->attachment->id);
        if (!empty($thumbnail)) {
            $this->out->element('img', array('alt' => '', 'src' => $thumbnail->url, 'width' => $thumbnail->width, 'height' => $thumbnail->height));
        }
    }

    /**
     * start a single notice.
     *
     * @return void
     */
    function showStart()
    {
        // XXX: RDFa
        // TODO: add notice_type class e.g., notice_video, notice_image
        $this->out->elementStart('li');
    }

    /**
     * finish the notice
     *
     * Close the last elements in the notice list item
     *
     * @return void
     */
    function showEnd()
    {
        $this->out->elementEnd('li');
    }
}

class Attachment extends AttachmentListItem
{
    function showLink() {
        $this->out->elementStart('div', array('id' => 'attachment_view',
                                              'class' => 'hentry'));
        $this->out->elementStart('div', 'entry-title');
        $this->out->element('a', $this->linkAttr(), $this->linkTitle());
        $this->out->elementEnd('div');

        $this->out->elementStart('div', 'entry-content');
        $this->showRepresentation();
        $this->out->elementEnd('div');

        if (!empty($this->oembed->author_name) || !empty($this->oembed->provider)) {
            $this->out->elementStart('div', array('id' => 'oembed_info',
                                                  'class' => 'entry-content'));
            if (!empty($this->oembed->author_name)) {
                $this->out->elementStart('dl', 'vcard author');
                // TRANS: DT element label in attachment list item.
                $this->out->element('dt', null, _('Author'));
                $this->out->elementStart('dd', 'fn');
                if (empty($this->oembed->author_url)) {
                    $this->out->text($this->oembed->author_name);
                } else {
                    $this->out->element('a', array('href' => $this->oembed->author_url,
                                                   'class' => 'url'), $this->oembed->author_name);
                }
                $this->out->elementEnd('dd');
                $this->out->elementEnd('dl');
            }
            if (!empty($this->oembed->provider)) {
                $this->out->elementStart('dl', 'vcard');
                // TRANS: DT element label in attachment list item.
                $this->out->element('dt', null, _('Provider'));
                $this->out->elementStart('dd', 'fn');
                if (empty($this->oembed->provider_url)) {
                    $this->out->text($this->oembed->provider);
                } else {
                    $this->out->element('a', array('href' => $this->oembed->provider_url,
                                                   'class' => 'url'), $this->oembed->provider);
                }
                $this->out->elementEnd('dd');
                $this->out->elementEnd('dl');
            }
            $this->out->elementEnd('div');
        }
        $this->out->elementEnd('div');
    }

    function show() {
        $this->showNoticeAttachment();
    }

    function linkAttr() {
        return array('rel' => 'external', 'href' => $this->attachment->url);
    }

    function linkTitle() {
        return $this->attachment->url;
    }

    function showRepresentation() {
        if (empty($this->oembed->type)) {
            if (empty($this->attachment->mimetype)) {
                $this->showFallback();
            } else {
                switch ($this->attachment->mimetype) {
                case 'image/gif':
                case 'image/png':
                case 'image/jpg':
                case 'image/jpeg':
                    $this->out->element('img', array('src' => $this->attachment->url, 'alt' => 'alt'));
                    break;

                case 'application/ogg':
                case 'audio/x-speex':
                case 'video/mpeg':
                case 'audio/mpeg':
                case 'video/mp4':
                case 'video/quicktime':
                    $arr  = array('type' => $this->attachment->mimetype,
                        'data' => $this->attachment->url,
                        'width' => 320,
                        'height' => 240
                    );
                    $this->out->elementStart('object', $arr);
                    $this->out->element('param', array('name' => 'src', 'value' => $this->attachment->url));
                    $this->out->element('param', array('name' => 'autoStart', 'value' => 1));
                    $this->out->elementEnd('object');
                    break;

                case 'text/html':
                    if ($this->attachment->filename) {
                        // Locally-uploaded HTML. Scrub and display inline.
                        $this->showHtmlFile($this->attachment);
                        break;
                    }
                    // Fall through to default.

                default:
                    $this->showFallback();
                }
            }
        } else {
            switch ($this->oembed->type) {
            case 'rich':
            case 'video':
            case 'link':
                if (!empty($this->oembed->html)) {
                    require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';
                    $config = array(
                        'safe'=>1,
                        'elements'=>'*+object+embed');
                    $this->out->raw(htmLawed($this->oembed->html,$config));
                    //$this->out->raw($this->oembed->html);
                }
                break;

            case 'photo':
                $this->out->element('img', array('src' => $this->oembed->url, 'width' => $this->oembed->width, 'height' => $this->oembed->height, 'alt' => 'alt'));
                break;

            default:
                $this->showFallback();
            }
        }
    }

    protected function showHtmlFile(File $attachment)
    {
        $body = $this->scrubHtmlFile($attachment);
        if ($body) {
            $this->out->raw($body);
        }
    }

    /**
     * @return mixed false on failure, HTML fragment string on success
     */
    protected function scrubHtmlFile(File $attachment)
    {
        $path = File::path($attachment->filename);
        if (!file_exists($path) || !is_readable($path)) {
            common_log(LOG_ERR, "Missing local HTML attachment $path");
            return false;
        }
        $raw = file_get_contents($path);

        // Normalize...
        $dom = new DOMDocument();
        if(!$dom->loadHTML($raw)) {
            common_log(LOG_ERR, "Bad HTML in local HTML attachment $path");
            return false;
        }

        // Remove <script>s or htmlawed will dump their contents into output!
        // Note: removing child nodes while iterating seems to mess things up,
        // hence the double loop.
        $scripts = array();
        foreach ($dom->getElementsByTagName('script') as $script) {
            $scripts[] = $script;
        }
        foreach ($scripts as $script) {
            common_log(LOG_DEBUG, $script->textContent);
            $script->parentNode->removeChild($script);
        }

        // Trim out everything outside the body...
        $body = $dom->saveHTML();
        $body = preg_replace('/^.*<body[^>]*>/is', '', $body);
        $body = preg_replace('/<\/body[^>]*>.*$/is', '', $body);

        require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';
        $config = array('safe' => 1,
                        'deny_attribute' => 'id,style,on*',
                        'comment' => 1); // remove comments
        $scrubbed = htmLawed($body, $config);

        return $scrubbed;
    }

    function showFallback()
    {
        // If we don't know how to display an attachment inline, we probably
        // shouldn't have gotten to this point.
        //
        // But, here we are... displaying details on a file or remote URL
        // either on the main view or in an ajax-loaded lightbox. As a lesser
        // of several evils, we'll try redirecting to the actual target via
        // client-side JS.

        common_log(LOG_ERR, "Empty or unknown type for file id {$this->attachment->id}; falling back to client-side redirect.");
        $this->out->raw('<script>window.location = ' . json_encode($this->attachment->url) . ';</script>');
    }
}
