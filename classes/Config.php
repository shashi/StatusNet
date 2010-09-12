<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Table Definition for config
 */

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Config extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'config';                          // table name
    public $section;                         // varchar(32)  primary_key not_null
    public $setting;                         // varchar(32)  primary_key not_null
    public $value;                           // varchar(255)

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Config',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    const settingsKey = 'config:settings';

    static function loadSettings()
    {
        $settings = self::_getSettings();
        if (!empty($settings)) {
            self::_applySettings($settings);
        }
    }

    static function _getSettings()
    {
        $c = self::memcache();

        if (!empty($c)) {
            $settings = $c->get(Cache::key(self::settingsKey));
            if ($settings !== false) {
                return $settings;
            }
        }

        $settings = array();

        $config = new Config();

        $config->find();

        while ($config->fetch()) {
            $settings[] = array($config->section, $config->setting, $config->value);
        }

        $config->free();

        if (!empty($c)) {
            $c->set(Cache::key(self::settingsKey), $settings);
        }

        return $settings;
    }

    static function _applySettings($settings)
    {
        global $config;

        foreach ($settings as $s) {
            list($section, $setting, $value) = $s;
            $config[$section][$setting] = $value;
        }
    }

    function insert()
    {
        $result = parent::insert();
        if ($result) {
            Config::_blowSettingsCache();
        }
        return $result;
    }

    function delete()
    {
        $result = parent::delete();
        if ($result) {
            Config::_blowSettingsCache();
        }
        return $result;
    }

    function update($orig=null)
    {
        $result = parent::update($orig);
        if ($result) {
            Config::_blowSettingsCache();
        }
        return $result;
    }

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Config', $kv);
    }

    static function save($section, $setting, $value)
    {
        $result = null;

        $config = Config::pkeyGet(array('section' => $section,
                                        'setting' => $setting));

        if (!empty($config)) {
            $orig = clone($config);
            $config->value = $value;
            $result = $config->update($orig);
        } else {
            $config = new Config();

            $config->section = $section;
            $config->setting = $setting;
            $config->value   = $value;

            $result = $config->insert();
        }

        return $result;
    }

    function _blowSettingsCache()
    {
        $c = self::memcache();

        if (!empty($c)) {
            $c->delete(Cache::key(self::settingsKey));
        }
    }
}
