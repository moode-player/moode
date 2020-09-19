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
 * @version    $Id: Trex.php 212 2011-04-30 06:14:16Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Track Extends Box</i> sets up default values used by the movie
 * fragments. By setting defaults in this way, space and complexity can be saved
 * in each {@link Zend_Media_Iso14496_Box_Traf Track Fragment Box}.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Trex.php 212 2011-04-30 06:14:16Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Trex extends Zend_Media_Iso14496_FullBox
{
    /** @var integer */
    private $_trackId;

    /** @var integer */
    private $_defaultSampleDescriptionIndex;

    /** @var integer */
    private $_defaultSampleDuration;

    /** @var integer */
    private $_defaultSampleSize;

    /** @var integer */
    private $_defaultSampleFlags;

    /**
     * Constructs the class with given parameters and reads box related data
     * from the ISO Base Media file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     * @todo  The sample flags could be parsed further
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->_trackId = $this->_reader->readUInt32BE();
        $this->_defaultSampleDescriptionIndex = $this->_reader->readUInt32BE();
        $this->_defaultSampleDuration = $this->_reader->readUInt32BE();
        $this->_defaultSampleSize = $this->_reader->readUInt32BE();
        $this->_defaultSampleFlags = $this->_reader->readUInt32BE();
    }

    /**
     * Returns the default track identifier.
     *
     * @return integer
     */
    public function getTrackId()
    {
        return $this->_trackId;
    }

    /**
     * Sets the default track identifier.
     *
     * @param integer $trackId The track identifier.
     */
    public function setTrackId($trackId)
    {
        $this->_trackId = $trackId;
    }

    /**
     * Returns the default sample description index.
     *
     * @return integer
     */
    public function getDefaultSampleDescriptionIndex()
    {
        return $this->_defaultSampleDescriptionIndex;
    }

    /**
     * Sets the default sample description index.
     *
     * @param integer $defaultSampleDescriptionIndex The description index.
     */
    public function setDefaultSampleDescriptionIndex
        ($defaultSampleDescriptionIndex)
    {
        $this->_defaultSampleDescriptionIndex = $defaultSampleDescriptionIndex;
    }

    /**
     * Returns the default sample duration.
     *
     * @return integer
     */
    public function getDefaultSampleDuration()
    {
        return $this->_defaultSampleDuration;
    }

    /**
     * Sets the default sample duration.
     *
     * @param integer $defaultSampleDuration The sample duration.
     */
    public function setDefaultSampleDuration($defaultSampleDuration)
    {
        $this->_defaultSampleDuration = $defaultSampleDuration;
    }

    /**
     * Returns the default sample size.
     *
     * @return integer
     */
    public function getDefaultSampleSize()
    {
        return $this->_defaultSampleSize;
    }

    /**
     * Sets the default sample size.
     *
     * @param integer $defaultSampleSize The sample size.
     */
    public function setDefaultSampleSize($defaultSampleSize)
    {
        $this->_defaultSampleSize = $defaultSampleSize;
    }

    /**
     * Returns the default sample flags.
     *
     * @return integer
     */
    public function getDefaultSampleFlags()
    {
        return $this->_defaultSampleFlags;
    }

    /**
     * Sets the default sample flags.
     *
     * @param integer $defaultSampleFlags The sample flags.
     */
    public function setDefaultSampleFlags()
    {
        $this->_defaultSampleFlags = $defaultSampleFlags;
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
        $writer->writeUInt32BE($this->_trackId)
               ->writeUInt32BE($this->_defaultSampleDescriptionIndex)
               ->writeUInt32BE($this->_defaultSampleDuration)
               ->writeUInt32BE($this->_defaultSampleSize)
               ->writeUInt32BE($this->_defaultSampleFlags);
    }
}
