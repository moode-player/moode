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
 * @version    $Id: Tenc.php 274 2012-11-28 19:03:18Z svollbehr@gmail.com $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Track Encryption Box</i>  contains default values for the IsEncrypted
 * flag, IV_size, and KID for the entire track. These values are used as the
 * encryption parameters for the samples in this track unless overridden by the
 * sample group description associated with a group of samples. For files with
 * only one key per track, this box allows the basic encryption parameters to be
 * specified once per track instead of being repeated per sample.
 * 
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Tenc.php 274 2012-11-28 19:03:18Z svollbehr@gmail.com $
 */
final class Zend_Media_Iso14496_Box_Tenc extends Zend_Media_Iso14496_FullBox
{
    private $_defaultEncryption;
    private $_defaultIVSize;
    private $_defaultKID;

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

        $this->_defaultEncryption = $this->_reader->readUInt24BE();
        $this->_defaultIVSize = $this->_reader->readUInt8();
        $this->_defaultKID = $this->_reader->read(16);
    }

    /**
     * Returns the encryption flag which indicates the default encryption state
     * of the samples in the track. 
     * 
     * This flag is the identifier of the encryption state of the samples in the
     * track or group of samples. This flag takes the following values:
     * 
     *   o 0x0: Not encrypted
     *   o 0x1: Encrypted using AES 128-bit in CTR mode
     *   o 0x000002 – 0xFFFFFF: Reserved
     * 
     * @return integer
     */
    public function getDefaultEncryption()
    {
        return $this->_defaultEncryption;
    }

    /**
     * Sets the encryption flag which indicates the default encryption state
     * of the samples in the track. 
     *
     * @param integer $defaultEncryption The default encryption flag.
     */
    public function setDefaultEncryption($defaultEncryption)
    {
        $this->_defaultEncryption = $defaultEncryption;
    }

    /**
     * Returns the default Initialization Vector size in bytes.
     * 
     * This field is the size in bytes of the InitializationVector field. 
     * Supported values:
     * 
     *   o 0 – If the IsEncrypted flag is 0x0 (Not Encrypted).
     *   o 8 – Specifies 64-bit initialization vectors.
     *   o 16 – Specifies 128-bit initialization vectors.
     * 
     * @return integer
     */
    public function getDefaultIVSize()
    {
        return $this->_defaultIVSize;
    }

    /**
     * Sets the default Initialization Vector size in bytes.
     *
     * @param integer $defaultIVSize The default vector size in bytes.
     */
    public function setDefaultIVSize($defaultIVSize)
    {
        $this->_defaultIVSize = $defaultIVSize;
    }

    /**
     * Returns the default key identifier used for samples in this track. 
     *
     * @return string
     */
    public function getDefaultKID()
    {
        return $this->_defaultKID;
    }

    /**
     * Sets the default key identifier used for samples in this track. 
     *
     * @param string $defaultKID The default key identifier.
     */
    public function setDefaultKID($defaultKID)
    {
        $this->_defaultKID = $defaultKID;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 20;
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
        $writer->writeUInt24BE($this->_defaultEncryption)
               ->writeUInt8($this->_defaultIVSize)
               ->write($this->_defaultKID);
    }
}
