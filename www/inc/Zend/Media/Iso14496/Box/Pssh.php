<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 'AS IS'
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Pssh.php 274 2012-11-28 19:03:18Z svollbehr@gmail.com $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>PSSH Box</i> contains information needed by a Content Protection
 * System to play back the content. The data format is specified by the system
 * identified by the ‘pssh’ parameter SystemID, and is considered opaque for the
 * purposes of this specification.
 * 
 * The data encapsulated in the Data field may  be read by the identified
 * Content Protection System to enable decryption key acquisition and decryption
 * of media data. For license/rights-based systems, the header information may
 * include data such as the URL of license server(s) or rights issuer(s) used,
 * embedded licenses/rights, and/or other protection system specific metadata.
 * 
 * A single file may be constructed to be playable by multiple key and digital 
 * rights management (DRM) systems, by including one Protection System Specific
 * Header box for each system supported. Readers that process such presentations
 * shall match the SystemID field in this box to the SystemID(s) of the DRM
 * System(s) they support, and select the matching Protection System Specific
 * Header box(es) for retrieval of Protection System Specific information
 * interpreted or created by that DRM system.
 * 
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Pssh.php 274 2012-11-28 19:03:18Z svollbehr@gmail.com $
 */
final class Zend_Media_Iso14496_Box_Pssh extends Zend_Media_Iso14496_FullBox
{
    private $_systemId;
    private $_data;

    /**
     * Constructs the class with given parameters and reads box related data
     * from the ISO Base Media file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($reader === null) {
            return;
        }

        $this->_systemId = $this->_reader->readGuid();
        $dataSize = $this->_reader->readUInt32BE();
        $this->_data = $reader->read($dataSize);
    }

    /**
     * Returns a UUID that uniquely identifies the content protection system
     * that this header belongs to.
     *
     * @return string
     */
    public function getSystemId()
    {
        return $this->_systemId;
    }

    /**
     * Sets a UUID that uniquely identifies the content protection system
     * that this header belongs to.
     *
     * @param string $systemId The system ID.
     */
    public function setSystemId($systemId)
    {
        $this->_systemId = $systemId;
    }


    /**
     * Returns the content protection system specific data.
     *
     * @return string
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Sets the content protection system specific data.
     *
     * @param string $data The data.
     */
    public function setData($data)
    {
        $this->_data = $data;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 20 + strlen($this->_data);
    }

    /**
     * Writes the box data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        parent::_writeData($writer);
        $writer->writeGuid($this->_systemId)
               ->writeUInt32BE(strlen($this->_data))
               ->write($this->_data);
    }
}
