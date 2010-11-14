<?php
/**
 * Table Definition for oauth_application
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Oauth_application extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'oauth_application';               // table name
    public $id;                              // int(4)  primary_key not_null
    public $owner;                           // int(4)   not_null
    public $consumer_key;                    // varchar(255)   not_null
    public $name;                            // varchar(255)   not_null
    public $description;                     // varchar(255)
    public $icon;                            // varchar(255)   not_null
    public $source_url;                      // varchar(255)
    public $organization;                    // varchar(255)
    public $homepage;                        // varchar(255)
    public $callback_url;                    // varchar(255)   not_null
    public $type;                            // tinyint(1)
    public $access_type;                     // tinyint(1)
    public $created;                         // datetime   not_null
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) {
    return Memcached_DataObject::staticGet('Oauth_application',$k,$v);
    }
    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    // Bit flags
    public static $readAccess  = 1;
    public static $writeAccess = 2;

    public static $browser = 1;
    public static $desktop = 2;

    function getConsumer()
    {
        return Consumer::staticGet('consumer_key', $this->consumer_key);
    }

    static function maxDesc()
    {
        // This used to default to textlimit or allow unlimited descriptions,
        // but this isn't part of a notice and the field's limited to 255 chars
        // in the DB, so those seem silly.
        //
        // Now just defaulting to 255 max unless a smaller application desclimit
        // is actually set. Setting to 0 will use the maximum.
        $max = 255;
        $desclimit = intval(common_config('application', 'desclimit'));
        if ($desclimit > 0 && $desclimit < $max) {
            return $desclimit;
        } else {
            return $max;
        }
    }

    static function descriptionTooLong($desc)
    {
        $desclimit = self::maxDesc();
        return ($desclimit > 0 && !empty($desc) && (mb_strlen($desc) > $desclimit));
    }

    function setAccessFlags($read, $write)
    {
        if ($read) {
            $this->access_type |= self::$readAccess;
        } else {
            $this->access_type &= ~self::$readAccess;
        }

        if ($write) {
            $this->access_type |= self::$writeAccess;
        } else {
            $this->access_type &= ~self::$writeAccess;
        }
    }

    function setOriginal($filename)
    {
        $imagefile = new ImageFile($this->id, Avatar::path($filename));

        // XXX: Do we want to have a bunch of different size icons? homepage, stream, mini?
        // or just one and control size via CSS? --Zach

        $orig = clone($this);
        $this->icon = Avatar::url($filename);
        common_debug(common_log_objstring($this));
        return $this->update($orig);
    }

    static function getByConsumerKey($key)
    {
        if (empty($key)) {
            return null;
        }

        $app = new Oauth_application();
        $app->consumer_key = $key;
        $app->limit(1);
        $result = $app->find(true);

        return empty($result) ? null : $app;
    }

    /**
     * Handle an image upload
     *
     * Does all the magic for handling an image upload, and crops the
     * image by default.
     *
     * @return void
     */
    function uploadLogo()
    {
        if ($_FILES['app_icon']['error'] ==
            UPLOAD_ERR_OK) {

            try {
                $imagefile = ImageFile::fromUpload('app_icon');
            } catch (Exception $e) {
                common_debug("damn that sucks");
                $this->showForm($e->getMessage());
                return;
            }

            $filename = Avatar::filename($this->id,
                                         image_type_to_extension($imagefile->type),
                                         null,
                                         'oauth-app-icon-'.common_timestamp());

            $filepath = Avatar::path($filename);

            move_uploaded_file($imagefile->filepath, $filepath);

            $this->setOriginal($filename);
        }
    }

    function delete()
    {
        $this->_deleteAppUsers();

        $consumer = $this->getConsumer();
        $consumer->delete();

        parent::delete();
    }

    function _deleteAppUsers()
    {
        $oauser = new Oauth_application_user();
        $oauser->application_id = $this->id;
        $oauser->delete();
    }
}
