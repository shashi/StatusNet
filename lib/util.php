<?php
/*
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
 */

/* XXX: break up into separate modules (HTTP, user, files) */

// Show a server error

function common_server_error($msg, $code=500)
{
    $err = new ServerErrorAction($msg, $code);
    $err->showPage();
}

// Show a user error
function common_user_error($msg, $code=400)
{
    $err = new ClientErrorAction($msg, $code);
    $err->showPage();
}

/**
 * This should only be used at setup; processes switching languages
 * to send text to other users should use common_switch_locale().
 * 
 * @param string $language Locale language code (optional; empty uses
 *                         current user's preference or site default)
 * @return mixed success
 */
function common_init_locale($language=null)
{
    if(!$language) {
        $language = common_language();
    }
    putenv('LANGUAGE='.$language);
    putenv('LANG='.$language);
    $ok =  setlocale(LC_ALL, $language . ".utf8",
                     $language . ".UTF8",
                     $language . ".utf-8",
                     $language . ".UTF-8",
                     $language);

    return $ok;
}

/**
 * Initialize locale and charset settings and gettext with our message catalog,
 * using the current user's language preference or the site default.
 * 
 * This should generally only be run at framework initialization; code switching
 * languages at runtime should call common_switch_language().
 * 
 * @access private
 */
function common_init_language()
{
    mb_internal_encoding('UTF-8');

    // Note that this setlocale() call may "fail" but this is harmless;
    // gettext will still select the right language.
    $language = common_language();
    $locale_set = common_init_locale($language);

    if (!$locale_set) {
        // The requested locale doesn't exist on the system.
        //
        // gettext seems very picky... We first need to setlocale()
        // to a locale which _does_ exist on the system, and _then_
        // we can set in another locale that may not be set up
        // (say, ga_ES for Galego/Galician) it seems to take it.
        //
        // For some reason C and POSIX which are guaranteed to work
        // don't do the job. en_US.UTF-8 should be there most of the
        // time, but not guaranteed.
        $ok = common_init_locale("en_US");
        if (!$ok) {
            // Try to find a complete, working locale...
            // @fixme shelling out feels awfully inefficient
            // but I don't think there's a more standard way.
            $all = `locale -a`;
            foreach (explode("\n", $all) as $locale) {
                if (preg_match('/\.utf[-_]?8$/i', $locale)) {
                    $ok = setlocale(LC_ALL, $locale);
                    if ($ok) {
                        break;
                    }
                }
            }
            if (!$ok) {
                common_log(LOG_ERR, "Unable to find a UTF-8 locale on this system; UI translations may not work.");
            }
        }
        $locale_set = common_init_locale($language);
    }

    common_init_gettext();
}

/**
 * @access private
 */
function common_init_gettext()
{
    setlocale(LC_CTYPE, 'C');
    // So we do not have to make people install the gettext locales
    $path = common_config('site','locale_path');
    bindtextdomain("statusnet", $path);
    bind_textdomain_codeset("statusnet", "UTF-8");
    textdomain("statusnet");
}

/**
 * Switch locale during runtime, and poke gettext until it cries uncle.
 * Otherwise, sometimes it doesn't actually switch away from the old language.
 *
 * @param string $language code for locale ('en', 'fr', 'pt_BR' etc)
 */
function common_switch_locale($language=null)
{
    common_init_locale($language);

    setlocale(LC_CTYPE, 'C');
    // So we do not have to make people install the gettext locales
    $path = common_config('site','locale_path');
    bindtextdomain("statusnet", $path);
    bind_textdomain_codeset("statusnet", "UTF-8");
    textdomain("statusnet");
}


function common_timezone()
{
    if (common_logged_in()) {
        $user = common_current_user();
        if ($user->timezone) {
            return $user->timezone;
        }
    }

    return common_config('site', 'timezone');
}

function common_valid_language($lang)
{
    if ($lang) {
        // Validate -- we don't want to end up with a bogus code
        // left over from some old junk.
        foreach (common_config('site', 'languages') as $code => $info) {
            if ($info['lang'] == $lang) {
                return true;
            }
        }
    }
    return false;
}

function common_language()
{
    // Allow ?uselang=xx override, very useful for debugging
    // and helping translators check usage and context.
    if (isset($_GET['uselang'])) {
        $uselang = strval($_GET['uselang']);
        if (common_valid_language($uselang)) {
            return $uselang;
        }
    }

    // If there is a user logged in and they've set a language preference
    // then return that one...
    if (_have_config() && common_logged_in()) {
        $user = common_current_user();

        if (common_valid_language($user->language)) {
            return $user->language;
        }
    }

    // Otherwise, find the best match for the languages requested by the
    // user's browser...
    if (common_config('site', 'langdetect')) {
        $httplang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null;
        if (!empty($httplang)) {
            $language = client_prefered_language($httplang);
            if ($language)
              return $language;
        }
    }

    // Finally, if none of the above worked, use the site's default...
    return common_config('site', 'language');
}
// salted, hashed passwords are stored in the DB

function common_munge_password($password, $id)
{
    if (is_object($id) || is_object($password)) {
        $e = new Exception();
        common_log(LOG_ERR, __METHOD__ . ' object in param to common_munge_password ' .
                   str_replace("\n", " ", $e->getTraceAsString()));
    }
    return md5($password . $id);
}

// check if a username exists and has matching password

function common_check_user($nickname, $password)
{
    // empty nickname always unacceptable
    if (empty($nickname)) {
        return false;
    }

    $authenticatedUser = false;

    if (Event::handle('StartCheckPassword', array($nickname, $password, &$authenticatedUser))) {
        $user = User::staticGet('nickname', common_canonical_nickname($nickname));
        if (!empty($user)) {
            if (!empty($password)) { // never allow login with blank password
                if (0 == strcmp(common_munge_password($password, $user->id),
                                $user->password)) {
                    //internal checking passed
                    $authenticatedUser = $user;
                }
            }
        }
        Event::handle('EndCheckPassword', array($nickname, $password, $authenticatedUser));
    }

    return $authenticatedUser;
}

// is the current user logged in?
function common_logged_in()
{
    return (!is_null(common_current_user()));
}

function common_have_session()
{
    return (0 != strcmp(session_id(), ''));
}

function common_ensure_session()
{
    $c = null;
    if (array_key_exists(session_name(), $_COOKIE)) {
        $c = $_COOKIE[session_name()];
    }
    if (!common_have_session()) {
        if (common_config('sessions', 'handle')) {
            Session::setSaveHandler();
        }
	if (array_key_exists(session_name(), $_GET)) {
	    $id = $_GET[session_name()];
	} else if (array_key_exists(session_name(), $_COOKIE)) {
	    $id = $_COOKIE[session_name()];
	}
	if (isset($id)) {
	    session_id($id);
	}
        @session_start();
        if (!isset($_SESSION['started'])) {
            $_SESSION['started'] = time();
            if (!empty($id)) {
                common_log(LOG_WARNING, 'Session cookie "' . $_COOKIE[session_name()] . '" ' .
                           ' is set but started value is null');
            }
        }
    }
}

// Three kinds of arguments:
// 1) a user object
// 2) a nickname
// 3) null to clear

// Initialize to false; set to null if none found

$_cur = false;

function common_set_user($user)
{

    global $_cur;

    if (is_null($user) && common_have_session()) {
        $_cur = null;
        unset($_SESSION['userid']);
        return true;
    } else if (is_string($user)) {
        $nickname = $user;
        $user = User::staticGet('nickname', $nickname);
    } else if (!($user instanceof User)) {
        return false;
    }

    if ($user) {
        if (Event::handle('StartSetUser', array(&$user))) {
            if($user){
                common_ensure_session();
                $_SESSION['userid'] = $user->id;
                $_cur = $user;
                Event::handle('EndSetUser', array($user));
                return $_cur;
            }
        }
    }
    return false;
}

function common_set_cookie($key, $value, $expiration=0)
{
    $path = common_config('site', 'path');
    $server = common_config('site', 'server');

    if ($path && ($path != '/')) {
        $cookiepath = '/' . $path . '/';
    } else {
        $cookiepath = '/';
    }
    return setcookie($key,
                     $value,
                     $expiration,
                     $cookiepath,
                     $server);
}

define('REMEMBERME', 'rememberme');
define('REMEMBERME_EXPIRY', 30 * 24 * 60 * 60); // 30 days

function common_rememberme($user=null)
{
    if (!$user) {
        $user = common_current_user();
        if (!$user) {
            return false;
        }
    }

    $rm = new Remember_me();

    $rm->code = common_good_rand(16);
    $rm->user_id = $user->id;

    // Wrap the insert in some good ol' fashioned transaction code

    $rm->query('BEGIN');

    $result = $rm->insert();

    if (!$result) {
        common_log_db_error($rm, 'INSERT', __FILE__);
        return false;
    }

    $rm->query('COMMIT');

    $cookieval = $rm->user_id . ':' . $rm->code;

    common_log(LOG_INFO, 'adding rememberme cookie "' . $cookieval . '" for ' . $user->nickname);

    common_set_cookie(REMEMBERME, $cookieval, time() + REMEMBERME_EXPIRY);

    return true;
}

function common_remembered_user()
{

    $user = null;

    $packed = isset($_COOKIE[REMEMBERME]) ? $_COOKIE[REMEMBERME] : null;

    if (!$packed) {
        return null;
    }

    list($id, $code) = explode(':', $packed);

    if (!$id || !$code) {
        common_log(LOG_WARNING, 'Malformed rememberme cookie: ' . $packed);
        common_forgetme();
        return null;
    }

    $rm = Remember_me::staticGet($code);

    if (!$rm) {
        common_log(LOG_WARNING, 'No such remember code: ' . $code);
        common_forgetme();
        return null;
    }

    if ($rm->user_id != $id) {
        common_log(LOG_WARNING, 'Rememberme code for wrong user: ' . $rm->user_id . ' != ' . $id);
        common_forgetme();
        return null;
    }

    $user = User::staticGet($rm->user_id);

    if (!$user) {
        common_log(LOG_WARNING, 'No such user for rememberme: ' . $rm->user_id);
        common_forgetme();
        return null;
    }

    // successful!
    $result = $rm->delete();

    if (!$result) {
        common_log_db_error($rm, 'DELETE', __FILE__);
        common_log(LOG_WARNING, 'Could not delete rememberme: ' . $code);
        common_forgetme();
        return null;
    }

    common_log(LOG_INFO, 'logging in ' . $user->nickname . ' using rememberme code ' . $rm->code);

    common_set_user($user);
    common_real_login(false);

    // We issue a new cookie, so they can log in
    // automatically again after this session

    common_rememberme($user);

    return $user;
}

// must be called with a valid user!

function common_forgetme()
{
    common_set_cookie(REMEMBERME, '', 0);
}

// who is the current user?
function common_current_user()
{
    global $_cur;

    if (!_have_config()) {
        return null;
    }

    if ($_cur === false) {

        if (isset($_COOKIE[session_name()]) || isset($_GET[session_name()])
            || (isset($_SESSION['userid']) && $_SESSION['userid'])) {
            common_ensure_session();
            $id = isset($_SESSION['userid']) ? $_SESSION['userid'] : false;
            if ($id) {
                $user = User::staticGet($id);
                if ($user) {
                	$_cur = $user;
                	return $_cur;
                }
            }
        }

        // that didn't work; try to remember; will init $_cur to null on failure
        $_cur = common_remembered_user();

        if ($_cur) {
            // XXX: Is this necessary?
            $_SESSION['userid'] = $_cur->id;
        }
    }

    return $_cur;
}

// Logins that are 'remembered' aren't 'real' -- they're subject to
// cookie-stealing. So, we don't let them do certain things. New reg,
// OpenID, and password logins _are_ real.

function common_real_login($real=true)
{
    common_ensure_session();
    $_SESSION['real_login'] = $real;
}

function common_is_real_login()
{
    return common_logged_in() && $_SESSION['real_login'];
}

// get canonical version of nickname for comparison
function common_canonical_nickname($nickname)
{
    // XXX: UTF-8 canonicalization (like combining chars)
    return strtolower($nickname);
}

// get canonical version of email for comparison
function common_canonical_email($email)
{
    // XXX: canonicalize UTF-8
    // XXX: lcase the domain part
    return $email;
}

function common_render_content($text, $notice)
{
    $r = common_render_text($text);
    $id = $notice->profile_id;
    $r = common_linkify_mentions($r, $notice);
    $r = preg_replace('/(^|[\s\.\,\:\;]+)!([A-Za-z0-9]{1,64})/e', "'\\1!'.common_group_link($id, '\\2')", $r);
    return $r;
}

function common_linkify_mentions($text, $notice)
{
    $mentions = common_find_mentions($text, $notice);

    // We need to go through in reverse order by position,
    // so our positions stay valid despite our fudging with the
    // string!

    $points = array();

    foreach ($mentions as $mention)
    {
        $points[$mention['position']] = $mention;
    }

    krsort($points);

    foreach ($points as $position => $mention) {

        $linkText = common_linkify_mention($mention);

        $text = substr_replace($text, $linkText, $position, mb_strlen($mention['text']));
    }

    return $text;
}

function common_linkify_mention($mention)
{
    $output = null;

    if (Event::handle('StartLinkifyMention', array($mention, &$output))) {

        $xs = new XMLStringer(false);

        $attrs = array('href' => $mention['url'],
                       'class' => 'url');

        if (!empty($mention['title'])) {
            $attrs['title'] = $mention['title'];
        }

        $xs->elementStart('span', 'vcard');
        $xs->elementStart('a', $attrs);
        $xs->element('span', 'fn nickname', $mention['text']);
        $xs->elementEnd('a');
        $xs->elementEnd('span');

        $output = $xs->getString();

        Event::handle('EndLinkifyMention', array($mention, &$output));
    }

    return $output;
}

function common_find_mentions($text, $notice)
{
    $mentions = array();

    $sender = Profile::staticGet('id', $notice->profile_id);

    if (empty($sender)) {
        return $mentions;
    }

    if (Event::handle('StartFindMentions', array($sender, $text, &$mentions))) {

        // Get the context of the original notice, if any

        $originalAuthor   = null;
        $originalNotice   = null;
        $originalMentions = array();

        // Is it a reply?

        if (!empty($notice) && !empty($notice->reply_to)) {
            $originalNotice = Notice::staticGet('id', $notice->reply_to);
            if (!empty($originalNotice)) {
                $originalAuthor = Profile::staticGet('id', $originalNotice->profile_id);

                $ids = $originalNotice->getReplies();

                foreach ($ids as $id) {
                    $repliedTo = Profile::staticGet('id', $id);
                    if (!empty($repliedTo)) {
                        $originalMentions[$repliedTo->nickname] = $repliedTo;
                    }
                }
            }
        }

        preg_match_all('/^T ([A-Z0-9]{1,64}) /',
                       $text,
                       $tmatches,
                       PREG_OFFSET_CAPTURE);

        preg_match_all('/(?:^|\s+)@(['.NICKNAME_FMT.']{1,64})/',
                       $text,
                       $atmatches,
                       PREG_OFFSET_CAPTURE);

        $matches = array_merge($tmatches[1], $atmatches[1]);

        foreach ($matches as $match) {

            $nickname = common_canonical_nickname($match[0]);

            // Try to get a profile for this nickname.
            // Start with conversation context, then go to
            // sender context.

            if (!empty($originalAuthor) && $originalAuthor->nickname == $nickname) {

                $mentioned = $originalAuthor;

            } else if (!empty($originalMentions) &&
                       array_key_exists($nickname, $originalMentions)) {

                $mentioned = $originalMentions[$nickname];
            } else {
                $mentioned = common_relative_profile($sender, $nickname);
            }

            if (!empty($mentioned)) {

                $user = User::staticGet('id', $mentioned->id);

                if ($user) {
                    $url = common_local_url('userbyid', array('id' => $user->id));
                } else {
                    $url = $mentioned->profileurl;
                }

                $mention = array('mentioned' => array($mentioned),
                                 'text' => $match[0],
                                 'position' => $match[1],
                                 'url' => $url);

                if (!empty($mentioned->fullname)) {
                    $mention['title'] = $mentioned->fullname;
                }

                $mentions[] = $mention;
            }
        }

        // @#tag => mention of all subscriptions tagged 'tag'

        preg_match_all('/(?:^|[\s\.\,\:\;]+)@#([\pL\pN_\-\.]{1,64})/',
                       $text,
                       $hmatches,
                       PREG_OFFSET_CAPTURE);

        foreach ($hmatches[1] as $hmatch) {

            $tag = common_canonical_tag($hmatch[0]);

            $tagged = Profile_tag::getTagged($sender->id, $tag);

            $url = common_local_url('subscriptions',
                                    array('nickname' => $sender->nickname,
                                          'tag' => $tag));

            $mentions[] = array('mentioned' => $tagged,
                                'text' => $hmatch[0],
                                'position' => $hmatch[1],
                                'url' => $url);
        }

        Event::handle('EndFindMentions', array($sender, $text, &$mentions));
    }

    return $mentions;
}

function common_render_text($text)
{
    $r = htmlspecialchars($text);

    $r = preg_replace('/[\x{0}-\x{8}\x{b}-\x{c}\x{e}-\x{19}]/', '', $r);
    $r = common_replace_urls_callback($r, 'common_linkify');
    $r = preg_replace('/(^|\&quot\;|\'|\(|\[|\{|\s+)#([\pL\pN_\-\.]{1,64})/e', "'\\1#'.common_tag_link('\\2')", $r);
    // XXX: machine tags
    return $r;
}

function common_replace_urls_callback($text, $callback, $notice_id = null) {
    // Start off with a regex
    $regex = '#'.
    '(?:^|[\s\<\>\(\)\[\]\{\}\\\'\\\";]+)(?![\@\!\#])'.
    '('.
        '(?:'.
            '(?:'. //Known protocols
                '(?:'.
                    '(?:(?:https?|ftps?|mms|rtsp|gopher|news|nntp|telnet|wais|file|prospero|webcal|irc)://)'.
                    '|'.
                    '(?:(?:mailto|aim|tel|xmpp):)'.
                ')'.
                '(?:[\pN\pL\-\_\+\%\~]+(?::[\pN\pL\-\_\+\%\~]+)?\@)?'. //user:pass@
                '(?:'.
                    '(?:'.
                        '\[[\pN\pL\-\_\:\.]+(?<![\.\:])\]'. //[dns]
                    ')|(?:'.
                        '[\pN\pL\-\_\:\.]+(?<![\.\:])'. //dns
                    ')'.
                ')'.
            ')'.
            '|(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)'. //IPv4
            '|(?:'. //IPv6
                '\[?(?:(?:(?:[0-9A-Fa-f]{1,4}:){7}(?:(?:[0-9A-Fa-f]{1,4})|:))|(?:(?:[0-9A-Fa-f]{1,4}:){6}(?::|(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})|(?::[0-9A-Fa-f]{1,4})))|(?:(?:[0-9A-Fa-f]{1,4}:){5}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:){4}(?::[0-9A-Fa-f]{1,4}){0,1}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:){3}(?::[0-9A-Fa-f]{1,4}){0,2}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:){2}(?::[0-9A-Fa-f]{1,4}){0,3}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:)(?::[0-9A-Fa-f]{1,4}){0,4}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?::(?::[0-9A-Fa-f]{1,4}){0,5}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})))\]?(?<!:)'.
            ')|(?:'. //DNS
                '(?:[\pN\pL\-\_\+\%\~]+(?:\:[\pN\pL\-\_\+\%\~]+)?\@)?'. //user:pass@
                '[\pN\pL\-\_]+(?:\.[\pN\pL\-\_]+)*\.'.
                //tld list from http://data.iana.org/TLD/tlds-alpha-by-domain.txt, also added local, loc, and onion
                '(?:AC|AD|AE|AERO|AF|AG|AI|AL|AM|AN|AO|AQ|AR|ARPA|AS|ASIA|AT|AU|AW|AX|AZ|BA|BB|BD|BE|BF|BG|BH|BI|BIZ|BJ|BM|BN|BO|BR|BS|BT|BV|BW|BY|BZ|CA|CAT|CC|CD|CF|CG|CH|CI|CK|CL|CM|CN|CO|COM|COOP|CR|CU|CV|CX|CY|CZ|DE|DJ|DK|DM|DO|DZ|EC|EDU|EE|EG|ER|ES|ET|EU|FI|FJ|FK|FM|FO|FR|GA|GB|GD|GE|GF|GG|GH|GI|GL|GM|GN|GOV|GP|GQ|GR|GS|GT|GU|GW|GY|HK|HM|HN|HR|HT|HU|ID|IE|IL|IM|IN|INFO|INT|IO|IQ|IR|IS|IT|JE|JM|JO|JOBS|JP|KE|KG|KH|KI|KM|KN|KP|KR|KW|KY|KZ|LA|LB|LC|LI|LK|LR|LS|LT|LU|LV|LY|MA|MC|MD|ME|MG|MH|MIL|MK|ML|MM|MN|MO|MOBI|MP|MQ|MR|MS|MT|MU|MUSEUM|MV|MW|MX|MY|MZ|NA|NAME|NC|NE|NET|NF|NG|NI|NL|NO|NP|NR|NU|NZ|OM|ORG|PA|PE|PF|PG|PH|PK|PL|PM|PN|PR|PRO|PS|PT|PW|PY|QA|RE|RO|RS|RU|RW|SA|SB|SC|SD|SE|SG|SH|SI|SJ|SK|SL|SM|SN|SO|SR|ST|SU|SV|SY|SZ|TC|TD|TEL|TF|TG|TH|TJ|TK|TL|TM|TN|TO|TP|TR|TRAVEL|TT|TV|TW|TZ|UA|UG|UK|US|UY|UZ|VA|VC|VE|VG|VI|VN|VU|WF|WS|XN--0ZWM56D|测试|XN--11B5BS3A9AJ6G|परीक्षा|XN--80AKHBYKNJ4F|испытание|XN--9T4B11YI5A|테스트|XN--DEBA0AD|טעסט|XN--G6W251D|測試|XN--HGBK6AJ7F53BBA|آزمایشی|XN--HLCJ6AYA9ESC7A|பரிட்சை|XN--JXALPDLP|δοκιμή|XN--KGBECHTV|إختبار|XN--ZCKZAH|テスト|YE|YT|YU|ZA|ZM|ZW|local|loc|onion)'.
            ')(?![\pN\pL\-\_])'.
        ')'.
        '(?:'.
            '(?:\:\d+)?'. //:port
            '(?:/[\pN\pL$\,\!\(\)\.\:\-\_\+\/\=\&\;\%\~\*\$\+\'@]*)?'. // /path
            '(?:\?[\pN\pL\$\,\!\(\)\.\:\-\_\+\/\=\&\;\%\~\*\$\+\'@\/]*)?'. // ?query string
            '(?:\#[\pN\pL$\,\!\(\)\.\:\-\_\+\/\=\&\;\%\~\*\$\+\'\@/\?\#]*)?'. // #fragment
        ')(?<![\?\.\,\#\,])'.
    ')'.
    '#ixu';
    //preg_match_all($regex,$text,$matches);
    //print_r($matches);
    return preg_replace_callback($regex, curry('callback_helper',$callback,$notice_id) ,$text);
}

function callback_helper($matches, $callback, $notice_id) {
    $url=$matches[1];
    $left = strpos($matches[0],$url);
    $right = $left+strlen($url);

    $groupSymbolSets=array(
        array(
            'left'=>'(',
            'right'=>')'
        ),
        array(
            'left'=>'[',
            'right'=>']'
        ),
        array(
            'left'=>'{',
            'right'=>'}'
        ),
        array(
            'left'=>'<',
            'right'=>'>'
        )
    );
    $cannotEndWith=array('.','?',',','#');
    $original_url=$url;
    do{
        $original_url=$url;
        foreach($groupSymbolSets as $groupSymbolSet){
            if(substr($url,-1)==$groupSymbolSet['right']){
                $group_left_count = substr_count($url,$groupSymbolSet['left']);
                $group_right_count = substr_count($url,$groupSymbolSet['right']);
                if($group_left_count<$group_right_count){
                    $right-=1;
                    $url=substr($url,0,-1);
                }
            }
        }
        if(in_array(substr($url,-1),$cannotEndWith)){
            $right-=1;
            $url=substr($url,0,-1);
        }
    }while($original_url!=$url);

    if(empty($notice_id)){
        $result = call_user_func_array($callback, array($url));
    }else{
        $result = call_user_func_array($callback, array(array($url,$notice_id)) );
    }
    return substr($matches[0],0,$left) . $result . substr($matches[0],$right);
}

if (version_compare(PHP_VERSION, '5.3.0', 'ge')) {
    // lambda implementation in a separate file; PHP 5.2 won't parse it.
    require_once INSTALLDIR . "/lib/curry.php";
} else {
    function curry($fn) {
        $args = func_get_args();
        array_shift($args);
        $id = uniqid('_partial');
        $GLOBALS[$id] = array($fn, $args);
        return create_function('',
                               '$args = func_get_args(); '.
                               'return call_user_func_array('.
                               '$GLOBALS["'.$id.'"][0],'.
                               'array_merge('.
                               '$args,'.
                               '$GLOBALS["'.$id.'"][1]));');
    }
}

function common_linkify($url) {
    // It comes in special'd, so we unspecial it before passing to the stringifying
    // functions
    $url = htmlspecialchars_decode($url);

   if(strpos($url, '@') !== false && strpos($url, ':') === false) {
       //url is an email address without the mailto: protocol
       $canon = "mailto:$url";
       $longurl = "mailto:$url";
   }else{

        $canon = File_redirection::_canonUrl($url);

        $longurl_data = File_redirection::where($canon);
        if (is_array($longurl_data)) {
            $longurl = $longurl_data['url'];
        } elseif (is_string($longurl_data)) {
            $longurl = $longurl_data;
        } else {
            throw new ServerException("Can't linkify url '$url'");
        }
    }
    $attrs = array('href' => $canon, 'title' => $longurl, 'rel' => 'external');

    $is_attachment = false;
    $attachment_id = null;
    $has_thumb = false;

    // Check to see whether this is a known "attachment" URL.

    $f = File::staticGet('url', $longurl);

    if (empty($f)) {
        // XXX: this writes to the database. :<
        $f = File::processNew($longurl);
    }

    if (!empty($f)) {
        if ($f->getEnclosure() || File_oembed::staticGet('file_id',$f->id)) {
            $is_attachment = true;
            $attachment_id = $f->id;

            $thumb = File_thumbnail::staticGet('file_id', $f->id);
            if (!empty($thumb)) {
                $has_thumb = true;
            }
        }
    }

    // Add clippy
    if ($is_attachment) {
        $attrs['class'] = 'attachment';
        if ($has_thumb) {
            $attrs['class'] = 'attachment thumbnail';
        }
        $attrs['id'] = "attachment-{$attachment_id}";
    }

    return XMLStringer::estring('a', $attrs, $url);
}

function common_shorten_links($text, $always = false)
{
    common_debug("common_shorten_links() called");

    $user = common_current_user();

    $maxLength = User_urlshortener_prefs::maxNoticeLength($user);

    common_debug("maxLength = $maxLength");

    if ($always || mb_strlen($text) > $maxLength) {
        common_debug("Forcing shortening");
        return common_replace_urls_callback($text, array('File_redirection', 'forceShort'));
    } else {
        common_debug("Not forcing shortening");
        return common_replace_urls_callback($text, array('File_redirection', 'makeShort'));
    }
}

function common_xml_safe_str($str)
{
    // Replace common eol and extra whitespace input chars
    $unWelcome = array(
        "\t",  // tab
        "\n",  // newline
        "\r",  // cr
        "\0",  // null byte eos
        "\x0B" // vertical tab
    );

    $replacement = array(
        ' ', // single space
        ' ',
        '',  // nothing
        '',
        ' '
    );

    $str = str_replace($unWelcome, $replacement, $str);

    // Neutralize any additional control codes and UTF-16 surrogates
    // (Twitter uses '*')
    return preg_replace('/[\p{Cc}\p{Cs}]/u', '*', $str);
}

function common_tag_link($tag)
{
    $canonical = common_canonical_tag($tag);
    if (common_config('singleuser', 'enabled')) {
        // regular TagAction isn't set up in 1user mode
        $url = common_local_url('showstream',
                                array('nickname' => common_config('singleuser', 'nickname'),
                                      'tag' => $canonical));
    } else {
        $url = common_local_url('tag', array('tag' => $canonical));
    }
    $xs = new XMLStringer();
    $xs->elementStart('span', 'tag');
    $xs->element('a', array('href' => $url,
                            'rel' => 'tag'),
                 $tag);
    $xs->elementEnd('span');
    return $xs->getString();
}

function common_canonical_tag($tag)
{
  $tag = mb_convert_case($tag, MB_CASE_LOWER, "UTF-8");
  return str_replace(array('-', '_', '.'), '', $tag);
}

function common_valid_profile_tag($str)
{
    return preg_match('/^[A-Za-z0-9_\-\.]{1,64}$/', $str);
}

function common_group_link($sender_id, $nickname)
{
    $sender = Profile::staticGet($sender_id);
    $group = User_group::getForNickname($nickname, $sender);
    if ($sender && $group && $sender->isMember($group)) {
        $attrs = array('href' => $group->permalink(),
                       'class' => 'url');
        if (!empty($group->fullname)) {
            $attrs['title'] = $group->fullname . ' (' . $group->nickname . ')';
        }
        $xs = new XMLStringer();
        $xs->elementStart('span', 'vcard');
        $xs->elementStart('a', $attrs);
        $xs->element('span', 'fn nickname', $nickname);
        $xs->elementEnd('a');
        $xs->elementEnd('span');
        return $xs->getString();
    } else {
        return $nickname;
    }
}

function common_relative_profile($sender, $nickname, $dt=null)
{
    // Try to find profiles this profile is subscribed to that have this nickname
    $recipient = new Profile();
    // XXX: use a join instead of a subquery
    $recipient->whereAdd('EXISTS (SELECT subscribed from subscription where subscriber = '.$sender->id.' and subscribed = id)', 'AND');
    $recipient->whereAdd("nickname = '" . trim($nickname) . "'", 'AND');
    if ($recipient->find(true)) {
        // XXX: should probably differentiate between profiles with
        // the same name by date of most recent update
        return $recipient;
    }
    // Try to find profiles that listen to this profile and that have this nickname
    $recipient = new Profile();
    // XXX: use a join instead of a subquery
    $recipient->whereAdd('EXISTS (SELECT subscriber from subscription where subscribed = '.$sender->id.' and subscriber = id)', 'AND');
    $recipient->whereAdd("nickname = '" . trim($nickname) . "'", 'AND');
    if ($recipient->find(true)) {
        // XXX: should probably differentiate between profiles with
        // the same name by date of most recent update
        return $recipient;
    }
    // If this is a local user, try to find a local user with that nickname.
    $sender = User::staticGet($sender->id);
    if ($sender) {
        $recipient_user = User::staticGet('nickname', $nickname);
        if ($recipient_user) {
            return $recipient_user->getProfile();
        }
    }
    // Otherwise, no links. @messages from local users to remote users,
    // or from remote users to other remote users, are just
    // outside our ability to make intelligent guesses about
    return null;
}

function common_local_url($action, $args=null, $params=null, $fragment=null, $addSession=true)
{
    $r = Router::get();
    $path = $r->build($action, $args, $params, $fragment);

    $ssl = common_is_sensitive($action);

    if (common_config('site','fancy')) {
        $url = common_path(mb_substr($path, 1), $ssl, $addSession);
    } else {
        if (mb_strpos($path, '/index.php') === 0) {
            $url = common_path(mb_substr($path, 1), $ssl, $addSession);
        } else {
            $url = common_path('index.php'.$path, $ssl, $addSession);
        }
    }
    return $url;
}

function common_is_sensitive($action)
{
    static $sensitive = array('login', 'register', 'passwordsettings',
                              'twittersettings', 'api');
    $ssl = null;

    if (Event::handle('SensitiveAction', array($action, &$ssl))) {
        $ssl = in_array($action, $sensitive);
    }

    return $ssl;
}

function common_path($relative, $ssl=false, $addSession=true)
{
    $pathpart = (common_config('site', 'path')) ? common_config('site', 'path')."/" : '';

    if (($ssl && (common_config('site', 'ssl') === 'sometimes'))
        || common_config('site', 'ssl') === 'always') {
        $proto = 'https';
        if (is_string(common_config('site', 'sslserver')) &&
            mb_strlen(common_config('site', 'sslserver')) > 0) {
            $serverpart = common_config('site', 'sslserver');
        } else if (common_config('site', 'server')) {
            $serverpart = common_config('site', 'server');
        } else {
            common_log(LOG_ERR, 'Site server not configured, unable to determine site name.');
        }
    } else {
        $proto = 'http';
        if (common_config('site', 'server')) {
            $serverpart = common_config('site', 'server');
        } else {
            common_log(LOG_ERR, 'Site server not configured, unable to determine site name.');
        }
    }

    if ($addSession) {
        $relative = common_inject_session($relative, $serverpart);
    }

    return $proto.'://'.$serverpart.'/'.$pathpart.$relative;
}

function common_inject_session($url, $serverpart = null)
{
    if (common_have_session()) {

	if (empty($serverpart)) {
	    $serverpart = parse_url($url, PHP_URL_HOST);
	}

        $currentServer = $_SERVER['HTTP_HOST'];

        // Are we pointing to another server (like an SSL server?)

        if (!empty($currentServer) &&
            0 != strcasecmp($currentServer, $serverpart)) {
            // Pass the session ID as a GET parameter
            $sesspart = session_name() . '=' . session_id();
            $i = strpos($url, '?');
            if ($i === false) { // no GET params, just append
                $url .= '?' . $sesspart;
            } else {
                $url = substr($url, 0, $i + 1).$sesspart.'&'.substr($url, $i + 1);
            }
        }
    }

    return $url;
}

function common_date_string($dt)
{
    // XXX: do some sexy date formatting
    // return date(DATE_RFC822, $dt);
    $t = strtotime($dt);
    $now = time();
    $diff = $now - $t;

    if ($now < $t) { // that shouldn't happen!
        return common_exact_date($dt);
    } else if ($diff < 60) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('a few seconds ago');
    } else if ($diff < 92) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('about a minute ago');
    } else if ($diff < 3300) {
        // XXX: should support plural.
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return sprintf(_('about %d minutes ago'), round($diff/60));
    } else if ($diff < 5400) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('about an hour ago');
    } else if ($diff < 22 * 3600) {
        // XXX: should support plural.
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return sprintf(_('about %d hours ago'), round($diff/3600));
    } else if ($diff < 37 * 3600) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('about a day ago');
    } else if ($diff < 24 * 24 * 3600) {
        // XXX: should support plural.
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return sprintf(_('about %d days ago'), round($diff/(24*3600)));
    } else if ($diff < 46 * 24 * 3600) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('about a month ago');
    } else if ($diff < 330 * 24 * 3600) {
        // XXX: should support plural.
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return sprintf(_('about %d months ago'), round($diff/(30*24*3600)));
    } else if ($diff < 480 * 24 * 3600) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('about a year ago');
    } else {
        return common_exact_date($dt);
    }
}

function common_exact_date($dt)
{
    static $_utc;
    static $_siteTz;

    if (!$_utc) {
        $_utc = new DateTimeZone('UTC');
        $_siteTz = new DateTimeZone(common_timezone());
    }

    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, $_utc);
    $d->setTimezone($_siteTz);
    return $d->format(DATE_RFC850);
}

function common_date_w3dtf($dt)
{
    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone(common_timezone()));
    return $d->format(DATE_W3C);
}

function common_date_rfc2822($dt)
{
    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone(common_timezone()));
    return $d->format('r');
}

function common_date_iso8601($dt)
{
    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone(common_timezone()));
    return $d->format('c');
}

function common_sql_now()
{
    return common_sql_date(time());
}

function common_sql_date($datetime)
{
    return strftime('%Y-%m-%d %H:%M:%S', $datetime);
}

/**
 * Return an SQL fragment to calculate an age-based weight from a given
 * timestamp or datetime column.
 *
 * @param string $column name of field we're comparing against current time
 * @param integer $dropoff divisor for age in seconds before exponentiation
 * @return string SQL fragment
 */
function common_sql_weight($column, $dropoff)
{
    if (common_config('db', 'type') == 'pgsql') {
        // PostgreSQL doesn't support timestampdiff function.
        // @fixme will this use the right time zone?
        // @fixme does this handle cross-year subtraction correctly?
        return "sum(exp(-extract(epoch from (now() - $column)) / $dropoff))";
    } else {
        return "sum(exp(timestampdiff(second, utc_timestamp(), $column) / $dropoff))";
    }
}

function common_redirect($url, $code=307)
{
    static $status = array(301 => "Moved Permanently",
                           302 => "Found",
                           303 => "See Other",
                           307 => "Temporary Redirect");

    header('HTTP/1.1 '.$code.' '.$status[$code]);
    header("Location: $url");

    $xo = new XMLOutputter();
    $xo->startXML('a',
                  '-//W3C//DTD XHTML 1.0 Strict//EN',
                  'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd');
    $xo->element('a', array('href' => $url), $url);
    $xo->endXML();
    exit;
}

function common_broadcast_notice($notice, $remote=false)
{
    // DO NOTHING!
}

// Stick the notice on the queue

function common_enqueue_notice($notice)
{
    static $localTransports = array('omb',
                                    'ping');

    $transports = array();
    if (common_config('sms', 'enabled')) {
        $transports[] = 'sms';
    }
    if (Event::hasHandler('HandleQueuedNotice')) {
        $transports[] = 'plugin';
    }

    // @fixme move these checks into QueueManager and/or individual handlers
    if ($notice->is_local == Notice::LOCAL_PUBLIC ||
        $notice->is_local == Notice::LOCAL_NONPUBLIC) {
        $transports = array_merge($transports, $localTransports);
    }

    if (Event::handle('StartEnqueueNotice', array($notice, &$transports))) {

        $qm = QueueManager::get();

        foreach ($transports as $transport)
        {
            $qm->enqueue($notice, $transport);
        }

        Event::handle('EndEnqueueNotice', array($notice, $transports));
    }

    return true;
}

/**
 * Broadcast profile updates to OMB and other remote subscribers.
 *
 * Since this may be slow with a lot of subscribers or bad remote sites,
 * this is run through the background queues if possible.
 */
function common_broadcast_profile(Profile $profile)
{
    $qm = QueueManager::get();
    $qm->enqueue($profile, "profile");
    return true;
}

function common_profile_url($nickname)
{
    return common_local_url('showstream', array('nickname' => $nickname),
                            null, null, false);
}

// Should make up a reasonable root URL

function common_root_url($ssl=false)
{
    $url = common_path('', $ssl, false);
    $i = strpos($url, '?');
    if ($i !== false) {
        $url = substr($url, 0, $i);
    }
    return $url;
}

// returns $bytes bytes of random data as a hexadecimal string
// "good" here is a goal and not a guarantee

function common_good_rand($bytes)
{
    // XXX: use random.org...?
    if (@file_exists('/dev/urandom')) {
        return common_urandom($bytes);
    } else { // FIXME: this is probably not good enough
        return common_mtrand($bytes);
    }
}

function common_urandom($bytes)
{
    $h = fopen('/dev/urandom', 'rb');
    // should not block
    $src = fread($h, $bytes);
    fclose($h);
    $enc = '';
    for ($i = 0; $i < $bytes; $i++) {
        $enc .= sprintf("%02x", (ord($src[$i])));
    }
    return $enc;
}

function common_mtrand($bytes)
{
    $enc = '';
    for ($i = 0; $i < $bytes; $i++) {
        $enc .= sprintf("%02x", mt_rand(0, 255));
    }
    return $enc;
}

/**
 * Record the given URL as the return destination for a future
 * form submission, to be read by common_get_returnto().
 * 
 * @param string $url
 * 
 * @fixme as a session-global setting, this can allow multiple forms
 * to conflict and overwrite each others' returnto destinations if
 * the user has multiple tabs or windows open.
 * 
 * Should refactor to index with a token or otherwise only pass the
 * data along its intended path.
 */
function common_set_returnto($url)
{
    common_ensure_session();
    $_SESSION['returnto'] = $url;
}

/**
 * Fetch a return-destination URL previously recorded by
 * common_set_returnto().
 * 
 * @return mixed URL string or null
 * 
 * @fixme as a session-global setting, this can allow multiple forms
 * to conflict and overwrite each others' returnto destinations if
 * the user has multiple tabs or windows open.
 * 
 * Should refactor to index with a token or otherwise only pass the
 * data along its intended path.
 */
function common_get_returnto()
{
    common_ensure_session();
    return (array_key_exists('returnto', $_SESSION)) ? $_SESSION['returnto'] : null;
}

function common_timestamp()
{
    return date('YmdHis');
}

function common_ensure_syslog()
{
    static $initialized = false;
    if (!$initialized) {
        openlog(common_config('syslog', 'appname'), 0,
            common_config('syslog', 'facility'));
        $initialized = true;
    }
}

function common_log_line($priority, $msg)
{
    static $syslog_priorities = array('LOG_EMERG', 'LOG_ALERT', 'LOG_CRIT', 'LOG_ERR',
                                      'LOG_WARNING', 'LOG_NOTICE', 'LOG_INFO', 'LOG_DEBUG');
    return date('Y-m-d H:i:s') . ' ' . $syslog_priorities[$priority] . ': ' . $msg . PHP_EOL;
}

function common_request_id()
{
    $pid = getmypid();
    $server = common_config('site', 'server');
    if (php_sapi_name() == 'cli') {
        $script = basename($_SERVER['PHP_SELF']);
        return "$server:$script:$pid";
    } else {
        static $req_id = null;
        if (!isset($req_id)) {
            $req_id = substr(md5(mt_rand()), 0, 8);
        }
        if (isset($_SERVER['REQUEST_URI'])) {
            $url = $_SERVER['REQUEST_URI'];
        }
        $method = $_SERVER['REQUEST_METHOD'];
        return "$server:$pid.$req_id $method $url";
    }
}

function common_log($priority, $msg, $filename=null)
{
    if(Event::handle('StartLog', array(&$priority, &$msg, &$filename))){
        $msg = '[' . common_request_id() . '] ' . $msg;
        $logfile = common_config('site', 'logfile');
        if ($logfile) {
            $log = fopen($logfile, "a");
            if ($log) {
                $output = common_log_line($priority, $msg);
                fwrite($log, $output);
                fclose($log);
            }
        } else {
            common_ensure_syslog();
            syslog($priority, $msg);
        }
        Event::handle('EndLog', array($priority, $msg, $filename));
    }
}

function common_debug($msg, $filename=null)
{
    if ($filename) {
        common_log(LOG_DEBUG, basename($filename).' - '.$msg);
    } else {
        common_log(LOG_DEBUG, $msg);
    }
}

function common_log_db_error(&$object, $verb, $filename=null)
{
    $objstr = common_log_objstring($object);
    $last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
    common_log(LOG_ERR, $last_error->message . '(' . $verb . ' on ' . $objstr . ')', $filename);
}

function common_log_objstring(&$object)
{
    if (is_null($object)) {
        return "null";
    }
    if (!($object instanceof DB_DataObject)) {
        return "(unknown)";
    }
    $arr = $object->toArray();
    $fields = array();
    foreach ($arr as $k => $v) {
        if (is_object($v)) {
            $fields[] = "$k='".get_class($v)."'";
        } else {
            $fields[] = "$k='$v'";
        }
    }
    $objstring = $object->tableName() . '[' . implode(',', $fields) . ']';
    return $objstring;
}

function common_valid_http_url($url)
{
    return Validate::uri($url, array('allowed_schemes' => array('http', 'https')));
}

function common_valid_tag($tag)
{
    if (preg_match('/^tag:(.*?),(\d{4}(-\d{2}(-\d{2})?)?):(.*)$/', $tag, $matches)) {
        return (Validate::email($matches[1]) ||
                preg_match('/^([\w-\.]+)$/', $matches[1]));
    }
    return false;
}

/**
 * Determine if given domain or address literal is valid
 * eg for use in JIDs and URLs. Does not check if the domain
 * exists!
 *
 * @param string $domain
 * @return boolean valid or not
 */
function common_valid_domain($domain)
{
    $octet = "(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9])";
    $ipv4 = "(?:$octet(?:\.$octet){3})";
    if (preg_match("/^$ipv4$/u", $domain)) return true;

    $group = "(?:[0-9a-f]{1,4})";
    $ipv6 = "(?:\[($group(?::$group){0,7})?(::)?($group(?::$group){0,7})?\])"; // http://tools.ietf.org/html/rfc3513#section-2.2

    if (preg_match("/^$ipv6$/ui", $domain, $matches)) {
        $before = explode(":", $matches[1]);
        $zeroes = $matches[2];
        $after = explode(":", $matches[3]);
        if ($zeroes) {
            $min = 0;
            $max = 7;
        } else {
            $min = 1;
            $max = 8;
        }
        $explicit = count($before) + count($after);
        if ($explicit < $min || $explicit > $max) {
            return false;
        }
        return true;
    }

    try {
        require_once "Net/IDNA.php";
        $idn = Net_IDNA::getInstance();
        $domain = $idn->encode($domain);
    } catch (Exception $e) {
        return false;
    }

    $subdomain = "(?:[a-z0-9][a-z0-9-]*)"; // @fixme
    $fqdn = "(?:$subdomain(?:\.$subdomain)*\.?)";

    return preg_match("/^$fqdn$/ui", $domain);
}

/* Following functions are copied from MediaWiki GlobalFunctions.php
 * and written by Evan Prodromou. */

function common_accept_to_prefs($accept, $def = '*/*')
{
    // No arg means accept anything (per HTTP spec)
    if(!$accept) {
        return array($def => 1);
    }

    $prefs = array();

    $parts = explode(',', $accept);

    foreach($parts as $part) {
        // FIXME: doesn't deal with params like 'text/html; level=1'
        @list($value, $qpart) = explode(';', trim($part));
        $match = array();
        if(!isset($qpart)) {
            $prefs[$value] = 1;
        } elseif(preg_match('/q\s*=\s*(\d*\.\d+)/', $qpart, $match)) {
            $prefs[$value] = $match[1];
        }
    }

    return $prefs;
}

function common_mime_type_match($type, $avail)
{
    if(array_key_exists($type, $avail)) {
        return $type;
    } else {
        $parts = explode('/', $type);
        if(array_key_exists($parts[0] . '/*', $avail)) {
            return $parts[0] . '/*';
        } elseif(array_key_exists('*/*', $avail)) {
            return '*/*';
        } else {
            return null;
        }
    }
}

function common_negotiate_type($cprefs, $sprefs)
{
    $combine = array();

    foreach(array_keys($sprefs) as $type) {
        $parts = explode('/', $type);
        if($parts[1] != '*') {
            $ckey = common_mime_type_match($type, $cprefs);
            if($ckey) {
                $combine[$type] = $sprefs[$type] * $cprefs[$ckey];
            }
        }
    }

    foreach(array_keys($cprefs) as $type) {
        $parts = explode('/', $type);
        if($parts[1] != '*' && !array_key_exists($type, $sprefs)) {
            $skey = common_mime_type_match($type, $sprefs);
            if($skey) {
                $combine[$type] = $sprefs[$skey] * $cprefs[$type];
            }
        }
    }

    $bestq = 0;
    $besttype = 'text/html';

    foreach(array_keys($combine) as $type) {
        if($combine[$type] > $bestq) {
            $besttype = $type;
            $bestq = $combine[$type];
        }
    }

    if ('text/html' === $besttype) {
        return "text/html; charset=utf-8";
    }
    return $besttype;
}

function common_config($main, $sub)
{
    global $config;
    return (array_key_exists($main, $config) &&
            array_key_exists($sub, $config[$main])) ? $config[$main][$sub] : false;
}

function common_copy_args($from)
{
    $to = array();
    $strip = get_magic_quotes_gpc();
    foreach ($from as $k => $v) {
        if($strip) {
            if(is_array($v)) {
                $to[$k] = common_copy_args($v);
            } else {
                $to[$k] = stripslashes($v);
            }
        } else {
            $to[$k] = $v;
        }
    }
    return $to;
}

/**
 * Neutralise the evil effects of magic_quotes_gpc in the current request.
 * This is used before handing a request off to OAuthRequest::from_request.
 * @fixme Doesn't consider vars other than _POST and _GET?
 * @fixme Can't be undone and could corrupt data if run twice.
 */
function common_remove_magic_from_request()
{
    if(get_magic_quotes_gpc()) {
        $_POST=array_map('stripslashes',$_POST);
        $_GET=array_map('stripslashes',$_GET);
    }
}

function common_user_uri(&$user)
{
    return common_local_url('userbyid', array('id' => $user->id),
                            null, null, false);
}

function common_notice_uri(&$notice)
{
    return common_local_url('shownotice',
                            array('notice' => $notice->id),
                            null, null, false);
}

// 36 alphanums - lookalikes (0, O, 1, I) = 32 chars = 5 bits

function common_confirmation_code($bits)
{
    // 36 alphanums - lookalikes (0, O, 1, I) = 32 chars = 5 bits
    static $codechars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $chars = ceil($bits/5);
    $code = '';
    for ($i = 0; $i < $chars; $i++) {
        // XXX: convert to string and back
        $num = hexdec(common_good_rand(1));
        // XXX: randomness is too precious to throw away almost
        // 40% of the bits we get!
        $code .= $codechars[$num%32];
    }
    return $code;
}

// convert markup to HTML

function common_markup_to_html($c)
{
    $c = preg_replace('/%%action.(\w+)%%/e', "common_local_url('\\1')", $c);
    $c = preg_replace('/%%doc.(\w+)%%/e', "common_local_url('doc', array('title'=>'\\1'))", $c);
    $c = preg_replace('/%%(\w+).(\w+)%%/e', 'common_config(\'\\1\', \'\\2\')', $c);
    return Markdown($c);
}

function common_profile_uri($profile)
{
    if (!$profile) {
        return null;
    }
    $user = User::staticGet($profile->id);
    if ($user) {
        return $user->uri;
    }

    $remote = Remote_profile::staticGet($profile->id);
    if ($remote) {
        return $remote->uri;
    }
    // XXX: this is a very bad profile!
    return null;
}

function common_canonical_sms($sms)
{
    // strip non-digits
    preg_replace('/\D/', '', $sms);
    return $sms;
}

function common_error_handler($errno, $errstr, $errfile, $errline, $errcontext)
{
    switch ($errno) {

     case E_ERROR:
     case E_COMPILE_ERROR:
     case E_CORE_ERROR:
     case E_USER_ERROR:
     case E_PARSE:
     case E_RECOVERABLE_ERROR:
        common_log(LOG_ERR, "[$errno] $errstr ($errfile:$errline) [ABORT]");
        die();
        break;

     case E_WARNING:
     case E_COMPILE_WARNING:
     case E_CORE_WARNING:
     case E_USER_WARNING:
        common_log(LOG_WARNING, "[$errno] $errstr ($errfile:$errline)");
        break;

     case E_NOTICE:
     case E_USER_NOTICE:
        common_log(LOG_NOTICE, "[$errno] $errstr ($errfile:$errline)");
        break;

     case E_STRICT:
     case E_DEPRECATED:
     case E_USER_DEPRECATED:
        // XXX: config variable to log this stuff, too
        break;

     default:
        common_log(LOG_ERR, "[$errno] $errstr ($errfile:$errline) [UNKNOWN LEVEL, die()'ing]");
        die();
        break;
    }

    // FIXME: show error page if we're on the Web
    /* Don't execute PHP internal error handler */
    return true;
}

function common_session_token()
{
    common_ensure_session();
    if (!array_key_exists('token', $_SESSION)) {
        $_SESSION['token'] = common_good_rand(64);
    }
    return $_SESSION['token'];
}

function common_cache_key($extra)
{
    return Cache::key($extra);
}

function common_keyize($str)
{
    return Cache::keyize($str);
}

function common_memcache()
{
    return Cache::instance();
}

function common_license_terms($uri)
{
    if(preg_match('/creativecommons.org\/licenses\/([^\/]+)/', $uri, $matches)) {
        return explode('-',$matches[1]);
    }
    return array($uri);
}

function common_compatible_license($from, $to)
{
    $from_terms = common_license_terms($from);
    // public domain and cc-by are compatible with everything
    if(count($from_terms) == 1 && ($from_terms[0] == 'publicdomain' || $from_terms[0] == 'by')) {
        return true;
    }
    $to_terms = common_license_terms($to);
    // sa is compatible across versions. IANAL
    if(in_array('sa',$from_terms) || in_array('sa',$to_terms)) {
        return count(array_diff($from_terms, $to_terms)) == 0;
    }
    // XXX: better compatibility check needed here!
    // Should at least normalise URIs
    return ($from == $to);
}

/**
 * returns a quoted table name, if required according to config
 */
function common_database_tablename($tablename)
{

  if(common_config('db','quote_identifiers')) {
      $tablename = '"'. $tablename .'"';
  }
  //table prefixes could be added here later
  return $tablename;
}

/**
 * Shorten a URL with the current user's configured shortening service,
 * or ur1.ca if configured, or not at all if no shortening is set up.
 *
 * @param string  $long_url original URL
 * @param boolean $force    Force shortening (used when notice is too long)
 *
 * @return string may return the original URL if shortening failed
 *
 * @fixme provide a way to specify a particular shortener
 * @fixme provide a way to specify to use a given user's shortening preferences
 */

function common_shorten_url($long_url, $force = false)
{
    common_debug("Shortening URL '$long_url' (force = $force)");

    $long_url = trim($long_url);

    $user = common_current_user();

    $maxUrlLength = User_urlshortener_prefs::maxUrlLength($user);
    common_debug("maxUrlLength = $maxUrlLength");

    // $force forces shortening even if it's not strictly needed

    if (mb_strlen($long_url) < $maxUrlLength && !$force) {
        common_debug("Skipped shortening URL.");
        return $long_url;
    }

    $shortenerName = User_urlshortener_prefs::urlShorteningService($user);

    common_debug("Shortener name = '$shortenerName'");

    if (Event::handle('StartShortenUrl', array($long_url, $shortenerName, &$shortenedUrl))) {
        //URL wasn't shortened, so return the long url
        return $long_url;
    } else {
        //URL was shortened, so return the result
        return trim($shortenedUrl);
    }
}

/**
 * @return mixed array($proxy, $ip) for web requests; proxy may be null
 *               null if not a web request
 *
 * @fixme X-Forwarded-For can be chained by multiple proxies;
          we should parse the list and provide a cleaner array
 * @fixme X-Forwarded-For can be forged by clients; only use them if trusted
 * @fixme X_Forwarded_For headers will override X-Forwarded-For read through $_SERVER;
 *        use function to get exact request headers from Apache if possible.
 */
function common_client_ip()
{
    if (!isset($_SERVER) || !array_key_exists('REQUEST_METHOD', $_SERVER)) {
        return null;
    }

    if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
        if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
            $proxy = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $proxy = $_SERVER['REMOTE_ADDR'];
        }
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $proxy = null;
        if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    }

    return array($proxy, $ip);
}

function common_url_to_nickname($url)
{
    static $bad = array('query', 'user', 'password', 'port', 'fragment');

    $parts = parse_url($url);

    # If any of these parts exist, this won't work

    foreach ($bad as $badpart) {
        if (array_key_exists($badpart, $parts)) {
            return null;
        }
    }

    # We just have host and/or path

    # If it's just a host...
    if (array_key_exists('host', $parts) &&
        (!array_key_exists('path', $parts) || strcmp($parts['path'], '/') == 0))
    {
        $hostparts = explode('.', $parts['host']);

        # Try to catch common idiom of nickname.service.tld

        if ((count($hostparts) > 2) &&
            (strlen($hostparts[count($hostparts) - 2]) > 3) && # try to skip .co.uk, .com.au
            (strcmp($hostparts[0], 'www') != 0))
        {
            return common_nicknamize($hostparts[0]);
        } else {
            # Do the whole hostname
            return common_nicknamize($parts['host']);
        }
    } else {
        if (array_key_exists('path', $parts)) {
            # Strip starting, ending slashes
            $path = preg_replace('@/$@', '', $parts['path']);
            $path = preg_replace('@^/@', '', $path);
            $path = basename($path);
            if ($path) {
                return common_nicknamize($path);
            }
        }
    }

    return null;
}

function common_nicknamize($str)
{
    $str = preg_replace('/\W/', '', $str);
    return strtolower($str);
}
