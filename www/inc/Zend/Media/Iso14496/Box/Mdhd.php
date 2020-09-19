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
 * @version    $Id: Mdhd.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Media Header Box</i> declares overall information that is
 * media-independent, and relevant to characteristics of the media in a track.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Mdhd.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Mdhd extends Zend_Media_Iso14496_FullBox
{
    /** @var integer */
    private $_creationTime;

    /** @var integer */
    private $_modificationTime;

    /** @var integer */
    private $_timescale;

    /** @var integer */
    private $_duration;

    /** @var string */
    private $_language = 'und';

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

        if ($this->getVersion() == 1) {
            $this->_creationTime = $this->_reader->readInt64BE();
            $this->_modificationTime = $this->_reader->readInt64BE();
            $this->_timescale = $this->_reader->readUInt32BE();
            $this->_duration = $this->_reader->readInt64BE();
        } else {
            $this->_creationTime = $this->_reader->readUInt32BE();
            $this->_modificationTime = $this->_reader->readUInt32BE();
            $this->_timescale = $this->_reader->readUInt32BE();
            $this->_duration = $this->_reader->readUInt32BE();
        }
        $this->_language = chr
            (((($tmp = $this->_reader->readUInt16BE()) >> 10) & 0x1f) + 0x60) .
            chr((($tmp >> 5) & 0x1f) + 0x60) . chr(($tmp & 0x1f) + 0x60);
    }

    /**
     * Returns the creation time of the media in this track, in seconds since
     * midnight, Jan. 1, 1904, in UTC time.
     *
     * @return integer
     */
    public function getCreationTime()
    {
        return $this->_creationTime;
    }

    /**
     * Sets the creation time of the media in this track, in seconds since
     * midnight, Jan. 1, 1904, in UTC time.
     *
     * @param integer $creationTime The creation time.
     */
    public function setCreationTime($creationTime)
    {
        $this->_creationTime = $creationTime;
    }

    /**
     * Returns the most recent time the media in this track was modified in
     * seconds since midnight, Jan. 1, 1904, in UTC time.
     *
     * @return integer
     */
    public function getModificationTime()
    {
        return $this->_modificationTime;
    }

    /**
     * Sets the most recent time the media in this track was modified in
     * seconds since midnight, Jan. 1, 1904, in UTC time.
     *
     * @param integer $modificationTime The modification time.
     */
    public function setModificationTime($modificationTime)
    {
        $this->_modificationTime = $modificationTime;
    }

    /**
     * Returns the time-scale for this media. This is the number of time units
     * that pass in one second. For example, a time coordinate system that
     * measures time in sixtieths of a second has a time scale of 60.
     *
     * @return integer
     */
    public function getTimescale()
    {
        return $this->_timescale;
    }

    /**
     * Sets the time-scale for this media. This is the number of time units
     * that pass in one second. For example, a time coordinate system that
     * measures time in sixtieths of a second has a time scale of 60.
     *
     * @param integer $timescale The time-scale.
     */
    public function setTimescale($timescale)
    {
        $this->_timescale = $timescale;
    }

    /**
     * Returns the duration of this media (in the scale of the timescale).
     *
     * @return integer
     */
    public function getDuration()
    {
        return $this->_duration;
    }

    /**
     * Sets the duration of this media (in the scale of the timescale).
     *
     * @param integer $duration The duration.
     */
    public function setDuration($duration)
    {
        $this->_duration = $duration;
    }

    /**
     * Returns the three byte language code to describe the language of this
     * media, according to {@link http://www.loc.gov/standards/iso639-2/
     * ISO 639-2/T}.
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->_language;
    }

    /**
     * Sets the three byte language code to describe the language of this
     * media, according to {@link http://www.loc.gov/standards/iso639-2/
     * ISO 639-2/T}.
     *
     * @param string $language The language code.
     */
    public function setLanguage($language)
    {
        $this->_language = $language;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() +
            ($this->getVersion() == 1 ? 28 : 16) + 4;
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
        if ($this->getVersion() == 1) {
            $writer->writeInt64BE($this->_creationTime)
                   ->writeInt64BE($this->_modificationTime)
                   ->writeUInt32BE($this->_timescale)
                   ->writeInt64BE($this->_duration);
        } else {
            $writer->writeUInt32BE($this->_creationTime)
                   ->writeUInt32BE($this->_modificationTime)
                   ->writeUInt32BE($this->_timescale)
                   ->writeUInt32BE($this->_duration);
        }
        $writer->writeUInt16BE((ord($this->_language[0]) - 0x60) << 10 |
                (ord($this->_language[1])- 0x60) << 5 |
                 (ord($this->_language[2])- 0x60))
               ->write(str_pad('', 2, "\0"));
    }
}
