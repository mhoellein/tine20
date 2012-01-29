<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tests
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2010-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */

/**
 * Test class for FolderSync_Controller_Event
 * 
 * @package     Tests
 */
class Syncope_Command_MoveItemsTests extends Syncope_Command_ATestCase
{
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('ActiveSync MoveItems command tests');
        PHPUnit_TextUI_TestRunner::run($suite);
    }
    
    /**
     */
    public function testMove()
    {
        // do initial sync first
        $doc = new DOMDocument();
        $doc->loadXML('<?xml version="1.0" encoding="utf-8"?>
            <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
            <FolderSync xmlns="uri:ItemOperations"><SyncKey>0</SyncKey></FolderSync>'
        );
        
        $folderSync = new Syncope_Command_FolderSync($doc, $this->_device, null);
        $folderSync->handle();
        $responseDoc = $folderSync->getResponse();
        
        
        $doc = new DOMDocument();
        $doc->loadXML('<?xml version="1.0" encoding="utf-8"?>
            <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
            <Moves xmlns="uri:Move"><Move><SrcMsgId>2246b0b87ee914e283d6c53717cc36c68cacd187</SrcMsgId><SrcFldId>a130b7462fde72c7d6215ce32226e1794d631fa8</SrcFldId><DstFldId>cf11782725c1e132d05fec5a7cd9862694933003</DstFldId></Move></Moves>'
        );
        
        $moveItems = new Syncope_Command_MoveItems($doc, $this->_device, null);
        $moveItems->handle();
        $responseDoc = $moveItems->getResponse();
        #$responseDoc->formatOutput = true; echo $responseDoc->saveXML();
        
        $xpath = new DomXPath($responseDoc);
        $xpath->registerNamespace('Move', 'uri:Move');
        
        // @todo improve test
        return;
        
        $nodes = $xpath->query('//ItemOperations:ItemOperations/ItemOperations:Status');
        $this->assertEquals(1, $nodes->length, $responseDoc->saveXML());
        $this->assertEquals(Syncope_Command_MoveItems::STATUS_SUCCESS, $nodes->item(0)->nodeValue, $responseDoc->saveXML());
        
        $nodes = $xpath->query('//ItemOperations:ItemOperations/ItemOperations:Response/ItemOperations:Fetch/ItemOperations:Status');
        $this->assertEquals(1, $nodes->length, $responseDoc->saveXML());
        $this->assertEquals(Syncope_Command_MoveItems::STATUS_SUCCESS, $nodes->item(0)->nodeValue, $responseDoc->saveXML());
        
        $nodes = $xpath->query('//ItemOperations:ItemOperations/ItemOperations:Response/ItemOperations:Fetch/ItemOperations:Properties/Email:Subject');
        $this->assertEquals(1, $nodes->length, $responseDoc->saveXML());
        $this->assertEquals('Subject of the email', $nodes->item(0)->nodeValue, $responseDoc->saveXML());

        $this->assertEquals("uri:Email", $responseDoc->lookupNamespaceURI('Email'), $responseDoc->saveXML());
    }    
}
