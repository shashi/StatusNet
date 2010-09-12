<?php
/**
 * Phergie
 *
 * PHP version 5
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://phergie.org/license
 *
 * @category  Phergie
 * @package   Phergie
 * @author    Phergie Development Team <team@phergie.org>
 * @copyright 2008-2010 Phergie Development Team (http://phergie.org)
 * @license   http://phergie.org/license New BSD License
 * @link      http://pear.phergie.org/package/Phergie
 */

/**
 * Autoloader for Phergie classes.
 *
 * @category Phergie
 * @package  Phergie
 * @author   Phergie Development Team <team@phergie.org>
 * @license  http://phergie.org/license New BSD License
 * @link     http://pear.phergie.org/package/Phergie
 */
class Phergie_Autoload
{
    /**
     * Constructor to add the base Phergie path to the include_path.
     *
     * @return void
     */
    public function __construct()
    {
        $path = realpath(dirname(__FILE__) . '/..');
        $includePath = get_include_path();
        $includePathList = explode(PATH_SEPARATOR, $includePath);
        if (!in_array($path, $includePathList)) {
            self::addPath($path);
        }
    }

    /**
     * Autoload callback for loading class files.
     *
     * @param string $class Class to load
     *
     * @return void
     */
    public function load($class)
    {
        include str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    }

    /**
     * Registers an instance of this class as an autoloader.
     *
     * @return void
     */
    public static function registerAutoloader()
    {
        spl_autoload_register(array(new self, 'load'));
    }

    /**
     * Add a path to the include path.
     *
     * @param string $path Path to add
     *
     * @return void
     */
    public static function addPath($path)
    {
        set_include_path($path . PATH_SEPARATOR . get_include_path());
    }
}
