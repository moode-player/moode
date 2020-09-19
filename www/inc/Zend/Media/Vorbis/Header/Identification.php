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
 * @subpackage Vorbis
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Identification.php 233 2011-05-14 16:00:55Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Vorbis/Header.php';
/**#@-*/

/**
 * The identication header is a short header of only a few fields used to declare the stream definitively as Vorbis,
 * and provide a few externally relevant pieces of information about the audio stream.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Vorbis
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Identification.php 233 2011-05-14 16:00:55Z svollbehr $
 */
final class Zend_Media_Vorbis_Header_Identification extends Zend_Media_Vorbis_Header
{
    /** @var integer */
    private $_vorbisVersion;

    /** @var integer */
    private $_audioChannels;

    /** @var integer */
    private $_audioSampleRate;

    /** @var integer */
    private $_bitrateMaximum;

    /** @var integer */
    private $_bitrateNominal;

    /** @var integer */
    private $_bitrateMinimum;

    /** @var integer */
    private $_blocksize0;

    /** @var integer */
    private $_blocksize1;

    /**
     * Constructs the class with given parameters.
     *
     * @param Zend_Io_Reader $reader The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);

        $this->_vorbisVersion = $this->_reader->readUInt32LE();
        $this->_audioChannels = $this->_reader->readUInt8();
        $this->_audioSampleRate = $this->_reader->readUInt32LE();
        $this->_bitrateMaximum = $this->_reader->readInt32LE();
        $this->_bitrateNominal = $this->_reader->readInt32LE();
        $this->_bitrateMinimum = $this->_reader->readInt32LE();
        $this->_blocksize0 = pow(2, ($tmp = $this->_reader->readUInt8()) & 0xf);
        $this->_blocksize1 = pow(2, ($tmp >> 4) & 0xf);
        $framingFlag = $this->_reader->readUInt8() & 0x1;
        if ($this->_blocksize0 > $this->_blocksize1 || $framingFlag == 0) {
            require_once 'Zend/Media/Vorbis/Exception.php';
            throw new Zend_Media_Vorbis_Exception('Undecodable Vorbis stream');
        }
    }

    /**
     * Returns the vorbis version.
     *
     * @return integer
     */
    public function getVorbisVersion()
    {
        return $this->_vorbisVersion;
    }

    /**
     * Returns the number of audio channels.
     *
     * @return integer
     */
    public function getAudioChannels()
    {
        return $this->_audioChannels;
    }

    /**
     * Returns the audio sample rate.
     *
     * @return integer
     */
    public function getAudioSampleRate()
    {
        return $this->_audioSampleRate;
    }

    /**
     * Returns the maximum bitrate.
     *
     * @return integer
     */
    public function getBitrateMaximum()
    {
        return $this->_bitrateMaximum;
    }

    /**
     * Returns the nominal bitrate.
     *
     * @return integer
     */
    public function getBitrateNominal()
    {
        return $this->_bitrateNominal;
    }

    /**
     * Returns the minimum bitrate.
     *
     * @return integer
     */
    public function getBitrateMinimum()
    {
        return $this->_bitrateMinimum;
    }

    /**
     * Returns the first block size. Allowed final blocksize values are 64, 128, 256, 512, 1024, 2048, 4096 and 8192 in
     * Vorbis I.
     *
     * @return integer
     */
    public function getBlocksize1()
    {
        return $this->_blocksize1;
    }

    /**
     * Returns the second block size. Allowed final blocksize values are 64, 128, 256, 512, 1024, 2048, 4096 and 8192 in
     * Vorbis I.
     *
     * @return integer
     */
    public function getBlocksize2()
    {
        return $this->_blocksize2;
    }
}
