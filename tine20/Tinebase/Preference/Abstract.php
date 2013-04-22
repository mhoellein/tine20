<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Preference
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 * @todo        make this a real controller + singleton (create extra sql backend)
 * @todo        add getAllprefsForApp (similar to config) to get all prefs for the registry in one request
 * @todo        add getPreference function that returns the complete record
 * @todo        allow free-form preferences
 * @todo        support group preferences
 */

/**
 * abstract backend for preferences
 *
 * @package     Tinebase
 * @subpackage  Preference
 */
abstract class Tinebase_Preference_Abstract extends Tinebase_Backend_Sql_Abstract
{
    /**
     * yes no options
     *
     * @staticvar string
     */
    const YES_NO_OPTIONS = 'yesnoopt';

    /**
     * default persistent filter
     */
    const DEFAULTPERSISTENTFILTER = 'defaultpersistentfilter';
    
    /**
     * default container options
     *
     * @staticvar string
     */
    const DEFAULTCONTAINER_OPTIONS = 'defaulcontaineropt';
    
    /**************************** backend settings *********************************/

    /**
     * Table name without prefix
     *
     * @var string
     */
    protected $_tableName = 'preferences';

    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = 'Tinebase_Model_Preference';
    
    /**
     * application
     *
     * @var string
     */
    protected $_application = 'Tinebase';
    
    /**
     * preference names that have no default option
     * 
     * @var array
     */
    protected $_skipDefaultOption = array();
    
    /**************************** public abstract functions *********************************/

    /**
     * get all possible application prefs
     * - every app should overwrite this
     *
     * @return  array   all application prefs
     */
    abstract public function getAllApplicationPreferences();

    /**
     * get translated right descriptions
     *
     * @return  array with translated descriptions for this applications preferences
     */
    abstract public function getTranslatedPreferences();

    /**
     * get preference defaults if no default is found in the database
     *
     * @param string $_preferenceName
     * @param string|Tinebase_Model_User $_accountId
     * @param string $_accountType
     * @return Tinebase_Model_Preference
     */
    abstract public function getApplicationPreferenceDefaults($_preferenceName, $_accountId = NULL, $_accountType = Tinebase_Acl_Rights::ACCOUNT_TYPE_USER);

    /**************************** public interceptior functions *********************************/

    /**
     * get interceptor (alias for getValue())
     *
     * @param string $_preferenceName
     * @return string
     */
    public function __get($_preferenceName)
    {
        return $this->getValue($_preferenceName);
    }

    /**
     * set interceptor (alias for setValue())
     *
     * @param string $_preferenceName
     * @param string $_value
     */
    public function __set($_preferenceName, $_value) {
        if (in_array($_preferenceName, $this->getAllApplicationPreferences())) {
            $this->setValue($_preferenceName, $_value);
        }
    }

    /**************************** public functions *********************************/

    /**
     * search for preferences
     * 
     * @param  Tinebase_Model_Filter_FilterGroup    $_filter
     * @param  Tinebase_Model_Pagination            $_pagination
     * @param  boolean                              $_onlyIds
     * @return Tinebase_Record_RecordSet|array of preferences / pref ids
     */
    public function search(Tinebase_Model_Filter_FilterGroup $_filter = NULL, Tinebase_Model_Pagination $_pagination = NULL, $_onlyIds = FALSE)
    {
        // make sure account is set in filter
        $userId = Tinebase_Core::getUser()->getId();
        if (! $_filter->isFilterSet('account')) {
            $accountFilter = $_filter->createFilter('account', 'equals', array(
                'accountId'   => (string) $userId, 
                'accountType' => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER
            ));
            $_filter->addFilter($accountFilter);
        } else {
            // only admins can search for other users prefs
            $accountFilter = $_filter->getAccountFilter();
            $accountFilterValue = $accountFilter->getValue();
            if ($accountFilterValue['accountId'] != $userId && $accountFilterValue['accountType'] == Tinebase_Acl_Rights::ACCOUNT_TYPE_USER) {
                if (!Tinebase_Acl_Roles::getInstance()->hasRight($this->_application, Tinebase_Core::getUser()->getId(), Tinebase_Acl_Rights_Abstract::ADMIN)) {
                    return new Tinebase_Record_RecordSet('Tinebase_Model_Preference');
                }
            }
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_filter->toArray(), TRUE));
        
        $paging = new Tinebase_Model_Pagination(array(
            'dir'       => 'ASC',
            'sort'      => array('name')
        ));
        
        $allPrefs = parent::search($_filter, $_pagination, $_onlyIds);

        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r((is_array($allPrefs)) ? $allPrefs : $allPrefs->toArray(), TRUE));
        
        if (! $_onlyIds) {
            $this->_addDefaultAndRemoveUndefinedPrefs($allPrefs, $_filter);
            
            // get single matching preferences for each different pref
            $result = $this->getMatchingPreferences($allPrefs);
        } else {
            $result = $allPrefs;
        }
        
        return $result;
    }
    
    /**
     * add default preferences to and remove undefined preferences from record set
     * 
     * @param Tinebase_Record_RecordSet $_prefs
     * @param Tinebase_Model_Filter_FilterGroup $_filter
     */
    protected function _addDefaultAndRemoveUndefinedPrefs(Tinebase_Record_RecordSet $_prefs, Tinebase_Model_Filter_FilterGroup $_filter)
    {
        $allAppPrefs = $this->getAllApplicationPreferences();
        
        // add default prefs if not already in array (only if no name or type filters are set)
        if (! $_filter->isFilterSet('name') && ! $_filter->isFilterSet('type')) {
            $missingDefaultPrefs = array_diff($allAppPrefs, $_prefs->name);
            foreach ($missingDefaultPrefs as $prefName) {
                $_prefs->addRecord($this->getApplicationPreferenceDefaults($prefName));
            }
        }
        // remove all prefs that are not defined
        $undefinedPrefs = array_diff($_prefs->name, $allAppPrefs);
        if (count($undefinedPrefs) > 0) {
            $_prefs->addIndices(array('name'));
            foreach ($undefinedPrefs as $undefinedPrefName) {
                $record = $_prefs->find('name', $undefinedPrefName);
                $_prefs->removeRecord($record);
                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Removed undefined preference from result: ' . $undefinedPrefName);
            }
        }
    }
    
    /**
     * do some call json functions if preferences name match
     * - every app should define its own special handlers
     *
     * @param Tinebase_Frontend_Json_Abstract $_jsonFrontend
     * @param string $name
     * @param string $value
     * @param string $appName
     */
    public function doSpecialJsonFrontendActions(Tinebase_Frontend_Json_Abstract $_jsonFrontend, $name, $value, $appName)
    {
    }

    /**
     * get value of preference
     *
     * @param string $_preferenceName
     * @param string $_default return this if no preference found and default given
     * @return string
     * @throws Tinebase_Exception_NotFound if no default given and no pref found
     */
    public function getValue($_preferenceName, $_default = NULL)
    {
        $accountId = $this->_getAccountId();

        try {
            $result = $this->getValueForUser(
                $_preferenceName, $accountId,
                ($accountId === '0')
                ? Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE
                : Tinebase_Acl_Rights::ACCOUNT_TYPE_USER
            );
        } catch (Tinebase_Exception_NotFound $tenf) {
            if ($_default !== NULL) {
                $result = $_default;
            } else {
                throw $tenf;
            }
        }
        
        if ($result == Tinebase_Model_Preference::DEFAULT_VALUE) {
            $result = $_default;
        }

        return $result;
    }
    
    /**
     * get account id
     * 
     * @return string
     */
    protected function _getAccountId()
    {
        return (is_object(Tinebase_Core::getUser())) ? Tinebase_Core::getUser()->getId() : '0';
    }

    /**
     * get value of preference for a user/group
     *
     * @param string $_preferenceName
     * @param integer $_accountId
     * @param string $_accountType
     * @return string
     * @throws Tinebase_Exception_NotFound
     */
    public function getValueForUser($_preferenceName, $_accountId, $_accountType = Tinebase_Acl_Rights::ACCOUNT_TYPE_USER)
    {
        $queryResult = $this->_getPrefs($_preferenceName, $_accountId, $_accountType);

        if (!$queryResult) {
            $pref = $this->getApplicationPreferenceDefaults($_preferenceName, $_accountId, $_accountType);
        } else {
            $pref = $this->_getMatchingPreference($this->_rawDataToRecordSet($queryResult));
        }

        $result = $pref->value;

        return $result;
    }

    /**
     * get preferences
     * 
     * @param string $_preferenceName
     * @param string $_accountId
     * @param string $_accountType
     * @return array result
     */
    protected function _getPrefs($_preferenceName, $_accountId = '0', $_accountType = Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE)
    {
        $select = $this->_getSelect();
        
        $appId = Tinebase_Application::getInstance()->getApplicationByName($this->_application)->getId();
        $filter = new Tinebase_Model_PreferenceFilter(array(
            array('field'     => 'account',         'operator'  => 'equals', 'value'     => array(
                    'accountId' => $_accountId, 'accountType' => $_accountType)
            ),
            array('field'     => 'name',            'operator'  => 'equals', 'value'     => $_preferenceName),
            array('field'     => 'application_id',  'operator'  => 'equals', 'value'     => $appId),
        ));
        Tinebase_Backend_Sql_Filter_FilterGroup::appendFilters($select, $filter, $this);
        
        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetchAll();
        
        return $queryResult;
    }
    
    /**
     * get all users who have the preference $_preferenceName = $_value
     *
     * @param string $_preferenceName
     * @param string $_value
     * @param array $_limitToUserIds [optional]
     * @return array of user ids
     */
    public function getUsersWithPref($_preferenceName, $_value, $_limitToUserIds = array())
    {
        $result = array();

        $queryResult = $this->_getPrefs($_preferenceName);

        if (empty($queryResult)) {
            $pref = $this->getApplicationPreferenceDefaults($_preferenceName);
        } else {
            $pref = new Tinebase_Model_Preference($queryResult[0]);
        }

        if ($pref->value == $_value) {

            if (! empty($_limitToUserIds)) {
                $result = Tinebase_User::getInstance()->getMultiple($_limitToUserIds)->getArrayOfIds();
            } else {
                $result = Tinebase_User::getInstance()->getUsers()->getArrayOfIds();
            }

            if ($pref->type == Tinebase_Model_Preference::TYPE_FORCED) {
                // forced: get all users -> do nothing here

            } else if ($pref->type == Tinebase_Model_Preference::TYPE_DEFAULT) {
                // default: remove all users/groups who don't have default
                $filter = new Tinebase_Model_PreferenceFilter(array(
                    array('field'   => 'account_type',    'operator'  => 'equals', 'value' => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER),
                    array('field'   => 'name',            'operator'  => 'equals', 'value' => $_preferenceName),
                    array('field'   => 'value',           'operator'  => 'not',    'value' => $_value),
                ));
                $accountsWithOtherValues = $this->search($filter)->account_id;
                $result = array_diff($result, $accountsWithOtherValues);

            } else {
                throw new Tinebase_Exception_UnexpectedValue('Preference should be of type "forced" or "default".');
            }

        } else {
            // not default or forced: get all users/groups who have the setting
            $filter = new Tinebase_Model_PreferenceFilter(array(
                array('field'   => 'account_type',    'operator'  => 'equals', 'value' => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER),
                array('field'   => 'name',            'operator'  => 'equals', 'value' => $_preferenceName),
                array('field'   => 'value',           'operator'  => 'equals', 'value' => $_value),
            ));
            $result = $this->search($filter)->account_id;
        }

        return $result;
    }

    /**
     * set value of preference
     *
     * @param string $_preferenceName
     * @param string $_value
     */
    public function setValue($_preferenceName, $_value)
    {
        $accountId = $this->_getAccountId();
        return $this->setValueForUser($_preferenceName, $_value, $accountId);
    }

    /**
     * set value of preference for a user/group
     *
     * @param string $_preferenceName
     * @param string $_value
     * @param integer $_userId
     * @param boolean $_ignoreAcl
     * @return void
     * 
     * @todo use generic savePreference fn
     */
    public function setValueForUser($_preferenceName, $_value, $_accountId, $_ignoreAcl = FALSE)
    {
        // check acl first
        if(!$_ignoreAcl){
            $userId = $this->_getAccountId();
            if (
                $_accountId !== $userId
                && !Tinebase_Acl_Roles::getInstance()->hasRight($this->_application, $userId, Tinebase_Acl_Rights_Abstract::ADMIN)
            ) {
                throw new Tinebase_Exception_AccessDenied('You are not allowed to change the preferences.');
            }
        }
        // check if already there -> update
        $queryResult = $this->_getPrefs($_preferenceName, $_accountId, Tinebase_Acl_Rights::ACCOUNT_TYPE_USER);
        $prefArray = NULL;
        // need to fetch preference for user account as _getPrefs() returns prefs for ANYONE, too
        foreach ($queryResult as $row) {
            if ($row['account_type'] === Tinebase_Acl_Rights::ACCOUNT_TYPE_USER) {
                $prefArray = $row;
                break;
            }
        }
        
        if ($prefArray === NULL) {
            if ($_value !== Tinebase_Model_Preference::DEFAULT_VALUE) {
                // no preference yet -> create
                $preference = new Tinebase_Model_Preference(array(
                    'application_id'    => $appId = Tinebase_Application::getInstance()->getApplicationByName($this->_application)->getId(),
                    'name'              => $_preferenceName,
                    'value'             => $_value,
                    'account_id'        => $_accountId,
                    'account_type'      => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER,
                    'type'              => Tinebase_Model_Preference::TYPE_USER
                ));
                $this->create($preference);
                $action = 'Created';
            } else {
                $action = 'No action required';
            }

        } else {
            $preference = $this->_rawDataToRecord($prefArray);
            if ($_value === Tinebase_Model_Preference::DEFAULT_VALUE) {
                // delete if new value = use default
                $this->delete($preference->getId());
                $action = 'Reset';
            } else {
                $preference->value = $_value;
                $this->update($preference);
                $action = 'Updated';
            }
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' ' . $action . ': ' . $_preferenceName . ' for user ' . $_accountId . ' -> ' . $_value);
    }

    /**
     * get matching preferences from recordset with multiple prefs)
     *
     * @param Tinebase_Record_RecordSet $_preferences
     */
    public function getMatchingPreferences(Tinebase_Record_RecordSet $_preferences)
    {
        $_preferences->addIndices(array('name'));

        // get unique names, the matching preference and add it to result set
        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Preference');
        $uniqueNames = array_unique($_preferences->name);
        foreach ($uniqueNames as $name) {
            $singlePrefSet = $_preferences->filter('name', $name);
            $result->addRecord($this->_getMatchingPreference($singlePrefSet));
        }

        return $result;
    }

    /**
     * resolve preference options and add 'use default'
     * 
     * @param Tinebase_Model_Preference $_preference
     */
    public function resolveOptions(Tinebase_Model_Preference $_preference)
    {
        $options = array();
        if (! empty($_preference->options)) {
             $options = $this->_convertXmlOptionsToArray($_preference->options);
        }
        
        // get default pref
        if (! in_array($_preference->name, $this->_skipDefaultOption)) {
            $default = $this->_getDefaultPreference($_preference->name);
            
            // check if value is in options and use that label
            $valueLabel = $default->value;
            foreach ($options as $option) {
                if ($default->value == $option[0]) {
                    $valueLabel = $option[1];
                    break;
                }
            }
            // add default setting to the top of options
            $defaultLabel = Tinebase_Translation::getTranslation('Tinebase')->_('default') . 
                ' (' . $valueLabel . ')';
            
            array_unshift($options, array(
                Tinebase_Model_Preference::DEFAULT_VALUE,
                $defaultLabel,
            ));
        }
        
        $_preference->options = $options;
    }
    
    /**
     * convert options xml string to array
     *
     * @param string $_xmlOptions
     * @return array
     */
    protected function _convertXmlOptionsToArray($_xmlOptions)
    {
        $result = array();
        $optionsXml = new SimpleXMLElement($_xmlOptions);

        if ($optionsXml->special) {
           $result = $this->_getSpecialOptions($optionsXml->special);
        } else {
            foreach ($optionsXml->option as $option) {
                $result[] = array((string)$option->value, (string)$option->label);
            }
        }

        return $result;
    }

    /**
     * delete user preference by name
     *
     * @param string $_preferenceName
     * @return void
     */
    public function deleteUserPref($_preferenceName)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Deleting pref ' . $_preferenceName);

        $where = array(
        $this->_db->quoteInto($this->_db->quoteIdentifier('name')           . ' = ?', $_preferenceName),
        $this->_db->quoteInto($this->_db->quoteIdentifier('account_id')     . ' = ?', Tinebase_Core::getUser()->getId()),
        $this->_db->quoteInto($this->_db->quoteIdentifier('account_type')   . ' = ?', Tinebase_Acl_Rights::ACCOUNT_TYPE_USER)
        );

        $this->_db->delete($this->_tablePrefix . $this->_tableName, $where);
    }

    /**
     * Creates new entry
     *
     * @param   Tinebase_Record_Interface $_record
     * @return  Tinebase_Record_Interface
     * @throws  Tinebase_Exception_UnexpectedValue
     */
    public function create(Tinebase_Record_Interface $_record)
    {
        // check if personal only and account type=anyone -> throw exception
        if ($_record->personal_only && $_record->account_type == Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE) {
            $message = 'It is not allowed to set this preference for anyone.';
            Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $message);
            throw new Tinebase_Exception_UnexpectedValue($message);
        }

        return parent::create($_record);
    }

    /**
     * save admin preferences for this app
     * 
     * @param array $_data
     * @param boolean $_adminMode
     * 
     * @todo use generic savePreference fn
     */
    public function saveAdminPreferences($_data)
    {
        // only admins are allowed to update app pref defaults/forced prefs
        if (! Tinebase_Acl_Roles::getInstance()->hasRight($this->_application, Tinebase_Core::getUser()->getId(), Tinebase_Acl_Rights_Abstract::ADMIN)) {
            throw new Tinebase_Exception_AccessDenied('You are not allowed to change the preference defaults.');
        }
        
        // create prefs that don't exist in the db
        foreach ($_data as $id => $prefData) {
            if (preg_match('/^default/', $id) && array_key_exists('name', $prefData) && $prefData['value'] != Tinebase_Model_Preference::DEFAULT_VALUE) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Create admin pref: ' . $prefData['name'] . ' = ' . $prefData['value']);
                $newPref = $this->getApplicationPreferenceDefaults($prefData['name']);
                $newPref->value = $prefData['value'];
                $newPref->type = ($prefData['type'] == Tinebase_Model_Preference::TYPE_FORCED) ? $prefData['type'] : Tinebase_Model_Preference::TYPE_ADMIN;
                unset($newPref->id);
                $this->create($newPref);
                
                unset($_data[$id]);
            }
        }
        
        // update default/forced preferences
        $records = $this->getMultiple(array_keys($_data));
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Saving admin prefs: ' . print_r($records->name, TRUE));
        foreach ($records as $preference) {
            if ($_data[$preference->getId()]['value'] == Tinebase_Model_Preference::DEFAULT_VALUE) {
                $this->delete($preference->getId());
            } else {
                $preference->value = $_data[$preference->getId()]['value'];
                $preference->type = ($_data[$preference->getId()]['type'] == Tinebase_Model_Preference::TYPE_FORCED) ? $_data[$preference->getId()]['type'] : Tinebase_Model_Preference::TYPE_ADMIN;
                $this->update($preference);
            }
        }
    }

    /**************************** protected functions *********************************/

    /**
     * get matching preference from result set
     * - order: forced > user > group > default
     * - get options xml from default pref if available
     *
     * @param Tinebase_Record_RecordSet $_preferences
     * @return Tinebase_Model_Preference
     */
    protected function _getMatchingPreference(Tinebase_Record_RecordSet $_preferences)
    {
        //if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_preferences->toArray(), TRUE));
        $_preferences->addIndices(array('type', 'account_type'));

        if (count($_preferences) == 1) {
            $result = $_preferences->getFirstRecord();
        } else {
            // check forced
            $forced = $_preferences->filter('type', Tinebase_Model_Preference::TYPE_FORCED);
            if (count($forced) > 0) {
                $_preferences = $forced;
            }

            // check user
            $user = $_preferences->filter('account_type', Tinebase_Acl_Rights::ACCOUNT_TYPE_USER);
            if (count($user) > 0) {
                $result = $user->getFirstRecord();
            } else {
                // check group
                $group = $_preferences->filter('account_type', Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP);
                if (count($group) > 0) {
                    $result = $group->getFirstRecord();
                } else {
                    // get first record of the remaining result set (defaults/anyone)
                    $result = $_preferences->getFirstRecord();
                }
            }
        }

        // add options and perhaps value from default preference
        if ($result->type !== Tinebase_Model_Preference::TYPE_DEFAULT) {
            $defaultPref = $this->_getDefaultPreference($result->name, $_preferences);
            $result->options = $defaultPref->options;
        }

        return $result;
    }
    
    /**
     * get default preference (from recordset, db or app defaults)
     * 
     * @param string $_preferenceName
     * @param Tinebase_Record_RecordSet $_preferences
     */
    protected function _getDefaultPreference($_preferenceName, $_preferences = NULL)
    {
        if ($_preferences !== NULL) {
            $defaults = $_preferences->filter('type', Tinebase_Model_Preference::TYPE_ADMIN);
        } else {
            $defaults = $this->search(new Tinebase_Model_PreferenceFilter(array(array(
                'field'     => 'type',
                'operator'  => 'equals',
                'value'     => Tinebase_Model_Preference::TYPE_ADMIN
            ), array(
                'field'     => 'name',
                'operator'  => 'equals',
                'value'     => $_preferenceName
            ), array(
                'field'     => 'account_id',
                'operator'  => 'equals',
                'value'     => 0
            ), array(
                'field'     => 'application_id',
                'operator'  => 'equals',
                'value'     => Tinebase_Application::getInstance()->getApplicationByName($this->_application)->getId()
            ))));
        }
        
        if (count($defaults) > 0) {
            $defaultPref = $defaults->getFirstRecord();
        } else {
            $defaultPref = $this->getApplicationPreferenceDefaults($_preferenceName);
        }
        
        return $defaultPref;
    }

    /**
     * return base default preference
     *
     * @param string $_preferenceName
     * @return Tinebase_Model_Preference
     */
    protected function _getDefaultBasePreference($_preferenceName)
    {
        return new Tinebase_Model_Preference(array(
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName($this->_application)->getId(),
            'name'              => $_preferenceName,
            'account_id'        => 0,
            'account_type'      => Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE,
            'type'              => Tinebase_Model_Preference::TYPE_DEFAULT,
            'options'           => '<?xml version="1.0" encoding="UTF-8"?>
                <options>
                    <special>' . $_preferenceName . '</special>
                </options>',
            'id'                => 'default' . Tinebase_Record_Abstract::generateUID(33),
            'value'             => Tinebase_Model_Preference::DEFAULT_VALUE,
        ), TRUE);
    }

    /**
     * overwrite this to add more special options for other apps
     *
     * - result array has to have the following format:
     *  array(
     *      array('value1', 'label1'),
     *      array('value2', 'label2'),
     *      ...
     *  )
     *
     * @param  string $_value
     * @return array
     */
    protected function _getSpecialOptions($_value)
    {
        $result = array();

        switch ($_value) {

            case self::YES_NO_OPTIONS:
                $locale = Tinebase_Core::get(Tinebase_Core::LOCALE);
                $question = Zend_Locale::getTranslationList('Question', $locale);

                list($yes, $dummy) = explode(':', $question['yes']);
                list($no, $dummy) = explode(':', $question['no']);

                $result[] = array(0, $no);
                $result[] = array(1, $yes);
                break;

            case self::DEFAULTCONTAINER_OPTIONS:
                $result = $this->_getDefaultContainerOptions();
                break;
                
            case self::DEFAULTPERSISTENTFILTER:
                $result = Tinebase_PersistentFilter::getPreferenceValues($this->_application);
                break;
                    
            default:
                throw new Tinebase_Exception_NotFound("Special option '{$_value}' not found.");
        }

        return $result;
    }
    
    /**
     * get all containers of current user and shared containers for app
     * 
     * @param string $_appName
     * @return array
     */
    protected function _getDefaultContainerOptions($_appName = NULL)
    {
        $result = array();
        $appName = ($_appName !== NULL) ? $_appName : $this->_application;
        
        $containers = Tinebase_Container::getInstance()->getPersonalContainer(Tinebase_Core::getUser(), $appName, Tinebase_Core::getUser(), Tinebase_Model_Grants::GRANT_ADD);
        $containers->merge(Tinebase_Container::getInstance()->getSharedContainer(Tinebase_Core::getUser(), $appName, Tinebase_Model_Grants::GRANT_ADD));
        foreach ($containers as $container) {
            $result[] = array($container->getId(), $container->name);
        }
        
        return $result;
    }
    
    /**
     * adds defaults to default container pref
     * 
     * @param Tinebase_Model_Preference $_preference
     * @param string|Tinebase_Model_User $_accountId
     * @param string $_appName
     */
    protected function _getDefaultContainerPreferenceDefaults(Tinebase_Model_Preference $_preference, $_accountId, $_appName = NULL, $_optionName = self::DEFAULTCONTAINER_OPTIONS)
    {
        $appName = ($_appName !== NULL) ? $_appName : $this->_application;
        
        $accountId = ($_accountId) ? $_accountId : Tinebase_Core::getUser()->getId();
        $containers = Tinebase_Container::getInstance()->getPersonalContainer($accountId, $appName, $accountId, 0, true);
        
        $_preference->value  = $containers->getFirstRecord()->getId();
        $_preference->options = '<?xml version="1.0" encoding="UTF-8"?>
            <options>
                <special>' . $_optionName . '</special>
            </options>';
    }
}
