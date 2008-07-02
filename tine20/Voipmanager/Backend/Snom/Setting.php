<?php

/**
 * Tine 2.0
 *
 * @package     Voipmanager Management
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Thomas Wadewitz <t.wadewitz@metaways.de>
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id:  $
 *
 */
 

/**
 * backend to handle Snom setting
 *
 * @package  Voipmanager
 */
class Voipmanager_Backend_Snom_Setting
{
    /**
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_db;    

	/**
	 * the constructor
	 */
    public function __construct()
    {
        $this->_db      = Zend_Db_Table_Abstract::getDefaultAdapter();
    }
   
  
	/**
	 * search settings
	 * 
     * @param Voipmanager_Model_SnomSettingFilter $_filter
     * @param Tinebase_Model_Pagination $_pagination
	 * @return Tinebase_Record_RecordSet of subtype Voipmanager_Model_SnomSetting
	 */
    public function search(Voipmanager_Model_SnomSettingFilter $_filter, Tinebase_Model_Pagination $_pagination)
    {	
        $where = array();
        
        $select = $this->_db->select()
            ->from(SQL_TABLE_PREFIX . 'snom_settings');
            
        $_pagination->appendPagination($select);

        if(!empty($_filter->query)) {
            $select->where($this->_db->quoteInto('(name LIKE ? OR description LIKE ?)', '%' . $_filter->query . '%'));
        } else {
            // handle the other fields separately
        }
       
        $stmt = $select->query();
        $rows = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);
        
       	$result = new Tinebase_Record_RecordSet('Voipmanager_Model_SnomSetting', $rows);
		
        return $result;
	}  
  
      
	/**
	 * get Setting by id
	 * 
     * @param string $_id
	 * @return Tinebase_Record_RecordSet of subtype Voipmanager_Model_Setting
	 */
    public function get($_id)
    {	
        $settingId = Voipmanager_Model_SnomSetting::convertSnomSettingIdToInt($_id);
        $select = $this->_db->select()->from(SQL_TABLE_PREFIX . 'snom_settings')->where($this->_db->quoteInto('id = ?', $settingId));
        $row = $this->_db->fetchRow($select);
        if (! $row) {
            throw new UnderflowException('setting not found');
        }
#       	$result = new Tinebase_Record_RecordSet('Voipmanager_Model_SnomSetting', $row);
        $result = new Voipmanager_Model_SnomSetting($row);
        return $result;
	}
	   
    /**
     * add new setting
     *
     * @param Voipmanager_Model_SnomSetting $_setting the setting data
     * @return Voipmanager_Model_SnomSetting
     */
    public function create(Voipmanager_Model_SnomSetting $_setting)
    {
        if (! $_setting->isValid()) {
            throw new Exception('invalid setting');
        }

        if ( empty($_setting->id) ) {
            $_setting->setId(Tinebase_Record_Abstract::generateUID());
        }
        
        $setting = $_setting->toArray();
        
        $this->_db->insert(SQL_TABLE_PREFIX . 'snom_settings', $setting);

        return $this->get($_setting->getId());
    }
    
    /**
     * update an existing setting
     *
     * @param Voipmanager_Model_SnomSetting $_setting the setting data
     * @return Voipmanager_Model_SnomSetting
     */
    public function update(Voipmanager_Model_SnomSetting $_setting)
    {
        if (! $_setting->isValid()) {
            throw new Exception('invalid setting');
        }
        $settingId = $_setting->getId();
        $settingData = $_setting->toArray();
        unset($settingData['id']);
     
        $writableFields = array("with_flash","message_led_other","global_missed_counter","scroll_outgoing","show_local_line","show_call_status","auto_connect_indication","privacy_out","privacy_in","enable_keyboard_lock","keyboard_lock");
        foreach($writableFields AS $wField) 
        { 
            if(empty($settingData[$wField])) {      
                $settingData[$wField] = NULL;
            }
            
            if($settingData[$wField] === false) {                     
                $settingData[$wField] = 'false'; 
            }
            
            if($settingData[$wField] === true) {                         
                $settingData[$wField] = 'true'; 
            }            
        }


        $where = array($this->_db->quoteInto('id = ?', $settingId));
        $this->_db->update(SQL_TABLE_PREFIX . 'snom_settings', $settingData, $where);
        
        return $this->get($settingId);
    }    


    /**
     * delete setting(s) identified by setting id
     *
     * @param string|array|Tinebase_Record_RecordSet $_id
     * @return void
     */
    public function delete($_id)
    {
        foreach ((array)$_id as $id) {
            $settingId = Voipmanager_Model_SnomSetting::convertSnomSettingIdToInt($id);
            $where[] = $this->_db->quoteInto('id = ?', $settingId);
        }

        try {
            $this->_db->beginTransaction();

            // NOTE: using array for second argument won't work as delete function joins array items using "AND"
            foreach($where AS $where_atom)
            {
                $this->_db->delete(SQL_TABLE_PREFIX . 'snom_settings', $where_atom);
            }

            $this->_db->commit();
        } catch (Exception $e) {
            $this->_db->rollBack();
            throw $e;
        }
    }
	        

}
