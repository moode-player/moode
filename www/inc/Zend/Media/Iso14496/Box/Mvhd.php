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
 * @version    $Id: Mvhd.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Movie Header Box</i> defines overall information which is
 * media-independent, and relevant to the entire presentation considered as a
 * whole.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Mvhd.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Mvhd extends Zend_Media_Iso14496_FullBox
{
    /** @var integer */
    private $_creationTime;

    /** @var integer */
    private $_modificationTime;

    /** @var integer */
    private $_timescale;

    /** @var integer */
    private $_duration;

    /** @var integer */
    private $_rate = 1.0;

    /** @var integer */
    private $_volume = 1.0;

    /** @var Array */
    private $_matrix = array
        (0x00010000, 0, 0, 0, 0x00010000, 0, 0, 0, 0x40000000);

    /** @var integer */
    private $_nextTrackId;

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
        $this->_rate =
            ((($tmp = $this->_reader->readUInt32BE()) >> 16) & 0xffff) +
            (float)("0." . ((string)($tmp & 0xffff)));
        $this->_volume =
            ((($tmp = $this->_reader->readUInt16BE()) >> 8) & 0xff) +
            (float)("0." . ((string)($tmp & 0xff)));
        $this->_reader->skip(10);
        for ($i = 0; $i < 9; $i++) {
            $this->_matrix[$i] = $this->_reader->readUInt32BE();
        }
        $this->_reader->skip(24);
        $this->_nextTrackId = $this->_reader->readUInt32BE();
    }

    /**
     * Returns the creation time of the presentation. The value is in seconds
     * since midnight, Jan. 1, 1904, in UTC time.
     *
     * @return integer
     */
    public function getCreationTime()
    {
        return $this->_creationTime;
    }

    /**
     * Sets the creation time of the presentation in seconds since midnight,
     * Jan. 1, 1904, in UTC time.
     *
     * @param integer $creationTime The creation time.
     */
    public function setCreationTime($creationTime)
    {
        $this->_creationTime = $creationTime;
    }

    /**
     * Returns the most recent time the presentation was modified. The value is
     * in seconds since midnight, Jan. 1, 1904, in UTC time.
     *
     * @return integer
     */
    public function getModificationTime()
    {
        return $this->_modificationTime;
    }

    /**
     * Sets the most recent time the presentation was modified in seconds since
     * midnight, Jan. 1, 1904, in UTC time.
     *
     * @param integer $modificationTime The most recent time the presentation
     * was modified.
     */
    public function setModificationTime($modificationTime)
    {
        $this->_modificationTime = $modificationTime;
    }

    /**
     * Returns the time-scale for the entire presentation. This is the number of
     * time units that pass in one second. For example, a time coordinate system
     * that measures time in sixtieths of a second has a time scale of 60.
     *
     * @return integer
     */
    public function getTimescale()
    {
        return $this->_timescale;
    }

    /**
     * Sets the time-scale for the entire presentation. This is the number of
     * time units that pass in one second. For example, a time coordinate system
     * that measures time in sixtieths of a second has a time scale of 60.
     *
     * @param integer $timescale The time-scale for the entire presentation.
     */
    public function setTimescale($timescale)
    {
        $this->_timescale = $timescale;
    }

    /**
     * Returns the length of the presentation in the indicated timescale. This
     * property is derived from the presentation's tracks: the value of this
     * field corresponds to the duration of the longest track in the
     * presentation.
     *
     * @return integer
     */
    public function getDuration()
    {
        return $this->_duration;
    }

    /**
     * Sets the length of the presentation in the indicated timescale. This
     * property must be derived from the presentation's tracks: the value of
     * this field must correspond to the duration of the longest track in the
     * presentation.
     *
     * @param integer $duration The length of the presentation.
     */
    public function setDuration($duration)
    {
        $this->_duration = $duration;
    }

    /**
     * Returns the preferred rate to play the presentation. 1.0 is normal
     * forward playback.
     *
     * @return integer
     */
    public function getRate()
    {
        return $this->_rate;
    }

    /**
     * Sets the preferred rate to play the presentation. 1.0 is normal
     * forward playback.
     *
     * @param integer $rate The preferred play rate.
     */
    public function setRate($rate)
    {
        $this->_rate = $rate;
    }

    /**
     * Returns the preferred playback volume. 1.0 is full volume.
     *
     * @return integer
     */
    public function getVolume()
    {
        return $this->_volume;
    }

    /**
     * Sets the preferred playback volume. 1.0 is full volume.
     *
     * @param integer $volume The playback volume.
     */
    public function setVolume($volume)
    {
        $this->_volume = $volume;
    }

    /**
     * Returns the transformation matrix for the video; (u,v,w) are restricted
     * here to (0,0,1), hex values (0,0,0x40000000).
     *
     * @return Array
     */
    public function getMatrix()
    {
        return $this->_matrix;
    }

    /**
     * Sets the transformation matrix for the video; (u,v,w) are restricted
     * here to (0,0,1), hex values (0,0,0x40000000).
     *
     * @param Array $matrix The transformation matrix array of 9 values
     */
    public function setMatrix($matrix)
    {
        $this->_matrix = $matrix;
    }

    /**
     * Returns a value to use for the track ID of the next track to be added to
     * this presentation. Zero is not a valid track ID value. The value is
     * larger than the largest track-ID in use. If this value is equal to or
     * larger than 32-bit maxint, and a new media track is to be added, then a
     * search must be made in the file for a unused track identifier.
     *
     * @return integer
     */
    public function getNextTrackId()
    {
        return $this->_nextTrackId;
    }

    /**
     * Sets a value to use for the track ID of the next track to be added to
     * this presentation. Zero is not a valid track ID value. The value must be
     * larger than the largest track-ID in use.
     *
     * @param integer $nextTrackId The next track ID.
     */
    public function setNextTrackId($nextTrackId)
    {
        $this->_nextTrackId = $nextTrackId;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() +
            ($this->getVersion() == 1 ? 28 : 16) + 80;
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

        @list(, $rateDecimals) = explode('.', (float)$this->_rate);
        @list(, $volumeDecimals) = explode('.', (float)$this->_volume);
        $writer->writeUInt32BE(floor($this->_rate) << 16 | $rateDecimals)
               ->writeUInt16BE(floor($this->_volume) << 8 | $volumeDecimals)
               ->write(str_pad('', 10, "\0"));
        for ($i = 0; $i < 9; $i++) {
            $writer->writeUInt32BE($this->_matrix[$i]);
        }
        $writer->write(str_pad('', 24, "\0"))
               ->writeUInt32BE($this->_nextTrackId);
    }
}
