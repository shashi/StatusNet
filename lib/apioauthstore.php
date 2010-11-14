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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR . '/lib/oauthstore.php';

class ApiStatusNetOAuthDataStore extends StatusNetOAuthDataStore
{
    function lookup_consumer($consumerKey)
    {
        $con = Consumer::staticGet('consumer_key', $consumerKey);

        if (!$con) {

            // Create an anon consumer and anon application if one
            // doesn't exist already
            if ($consumerKey == 'anonymous') {

                common_debug("API OAuth - creating anonymous consumer");
                $con = new Consumer();
                $con->consumer_key    = $consumerKey;
                $con->consumer_secret = $consumerKey;
                $con->created         = common_sql_now();

                $result = $con->insert();
                if (!$result) {
                    // TRANS: Server error displayed when trying to create an anynymous OAuth consumer.
                    $this->serverError(_('Could not create anonymous consumer.'));
                }

                $app = Oauth_application::getByConsumerKey('anonymous');

                if (!$app) {
                    common_debug("API OAuth - creating anonymous application");
                    $app               = new OAuth_application();
                    $app->owner        = 1; // XXX: What to do here?
                    $app->consumer_key = $con->consumer_key;
                    $app->name         = 'anonymous';
                    $app->icon         = 'default-avatar-stream.png'; // XXX: Fix this!
                    $app->description  = "An anonymous application";
                    // XXX: allow the user to set the access type when
                    // authorizing? Currently we default to r+w for anonymous
                    // OAuth client applications
                    $app->access_type  = 3; // read + write
                    $app->type         = 2; // desktop
                    $app->created      = common_sql_now();

                    $id = $app->insert();

                    if (!$id) {
			// TRANS: Server error displayed when trying to create an anynymous OAuth application.
                        $this->serverError(_("Could not create anonymous OAuth application."));
                    }
                }
            } else {
                return null;
            }
        }

        return new OAuthConsumer(
            $con->consumer_key,
            $con->consumer_secret
        );
    }

    function getAppByRequestToken($token_key)
    {
        // Look up the full req token
        $req_token = $this->lookup_token(
            null,
            'request',
            $token_key
        );

        if (empty($req_token)) {
            common_debug("couldn't get request token from oauth datastore");
            return null;
        }

        // Look up the full Token
        $token = new Token();
        $token->tok = $req_token->key;
        $result = $token->find(true);

        if (empty($result)) {
            common_debug('Couldn\'t find req token in the token table.');
            return null;
        }

        // Look up the app
        $app = new Oauth_application();
        $app->consumer_key = $token->consumer_key;
        $result = $app->find(true);

        if (!empty($result)) {
            return $app;
        } else {
            common_debug("Couldn't find the app!");
            return null;
        }
    }

    function new_access_token($token, $consumer, $verifier)
    {
        common_debug(
            sprintf(
                "New access token from request token %s, consumer %s and verifier %s ",
                $token,
                $consumer,
                $verifier
            ),
            __FILE__
        );

        $rt = new Token();

        $rt->consumer_key = $consumer->key;
        $rt->tok          = $token->key;
        $rt->type         = 0; // request

        $app = Oauth_application::getByConsumerKey($consumer->key);
        assert(!empty($app));

        if ($rt->find(true) && $rt->state == 1 && $rt->verifier == $verifier) { // authorized

            common_debug('Request token found.', __FILE__);

            // find the app and profile associated with this token
            $tokenAssoc = Oauth_token_association::staticGet('token', $rt->tok);

            if (!$tokenAssoc) {
                throw new Exception(
                    // TRANS: Exception thrown when no token association could be found.
                    _('Could not find a profile and application associated with the request token.')
                );
            }

            // check to see if we have previously issued an access token for this application
            // and profile

            $appUser = new Oauth_application_user();

            $appUser->application_id = $app->id;
            $appUser->profile_id     = $tokenAssoc->profile_id;

            $result = $appUser->find(true);

            if (!empty($result)) {

                common_log(LOG_INFO,
                     sprintf(
                        "Existing access token found for application %s, profile %s.",
                        $app->id,
                        $tokenAssoc->profile_id
                     )
                );

                $at = new Token();

                // fetch the full access token
                $at->consumer_key = $consumer->key;
                $at->tok          = $appUser->token;

                $result = $at->find(true);

                if (!$result) {
                    throw new Exception(
                        // TRANS: Exception thrown when no access token can be issued.
                        _('Could not issue access token.')
                    );
                }

                // Yay, we can re-issue the access token
                return new OAuthToken($at->tok, $at->secret);

            } else {

               common_log(LOG_INFO,
                    sprintf(
                        "Creating new access token for application %s, profile %s.",
                        $app->id,
                        $tokenAssoc->profile_id
                     )
                );

                // make a brand new access token
                $at = new Token();

                $at->consumer_key      = $consumer->key;
                $at->tok               = common_good_rand(16);
                $at->secret            = common_good_rand(16);
                $at->type              = 1; // access
                $at->verifier          = $verifier;
                $at->verified_callback = $rt->verified_callback; // 1.0a
                $at->created           = common_sql_now();

                if (!$at->insert()) {
                    $e = $at->_lastError;
                    common_debug('access token "' . $at->tok . '" not inserted: "' . $e->message . '"', __FILE__);
                    return null;
                } else {
                    common_debug('access token "' . $at->tok . '" inserted', __FILE__);
                    // burn the old one
                    $orig_rt   = clone($rt);
                    $rt->state = 2; // used
                    if (!$rt->update($orig_rt)) {
                        return null;
                    }
                    common_debug('request token "' . $rt->tok . '" updated', __FILE__);
                }

                // insert a new Oauth_application_user record w/access token
                $appUser = new Oauth_application_user();

                $appUser->profile_id     = $tokenAssoc->profile_id;;
                $appUser->application_id = $app->id;
                $appUser->access_type    = $app->access_type;
                $appUser->token          = $at->tok;
                $appUser->created        = common_sql_now();

                $result = $appUser->insert();

                if (!$result) {
                    common_log_db_error($appUser, 'INSERT', __FILE__);
                    // TRANS: Server error displayed when a database error occurs.
                    $this->serverError(_('Database error inserting OAuth application user.'));
                }

                // Okay, good
                return new OAuthToken($at->tok, $at->secret);
            }

        } else {

            // the token was not authorized or not verfied
            common_log(
                LOG_INFO,
                sprintf(
                    "API OAuth - Attempt to exchange unauthorized or unverified request token %s for an access token.",
                     $rt->tok
                )
            );
            return null;
        }
    }

    /**
     * Revoke specified access token
     *
     * Revokes the token specified by $token_key.
     * Throws exceptions in case of error.
     *
     * @param string $token_key the token to be revoked
     * @param int    $type      type of token (0 = req, 1 = access)
     *
     * @access public
     *
     * @return void
     */
    public function revoke_token($token_key, $type = 0) {
        $rt        = new Token();
        $rt->tok   = $token_key;
        $rt->type  = $type;
        $rt->state = 0;

        if (!$rt->find(true)) {
            // TRANS: Exception thrown when an attempt is made to revoke an unknown token.
            throw new Exception(_('Tried to revoke unknown token.'));
        }

        if (!$rt->delete()) {
            // TRANS: Exception thrown when an attempt is made to remove a revoked token.
            throw new Exception(_('Failed to delete revoked token.'));
        }
    }

    /*
     * Create a new request token. Overrided to support OAuth 1.0a callback
     *
     * @param OAuthConsumer $consumer the OAuth Consumer for this token
     * @param string        $callback the verified OAuth callback URL
     *
     * @return OAuthToken   $token a new unauthorized OAuth request token
     */
    function new_request_token($consumer, $callback)
    {
        $t = new Token();
        $t->consumer_key = $consumer->key;
        $t->tok = common_good_rand(16);
        $t->secret = common_good_rand(16);
        $t->type = 0; // request
        $t->state = 0; // unauthorized
        $t->verified_callback = $callback;

        if ($callback === 'oob') {
            // six digit pin
            $t->verifier = mt_rand(0, 9999999);
        } else {
            $t->verifier = common_good_rand(8);
        }

        $t->created = DB_DataObject_Cast::dateTime();
        if (!$t->insert()) {
            return null;
        } else {
            return new OAuthToken($t->tok, $t->secret);
        }
    }
}
