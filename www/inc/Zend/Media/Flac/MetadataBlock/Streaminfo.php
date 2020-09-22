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
 * @version    $Id: Streaminfo.php 241 2011-06-11 16:46:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Flac/MetadataBlock.php';
require_once 'Zend/Bit/Twiddling.php';
/**#@-*/

/**
 * This class represents the streaminfo metadata block. This block has information about the whole stream, like sample
 * rate, number of channels, total number of samples, etc. It must be present as the first metadata block in the stream.
 * Other metadata blocks may follow, and ones that the decoder doesn't understand, it will skip.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage FLAC
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Streaminfo.php 241 2011-06-11 16:46:52Z svollbehr $
 */
final class Zend_Media_Flac_MetadataBlock_Streaminfo extends Zend_Media_Flac_MetadataBlock
{
    /** @var integer */
    private $_minimumBlockSize;

    /** @var integer */
    private $_maximumBlockSize;

    /** @var integer */
    private $_minimumFrameSize;

    /** @var integer */
    private $_maximumFrameSize;

    /** @var integer */
    private $_sampleRate;

    /** @var integer */
    private $_numberOfChannels;

    /** @var integer */
    private $_bitsPerSample;

    /** @var integer */
    private $_numberOfSamples;

    /** @var string */
    private $_md5Signature;

    /**
     * Constructs the class with given parameters and parses object related data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);

        $this->_minimumBlockSize = $this->_reader->readUInt16BE();
        $this->_maximumBlockSize = $this->_reader->readUInt16BE();
        $this->_minimumFrameSize = $this->_reader->readUInt24BE();
        $this->_maximumFrameSize = $this->_reader->readUInt24BE();
        $this->_sampleRate = Zend_Bit_Twiddling::getValue(($tmp = $this->_reader->readUInt32BE()), 12, 31);
        $this->_numberOfChannels = Zend_Bit_Twiddling::getValue($tmp, 9, 11) + 1;
        $this->_bitsPerSample = Zend_Bit_Twiddling::getValue($tmp, 4, 8) + 1;
        $this->_numberOfSamples = (Zend_Bit_Twiddling::getValue($tmp, 0, 3) << 32) | $this->_reader->readUInt32BE();
        $this->_md5Signature = bin2hex($this->_reader->read(16));
    }

    /**
     * Returns the minimum block size (in samples) used in the stream.
     *
     * @return integer
     */
    public function getMinimumBlockSize()
    {
        return $this->_minimumBlockSize;
    }

    /**
     * Returns the maximum block size (in samples) used in the stream. (Minimum blocksize == maximum blocksize) implies
     * a fixed-blocksize stream.
     *
     * @return integer
     */
    public function getMaximumBlockSize()
    {
        return $this->_maximumBlockSize;
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
     * Returns number of channels. FLAC supports from 1 to 8 channels.
     *
     * @return integer
     */
    public function getNumberOfChannels()
    {
        return $this->_numberOfChannels;
    }

    /**
     * Returns bits per sample. FLAC supports from 4 to 32 bits per sample. Currently the reference encoder and
     * decoders only support up to 24 bits per sample.
     *
     * @return integer
     */
    public function getBitsPerSample()
    {
        return $this->_bitsPerSample;
    }

    /**
     * Returns total samples in stream. 'Samples' means inter-channel sample, i.e. one second of 44.1Khz audio will
     * have 44100 samples regardless of the number of channels. A value of zero here means the number of total samples
     * is unknown.
     *
     * @return integer
     */
    public function getNumberOfSamples()
    {
        return $this->_numberOfSamples;
    }

    /**
     * Returns MD5 signature of the unencoded audio data. This allows the decoder to determine if an error exists in
     * the audio data even when the error does not result in an invalid bitstream.
     *
     * @return integer
     */
    public function getMd5Signature()
    {
        return $this->_md5Signature;
    }
}
