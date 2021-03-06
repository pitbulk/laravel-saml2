<?php

namespace Pitbulk\Saml2;

use OneLogin_Saml2_Auth;
use OneLogin_Saml2_Error;
use OneLogin_Saml2_Utils;

use Log;
use Event;
use Config;
use Psr\Log\InvalidArgumentException;

class Saml2Auth
{
    /**
     * @var \OneLogin_Saml2_Auth
     */
    protected $auth;

    protected $samlAssertion;

    public function __construct($config)
    {
        $this->auth = new OneLogin_Saml2_Auth($config);
    }

    /**
     * @return bool if a valid user was fetched from the saml assertion this request.
     */
    public function isAuthenticated()
    {
        $auth = $this->auth;

        return $auth->isAuthenticated();
    }

    /**
     * The user info from the assertion
     * @return Saml2User
     */
    public function getSaml2User()
    {
        return new Saml2User($this->auth);
    }

    /**
     * Initiate a saml2 login flow. It will redirect! Before calling this, check if user is
     * authenticated (here in saml2). That would be true when the assertion was received this request.
     */
    public function login($returnTo = null)
    {
        $auth = $this->auth;
        $auth->login($returnTo);
    }
    /**
     * Initiate a saml2 logout flow. It will close session on all other SSO services. You should close
     * local session if applicable.
     */
    public function logout($returnTo = null, $nameId = null, $sessionIndex = null)
    {
        $auth = $this->auth;
        if ($returnTo == null) {
            $returnTo = "/";
        }
        $auth->logout($returnTo, [], $nameId, $sessionIndex);
    }

    /**
     * Process a Saml response (assertion consumer service)
     * @throws \Exception when errors are encountered. This sould not happen in a normal flow.
     */
    public function acs()
    {

        /** @var $auth OneLogin_Saml2_Auth */
        $auth = $this->auth;

        $auth->processResponse();

        $errors = $auth->getErrors();

        if (!empty($errors)) {
            return $errors;
        }
        if (!$auth->isAuthenticated()) {
            return array('error' => 'Could not authenticate');
        }
        return null;
    }

    /**
     * Process a Saml response (assertion consumer service)
     * @throws \Exception
     */
    public function sls($retrieveParametersFromServer = false, $keep_local_session = false, $stay = false)
    {
        $auth = $this->auth;

        $session_callback = function () {
            Event::fire('saml2.logoutRequestReceived');
        };
        $auth->processSLO($keep_local_session, null, $retrieveParametersFromServer, $session_callback, $stay);

        $errors = $auth->getErrors();

        return $errors;
    }

    /**
     * Show metadata about the local sp. Use this to configure your saml2 IDP
     * @return mixed xml string representing metadata
     * @throws \InvalidArgumentException if metadata is not correctly set
     */
    public function getMetadata()
    {
        $auth = $this->auth;
        $settings = $auth->getSettings();
        $metadata = $settings->getSPMetadata();
        $errors = $settings->validateMetadata($metadata);

        if (empty($errors)) {
            return $metadata;
        } else {
            throw new InvalidArgumentException(
                'Invalid SP metadata: ' . implode(', ', $errors),
                OneLogin_Saml2_Error::METADATA_SP_INVALID
            );
        }
    }

    /**
     * Wrapper to fetch error reason for the last error
     * @return string  Error reason
     */
    public function getLastErrorReason()
    {
        return $this->auth->getLastErrorReason();
    }
}
