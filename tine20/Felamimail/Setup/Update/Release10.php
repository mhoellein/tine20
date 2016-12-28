<?php
/**
 * Tine 2.0
 *
 * @package     Felamimail
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2015-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */
class Felamimail_Setup_Update_Release10 extends Setup_Update_Abstract
{
    /**
     * update to 10.1
     * 
     * change signature to medium text
     */
    public function update_0()
    {
        $update8 = new Felamimail_Setup_Update_Release9($this->_backend);
        $update8->update_2();
        $this->setApplicationVersion('Felamimail', '10.1');
    }
}
