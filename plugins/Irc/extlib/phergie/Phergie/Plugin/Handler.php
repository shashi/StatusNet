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
 * Handles on-demand loading of, iteration over, and access to plugins.
 *
 * @category Phergie
 * @package  Phergie
 * @author   Phergie Development Team <team@phergie.org>
 * @license  http://phergie.org/license New BSD License
 * @link     http://pear.phergie.org/package/Phergie
 */
class Phergie_Plugin_Handler implements IteratorAggregate, Countable
{
    /**
     * Current list of plugin instances
     *
     * @var array
     */
    protected $plugins;

    /**
     * Paths in which to search for plugin class files
     *
     * @var array
     */
    protected $paths;

    /**
     * Flag indicating whether plugin classes should be instantiated on
     * demand if they are requested but no instance currently exists
     *
     * @var bool
     */
    protected $autoload;

    /**
     * Phergie_Config instance that should be passed in to any plugin
     * instantiated within the handler
     *
     * @var Phergie_Config
     */
    protected $config;

    /**
     * Phergie_Event_Handler instance that should be passed in to any plugin
     * instantiated within the handler
     *
     * @var Phergie_Event_Handler
     */
    protected $events;

    /**
     * Name of the class to use for iterating over all currently loaded
     * plugins
     *
     * @var string
     */
    protected $iteratorClass = 'Phergie_Plugin_Iterator';

    /**
     * Constructor to initialize class properties and add the path for core
     * plugins.
     *
     * @param Phergie_Config        $config configuration to pass to any
     *        instantiated plugin
     * @param Phergie_Event_Handler $events event handler to pass to any
     *        instantiated plugin
     *
     * @return void
     */
    public function __construct(
        Phergie_Config $config,
        Phergie_Event_Handler $events
    ) {
        $this->config = $config;
        $this->events = $events;

        $this->plugins = array();
        $this->paths = array();
        $this->autoload = false;

        if (!empty($config['plugins.paths'])) {
            foreach ($config['plugins.paths'] as $dir => $prefix) {
                $this->addPath($dir, $prefix);
            }
        }

        $this->addPath(dirname(__FILE__), 'Phergie_Plugin_');
    }


    /**
     * Adds a path to search for plugin class files. Paths are searched in
     * the reverse order in which they are added.
     *
     * @param string $path   Filesystem directory path
     * @param string $prefix Optional class name prefix corresponding to the
     *        path
     *
     * @return Phergie_Plugin_Handler Provides a fluent interface
     * @throws Phergie_Plugin_Exception
     */
    public function addPath($path, $prefix = '')
    {
        if (!is_readable($path)) {
            throw new Phergie_Plugin_Exception(
                'Path "' . $path . '" does not reference a readable directory',
                Phergie_Plugin_Exception::ERR_DIRECTORY_NOT_READABLE
            );
        }

        $this->paths[] = array(
            'path' => rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
            'prefix' => $prefix
        );

        return $this;
    }

    /**
     * Returns metadata corresponding to a specified plugin.
     *
     * @param string $plugin Short name of the plugin class
     *
     * @throws Phergie_Plugin_Exception Class file can't be found
     *
     * @return array|boolean Associative array containing the path to the
     *         class file and its containing directory as well as the full
     *         class name
     */
    public function getPluginInfo($plugin)
    {
        foreach (array_reverse($this->paths) as $path) {
            $file = $path['path'] . $plugin . '.php';
            if (file_exists($file)) {
                $path = array(
                    'dir' => $path['path'],
                    'file' => $file,
                    'class' => $path['prefix'] . $plugin,
                );
                return $path;
            }
        }

        // If the class can't be found, display an error
        throw new Phergie_Plugin_Exception(
            'Class file for plugin "' . $plugin . '" cannot be found',
            Phergie_Plugin_Exception::ERR_CLASS_NOT_FOUND
        );
    }

    /**
     * Adds a plugin instance to the handler.
     *
     * @param string|Phergie_Plugin_Abstract $plugin Short name of the
     *        plugin class or a plugin object
     * @param array                          $args   Optional array of
     *        arguments to pass to the plugin constructor if a short name is
     *        passed for $plugin
     *
     * @return Phergie_Plugin_Abstract New plugin instance
     */
    public function addPlugin($plugin, array $args = null)
    {
        // If a short plugin name is specified...
        if (is_string($plugin)) {
            $index = strtolower($plugin);
            if (isset($this->plugins[$index])) {
                return $this->plugins[$index];
            }

            // Attempt to locate and load the class
            $info = $this->getPluginInfo($plugin);
            $file = $info['file'];
            $class = $info['class'];
            include_once $file;
            if (!class_exists($class, false)) {
                throw new Phergie_Plugin_Exception(
                    'File "' . $file . '" does not contain class "' . $class . '"',
                    Phergie_Plugin_Exception::ERR_CLASS_NOT_FOUND
                );
            }

            // Check to ensure the class is a plugin class
            if (!is_subclass_of($class, 'Phergie_Plugin_Abstract')) {
                $msg
                    = 'Class for plugin "' . $plugin .
                    '" does not extend Phergie_Plugin_Abstract';
                throw new Phergie_Plugin_Exception(
                    $msg,
                    Phergie_Plugin_Exception::ERR_INCORRECT_BASE_CLASS
                );
            }

            // Check to ensure the class can be instantiated
            $reflection = new ReflectionClass($class);
            if (!$reflection->isInstantiable()) {
                throw new Phergie_Plugin_Exception(
                    'Class for plugin "' . $plugin . '" cannot be instantiated',
                    Phergie_Plugin_Exception::ERR_CLASS_NOT_INSTANTIABLE
                );
            }

            // If the class is found, instantiate it
            if (!empty($args)) {
                $instance = $reflection->newInstanceArgs($args);
            } else {
                $instance = new $class;
            }

            // Store the instance
            $this->plugins[$index] = $instance;
            $plugin = $instance;

        } elseif ($plugin instanceof Phergie_Plugin_Abstract) {
            // If a plugin instance is specified...

            // Add the plugin instance to the list of plugins
            $this->plugins[strtolower($plugin->getName())] = $plugin;
        }

        // Configure and initialize the instance
        $plugin->setPluginHandler($this);
        $plugin->setConfig($this->config);
        $plugin->setEventHandler($this->events);
        $plugin->onLoad();

        return $plugin;
    }

    /**
     * Adds multiple plugin instances to the handler.
     *
     * @param array $plugins List of elements where each is of the form
     *        'ShortPluginName' or array('ShortPluginName', array($arg1,
     *        ..., $argN))
     *
     * @return Phergie_Plugin_Handler Provides a fluent interface
     */
    public function addPlugins(array $plugins)
    {
        foreach ($plugins as $plugin) {
            if (is_array($plugin)) {
                $this->addPlugin($plugin[0], $plugin[1]);
            } else {
                $this->addPlugin($plugin);
            }
        }

        return $this;
    }

    /**
     * Removes a plugin instance from the handler.
     *
     * @param string|Phergie_Plugin_Abstract $plugin Short name of the
     *        plugin class or a plugin object
     *
     * @return Phergie_Plugin_Handler Provides a fluent interface
     */
    public function removePlugin($plugin)
    {
        if ($plugin instanceof Phergie_Plugin_Abstract) {
            $plugin = $plugin->getName();
        }
        $plugin = strtolower($plugin);

        unset($this->plugins[$plugin]);

        return $this;
    }

    /**
     * Returns the corresponding instance for a specified plugin, loading it
     * if it is not already loaded and autoloading is enabled.
     *
     * @param string $name Short name of the plugin class
     *
     * @return Phergie_Plugin_Abstract Plugin instance
     */
    public function getPlugin($name)
    {
        // If the plugin is loaded, return the instance
        $lower = strtolower($name);
        if (isset($this->plugins[$lower])) {
            return $this->plugins[$lower];
        }

        // If autoloading is disabled, display an error
        if (!$this->autoload) {
            $msg
                = 'Plugin "' . $name . '" has been requested, ' .
                'is not loaded, and autoload is disabled';
            throw new Phergie_Plugin_Exception(
                $msg,
                Phergie_Plugin_Exception::ERR_PLUGIN_NOT_LOADED
            );
        }

        // If autoloading is enabled, attempt to load the plugin
        $plugin = $this->addPlugin($name);

        // Return the added plugin
        return $plugin;
    }

    /**
     * Returns the corresponding instances for multiple specified plugins,
     * loading them if they are not already loaded and autoloading is
     * enabled.
     *
     * @param array $names Optional list of short names of the plugin
     *        classes to which the returned plugin list will be limited,
     *        defaults to all presently loaded plugins
     *
     * @return array Associative array mapping lowercased plugin class short
     *         names to corresponding plugin instances
     */
    public function getPlugins(array $names = array())
    {
        if (empty($names)) {
            return $this->plugins;
        }

        $plugins = array();
        foreach ($names as $name) {
            $plugins[strtolower($name)] = $this->getPlugin($name);
        }
        return $plugins;
    }

    /**
     * Returns whether or not at least one instance of a specified plugin
     * class is loaded.
     *
     * @param string $name Short name of the plugin class
     *
     * @return bool TRUE if an instance exists, FALSE otherwise
     */
    public function hasPlugin($name)
    {
        return isset($this->plugins[strtolower($name)]);
    }

    /**
     * Sets a flag used to determine whether plugins should be loaded
     * automatically if they have not been explicitly loaded.
     *
     * @param bool $flag TRUE to have plugins autoload (default), FALSE
     *        otherwise
     *
     * @return Phergie_Plugin_Handler Provides a fluent interface.
     */
    public function setAutoload($flag = true)
    {
        $this->autoload = $flag;

        return $this;
    }

    /**
     * Returns the value of a flag used to determine whether plugins should
     * be loaded automatically if they have not been explicitly loaded.
     *
     * @return bool TRUE if autoloading is enabled, FALSE otherwise
     */
    public function getAutoload()
    {
        return $this->autoload;
    }

    /**
     * Allows plugin instances to be accessed as properties of the handler.
     *
     * @param string $name Short name of the plugin
     *
     * @return Phergie_Plugin_Abstract Requested plugin instance
     */
    public function __get($name)
    {
        return $this->getPlugin($name);
    }

    /**
     * Allows plugin instances to be detected as properties of the handler.
     *
     * @param string $name Short name of the plugin
     *
     * @return bool TRUE if the plugin is loaded, FALSE otherwise
     */
    public function __isset($name)
    {
        return $this->hasPlugin($name);
    }

    /**
     * Allows plugin instances to be removed as properties of handler.
     *
     * @param string $name Short name of the plugin
     *
     * @return void
     */
    public function __unset($name)
    {
        $this->removePlugin($name);
    }

    /**
     * Returns an iterator for all currently loaded plugin instances.
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new $this->iteratorClass(
            new ArrayIterator($this->plugins)
        );
    }

    /**
     * Sets the iterator class used for all currently loaded plugin
     * instances.
     *
     * @param string $class Name of a class that extends FilterIterator
     *
     * @return Phergie_Plugin_Handler Provides a fluent API
     * @throws Phergie_Plugin_Exception Class cannot be found or is not an
     *         FilterIterator-based class
     */
    public function setIteratorClass($class)
    {
        $valid = true;

        try {
            $error_reporting = error_reporting(0); // ignore autoloader errors
            $r = new ReflectionClass($class);
            error_reporting($error_reporting);
            if (!$r->isSubclassOf('FilterIterator')) {
                $message = 'Class ' . $class . ' is not a subclass of FilterIterator';
                $valid = false;
            }
        } catch (ReflectionException $e) {
            $message = $e->getMessage();
            $valid = false;
        }

        if (!$valid) {
            throw new Phergie_Plugin_Exception(
                $message,
                Phergie_Plugin_Exception::ERR_INVALID_ITERATOR_CLASS
            );
        }

        $this->iteratorClass = $class;
    }

    /**
     * Proxies method calls to all plugins containing the called method.
     *
     * @param string $name Name of the method called
     * @param array  $args Arguments passed in the method call
     *
     * @return void
     */
    public function __call($name, array $args)
    {
        foreach ($this->getIterator() as $plugin) {
            call_user_func_array(array($plugin, $name), $args);
        }
        return true;
    }

    /**
     * Returns the number of plugins contained within the handler.
     *
     * @return int Plugin count
     */
    public function count()
    {
        return count($this->plugins);
    }
}
