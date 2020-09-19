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
 * @version    $Id: Tkhd.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/FullBox.php';
/**#@-*/

/**
 * The <i>Track Header Box</i> specifies the characteristics of a single track.
 * Exactly one Track Header Box is contained in a track.
 *
 * In the absence of an edit list, the presentation of a track starts at the
 * beginning of the overall presentation. An empty edit is used to offset the
 * start time of a track.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Tkhd.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Iso14496_Box_Tkhd extends Zend_Media_Iso14496_FullBox
{
    /** @var integer */
    private $_creationTime;

    /** @var integer */
    private $_modificationTime;

    /** @var integer */
    private $_trackId;

    /** @var integer */
    private $_duration;

    /** @var integer */
    private $_layer = 0;

    /** @var integer */
    private $_alternateGroup = 0;

    /** @var integer */
    private $_volume = 0;

    /** @var Array */
    private $_matrix = array
        (0x00010000, 0, 0, 0, 0x00010000, 0, 0, 0, 0x40000000);

    /** @var integer */
    private $_width;

    /** @var integer */
    private $_height;

    /**
     * Indicates that the track is enabled. A disabled track is treated as if it
     * were not present.
     */
    const TRACK_ENABLED = 1;

    /** Indicates that the track is used in the presentation. */
    const TRACK_IN_MOVIE = 2;

    /** Indicates that the track is used when previewing the presentation. */
    const TRACK_IN_PREVIEW = 4;

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
            $this->_trackId = $this->_reader->readUInt32BE();
            $this->_reader->skip(4);
            $this->_duration = $this->_reader->readInt64BE();
        } else {
            $this->_creationTime = $this->_reader->readUInt32BE();
            $this->_modificationTime = $this->_reader->readUInt32BE();
            $this->_trackId = $this->_reader->readUInt32BE();
            $this->_reader->skip(4);
            $this->_duration = $this->_reader->readUInt32BE();
        }
        $this->_reader->skip(8);
        $this->_layer = $this->_reader->readInt16BE();
        $this->_alternateGroup = $this->_reader->readInt16BE();
        $this->_volume =
            ((($tmp = $this->_reader->readUInt16BE()) >> 8) & 0xff) +
            (float)("0." . ((string)($tmp & 0xff)));
        $this->_reader->skip(2);
        for ($i = 0; $i < 9; $i++) {
            $this->_matrix[$i] = $this->_reader->readUInt32BE();
        }
        $this->_width =
            ((($tmp = $this->_reader->readUInt32BE()) >> 16) & 0xffff) +
            (float)("0." . ((string)($tmp & 0xffff)));
        $this->_height =
            ((($tmp = $this->_reader->readUInt32BE()) >> 16) & 0xffff) +
            (float)("0." . ((string)($tmp & 0xffff)));
    }

    /**
     * Returns the creation time of this track in seconds since midnight, Jan. 1,
     * 1904, in UTC time.
     *
     * @return integer
     */
    public function getCreationTime()
    {
        return $this->_creationTime;
    }

    /**
     * Sets the creation time of this track in seconds since midnight, Jan. 1,
     * 1904, in UTC time.
     *
     * @param integer $creationTime The creation time.
     */
    public function setCreationTime()
    {
        $this->_creationTime = $creationTime;
    }

    /**
     * Returns the most recent time the track was modified in seconds since
     * midnight, Jan. 1, 1904, in UTC time.
     *
     * @return integer
     */
    public function getModificationTime()
    {
        return $this->_modificationTime;
    }

    /**
     * Sets the most recent time the track was modified in seconds since
     * midnight, Jan. 1, 1904, in UTC time.
     *
     * @param integer $modificationTime The modification time.
     */
    public function setModificationTime($modificationTime)
    {
        $this->_modificationTime = $modificationTime;
    }

    /**
     * Returns a number that uniquely identifies this track over the entire
     * life-time of this presentation. Track IDs are never re-used and cannot be
     * zero.
     *
     * @return integer
     */
    public function getTrackId()
    {
        return $this->_trackId;
    }

    /**
     * Returns a number that uniquely identifies this track over the entire
     * life-time of this presentation. Track IDs are never re-used and cannot be
     * zero.
     *
     * @param integer $trackId The track identification.
     */
    public function setTrackId($trackId)
    {
        $this->_trackId = $trackId;
    }

    /**
     * Returns the duration of this track (in the timescale indicated in the
     * {@link Zend_Media_Iso14496_Box_Mvhd Movie Header Box}). The value of this
     * field is equal to the sum of the durations of all of the track's edits.
     * If there is no edit list, then the duration is the sum of the sample
     * durations, converted into the timescale in the
     * {@link Zend_Media_Iso14496_Box_Mvhd Movie Header Box}. If the duration
     * of this track cannot be determined then duration is set to all 32-bit
     * maxint.
     *
     * @return integer
     */
    public function getDuration()
    {
        return $this->_duration;
    }

    /**
     * Sets the duration of this track (in the timescale indicated in the
     * {@link Zend_Media_Iso14496_Box_Mvhd Movie Header Box}). The value of this
     * field must be equal to the sum of the durations of all of the track's
     * edits. If there is no edit list, then the duration must be the sum of the
     * sample durations, converted into the timescale in the
     * {@link Zend_Media_Iso14496_Box_Mvhd Movie Header Box}. If the duration
     * of this track cannot be determined then duration is set to all 32-bit
     * maxint.
     *
     * @param integer $duration The duration of this track.
     */
    public function setDuration($duration)
    {
        $this->_duration = $duration;
    }

    /**
     * Returns the front-to-back ordering of video tracks; tracks with lower
     * numbers are closer to the viewer. 0 is the normal value, and -1 would be
     * in front of track 0, and so on.
     *
     * @return integer
     */
    public function getLayer()
    {
        return $this->_layer;
    }

    /**
     * Sets the front-to-back ordering of video tracks; tracks with lower
     * numbers are closer to the viewer. 0 is the normal value, and -1 would be
     * in front of track 0, and so on.
     *
     * @param integer $layer The layer.
     */
    public function setLayer($layer)
    {
        $this->_layer = $layer;
    }

    /**
     * Returns an integer that specifies a group or collection of tracks. If
     * this field is 0 there is no information on possible relations to other
     * tracks. If this field is not 0, it should be the same for tracks that
     * contain alternate data for one another and different for tracks belonging
     * to different such groups. Only one track within an alternate group
     * should be played or streamed at any one time, and must be distinguishable
     * from other tracks in the group via attributes such as bitrate, codec,
     * language, packet size etc. A group may have only one member.
     *
     * @return integer
     */
    public function getAlternateGroup()
    {
        return $this->_alternateGroup;
    }

    /**
     * Returns an integer that specifies a group or collection of tracks. If
     * this field is 0 there is no information on possible relations to other
     * tracks. If this field is not 0, it should be the same for tracks that
     * contain alternate data for one another and different for tracks belonging
     * to different such groups. Only one track within an alternate group
     * should be played or streamed at any one time, and must be distinguishable
     * from other tracks in the group via attributes such as bitrate, codec,
     * language, packet size etc. A group may have only one member.
     *
     * @param integer $alternateGroup The alternate group.
     */
    public function setAlternateGroup($alternateGroup)
    {
        $this->_alternateGroup = $alternateGroup;
    }

    /**
     * Returns track's relative audio volume. Full volume is 1.0 (0x0100) and
     * is the normal value. Its value is irrelevant for a purely visual track.
     * Tracks may be composed by combining them according to their volume, and
     * then using the overall Movie Header Box volume setting; or more complex
     * audio composition (e.g. MPEG-4 BIFS) may be used.
     *
     * @return integer
     */
    public function getVolume()
    {
        return $this->_volume;
    }

    /**
     * Sets track's relative audio volume. Full volume is 1.0 (0x0100) and
     * is the normal value. Its value is irrelevant for a purely visual track.
     * Tracks may be composed by combining them according to their volume, and
     * then using the overall Movie Header Box volume setting; or more complex
     * audio composition (e.g. MPEG-4 BIFS) may be used.
     *
     * @param integer $volume The volume.
     */
    public function setVolume($volume)
    {
        $this->_volume = $volume;
    }

    /**
     * Returns the track's visual presentation width. This needs not be the same
     * as the pixel width of the images; all images in the sequence are scaled
     * to this width, before any overall transformation of the track represented
     * by the matrix. The pixel width of the images is the default value.
     *
     * @return integer
     */
    public function getWidth()
    {
        return $this->_width;
    }

    /**
     * Set the track's visual presentation width. This needs not be the same
     * as the pixel width of the images; all images in the sequence are scaled
     * to this width, before any overall transformation of the track represented
     * by the matrix. The pixel width of the images should be the default value.
     *
     * @param integer $width The width.
     */
    public function setWidth($width)
    {
        $this->_width = $width;
    }

    /**
     * Returns the track's visual presentation height. This needs not be the
     * same as the pixel height of the images; all images in the sequence are
     * scaled to this height, before any overall transformation of the track
     * represented by the matrix. The pixel height of the images is the default
     * value.
     *
     * @return integer
     */
    public function getHeight()
    {
        return $this->_height;
    }

    /**
     * Sets the track's visual presentation height. This needs not be the
     * same as the pixel height of the images; all images in the sequence are
     * scaled to this height, before any overall transformation of the track
     * represented by the matrix. The pixel height of the images should be the
     * default value.
     *
     * @param integer $height The height.
     */
    public function setHeight($height)
    {
        $this->_height = $height;
    }

    /**
     * Returns the box heap size in bytes.
     *
     * @return integer
     */
    public function getHeapSize()
    {
        return parent::getHeapSize() +
            ($this->getVersion() == 1 ? 32 : 20) + 60;
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
                   ->writeUInt32BE($this->_trackId)
                   ->writeUInt32BE(0)
                   ->writeInt64BE($this->_duration);
        } else {
            $writer->writeUInt32BE($this->_creationTime)
                   ->writeUInt32BE($this->_modificationTime)
                   ->writeUInt32BE($this->_trackId)
                   ->writeUInt32BE(0)
                   ->writeUInt32BE($this->_duration);
        }

        @list(, $volumeDecimals) = explode('.', (float)$this->_volume);
        $writer->write(str_pad('', 8, "\0"))
               ->writeInt16BE($this->_layer)
               ->writeInt16BE($this->_alternateGroup)
               ->writeUInt16BE(floor($this->_volume) << 8 | $volumeDecimals)
               ->write(str_pad('', 2, "\0"));
        for ($i = 0; $i < 9; $i++) {
            $writer->writeUInt32BE($this->_matrix[$i]);
        }
        @list(, $widthDecimals) = explode('.', (float)$this->_width);
        @list(, $heightDecimals) = explode('.', (float)$this->_height);
        $writer->writeUInt32BE(floor($this->_width) << 16 | $widthDecimals)
               ->writeUInt32BE(floor($this->_height) << 16 | $heightDecimals);
    }
}
