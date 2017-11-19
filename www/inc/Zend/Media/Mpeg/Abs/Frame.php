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
 * @subpackage MPEG
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Frame.php 261 2012-03-05 20:43:15Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Bit/Twiddling.php';
require_once 'Zend/Media/Mpeg/Abs/Object.php';
/**#@-*/

/**
 * This class represents an MPEG Audio Bit Stream frame as described in
 * ISO/IEC 11172-3 and ISO/IEC 13818-3 standards.
 *
 * To accommodate fast header processing the error checking data and the audio
 * data are lazy fetch by default. You can change this behaviour by giving a
 * proper option to the {@link Zend_Media_Mpeg_Abs} class.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage MPEG
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Frame.php 261 2012-03-05 20:43:15Z svollbehr $
 */
final class Zend_Media_Mpeg_Abs_Frame extends Zend_Media_Mpeg_Abs_Object
{
    /**
     * The bitrate lookup table. The table has the following format.
     *
     * <code>
     * array (
     *   SAMPLING_FREQUENCY_HIGH | SAMPLING_FREQUENCY_LOW => array (
     *     LAYER_ONE | LAYER_TWO | LAYER_TREE => array ( <bitrates> )
     *   )
     * )
     * </code>
     *
     * @var Array
     */
    private static $bitrates = array (
        self::SAMPLING_FREQUENCY_HIGH => array (
            self::LAYER_ONE   => array (
                1 => 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384,
                     416, 448
            ),
            self::LAYER_TWO   => array (
                1 => 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320,
                     384
            ),
            self::LAYER_THREE => array (
                1 => 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256,
                     320
            )
        ),
        self::SAMPLING_FREQUENCY_LOW  => array (
            self::LAYER_ONE   => array (
                1 => 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224,
                     256
            ),
            self::LAYER_TWO   => array (
                1 => 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160
            ),
            self::LAYER_THREE => array (
                1 => 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160
            )
        )
    );

    /**
     * Sample rate lookup table. The table has the following format.
     *
     * <code>
     * array (
     *   LAYER_ONE | LAYER_TWO | LAYER_TREE => array ( <sample rates> )
     * )
     * </code>
     *
     * @var Array
     */
    private static $samplingFrequencies = array (
        self::VERSION_ONE      => array (44100, 48000, 32000),
        self::VERSION_TWO      => array (22050, 24000, 16000),
        self::VERSION_TWO_FIVE => array (11025, 12000, 8000)
    );

    /**
     * Samples per frame lookup table. The table has the following format.
     *
     * <code>
     * array (
     *   SAMPLING_FREQUENCY_HIGH | SAMPLING_FREQUENCY_LOW => array (
     *     LAYER_ONE | LAYER_TWO | LAYER_TREE => <sample count>
     *   )
     * )
     * </code>
     *
     * @var Array
     */
    private static $samples = array (
        self::SAMPLING_FREQUENCY_HIGH => array (
            self::LAYER_ONE => 384,
            self::LAYER_TWO => 1152,
            self::LAYER_THREE => 1152),
        self::SAMPLING_FREQUENCY_LOW => array (
            self::LAYER_ONE => 384,
            self::LAYER_TWO => 1152,
            self::LAYER_THREE => 576));

    /**
     * Coefficient lookup table. The table has the following format.
     *
     * <code>
     * array (
     *   SAMPLING_FREQUENCY_HIGH | SAMPLING_FREQUENCY_LOW => array (
     *     LAYER_ONE | LAYER_TWO | LAYER_TREE => array ( <coefficient> )
     *   )
     * )
     * </code>
     *
     * @var Array
     */
    private static $coefficients = array (
        self::SAMPLING_FREQUENCY_HIGH => array (
            self::LAYER_ONE => 12, self::LAYER_TWO => 144,
            self::LAYER_THREE => 144
        ),
        self::SAMPLING_FREQUENCY_LOW  => array (
            self::LAYER_ONE => 12, self::LAYER_TWO => 144,
            self::LAYER_THREE => 72
        )
    );

    /**
     * Slot size per layer lookup table. The table has the following format.
     *
     * <code>
     * array (
     *    LAYER_ONE | LAYER_TWO | LAYER_TREE => <size>
     * )
     * </code>
     *
     * @var Array
     */
    private static $slotsizes = array (
        self::LAYER_ONE => 4, self::LAYER_TWO => 1, self::LAYER_THREE => 1
    );

    /** @var integer */
    private $_offset;

    /** @var integer */
    private $_version;

    /** @var integer */
    private $_frequencyType;

    /** @var integer */
    private $_layer;

    /** @var integer */
    private $_redundancy;

    /** @var integer */
    private $_bitrate;

    /** @var integer */
    private $_samplingFrequency;

    /** @var integer */
    private $_padding;

    /** @var integer */
    private $_mode;

    /** @var integer */
    private $_modeExtension;

    /** @var integer */
    private $_copyright;

    /** @var integer */
    private $_original;

    /** @var integer */
    private $_emphasis;

    /** @var integer */
    private $_length;

    /** @var integer */
    private $_samples;

    /** @var integer */
    private $_crc = false;

    /** @var string */
    private $_data = false;

    /**
     * Constructs the class with given parameters and reads object related data
     * from the frame.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array          $options Array of options.
     */
    public function __construct($reader, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->_offset = $this->_reader->getOffset();
        
        $header = null;
        for ($i = 0; $i < 5775 /* max attempts: max frame size x2 */; $i++) {
            $header = $this->_reader->readUInt32BE();
            if (Zend_Bit_Twiddling::testAllBits(Zend_Bit_Twiddling::getValue($header, 21, 32), 0xffe)) {
                break;
            }
            $this->_reader->setOffset(++$this->_offset + 1);
            if ($this->_offset == $this->_reader->getSize() || $i == (5775 - 1)) {
                require_once 'Zend/Media/Mpeg/Exception.php';
                throw new Zend_Media_Mpeg_Exception
                    ('File does not contain a valid MPEG Audio Bit Stream (Invalid frame sync and resynchronization failed)');
            }
        }
        
        $this->_version = Zend_Bit_Twiddling::getValue($header, 19, 20);
        $this->_frequencyType = Zend_Bit_Twiddling::testBit($header, 19);
        $this->_layer = Zend_Bit_Twiddling::getValue($header, 17, 18);
        $this->_redundancy = !Zend_Bit_Twiddling::testBit($header, 16);
        $this->_bitrate = isset
            (self::$bitrates[$this->_frequencyType][$this->_layer]
                 [$index = Zend_Bit_Twiddling::getValue($header, 12, 15)]) ?
            self::$bitrates[$this->_frequencyType][$this->_layer][$index] :
            false;
        $this->_samplingFrequency = isset
            (self::$samplingFrequencies[$this->_version]
                 [$index = Zend_Bit_Twiddling::getValue($header, 10, 11)]) ?
            self::$samplingFrequencies[$this->_version][$index] : false;
        $this->_padding = Zend_Bit_Twiddling::testBit($header, 9);
        $this->_mode = Zend_Bit_Twiddling::getValue($header, 6, 7);
        $this->_modeExtension = Zend_Bit_Twiddling::getValue($header, 4, 5);
        $this->_copyright = Zend_Bit_Twiddling::testBit($header, 3);
        $this->_original = Zend_Bit_Twiddling::testBit($header, 2);
        $this->_emphasis = Zend_Bit_Twiddling::getValue($header, 0, 1);

        $this->_length = (int)
            ((self::$coefficients[$this->_frequencyType][$this->_layer] *
                ($this->_bitrate * 1000) / $this->_samplingFrequency) +
             ($this->_padding ? 1 : 0)) * self::$slotsizes[$this->_layer];
        $this->_samples = self::$samples[$this->_frequencyType][$this->_layer];

        if ($this->getOption('readmode', 'lazy') == 'full') {
            $this->_readCrc();
            $this->_readData();
        }
        $this->_reader->skip($this->_length - 4);
    }

    /**
     * Returns the offset where the frame actually begins (stream error may
     * cause resynchronization).
     *
     * @return integer
     */
    public function getOffset()
    {
        return $this->_offset;
    }

    /**
     * Returns the version identifier of the algorithm.
     *
     * @see VERSION_ONE, VERSION_TWO, VERSION_TWO_FIVE
     * @return integer
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * Returns the sampling frequency type. This can be one of the following
     * values.
     *
     *   o <b>{@link SAMPLING_FREQUENCY_HIGH}</b> -- Higher Sampling Frequency
     *     (Version 1)
     *   o <b>{@link SAMPLING_FREQUENCY_LOW}</b> -- Lower Sampling Frequency
     *     (Version 2 and 2.5)
     *
     * @see SAMPLING_FREQUENCY_LOW, SAMPLING_FREQUENCY_HIGH
     * @return integer
     */
    public function getFrequencyType()
    {
        return $this->_frequencyType;
    }

    /**
     * Returns the type of layer used.
     *
     * @see LAYER_ONE, LAYER_TWO, LAYER_THREE
     * @return integer
     */
    public function getLayer()
    {
        return $this->_layer;
    }

    /**
     * An alias to getRedundancy().
     *
     * @see getRedundancy
     * @return boolean
     */
    public function hasRedundancy()
    {
        return $this->getRedundancy();
    }

    /**
     * Returns boolean corresponding to whether redundancy has been added in the
     * audio bitstream to facilitate error detection and concealment. Equals
     * <var>false</var> if no redundancy has been added, <var>true</var> if
     * redundancy has been added.
     *
     * @return boolean
     */
    public function getRedundancy()
    {
        return $this->_redundancy;
    }

    /**
     * Returns the bitrate in kbps. The returned value indicates the total bitrate
     * irrespective of the mode (stereo, joint_stereo, dual_channel,
     * single_channel).
     *
     * @return integer
     */
    public function getBitrate()
    {
        return $this->_bitrate;
    }

    /**
     * Returns the sampling frequency in Hz.
     *
     * @return integer
     */
    public function getSamplingFrequency()
    {
        return $this->_samplingFrequency;
    }

    /**
     * An alias to getPadding().
     *
     * @see getPadding
     * @return boolean
     */
    public function hasPadding()
    {
        return $this->getPadding();
    }

    /**
     * Returns boolean corresponding the frame contains an additional slot to
     * adjust the mean bitrate to the sampling frequency. Equals to
     * <var>true</var> if padding has been added, <var>false</var> otherwise.
     *
     * Padding is only necessary with a sampling frequency of 44.1kHz.
     *
     * @return boolean
     */
    public function getPadding()
    {
        return $this->_padding;
    }

    /**
     * Returns the mode. In Layer I and II the CHANNEL_JOINT_STEREO mode is
     * intensity_stereo, in Layer III it is intensity_stereo and/or ms_stereo.
     *
     * @see CHANNEL_STEREO, CHANNEL_JOINT_STEREO, CHANNEL_DUAL_CHANNEL,
     *      CHANNEL_SINGLE_CHANNEL
     * @return integer
     */
    public function getMode()
    {
        return $this->_mode;
    }

    /**
     * Returns the mode extension used in CHANNEL_JOINT_STEREO mode.
     *
     * In Layer I and II the return type indicates which subbands are in
     * intensity_stereo. All other subbands are coded in stereo.
     *
     *   o <b>{@link MODE_SUBBAND_4_TO_31}</b> -- subbands  4-31 in
     *     intensity_stereo, bound==4
     *   o <b>{@link MODE_SUBBAND_8_TO_31}</b> -- subbands  8-31 in
     *     intensity_stereo, bound==8
     *   o <b>{@link MODE_SUBBAND_12_TO_31}</b> -- subbands 12-31 in
     *     intensity_stereo, bound==12
     *   o <b>{@link MODE_SUBBAND_16_TO_31}</b> -- subbands 16-31 in
     *     intensity_stereo, bound==16
     *
     * In Layer III they indicate which type of joint stereo coding method is
     * applied. The frequency ranges over which the intensity_stereo and
     * ms_stereo modes are applied are implicit in the algorithm. Please see
     * {@link MODE_ISOFF_MSSOFF}, {@link MODE_ISON_MSSOFF},
     * {@link MODE_ISOFF_MSSON}, and {@link MODE_ISON_MSSON}.
     *
     * @return integer
     */
    public function getModeExtension()
    {
        return $this->_modeExtension;
    }

    /**
     * An alias to getCopyright().
     *
     * @see getCopyright
     * @return boolean
     */
    public function hasCopyright()
    {
        return $this->getCopyright();
    }

    /**
     * Returns <var>true</var> if the coded bitstream is copyright protected,
     * <var>false</var> otherwise.
     *
     * @return boolean
     */
    public function getCopyright()
    {
        return $this->_copyright;
    }

    /**
     * An alias to getOriginal().
     *
     * @see getOriginal
     * @return boolean
     */
    public function isOriginal()
    {
        return $this->getOriginal();
    }

    /**
     * Returns whether the bitstream is original or home made.
     *
     * @return boolean
     */
    public function getOriginal()
    {
        return $this->_original;
    }

    /**
     * Returns the type of de-emphasis that shall be used. The value is one of
     * the following.
     *
     *   o <b>{@link EMPHASIS_NONE}</b> -- No emphasis
     *   o <b>{@link EMPHASIS_50_15}</b> -- 50/15 microsec. emphasis
     *   o <b>{@link EMPHASIS_CCIT_J17}</b> -- CCITT J.17
     *
     * @see EMPHASIS_NONE, EMPHASIS_50_15, EMPHASIS_CCIT_J17
     * @return integer
     */
    public function getEmphasis()
    {
        return $this->_emphasis;
    }

    /**
     * Returns the length of the frame based on the current layer, bit rate,
     * sampling frequency and padding, in bytes.
     *
     * @return integer
     */
    public function getLength()
    {
        return $this->_length;
    }

    /**
     * Returns the number of samples contained in the frame.
     *
     * @return integer
     */
    public function getSamples()
    {
        return $this->_samples;
    }

    /**
     * Returns the 16-bit CRC of the frame or <var>false</var> if not present.
     *
     * @return integer
     */
    public function getCrc()
    {
        if ($this->getOption('readmode', 'lazy') == 'lazy' &&
                $this->hasRedundancy() && $this->_crc === false) {
            $this->_readCrc();
        }
        return $this->_crc;
    }

    /**
     * Reads the CRC data.
     */
    private function _readCrc()
    {
        if ($this->hasRedundancy()) {
            $offset = $this->_reader->getOffset();
            $this->_reader->setOffset($this->_offset + 4);
            $this->_crc = $this->_reader->readUInt16BE();
            $this->_reader->setOffset($offset);
        }
    }

    /**
     * Returns the audio data.
     *
     * @return string
     */
    public function getData()
    {
        if ($this->getOption('readmode', 'lazy') == 'lazy' &&
                $this->_data === false) {
            $this->_readData();
        }
        return $this->_data;
    }

    /**
     * Reads the frame data.
     */
    private function _readData()
    {
        $offset = $this->_reader->getOffset();
        $this->_reader->setOffset
            ($this->_offset + 4 + ($this->hasRedundancy() ? 2 : 0));
        $this->_data = $this->_reader->read
            ($this->getLength() - 4 - ($this->hasRedundancy() ? 2 : 0));
        $this->_reader->setOffset($offset);
    }
}
