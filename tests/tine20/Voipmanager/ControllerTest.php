<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Voipmanager
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @version     $Id$
 */

/**
 * Test helper
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Voipmanager_ControllerTest::main');
}

/**
 * Test class for Tinebase_Group
 */
class Voipmanager_ControllerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Fixtures
     * 
     * @var array test objects
     */
    protected $_objects = array();

    /**
     * Backend
     *
     * @var Voipmanager_Controller
     */
    protected $_backend;
    
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
		$suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 Voipmanager Controller Tests');
        PHPUnit_TextUI_TestRunner::run($suite);
	}

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        $this->_backend = Voipmanager_Controller::getInstance();    

        #$this->_objects['call'] = new Phone_Model_Call(array(
        #    'id'                    => 'phpunitcallid',
        #    'line_id'               => 'phpunitlineid',
        #    'phone_id'              => 'phpunitphoneid',
        #    'direction'             => Phone_Model_Call::TYPE_INCOMING,
        #    'source'                => '26',
        #    'destination'           => '0406437435',    
        #));
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {	
    }

    /**
     * test getDBInstance
     * 
     */
    public function testGetDBInstance()
    {
        $db = $this->_backend->getDBInstance();
        
        $this->assertType('Zend_Db_Adapter_Abstract', $db);
    }
    
    /**
     * test creation of asterisk context
     *
     */
    public function testCreateAsteriskContext()
    {
        $test = $this->_getAsteriskContext();
        
        $returned = $this->_backend->createAsteriskContext($test);
        $this->assertEquals($test->name, $returned->name);
        $this->assertEquals($test->description, $returned->description);
        $this->assertNotNull($returned->id);
        
        $this->_backend->deleteAsteriskContexts($returned->getId()); 
    }
    
    /**
     * test update of asterisk context
     *
     */
    public function testUpdateAsteriskContext()
    {
        $test = $this->_getAsteriskContext();
        
        $test = $this->_backend->createAsteriskContext($test);
        $returned = $this->_backend->updateAsteriskContext($test);
        $this->assertEquals($test->name, $returned->name);
        $this->assertEquals($test->description, $returned->description);
        $this->assertNotNull($returned->id);
        
        $this->_backend->deleteAsteriskContexts($returned->getId()); 
    }
    
    /**
     * test search of asterisk context
     *
     */
    public function testSearchAsteriskContext()
    {
        $test = $this->_getAsteriskContext();
        
        $test = $this->_backend->createAsteriskContext($test);
        
        $returned = $this->_backend->getAsteriskContexts('id', 'ASC', $test->name);
        $this->assertEquals(1, count($returned));
        
        $this->_backend->deleteAsteriskContexts($returned->getId()); 
    }
    
    protected function _getAsteriskContext()
    {
        return new Voipmanager_Model_AsteriskContext(array(
            'name'  => Tinebase_Record_Abstract::generateUID(),
            'description' => Tinebase_Record_Abstract::generateUID()
        ));
    }

    /** MeetMe tests **/
    
    /**
     * test creation of asterisk meetme room
     *
     */
    public function testCreateAsteriskMeetme()
    {
        $test = $this->_getAsteriskMeetme();
        
        $returned = $this->_backend->createAsteriskMeetme($test);
        $this->assertEquals($test->confno, $returned->confno);
        $this->assertEquals($test->adminpin, $returned->adminpin);
        $this->assertNotNull($returned->id);
        
        $this->_backend->deleteAsteriskMeetmes($returned->getId()); 
    }
    
    /**
     * test update of asterisk meetme room
     *
     */
    public function testUpdateAsteriskMeetme()
    {
        $test = $this->_getAsteriskMeetme();
        
        $test = $this->_backend->createAsteriskMeetme($test);
        $returned = $this->_backend->updateAsteriskMeetme($test);
        $this->assertEquals($test->confno, $returned->confno);
        $this->assertEquals($test->adminpin, $returned->adminpin);
        $this->assertNotNull($returned->id);
        
        $this->_backend->deleteAsteriskMeetmes($returned->getId()); 
    }
    
    /**
     * test search of asterisk meetme room
     *
     */
    public function testSearchAsteriskMeetme()
    {
        $test = $this->_getAsteriskMeetme();
        
        $test = $this->_backend->createAsteriskMeetme($test);
        
        $returned = $this->_backend->getAsteriskMeetmes('id', 'ASC', $test->confno);
        $this->assertEquals(1, count($returned));
        
        $this->_backend->deleteAsteriskMeetmes($returned->getId()); 
    }
    
    protected function _getAsteriskMeetme()
    {
        return new Voipmanager_Model_AsteriskMeetme(array(
            'confno'  => Tinebase_Record_Abstract::generateUID(),
            'adminpin' => Tinebase_Record_Abstract::generateUID(),
            'pin' => Tinebase_Record_Abstract::generateUID()
        ));
    }    

    /** SipPeer tests **/
    
    /**
     * test creation of asterisk sip peer
     *
     */
    public function testCreateAsteriskSipPeer()
    {
        $test = $this->_getAsteriskSipPeer();
        
        $returned = $this->_backend->createAsteriskSipPeer($test);
        $this->assertEquals($test->name, $returned->name);
        $this->assertEquals($test->qualify, $returned->qualify);
        $this->assertNotNull($returned->id);
        
        $this->_backend->deleteAsteriskSipPeers($returned->getId()); 
    }
    
    /**
     * test update of asterisk sip peer
     *
     */
    public function testUpdateAsteriskSipPeer()
    {
        $test = $this->_getAsteriskSipPeer();
        
        $test = $this->_backend->createAsteriskSipPeer($test);
        $returned = $this->_backend->updateAsteriskSipPeer($test);
        $this->assertEquals($test->name, $returned->name);
        $this->assertEquals($test->qualify, $returned->qualify);
        $this->assertNotNull($returned->id);
        
        $this->_backend->deleteAsteriskSipPeers($returned->getId()); 
    }
    
    /**
     * test search of asterisk sip peer
     *
     */
    public function testSearchAsteriskSipPeer()
    {
        $test = $this->_getAsteriskSipPeer();
        
        $test = $this->_backend->createAsteriskSipPeer($test);
        
        $filter = new Voipmanager_Model_AsteriskSipPeerFilter(array(
            'callerid' => $test->callerid
        ));
        $returned = $this->_backend->searchAsteriskSipPeers($filter);
        $this->assertEquals(1, count($returned));
        
        $filter = new Voipmanager_Model_AsteriskSipPeerFilter(array(
            'query' => $test->callerid
        ));
        $returned = $this->_backend->searchAsteriskSipPeers($filter);
        $this->assertEquals(1, count($returned));
        
        $this->_backend->deleteAsteriskSipPeers($returned->getId()); 
    }
    
    protected function _getAsteriskSipPeer()
    {
        return new Voipmanager_Model_AsteriskSipPeer(array(
            'name'  => Tinebase_Record_Abstract::generateUID(),
            'callerid' => Tinebase_Record_Abstract::generateUID(),
            'qualify' => 'yes'
        ));
    }    
    
}		
