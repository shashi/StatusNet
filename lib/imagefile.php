<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Abstraction for an image file
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
 * @category  Image
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * A wrapper on uploaded files
 *
 * Makes it slightly easier to accept an image file from upload.
 *
 * @category Image
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ImageFile
{
    var $id;
    var $filepath;
    var $barename;
    var $type;
    var $height;
    var $width;

    function __construct($id=null, $filepath=null, $type=null, $width=null, $height=null)
    {
        $this->id = $id;
        $this->filepath = $filepath;

        $info = @getimagesize($this->filepath);

        if (!(
            ($info[2] == IMAGETYPE_GIF && function_exists('imagecreatefromgif')) ||
            ($info[2] == IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) ||
            $info[2] == IMAGETYPE_BMP ||
            ($info[2] == IMAGETYPE_WBMP && function_exists('imagecreatefromwbmp')) ||
            ($info[2] == IMAGETYPE_XBM && function_exists('imagecreatefromxbm')) ||
            ($info[2] == IMAGETYPE_PNG && function_exists('imagecreatefrompng')))) {

            throw new Exception(_('Unsupported image file format.'));
            return;
        }

        $this->type = ($info) ? $info[2]:$type;
        $this->width = ($info) ? $info[0]:$width;
        $this->height = ($info) ? $info[1]:$height;
    }

    static function fromUpload($param='upload')
    {
        switch ($_FILES[$param]['error']) {
         case UPLOAD_ERR_OK: // success, jump out
            break;
         case UPLOAD_ERR_INI_SIZE:
         case UPLOAD_ERR_FORM_SIZE:
            // TRANS: Exception thrown when too large a file is uploaded.
            // TRANS: %s is the maximum file size, for example "500b", "10kB" or "2MB".
            throw new Exception(sprintf(_('That file is too big. The maximum file size is %s.'),
                ImageFile::maxFileSize()));
            return;
         case UPLOAD_ERR_PARTIAL:
            @unlink($_FILES[$param]['tmp_name']);
            throw new Exception(_('Partial upload.'));
            return;
         case UPLOAD_ERR_NO_FILE:
            // No file; probably just a non-AJAX submission.
            return;
         default:
            common_log(LOG_ERR, __METHOD__ . ": Unknown upload error " .
                $_FILES[$param]['error']);
            throw new Exception(_('System error uploading file.'));
            return;
        }

        $info = @getimagesize($_FILES[$param]['tmp_name']);

        if (!$info) {
            @unlink($_FILES[$param]['tmp_name']);
            throw new Exception(_('Not an image or corrupt file.'));
            return;
        }

        return new ImageFile(null, $_FILES[$param]['tmp_name']);
    }

    /**
     * Compat interface for old code generating avatar thumbnails...
     * Saves the scaled file directly into the avatar area.
     *
     * @param int $size target width & height -- must be square
     * @param int $x (default 0) upper-left corner to crop from
     * @param int $y (default 0) upper-left corner to crop from
     * @param int $w (default full) width of image area to crop
     * @param int $h (default full) height of image area to crop
     * @return string filename
     */
    function resize($size, $x = 0, $y = 0, $w = null, $h = null)
    {
        $targetType = $this->preferredType($this->type);
        $outname = Avatar::filename($this->id,
                                    image_type_to_extension($targetType),
                                    $size,
                                    common_timestamp());
        $outpath = Avatar::path($outname);
        $this->resizeTo($outpath, $size, $size, $x, $y, $w, $h);
        return $outname;
    }

    /**
     * Create and save a thumbnail image.
     *
     * @param string $outpath
     * @param int $width target width
     * @param int $height target height
     * @param int $x (default 0) upper-left corner to crop from
     * @param int $y (default 0) upper-left corner to crop from
     * @param int $w (default full) width of image area to crop
     * @param int $h (default full) height of image area to crop
     * @return string full local filesystem filename
     */
    function resizeTo($outpath, $width, $height, $x=0, $y=0, $w=null, $h=null)
    {
        $w = ($w === null) ? $this->width:$w;
        $h = ($h === null) ? $this->height:$h;
        $targetType = $this->preferredType($this->type);

        if (!file_exists($this->filepath)) {
            throw new Exception(_('Lost our file.'));
            return;
        }

        // Don't crop/scale if it isn't necessary
        if ($width === $this->width
            && $height === $this->height
            && $x === 0
            && $y === 0
            && $w === $this->width
            && $h === $this->height
            && $this->type == $targetType) {

            @copy($this->filepath, $outpath);
            return $outpath;
        }

        switch ($this->type) {
         case IMAGETYPE_GIF:
            $image_src = imagecreatefromgif($this->filepath);
            break;
         case IMAGETYPE_JPEG:
            $image_src = imagecreatefromjpeg($this->filepath);
            break;
         case IMAGETYPE_PNG:
            $image_src = imagecreatefrompng($this->filepath);
            break;
         case IMAGETYPE_BMP:
            $image_src = imagecreatefrombmp($this->filepath);
            break;
         case IMAGETYPE_WBMP:
            $image_src = imagecreatefromwbmp($this->filepath);
            break;
         case IMAGETYPE_XBM:
            $image_src = imagecreatefromxbm($this->filepath);
            break;
         default:
            throw new Exception(_('Unknown file type'));
            return;
        }

        $image_dest = imagecreatetruecolor($width, $height);

        if ($this->type == IMAGETYPE_GIF || $this->type == IMAGETYPE_PNG || $this->type == IMAGETYPE_BMP) {

            $transparent_idx = imagecolortransparent($image_src);

            if ($transparent_idx >= 0) {

                $transparent_color = imagecolorsforindex($image_src, $transparent_idx);
                $transparent_idx = imagecolorallocate($image_dest, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                imagefill($image_dest, 0, 0, $transparent_idx);
                imagecolortransparent($image_dest, $transparent_idx);

            } elseif ($this->type == IMAGETYPE_PNG) {

                imagealphablending($image_dest, false);
                $transparent = imagecolorallocatealpha($image_dest, 0, 0, 0, 127);
                imagefill($image_dest, 0, 0, $transparent);
                imagesavealpha($image_dest, true);

            }
        }

        imagecopyresampled($image_dest, $image_src, 0, 0, $x, $y, $width, $height, $w, $h);

        switch ($targetType) {
         case IMAGETYPE_GIF:
            imagegif($image_dest, $outpath);
            break;
         case IMAGETYPE_JPEG:
            imagejpeg($image_dest, $outpath, 100);
            break;
         case IMAGETYPE_PNG:
            imagepng($image_dest, $outpath);
            break;
         default:
            throw new Exception(_('Unknown file type'));
            return;
        }

        imagedestroy($image_src);
        imagedestroy($image_dest);

        return $outpath;
    }

    /**
     * Several obscure file types should be normalized to PNG on resize.
     *
     * @param int $type
     * @return int
     */
    function preferredType($type)
    {
        if($type == IMAGETYPE_BMP) {
            //we don't want to save BMP... it's an inefficient, rare, antiquated format
            //save png instead
            return IMAGETYPE_PNG;
        } else if($type == IMAGETYPE_WBMP) {
            //we don't want to save WBMP... it's a rare format that we can't guarantee clients will support
            //save png instead
            return IMAGETYPE_PNG;
        } else if($type == IMAGETYPE_XBM) {
            //we don't want to save XBM... it's a rare format that we can't guarantee clients will support
            //save png instead
            return IMAGETYPE_PNG;
        }
        return $type;
    }

    function unlink()
    {
        @unlink($this->filename);
    }

    static function maxFileSize()
    {
        $value = ImageFile::maxFileSizeInt();

        if ($value > 1024 * 1024) {
            $value = $value/(1024*1024);
            // TRANS: Number of megabytes. %d is the number.
            return sprintf(_m('%dMB','%dMB',$value),$value);
        } else if ($value > 1024) {
            $value = $value/1024;
            // TRANS: Number of kilobytes. %d is the number.
            return sprintf(_m('%dkB','%dkB',$value),$value);
        } else {
            // TRANS: Number of bytes. %d is the number.
            return sprintf(_m('%dB','%dB',$value),$value);
        }
    }

    static function maxFileSizeInt()
    {
        return min(ImageFile::strToInt(ini_get('post_max_size')),
                   ImageFile::strToInt(ini_get('upload_max_filesize')),
                   ImageFile::strToInt(ini_get('memory_limit')));
    }

    static function strToInt($str)
    {
        $unit = substr($str, -1);
        $num = substr($str, 0, -1);

        switch(strtoupper($unit)){
         case 'G':
            $num *= 1024;
         case 'M':
            $num *= 1024;
         case 'K':
            $num *= 1024;
        }

        return $num;
    }
}

//PHP doesn't (as of 2/24/2010) have an imagecreatefrombmp so conditionally define one
if(!function_exists('imagecreatefrombmp')){
    //taken shamelessly from http://www.php.net/manual/en/function.imagecreatefromwbmp.php#86214
    function imagecreatefrombmp($p_sFile)
    {
        //    Load the image into a string
        $file    =    fopen($p_sFile,"rb");
        $read    =    fread($file,10);
        while(!feof($file)&&($read<>""))
            $read    .=    fread($file,1024);

        $temp    =    unpack("H*",$read);
        $hex    =    $temp[1];
        $header    =    substr($hex,0,108);

        //    Process the header
        //    Structure: http://www.fastgraph.com/help/bmp_header_format.html
        if (substr($header,0,4)=="424d")
        {
            //    Cut it in parts of 2 bytes
            $header_parts    =    str_split($header,2);

            //    Get the width        4 bytes
            $width            =    hexdec($header_parts[19].$header_parts[18]);

            //    Get the height        4 bytes
            $height            =    hexdec($header_parts[23].$header_parts[22]);

            //    Unset the header params
            unset($header_parts);
        }

        //    Define starting X and Y
        $x                =    0;
        $y                =    1;

        //    Create newimage
        $image            =    imagecreatetruecolor($width,$height);

        //    Grab the body from the image
        $body            =    substr($hex,108);

        //    Calculate if padding at the end-line is needed
        //    Divided by two to keep overview.
        //    1 byte = 2 HEX-chars
        $body_size        =    (strlen($body)/2);
        $header_size    =    ($width*$height);

        //    Use end-line padding? Only when needed
        $usePadding        =    ($body_size>($header_size*3)+4);

        //    Using a for-loop with index-calculation instaid of str_split to avoid large memory consumption
        //    Calculate the next DWORD-position in the body
        for ($i=0;$i<$body_size;$i+=3)
        {
            //    Calculate line-ending and padding
            if ($x>=$width)
            {
                //    If padding needed, ignore image-padding
                //    Shift i to the ending of the current 32-bit-block
                if ($usePadding)
                    $i    +=    $width%4;

                //    Reset horizontal position
                $x    =    0;

                //    Raise the height-position (bottom-up)
                $y++;

                //    Reached the image-height? Break the for-loop
                if ($y>$height)
                    break;
            }

            //    Calculation of the RGB-pixel (defined as BGR in image-data)
            //    Define $i_pos as absolute position in the body
            $i_pos    =    $i*2;
            $r        =    hexdec($body[$i_pos+4].$body[$i_pos+5]);
            $g        =    hexdec($body[$i_pos+2].$body[$i_pos+3]);
            $b        =    hexdec($body[$i_pos].$body[$i_pos+1]);

            //    Calculate and draw the pixel
            $color    =    imagecolorallocate($image,$r,$g,$b);
            imagesetpixel($image,$x,$height-$y,$color);

            //    Raise the horizontal position
            $x++;
        }

        //    Unset the body / free the memory
        unset($body);

        //    Return image-object
        return $image;
    }
}
