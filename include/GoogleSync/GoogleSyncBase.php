<?php

/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2018 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo and "Supercharged by SuiteCRM" logo. If the display of the logos is not
 * reasonably feasible for technical reasons, the Appropriate Legal Notices must
 * display the words "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once __DIR__ . '/../../modules/Users/User.php';
require_once __DIR__ . '/../../modules/Meetings/Meeting.php';
require_once 'GoogleSyncExceptions.php';

use SuiteCRM\Utility\SuiteValidator;

/**
 * Implements Google Calendar Syncing
 *
 * @license https://raw.githubusercontent.com/salesagility/SuiteCRM/master/LICENSE.txt
 * GNU Affero General Public License version 3
 * @author Benjamin Long <ben@offsite.guru>
 */

class GoogleSyncBase
{
    /** @var User The SuiteCRM User Bean we're currently working with */
    protected $workingUser;

    /** @var \Google_Client The Google client object for the current sync job */
    protected $gClient;

    /** @var \Google_Service_Calendar The Google Calendar Service Object */
    protected $gService;

    /** @var array The Google AuthcConfig json */
    protected $authJson = array();

    /** @var string The local timezone */
    protected $timezone;

    /** @var string The Calendar ID */
    protected $calendarId;

    /** @var array An array of SuiteCRM meeting id's that we've already synced this session */
    protected $syncedList = array();

    /** @var object A Database Instance */
    protected $db;

    /** @var object A Logger Instance */
    protected $logger;

    /** @var string The log level before we begin */
    protected $oldLogLevel;

    /**
     * Class Constructor
     */
    public function __construct()
    {
        // This sets the log level to a variable that can be set on the command line while running cron.php on the server. It's for debugging only.
        // EXAMPLE: $ GSYNC_LOGLEVEL=debug php cron.php
        $this->logger = LoggerManager::getLogger();
        if (isset($_SERVER['GSYNC_LOGLEVEL'])) {
            $this->oldLogLevel = $this->logger->getLogLevel();
            $this->logger->setLevel($_SERVER['GSYNC_LOGLEVEL']);
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Log Level Set To: ' . $_SERVER['GSYNC_LOGLEVEL']);
        }
        $this->timezone = date_default_timezone_get(); // This defaults to the server timezone. Overridden later.
        $this->authJson = $this->getAuthJson();
        $this->db = DBManagerFactory::getInstance();
        $this->logger->debug(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . '__construct');
    }

    /**
     * Class Destructor
     */
    public function __destruct()
    {
        // Set the log level back to the original value
        $this->logger->debug(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . '__destruct');
        if (isset($this->oldLogLevel)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Setting Log Level Back To: ' . $this->oldLogLevel);
            $this->logger->setLevel($this->oldLogLevel);
        }
    }

    /**
     * Gets the auth json string from the system
     *
     * @return array|false json on success, false on failure
     */
    protected function getAuthJson()
    {
        global $sugar_config;
        $authJson_local = json_decode(base64_decode($sugar_config['google_auth_json']), true);
        if (!$authJson_local) {
            // The authconfig json string is invalid json
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'AuthConfig is not proper JSON string');
            return false;
        } elseif (!array_key_exists('web', $authJson_local)) {
            // The authconfig is valid json, but the 'web' key is missing. This is not a valid authconfig.
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'AuthConfig is missing required [web] key');
            return false;
        }
        return $authJson_local;
    }

    /**
     * Creates and Sets the Google client in the object
     *
     * @param string $id : the SuiteCRM user id
     *
     * @return bool Success/Failure
     */
    protected function setClient($id)
    {
        $isValidator = new SuiteValidator();
        if (!$isValidator->isValidId($id)) {
            throw new E_ValidationFailure();
        }
        
        if (!$gClient_local = $this->getClient($id)) {
            return false;
        }
        $this->gClient = $gClient_local;
        return true;
    }

    /**
     * Set the Google client up for the user by id
     *
     * @param string $id : the SuiteCRM user id
     *
     * @return \Google_Client|false Google_Client on success. False on failure.
     * @throws E_RecordRetrievalFail if unable to retrive the user
     */
    protected function getClient($id)
    {
        $isValidator = new SuiteValidator();
        if (!$isValidator->isValidId($id)) {
            throw new E_ValidationFailure();
        }

        // Retrieve user bean
        if (!isset($this->workingUser)) {
            $this->workingUser = BeanFactory::getBean('Users', $id);
            if (!$this->workingUser) {
                throw new E_RecordRetrievalFail('Unable to retrieve a User bean');
            }
        }

        // Retrieve Access Token JSON from user preference
        $accessToken = json_decode(base64_decode($this->workingUser->getPreference('GoogleApiToken', 'GoogleSync')), true);
        if (!array_key_exists('access_token', $accessToken)) {
            // The Token is invalid JSON or missing
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Invalid or Missing AuthToken');
            return false;
        }
        // The refresh token is only provided once, on first authentication. It must be added afterwards.
        if (!array_key_exists('refresh_token', $accessToken)) {
            $accessToken['refresh_token'] = base64_decode($this->workingUser->getPreference('GoogleApiRefreshToken', 'GoogleSync'));
        }

        // New Google Client and refresh the token if needed
        try {
            $client = $this->getGoogleClient($accessToken);
        } catch (Exception $e) {
            $this->logger->fatal('Caught exception: ',  $e->getMessage());
        }

        if (!$client) {
            return false;
        }
        return $client;
    }

    /**
     * New Google Client and refresh the token if needed
     *
     * @param array $accessToken
     * @return \Google_Client or false on Exception
     * @throws E_NoRefreshToken If the refresh token is missing
     * @throws Exception rethrows if caught from Google_Client::fetchAccessTokenWithRefreshToken
     */
    protected function getGoogleClient($accessToken)
    {
        // New Google Client
        $client = new Google_Client();
        $client->setApplicationName('SuiteCRM');
        $client->setScopes(Google_Service_Calendar::CALENDAR);
        $client->setAccessType('offline');
        $client->setAuthConfig($this->authJson);
        $client->setAccessToken($accessToken);

        // Refresh the token if needed
        if ($client->isAccessTokenExpired()) {
            $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Refreshing Access Token');
            $refreshToken = $client->getRefreshToken();
            if (!empty($refreshToken)) {
                try {
                    $client->fetchAccessTokenWithRefreshToken($refreshToken);
                } catch (Exception $e) {
                    $this->logger->fatal('Caught exception: ' . $e->getMessage());
                    throw $e;
                }
                // Save new token to user preference
                $this->workingUser->setPreference('GoogleApiToken', base64_encode(json_encode($client->getAccessToken())), 'GoogleSync');
                $this->workingUser->savePreferencesToDB();    
            } elseif (empty($refreshToken)) {
                throw new E_NoRefreshToken('Refresh token is missing');
            }
        }
        return $client;
    }

    /**
     * Initialize Service for User
     * 
     * @param string $id The SuiteCRM user id
     * 
     * @return bool Success/Failure
     * @throws E_ValidationFailureif $id is invalid
     * @throws E_RecordRetrievalFail if unable to retrive the user
     * @throws E_GoogleClientFailure if Google Client fails to set up
     * @throws E_TimezoneSetFailure if timezone set fails
     * @throws E_GoogleServiceFailure if unable to setup Google Service
     * @throws E_GoogleCalendarFailure if unable to setup Google Calendar Id
     */
    protected function initUserService($id)
    {
        $isValidator = new SuiteValidator();
        if (!$isValidator->isValidId($this->db->quote($id))) {
            throw new E_ValidationFailure('Invalid ID requested in initUserService');
        }

        // Retrieve user bean
        $this->workingUser = null;
        $this->workingUser = BeanFactory::getBean('Users', $id);
        if (!$this->workingUser) {
            throw new E_RecordRetrievalFail('Unable to retrieve a User bean');
        }

        if (!$this->setClient($id)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unable to setup Google Client');
            throw new E_GoogleClientFailure('Unable to setup Google Client');
        }

        if (!$this->setTimezone($this->workingUser->getPreference('timezone', 'global'))) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Failed to set the working user and timezone');
            throw new E_TimezoneSetFailure('Failed to set the working user\'s timezone');
        }

        if (!$this->setGService()) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unable to setup Google Service');
            throw new E_GoogleServiceFailure('Unable to setup Google Service');
        }

        if (!$this->setUsersGoogleCalendar()) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unable to setup Google Calendar Id');
            throw new E_GoogleCalendarFailure('Unable to setup Google Calendar Id');
        }
        return true;
    }

    /**
     * Retrieve List of meetings owned by the Current Working User
     *
     *
     * @return array Array of SuiteCRM Meeting Beans
     * @throws E_ValidationFailure if $this->workingUser->id is invalid
     * @throws E_RecordRetrievalFail if unable to get Meetings bean
     */
    protected function getUserMeetings($userId)
    {
        $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Id ' . $userId);
        // Validate the workingUser id
        $isValidator = new SuiteValidator();
        if (!$isValidator->isValidId($userId)) {
            throw new E_ValidationFailure('Invalid ID requested in getUserMeetings');
        }

        // We do it this way so we also get deleted meetings
        $query = "SELECT id FROM meetings WHERE assigned_user_id = '" . $userId . "' AND date_start <= now() + interval 3 month";
        $result = $this->db->query($query);

        $meetings = array();
        while ($row = $this->db->fetchByAssoc($result)) {
            $meeting = BeanFactory::getBean('Meetings');
            if (!$meeting) {
                throw new E_RecordRetrievalFail('Unable to get Meetings bean.');
            }
            $meeting->retrieve($row['id'], true, false);
            $meetings[] = $meeting;
        }
        return $meetings;
    }

    /**
     * Set user's google calendar id
     *
     *
     * @return bool|int Success/Failure calendar ID if success
     */
    protected function setUsersGoogleCalendar()
    {

        // Make sure we have a Google Calendar Service instance
        if (!$this->isServiceExists()) {
            return false;
        }

        // get list of users calendars
        $calendarList = $this->gService->calendarList->listCalendarList();

        // find the id of the 'SuiteCRM' calendar ... in the future, this will set the calendar of the users choosing.
        $this->calendarId = $this->getSuiteCRMCalendar($calendarList);

        // if the SuiteCRM calendar doesn't exist... Create it!
        if (!$this->isCalendarExists()) {
            $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unable to find the SuiteCRM Google Calendar, Creating it!');
            $calendar = new Google_Service_Calendar_Calendar();
            $calendar->setSummary('SuiteCRM');
            $calendar->setTimeZone($this->timezone);

            $createdCalendar = $this->gService->calendars->insert($calendar);
            $this->calendarId = $createdCalendar->getId();
        }

        // Final check to make sure we have an ID
        if (!$this->isCalendarExists()) {
            return false;
        }
        return $this->calendarId;
    }

    /**
     * find the id of the 'SuiteCRM' calendar ... in the future, this will return the calendar of the users choosing.
     *
     * @param Google_Service_Calendar_CalendarList $calendarList
     * 
     * @return string|null Matching Google Calendar ID or null.
     */
    protected function getSuiteCRMCalendar(Google_Service_Calendar_CalendarList $calendarList)
    {
        foreach ($calendarList->getItems() as $calendarListEntry) {
            if ($calendarListEntry->getSummary() == 'SuiteCRM') {
                $return = $calendarListEntry->getId();
                break;
            }
        }
        if (empty($return)) {
            return null;
        }
        return $return;
    }

    /**
     * Get events in users google calendar
     *
     *
     * @return bool|array Array of Google_Service_Calendar_Event Objects
     */
    protected function getUserGoogleEvents()
    {

        // Make sure we have a Google Calendar Service instance and
        // Make sure we have a calendar id
        if (!$this->isServiceExists() || !$this->isCalendarExists()) {
            return false;
        }

        // Set Options for what events we get from Google
        $optParams = array(
            'maxResults' => 500,
            'showDeleted' => true,
            'singleEvents' => true,
            'timeMin' => date('c', strtotime('-1 month')),
            'timeMax' => date('c', strtotime('+3 month')),
        );

        $results_g = $this->gService->events->listEvents($this->calendarId, $optParams);

        // We only want the events, not the leading cruft
        $results = $results_g->getItems();

        if (empty($results)) {
            $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'No events found.');
            return array();
        }
        $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Found ' . count($results) . ' Google Events');
        return $results;

    }

    /**
     * Make sure we have a Google Calendar Service instance
     *
     * @return boolean
     */
    protected function isServiceExists()
    {
        // Make sure we have a Google Calendar Service instance
        if (!isset($this->gService)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'The Google Service is not set up. See setGService Method.');
            return false;
        }
        return true;
    }

    /**
     * Make sure we have a calendar id
     *
     * @return boolean
     */
    protected function isCalendarExists()
    {
        // Make sure we have a calendar id
        if (!isset($this->calendarId)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'The calendar ID is not set. See setUsersGoogleCalendar Method');
            return false;
        }
        return true;
    }

    /**
     * Get a google event by the event id
     *
     * @param string $event_id Google Event ID
     *
     * @return \Google_Service_Calendar_Event|null Google_Service_Calendar_Event if found, null if not found
     * @throws E_MissingParameters if $event_id is empty
     * @throws E_GoogleServiceFailure if Google Service not set up
     */
    protected function getGoogleEventById($event_id)
    {

        if (empty($event_id)) {
            // If we didn't get passed an event id, throw an exception
            throw new E_MissingParameters('event ID is empty');
        }

        // Make sure the calendar service is set up
        if (!$this->isServiceExists()) {
            throw new E_GoogleServiceFailure('Cannot Continue Without Google Service');
        }

        $gEvent = $this->gService->events->get($this->calendarId, $event_id);

        if (!empty($gEvent->getId())) {
            return $gEvent;
        }
        return null;
    }

    /**
     * Get a SuiteCRM meeting by Google Event ID
     *
     * @param string $event_id The Google Event ID
     *
     * @return \Meeting|null SuiteCRM Meeting Bean if found, null if not found
     * @throws E_DbDataError if more than one meeting matches $event_id
     * @throws E_RecordRetrievalFail If unable to retrieve meeting bean
     */
    protected function getMeetingByEventId($event_id)
    {

        // We do it this way so we also get deleted meetings
        $eventIdQuoted = $this->db->quoted($event_id);
        $query = "SELECT id FROM meetings WHERE gsync_id = {$eventIdQuoted}";
        $result = $this->db->query($query);

        // This checks to make sure we only get one result. If we get more than one, something is inconsistant in the DB
        if ($result->num_rows > 1) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'More than one meeting matches Google Id: ' . $eventIdQuoted);
            throw new E_DbDataError('More than one meeting matches Google Id!');
        } elseif ($result->num_rows == 0) {
            return null; // No matches Found
        }

        $meeting = BeanFactory::getBean('Meetings');
        if (!$meeting) {
            throw new E_RecordRetrievalFail('Unable to get Meetings bean.');
        }
        $row = $this->db->fetchByAssoc($result);
        $meeting->retrieve($row['id'], true, false);

        return $meeting;
    }

    /**
     * Creates and Sets $this->gService to a valid Google Calendar Service
     *
     * @return bool Success/Failure
     */
    protected function setGService()
    {
        // make sure we have a client set
        if (!isset($this->gClient)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'The Google Client is not set up. See setClient Method');
            return false;
        }

        // create new calendar service
        $this->gService = new Google_Service_Calendar($this->gClient);
        if ($this->isServiceExists()) {
            return true;
        }
        $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Setting $this->gService Failed');
        return false;
    }

    /**
     * Push event from SuiteCRM to Google Calendar
     *
     * If the google event is not provided, a new one will be created
     * and inserted. If one is provided, the existing Google Event will
     * be updated.
     *
     * @param Meeting $event_local : SuiteCRM Meeting Bean
     * @param \Google_Service_Calendar_Event $event_remote (optional) \Google_Service_Calendar_Event Object
     *
     * @return string|bool Meeting Id on success, false on failure
     */
    protected function pushEvent(Meeting $event_local, Google_Service_Calendar_Event $event_remote = null)
    {
        if (!isset($event_remote) || empty($event_remote)) {
            $event = $this->createGoogleCalendarEvent($event_local);
            $return = $this->gService->events->insert($this->calendarId, $event);
        } elseif (isset($event_remote)) {
            $event = $this->updateGoogleCalendarEvent($event_local, $event_remote);
            $return = $this->gService->events->update($this->calendarId, $event->getId(), $event);
        }

        /* We don't get a status code back showing success. Instead, the return of the
         * create or update is the Google_Service_Calendar_Event object after saving.
         * So we check to make sure it has an ID to determine Success/Failure.
         */
        if (!isset($return->id)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'GCalendar insert/update failed.');
            return false;
        }

        // Set the SuiteCRM Meeting's last sync timestamp, and google id
        $ret = $this->setLastSync($event_local, $return->getId());
        if (empty($ret)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'setLastSync returned error.');
            return false;
        }

        return $ret;
    }

    /**
     * Helper method to get a Google_Service_Calendar_EventExtendedProperties object for the Google event
     *
     * Takes the local and remote events, and returns a Google_Service_Calendar_EventExtendedProperties
     *
     * @param \Google_Service_Calendar_Event $event_remote \Google_Service_Calendar_Event Object
     * @param Meeting $event_local Meeting (optional) \Meeting Bean
     *
     * @return Google_Service_Calendar_EventExtendedProperties object
     */
    protected function returnExtendedProperties(Google_Service_Calendar_Event $event_remote, Meeting $event_local)
    {
        // We pull the existing extendedProperties, and change our values
        // That way we don't mess with anything else that's using other values.
        $extendedProperties = $event_remote->getExtendedProperties();

        if (!empty($extendedProperties)) {
            $private = $extendedProperties->getPrivate();
        } elseif (empty($extendedProperties)) {
            $extendedProperties = new Google_Service_Calendar_EventExtendedProperties;
            $private = array();
        }

        $private['suitecrm_id'] = $event_local->id;
        $private['suitecrm_type'] = $event_local->module_name;

        $extendedProperties->setPrivate($private);

        return $extendedProperties;
    }

    /**
     * Pull event from Google Calendar to SuiteCRM
     *
     * If the SuiteCRM Meeting is not provided, a new one will be created
     * and inserted. If one is provided, the existing meeting will be updated.
     *
     * @param \Google_Service_Calendar_Event $event_remote \Google_Service_Calendar_Event Object
     * @param Meeting $event_local Meeting (optional) \Meeting Bean
     *
     * @return bool Success/Failure of setLastSync, since that's what saves the record
     */
    protected function pullEvent(Google_Service_Calendar_Event $event_remote, Meeting $event_local = null)
    {
        if (!isset($event_local) || empty($event_local)) {
            $event = $this->createSuitecrmMeetingEvent($event_remote);
        } elseif (isset($event_local)) {
            $event = $this->updateSuitecrmMeetingEvent($event_local, $event_remote);
        }

        if (empty($event)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Something Horrible Happened in [create|update]SuitecrmMeetingEvent!');
            return false;
        }

        // We need to set the suitecrm_ private properties in the Google event here,
        // Otherwise it's seen as a new event next time we sync
        $extendedProperties = $this->returnExtendedProperties($event_remote, $event);
        $event_remote->setExtendedProperties($extendedProperties);

        $greturn = $this->gService->events->update($this->calendarId, $event_remote->getId(), $event_remote);

        /* We don't get a status code back showing success. Instead, the return of the
         * create or update is the Google_Service_Calendar_Event object after saving.
         * So we check to make sure it has an ID to determine Success/Failure.
         */
        if (isset($greturn->id)) {
            return $this->setLastSync($event, $event_remote->getId());
        }
        return false;
    }

    /**
     * Delete SuiteCRM Meeting
     *
     * @param Meeting $meeting SuiteCRM Meeting Bean
     *
     * @return string|bool Meeting Id on success, false on failure (from setLastSync, since that's what saves the record)
     */
    protected function delMeeting(Meeting $meeting)
    {
        $meeting->deleted = '1';
        $meeting->gsync_id = '';
        return $this->setLastSync($meeting);
    }

    /**
     * Delete Google Event
     *
     * @param \Google_Service_Calendar_Event $event \Google_Service_Calendar_Event Object
     * @param String $meeting_id SuiteCRM Meeting Id
     *
     * @return string|bool Meeting Id on success, false on failure
     */
    protected function delEvent(Google_Service_Calendar_Event $event, $meeting_id)
    {
        // Make sure the calendar service is set up
        if (!$this->isServiceExists()) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'The Google Service is not set up. See setGService Method.');
            return false;
        }

        // Make sure we got a meeting_id
        if (!isset($meeting_id)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'This method requires a meeting id as the 2nd parameter');
            return false;
        }

        // Validate and quote the meetingID
        $valMeetingId = $this->db->quote($meeting_id);
        $isValidator = new SuiteValidator();
        if (!$isValidator->isValidId($valMeetingId)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Meeting ID could not be validated');
            return false;
        }

        $return = $this->gService->events->delete($this->calendarId, $event->getId());

        // Pull the status code returned to determine Success/Failure
        $statusCode = $return->getStatusCode();

        if ($statusCode >= 200 && $statusCode <= 299) {
            // 2xx statusCode = success
            $this->logger->debug(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Received Success Status Code: ' . $statusCode . ' on delete.');

            // This removes the gsync_id reference from the table.
            $sql = "UPDATE meetings SET gsync_id = '' WHERE id = {$valMeetingId}";
            $res = $this->db->query($sql);
            if (!$res) {
                $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Failed to remove gsync_id from record' . $valMeetingId);
            }
            //$this->syncedList[] = $meeting_id;
            return $meeting_id;
        }
        $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Received Failure Status Code: ' . $statusCode . ' on delete!');
        return false;
    }

    /**
     * Helper method. Clear all popup reminders from crm meeting
     *
     * @param string $event_id The ID of the event in the DB
     *
     * @return bool Success/Failure
     */
    protected function clearPopups($event_id)
    {
        if (!isset($event_id) || empty($event_id)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Event_id is missing');
            return false;
        }

        // Disable all popup reminders for the SuiteCRM meeting, and mark reminders where email is disabled as deleted.
        $eventIdQuoted = $this->db->quoted($event_id);
        $sql = sprintf("UPDATE reminders SET popup = '0', deleted = CASE WHEN email = '0' THEN '1' ELSE deleted	END WHERE related_event_module_id = %s AND deleted = '0'", $eventIdQuoted);
        $res = $this->db->query($sql);
        if (!$res) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'SQL Failure!');
            return false;
        }
        return true;
    }

    /**
     * Update SuiteCRM Meeting from Google Calendar Event
     *
     * @param Meeting $event_local SuiteCRM Meeting Bean
     * @param \Google_Service_Calendar_Event $event_remote Google_Service_Calendar_Event Object
     *
     * @return Meeting|bool SuiteCRM Meeting Bean or false on failure
     * @throws E_GoogleRecordParseFailure if the Google Event is missing required data
     */
    protected function updateSuitecrmMeetingEvent(Meeting $event_local, Google_Service_Calendar_Event $event_remote)
    {

        $event_local->name = (string) $event_remote->getSummary();

        if (empty($event_local->name)) { // Google doesn't require titles on events.
            $event_local->name = '(No title)'; // This is what they look like in google, so it should be seamless.
        }

        $event_local->description = (string) $event_remote->getDescription();
        $event_local->location = (string) $event_remote->getLocation();

        // Get Start/End/Duration from Google Event TODO: This is where all day event conversion will need to happen.
        $starttime = strtotime($event_remote->getStart()->getDateTime());
        $endtime = strtotime($event_remote->getEnd()->getDateTime());
        if (!$starttime || !$endtime) { // Verify we have valid time objects (All day events will fail here.)
            throw new E_GoogleRecordParseFailure('Unable to retrieve times from Google Event');
        }
        $diff = abs($starttime - $endtime);
        $tmins = $diff / 60;
        $hours = floor($tmins / 60);
        $mins = $tmins % 60;

        // Set Start/End/Duration in SuiteCRM Meeting and Assigned User
        $event_local->date_start = gmdate("Y-m-d H:i:s", $starttime);
        $event_local->date_end = gmdate("Y-m-d H:i:s", $endtime);
        $event_local->duration_hours = $hours;
        $event_local->duration_minutes = $mins;
        $event_local->assigned_user_id = $this->workingUser->id;

        // Disable all popup reminders for the SuiteCRM meeting. We add them back from Google event below.
        $event_id = $event_local->id;
        $res = $this->clearPopups($event_id);
        if (empty($res)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'clearPopups() returned error');
        }

        // Get Google Event Popup Reminders
        $gReminders = $event_remote->getReminders();
        $overrides = $gReminders->getOverrides();

        // Create a new popup reminder for each google reminder
        $helper = new GoogleSyncHelper;
        $nestedArray = $helper->createSuitecrmReminders($overrides, $event_local);
        $reminders = $nestedArray[0];
        $invitees = $nestedArray[1];

        foreach($reminders as $reminder) {
            $reminder->save(false);
        }

        foreach($invitees as $invitee) {
            $invitee->save(false);
        }

        return $event_local;
    }

    /**
     * Create SuiteCRM Meeting event
     *
     * @param \Google_Service_Calendar_Event $event_remote The Google_Service_Calendar_Event we're creating a SuiteCRM Meeting for
     *
     * @return Meeting|bool SuiteCRM Meeting Bean or false on failure
     * @throws E_RecordRetrievalFail if fails to retrive meeting
     */
    protected function createSuitecrmMeetingEvent(Google_Service_Calendar_Event $event_remote)
    {
        $this->logger->debug(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Creating New SuiteCRM Meeting');
        $meeting = BeanFactory::getBean('Meetings');
        if (!$meeting) {
            throw new E_RecordRetrievalFail('Unable to get Meeting bean.');
        }
        $meeting->id = create_guid();
        $meeting->new_with_id = true;
        $event_local = $this->updateSuitecrmMeetingEvent($meeting, $event_remote);
        return $event_local;
    }

    /**
     * Update Google Calendar Event from SuiteCRM Meeting
     *
     * @param Meeting $event_local SuiteCRM Meeting Bean
     * @param \Google_Service_Calendar_Event $event_remote Google Event Object
     *
     * @return \Google_Service_Calendar_Event
     */
    protected function updateGoogleCalendarEvent(Meeting $event_local, Google_Service_Calendar_Event $event_remote)
    {
        if ((!isset($event_local) || empty($event_local)) || (!isset($event_remote) || empty($event_remote))) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'ERROR:Missing Variables');
            return false;
        }

        $event_remote->setSummary($event_local->name);
        $event_remote->setDescription($event_local->description);
        $event_remote->setLocation($event_local->location);

        $timedate = new TimeDate;
        $localStart = $timedate->to_db($event_local->date_start, false);
        $localEnd = $timedate->to_db($event_local->date_end, false);

        $startDateTime = new Google_Service_Calendar_EventDateTime;
        $startDateTime->setDateTime(date(DATE_ATOM, strtotime($localStart . ' UTC')));
        $startDateTime->setTimeZone($this->timezone);
        $event_remote->setStart($startDateTime);

        $endDateTime = new Google_Service_Calendar_EventDateTime;
        $endDateTime->setDateTime(date(DATE_ATOM, strtotime($localEnd . ' UTC')));
        $endDateTime->setTimeZone($this->timezone);
        $event_remote->setEnd($endDateTime);

        $extendedProperties = $this->returnExtendedProperties($event_remote, $event_local);
        $event_remote->setExtendedProperties($extendedProperties);

        // Copy over popup reminders
        $event_local_id = $this->db->quoted($event_local->id);
        $reminders_local = BeanFactory::getBean('Reminders')->get_full_list(
                "", "reminders.related_event_module = 'Meetings'" .
                " AND reminders.related_event_module_id = $event_local_id" .
                " AND popup = '1'"
        );

        if ($reminders_local) {
            $reminders_remote = new Google_Service_Calendar_EventReminders;
            $reminders_remote->setUseDefault(false);
            $reminders_array = array();
            foreach ($reminders_local as $reminder_local) {
                $reminder_remote = new Google_Service_Calendar_EventReminder;
                $reminder_remote->setMethod('popup');
                $reminder_remote->setMinutes($reminder_local->timer_popup / 60);
                $reminders_array[] = $reminder_remote;
            }
            $reminders_remote->setOverrides($reminders_array);
            $event_remote->setReminders($reminders_remote);
        }
        return $event_remote;
    }

    /**
     * Create New Google Event object for SuiteCRM Meeting
     *
     * @param Meeting $event_local SuiteCRM Meeting Bean
     *
     * @return \Google_Service_Calendar_Event Google_Service_Calendar_Event Object
     */
    protected function createGoogleCalendarEvent(Meeting $event_local)
    {

        //We're creating a new event
        $event_remote_empty = new Google_Service_Calendar_Event;

        $extendedProperties = new Google_Service_Calendar_EventExtendedProperties;
        $extendedProperties->setPrivate(array());

        $event_remote_empty->setExtendedProperties($extendedProperties);

        //Set the Google Event up to match the SuiteCRM one
        $event_remote = $this->updateGoogleCalendarEvent($event_local, $event_remote_empty);

        return $event_remote;
    }

    /**
     * Set the timezone
     *
     * @param string $timezone : timezone_identifier (ie. America/New_York)
     *
     * @return bool Success/Failure
     */
    protected function setTimezone($timezone)
    {
        if (date_default_timezone_set($timezone)) {
            $this->timezone = date_default_timezone_get();
            $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Timezone set to \'' . $this->timezone . '\'');
            return true;
        }
        $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Failed to set timezone to \'' . $timezone . '\'');
        return false;
    }

    /**
     * Set the last sync time for the record
     *
     * This *must* be called *after* the sync is done
     * This also saves the event, so you don't need to do it twice. Just call this.
     * Also adds the id of the event so it doesn't get synced again this session.
     *
     * @param Meeting $event_local SuiteCRM Meeting bean
     * @param string $gEventId (optional) The ID that Google has for the event.
     *
     * @return string|bool Meeting Id on success, false on failure
     */
    protected function setLastSync(Meeting $event_local, $gEventId = null)
    {
        if (isset($gEventId)) {
            $event_local->gsync_id = $gEventId;
        }

        $event_local->gsync_lastsync = time() + 3; // we add three seconds to this, so that the modified time is always older than the gsync_lastsync time
        $return = $event_local->save(false);

        $isValidator = new SuiteValidator();
        if ($isValidator->isValidId($return)) {
            $event_local->set_accept_status($this->workingUser, 'accept');  // Set the meeting as accepted by the user, otherwise it doesn't show up on the calendar. We do it here because it must be saved first.
            //$this->syncedList[] = $event_local->id;
            return $event_local->id;
        }
        $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Something went wrong saving the local record.');
        return false;
    }
}
