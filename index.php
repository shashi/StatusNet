<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 * @category StatusNet
 * @package  StatusNet
 * @author   Brenda Wallace <shiny@cpan.org>
 * @author   Brion Vibber <brion@pobox.com>
 * @author   Christopher Vollick <psycotica0@gmail.com>
 * @author   CiaranG <ciaran@ciarang.com>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@controlezvous.ca>
 * @author   Gina Haeussge <osd@foosel.net>
 * @author   James Walker <walkah@walkah.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Mike Cochrane <mikec@mikenz.geek.nz>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @author   Tom Adams <tom@holizz.com>
 * @author   Zach Copley <zach@status.net>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 *
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 */

define('INSTALLDIR', dirname(__FILE__));
define('STATUSNET', true);
define('LACONICA', true); // compatibility

$user = null;
$action = null;

function getPath($req)
{
    if ((common_config('site', 'fancy') || !array_key_exists('PATH_INFO', $_SERVER))
        && array_key_exists('p', $req)
    ) {
        return $req['p'];
    } else if (array_key_exists('PATH_INFO', $_SERVER)) {
        $path = $_SERVER['PATH_INFO'];
        $script = $_SERVER['SCRIPT_NAME'];
        if (substr($path, 0, mb_strlen($script)) == $script) {
            return substr($path, mb_strlen($script));
        } else {
            return $path;
        }
    } else {
        return null;
    }
}

/**
 * logs and then displays error messages
 *
 * @return void
 */
function handleError($error)
{
    try {

        if ($error->getCode() == DB_DATAOBJECT_ERROR_NODATA) {
            return;
        }

        $logmsg = "PEAR error: " . $error->getMessage();
        if ($error instanceof PEAR_Exception && common_config('site', 'logdebug')) {
            $logmsg .= " : ". $error->toText();
        }
        // DB queries often end up with a lot of newlines; merge to a single line
        // for easier grepability...
        $logmsg = str_replace("\n", " ", $logmsg);
        common_log(LOG_ERR, $logmsg);

        // @fixme backtrace output should be consistent with exception handling
        if (common_config('site', 'logdebug')) {
            $bt = $error->getTrace();
            foreach ($bt as $n => $line) {
                common_log(LOG_ERR, formatBacktraceLine($n, $line));
            }
        }
        if ($error instanceof DB_DataObject_Error
            || $error instanceof DB_Error
            || ($error instanceof PEAR_Exception && $error->getCode() == -24)
        ) {
            //If we run into a DB error, assume we can't connect to the DB at all
            //so set the current user to null, so we don't try to access the DB
            //while rendering the error page.
            global $_cur;
            $_cur = null;

            $msg = sprintf(
                _(
                    'The database for %s isn\'t responding correctly, '.
                    'so the site won\'t work properly. '.
                    'The site admins probably know about the problem, '.
                    'but you can contact them at %s to make sure. '.
                    'Otherwise, wait a few minutes and try again.'
                ),
                common_config('site', 'name'),
                common_config('site', 'email')
            );
        } else {
            $msg = _(
                'An important error occured, probably related to email setup. '.
                'Check logfiles for more info..'
            );
        }

        $dac = new DBErrorAction($msg, 500);
        $dac->showPage();

    } catch (Exception $e) {
        echo _('An error occurred.');
    }
    exit(-1);
}

set_exception_handler('handleError');

require_once INSTALLDIR . '/lib/common.php';

/**
 * Format a backtrace line for debug output roughly like debug_print_backtrace() does.
 * Exceptions already have this built in, but PEAR error objects just give us the array.
 *
 * @param int $n line number
 * @param array $line per-frame array item from debug_backtrace()
 * @return string
 */
function formatBacktraceLine($n, $line)
{
    $out = "#$n ";
    if (isset($line['class'])) $out .= $line['class'];
    if (isset($line['type'])) $out .= $line['type'];
    if (isset($line['function'])) $out .= $line['function'];
    $out .= '(';
    if (isset($line['args'])) {
        $args = array();
        foreach ($line['args'] as $arg) {
            // debug_print_backtrace seems to use var_export
            // but this gets *very* verbose!
            $args[] = gettype($arg);
        }
        $out .= implode(',', $args);
    }
    $out .= ')';
    $out .= ' called at [';
    if (isset($line['file'])) $out .= $line['file'];
    if (isset($line['line'])) $out .= ':' . $line['line'];
    $out .= ']';
    return $out;
}

function setupRW()
{
    global $config;

    static $alwaysRW = array('session', 'remember_me');

    // We ensure that these tables always are used
    // on the master DB

    $config['db']['database_rw'] = $config['db']['database'];
    $config['db']['ini_rw'] = INSTALLDIR.'/classes/statusnet.ini';

    foreach ($alwaysRW as $table) {
        $config['db']['table_'.$table] = 'rw';
    }
}

function checkMirror($action_obj, $args)
{
    global $config;

    if (common_config('db', 'mirror') && $action_obj->isReadOnly($args)) {
        if (is_array(common_config('db', 'mirror'))) {
            // "load balancing", ha ha
            $arr = common_config('db', 'mirror');
            $k = array_rand($arr);
            $mirror = $arr[$k];
        } else {
            $mirror = common_config('db', 'mirror');
        }

        // everyone else uses the mirror

        $config['db']['database'] = $mirror;
    }
}

function isLoginAction($action)
{
    static $loginActions =  array('login', 'recoverpassword', 'api', 'doc', 'register', 'publicxrds', 'otp', 'opensearch', 'rsd', 'hostmeta');

    $login = null;

    if (Event::handle('LoginAction', array($action, &$login))) {
        $login = in_array($action, $loginActions);
    }

    return $login;
}

function main()
{
    // fake HTTP redirects using lighttpd's 404 redirects
    if (strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false) {
        $_lighty_url = $base_url.$_SERVER['REQUEST_URI'];
        $_lighty_url = @parse_url($_lighty_url);

        if ($_lighty_url['path'] != '/index.php' && $_lighty_url['path'] != '/') {
            $_lighty_path = preg_replace('/^'.preg_quote(common_config('site', 'path')).'\//', '', substr($_lighty_url['path'], 1));
            $_SERVER['QUERY_STRING'] = 'p='.$_lighty_path;
            if (isset($_lighty_url['query']) && $_lighty_url['query'] != '') {
                $_SERVER['QUERY_STRING'] .= '&'.$_lighty_url['query'];
                parse_str($_lighty_url['query'], $_lighty_query);
                foreach ($_lighty_query as $key => $val) {
                    $_GET[$key] = $_REQUEST[$key] = $val;
                }
            }
            $_GET['p'] = $_REQUEST['p'] = $_lighty_path;
        }
    }
    $_SERVER['REDIRECT_URL'] = preg_replace("/\?.+$/", "", $_SERVER['REQUEST_URI']);

    // quick check for fancy URL auto-detection support in installer.
    if (isset($_SERVER['REDIRECT_URL']) && (preg_replace("/^\/$/", "", (dirname($_SERVER['REQUEST_URI']))) . '/check-fancy') === $_SERVER['REDIRECT_URL']) {
        die("Fancy URL support detection succeeded. We suggest you enable this to get fancy (pretty) URLs.");
    }
    global $user, $action;

    Snapshot::check();

    if (!_have_config()) {
        $msg = sprintf(
            _(
                "No configuration file found. Try running ".
                "the installation program first."
            )
        );
        $sac = new ServerErrorAction($msg);
        $sac->showPage();
        return;
    }

    // Make sure RW database is setup

    setupRW();

    // XXX: we need a little more structure in this script

    // get and cache current user (may hit RW!)

    $user = common_current_user();

    // initialize language env

    common_init_language();

    $path = getPath($_REQUEST);

    $r = Router::get();

    $args = $r->map($path);

    if (!$args) {
        $cac = new ClientErrorAction(_('Unknown page'), 404);
        $cac->showPage();
        return;
    }

    $site_ssl = common_config('site', 'ssl');

    // If the request is HTTP and it should be HTTPS...
    if ($site_ssl != 'never' && !StatusNet::isHTTPS() && common_is_sensitive($args['action'])) {
        common_redirect(common_local_url($args['action'], $args));
        return;
    }

    $args = array_merge($args, $_REQUEST);

    Event::handle('ArgsInitialize', array(&$args));

    $action = $args['action'];

    if (!$action || !preg_match('/^[a-zA-Z0-9_-]*$/', $action)) {
        common_redirect(common_local_url('public'));
        return;
    }

    // If the site is private, and they're not on one of the "public"
    // parts of the site, redirect to login

    if (!$user && common_config('site', 'private')
        && !isLoginAction($action)
        && !preg_match('/rss$/', $action)
        && $action != 'robotstxt'
        && !preg_match('/^Api/', $action)) {

        // set returnto
        $rargs =& common_copy_args($args);
        unset($rargs['action']);
        if (common_config('site', 'fancy')) {
            unset($rargs['p']);
        }
        if (array_key_exists('submit', $rargs)) {
            unset($rargs['submit']);
        }
        foreach (array_keys($_COOKIE) as $cookie) {
            unset($rargs[$cookie]);
        }
        common_set_returnto(common_local_url($action, $rargs));

        common_redirect(common_local_url('login'));
        return;
    }

    $action_class = ucfirst($action).'Action';

    if (!class_exists($action_class)) {
        $cac = new ClientErrorAction(_('Unknown action'), 404);
        $cac->showPage();
    } else {
        $action_obj = new $action_class();

        checkMirror($action_obj, $args);

        try {
            if ($action_obj->prepare($args)) {
                $action_obj->handle($args);
            }
        } catch (ClientException $cex) {
            $cac = new ClientErrorAction($cex->getMessage(), $cex->getCode());
            $cac->showPage();
        } catch (ServerException $sex) { // snort snort guffaw
            $sac = new ServerErrorAction($sex->getMessage(), $sex->getCode(), $sex);
            $sac->showPage();
        } catch (Exception $ex) {
            $sac = new ServerErrorAction($ex->getMessage(), 500, $ex);
            $sac->showPage();
        }
    }
}

main();

// XXX: cleanup exit() calls or add an exit handler so
// this always gets called

Event::handle('CleanupPlugin');
