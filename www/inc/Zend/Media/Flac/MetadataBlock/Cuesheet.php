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
 * @category   Zend
 * @package    Zend_Media
 * @subpackage FLAC
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Cuesheet.php 241 2011-06-11 16:46:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Flac/MetadataBlock.php';
/**#@-*/

/**
 * This class represents the cuesheet metadata block. This block is for storing various information that can be used in
 * a cue sheet. It supports track and index points, compatible with Red Book CD digital audio discs, as well as other
 * CD-DA metadata such as media catalog number and track ISRCs. The CUESHEET block is especially useful for backing up
 * CD-DA discs, but it can be used as a general purpose cueing mechanism for playback.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage FLAC
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Cuesheet.php 241 2011-06-11 16:46:52Z svollbehr $
 */
final class Zend_Media_Flac_MetadataBlock_Cuesheet extends Zend_Media_Flac_MetadataBlock
{
    /** @var string */
    private $_catalogNumber;

    /** @var integer */
    private $_leadinSamples;

    /** @var boolean */
    private $_compactDisc;

    /** @var Array */
    private $_tracks;

    /**
     * Constructs the class with given parameters and parses object related data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);

        $this->_catalogNumber = rtrim($this->_reader->read(128), "\x00");
        $this->_leadinSamples = $this->_reader->readInt64BE();
        $this->_compactDisc = ($this->_reader->readUInt8() >> 7) & 0x1;
        $this->_reader->skip(258);
        $tracksLength = $this->_reader->readUInt8();
        for ($i = 0; $i < $tracksLength; $i++) {
            $this->_tracks[$i] = array(
                'offset' => $this->_reader->readInt64BE(),
                'number' => $this->_reader->readUInt8(),
                'isrc' => rtrim($this->_reader->read(12), "\x00"),
                'type' => (($tmp = $this->_reader->readUInt8()) >> 7 ) & 0x1,
                'pre-emphasis' => (($tmp) >> 6 ) & 0x1,
                'index' => array());
            $this->_reader->skip(13);
            $indexPointsLength = $this->_reader->readUInt8();
            for ($j = 0; $j < $indexPointsLength; $j++) {
                $this->_tracks[$i]['index'][$j] = array(
                    'offset' => $this->_reader->readInt64BE(),
                    'number' => $this->_reader->readUInt8()
                );
                $this->_reader->skip(3);
            }
        }
    }

    /**
     * Returns the media catalog number, in ASCII printable characters 0x20-0x7e. In general, the media catalog number
     * may be 0 to 128 bytes long; any unused characters should be right-padded with NUL characters. For CD-DA, this is
     * a thirteen digit number, followed by 115 NUL bytes.minimum block size (in samples) used in the stream.
     *
     * @return string
     */
    public function getCatalogNumber()
    {
        return $this->_catalogNumber;
    }

    /**
     * Returns the number of lead-in samples. This field has meaning only for CD-DA cuesheets; for other uses it should
     * be 0. For CD-DA, the lead-in is the TRACK 00 area where the table of contents is stored; more precisely, it is
     * the number of samples from the first sample of the media to the first sample of the first index point of the
     * first track. According to the Red Book, the lead-in must be silence and CD grabbing software does not usually
     * store it; additionally, the lead-in must be at least two seconds but may be longer. For these reasons the
     * lead-in length is stored here so that the absolute position of the first track can be computed. Note that the
     * lead-in stored here is the number of samples up to the first index point of the first track, not necessarily to
     * INDEX 01 of the first track; even the first track may have INDEX 00 data.
     *
     * @return integer
     */
    public function getLeadinSamples()
    {
        return $this->_leadinSamples;
    }

    /**
     * Returns the minimum frame size (in bytes) used in the stream. May be 0 to imply the value is not known.
     *
     * @return integer
     */
    public function getMinimumFrameSize()
    {
        return $this->_minimumFrameSize;
    }

    /**
     * Returns the maximum frame size (in bytes) used in the stream. May be 0 to imply the value is not known.
     *
     * @return integer
     */
    public function getMaximumFrameSize()
    {
        return $this->_maximumFrameSize;
    }

    /**
     * Returns sample rate in Hz. The maximum sample rate is limited by the structure of frame headers to 655350Hz.
     * Also, a value of 0 is invalid.
     *
     * @return integer
     */
    public function getSampleRate()
    {
        return $this->_sampleRate;
    }

    /**
     * Returns whether the CUESHEET corresponds to a Compact Disc or not.
     *
     * @return boolean
     */
    public function getCompactDisk()
    {
        return $this->_compactDisk == 1;
    }

    /**
     * Returns an array of values. Each entry is an array containing the following keys.
     *   o offset -- Track offset in samples, relative to the beginning of the FLAC audio stream. It is the offset to
     *     the first index point of the track. (Note how this differs from CD-DA, where the track's offset in the TOC
     *     is that of the track's INDEX 01 even if there is an INDEX 00.) For CD-DA, the offset must be evenly divisible
     *     by 588 samples (588 samples = 44100 samples/sec * 1/75th of a sec).
     *   o number -- Track number. A track number of 0 is not allowed to avoid conflicting with the CD-DA spec, which
     *     reserves this for the lead-in. For CD-DA the number must be 1-99, or 170 for the lead-out; for non-CD-DA,
     *     the track number must for 255 for the lead-out. It is not required but encouraged to start with track 1 and
     *     increase sequentially. Track numbers must be unique within a CUESHEET.
     *   o isrc -- Track ISRC. This is a 12-digit alphanumeric code or an empty string to denote absence of an ISRC.
     *   o type -- The track type: 0 for audio, 1 for non-audio. This corresponds to the CD-DA Q-channel control bit 3.
     *   o pre-emphasis -- The pre-emphasis flag: 0 for no pre-emphasis, 1 for pre-emphasis. This corresponds to the
     *     CD-DA Q-channel control bit 5.
     *   o index -- An array of track index points. There must be at least one index in every track in a CUESHEET except
     *     for the lead-out track, which must have zero. For CD-DA, this number may be no more than 100. Each entry is
     *     an array containing the following keys.
     *       o offset -- Offset in samples, relative to the track offset, of the index point. For CD-DA, the offset must
     *         be evenly divisible by 588 samples (588 samples = 44100 samples/sec * 1/75th of a sec). Note that the
     *         offset is from the beginning of the track, not the beginning of the audio data.
     *       o number -- The index point number. For CD-DA, an index number of 0 corresponds to the track pre-gap. The
     *         first index in a track must have a number of 0 or 1, and subsequently, index numbers must increase by 1.
     *         Index numbers must be unique within a track.
     *
     * @return Array
     */
    public function getTracks()
    {
        return $this->_tracks;
    }
}
