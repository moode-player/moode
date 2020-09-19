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
 * @version    $Id: Hmhd.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Hint Media Header Box</i> header contains general information,
 * independent of the protocol, for hint tracks.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Hmhd.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Hmhd extends Zend_Media_Iso14496_FullBox
{
    /** @var integer */
    private $_maxPDUSize;

    /** @var integer */
    private $_avgPDUSize;

    /** @var integer */
    private $_maxBitrate;

    /** @var integer */
    private $_avgBitrate;

    /**
     * Constructs the class with given parameters and reads box related data
     * from the ISO Base Media file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->_maxPDUSize = $this->_reader->readUInt16BE();
        $this->_avgPDUSize = $this->_reader->readUInt16BE();
        $this->_maxBitrate = $this->_reader->readUInt32BE();
        $this->_avgBitrate = $this->_reader->readUInt32BE();
    }

    /**
     * Returns the size in bytes of the largest PDU in this (hint) stream.
     *
     * @return integer
     */
    public function getMaxPDUSize()
    {
        return $this->_maxPDUSize;
    }

    /**
     * Returns the size in bytes of the largest PDU in this (hint) stream.
     *
     * @param integer $maxPDUSize The maximum size.
     */
    public function setMaxPDUSize($maxPDUSize)
    {
        $this->_maxPDUSize = $maxPDUSize;
    }

    /**
     * Returns the average size of a PDU over the entire presentation.
     *
     * @return integer
     */
    public function getAvgPDUSize()
    {
        return $this->_avgPDUSize;
    }

    /**
     * Sets the average size of a PDU over the entire presentation.
     *
     * @param integer $avgPDUSize The average size.
     */
    public function setAvgPDUSize()
    {
        $this->_avgPDUSize = $avgPDUSize;
    }

    /**
     * Returns the maximum rate in bits/second over any window of one second.
     *
     * @return integer
     */
    public function getMaxBitrate()
    {
        return $this->_maxBitrate;
    }

    /**
     * Sets the maximum rate in bits/second over any window of one second.
     *
     * @param integer $maxBitrate The maximum bitrate.
     */
    public function setMaxBitrate($maxBitrate)
    {
        $this->_maxBitrate = $maxBitrate;
    }

    /**
     * Returns the average rate in bits/second over the entire presentation.
     *
     * @return integer
     */
    public function getAvgBitrate()
    {
        return $this->_avgBitrate;
    }

    /**
     * Sets the average rate in bits/second over the entire presentation.
     *
     * @param integer $maxbitrate The agerage bitrate.
     */
    public function setAvgBitrate($avgBitrate)
    {
        $this->_avgBitrate = $avgBitrate;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() + 2;
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
        
        $writer->writeUInt16BE($this->_maxPDUSize)
               ->writeUInt16BE($this->_avgPDUSize)
               ->writeUInt16BE($this->_maxBitrate)
               ->writeUInt16BE($this->_avgBitrate);
    }
}
