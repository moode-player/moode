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
 * @version    $Id: Flac.php 251 2011-06-13 15:41:51Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Io/Reader.php';
/**#@-*/

/**
 * This class represents a file FLAC file format as described in {@link http://flac.sourceforge.net/format.html}. FLAC
 * stands for Free Lossless Audio Codec, an audio format similar to MP3, but lossless, meaning that audio is compressed
 * in FLAC without any loss in quality. This is similar to how Zip works, except with FLAC you will get much better
 * compression because it is designed specifically for audio, and you can play back compressed FLAC files in your
 * favorite player (or your car or home stereo, see supported devices) just like you would an MP3 file.
 *
 * FLAC stands out as the fastest and most widely supported lossless audio codec, and the only one that at once is
 * non-proprietary, is unencumbered by patents, has an open-source reference implementation, has a well documented
 * format and API, and has several other independent implementations.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage FLAC
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Flac.php 251 2011-06-13 15:41:51Z svollbehr $
 */
final class Zend_Media_Flac
{
    /** The streaminfo metadata block */
    const STREAMINFO     = 0;

    /** The padding metadata block */
    const PADDING        = 1;

    /** The application metadata block */
    const APPLICATION    = 2;

    /** The seektable metadata block */
    const SEEKTABLE      = 3;

    /** The vorbis comment metadata block */
    const VORBIS_COMMENT = 4;

    /** The cuesheet metadata block */
    const CUESHEET       = 5;

    /** The picture metadata block */
    const PICTURE        = 6;

    /** @var Zend_Io_Reader */
    private $_reader;

    /** @var Array */
    private $_metadataBlocks = array();

    /** @var string */
    private $_filename = null;

    /**
     * Constructs the class with given filename.
     *
     * @param string|resource|Zend_Io_Reader $filename The path to the file,
     *  file descriptor of an opened file, or a {@link Zend_Io_Reader} instance.
     * @throws Zend_Io_Exception if an error occur in stream handling.
     * @throws Zend_Media_Flac_Exception if an error occurs in vorbis bitstream reading.
     */
    public function __construct($filename)
    {
        if ($filename instanceof Zend_Io_Reader) {
            $this->_reader = &$filename;
        } else {
            $this->_filename = $filename;
            require_once('Zend/Io/FileReader.php');
            try {
                $this->_reader = new Zend_Io_FileReader($filename);
            } catch (Zend_Io_Exception $e) {
                $this->_reader = null;
                require_once 'Zend/Media/Flac/Exception.php';
                throw new Zend_Media_Flac_Exception($e->getMessage());
            }
        }

        $capturePattern = $this->_reader->read(4);
        if ($capturePattern != 'fLaC') {
            require_once 'Zend/Media/Flac/Exception.php';
            throw new Zend_Media_Flac_Exception('Not a valid FLAC bitstream');
        }

        while (true) {
            $offset = $this->_reader->getOffset();
            $last = ($tmp = $this->_reader->readUInt8()) >> 7 & 0x1;
            $type = $tmp & 0x7f;
            $size = $this->_reader->readUInt24BE();

            $this->_reader->setOffset($offset);
            switch ($type) {
            case self::STREAMINFO:     // 0
                require_once 'Zend/Media/Flac/MetadataBlock/Streaminfo.php';
                $this->_metadataBlocks[] = new Zend_Media_Flac_MetadataBlock_Streaminfo($this->_reader);
                break;
            case self::PADDING:        // 1
                require_once 'Zend/Media/Flac/MetadataBlock/Padding.php';
                $this->_metadataBlocks[] = new Zend_Media_Flac_MetadataBlock_Padding($this->_reader);
                break;
            case self::APPLICATION:    // 2
                require_once 'Zend/Media/Flac/MetadataBlock/Application.php';
                $this->_metadataBlocks[] = new Zend_Media_Flac_MetadataBlock_Application($this->_reader);
                break;
            case self::SEEKTABLE:      // 3
                require_once 'Zend/Media/Flac/MetadataBlock/Seektable.php';
                $this->_metadataBlocks[] = new Zend_Media_Flac_MetadataBlock_Seektable($this->_reader);
                break;
            case self::VORBIS_COMMENT: // 4
                require_once 'Zend/Media/Flac/MetadataBlock/VorbisComment.php';
                $this->_metadataBlocks[] = new Zend_Media_Flac_MetadataBlock_VorbisComment($this->_reader);
                break;
            case self::CUESHEET:       // 5
                require_once 'Zend/Media/Flac/MetadataBlock/Cuesheet.php';
                $this->_metadataBlocks[] = new Zend_Media_Flac_MetadataBlock_Cuesheet($this->_reader);
                break;
            case self::PICTURE:        // 6
                require_once 'Zend/Media/Flac/MetadataBlock/Picture.php';
                $this->_metadataBlocks[] = new Zend_Media_Flac_MetadataBlock_Picture($this->_reader);
                break;
            default:
                // break intentionally omitted
            }
            $this->_reader->setOffset($offset + 4 /* header */ + $size);

            // Jump off the loop if we reached the end of metadata blocks
            if ($last === 1) {
                break;
            }
        }
    }

    /**
     * Checks whether the given metadata block is there. Returns <var>true</var> if one ore more frames are present,
     * <var>false</var> otherwise.
     *
     * @param string $type The metadata block type.
     * @return boolean
     */
    public function hasMetadataBlock($type)
    {
        $metadataBlockCount = count($this->_metadataBlocks);
        for ($i = 0; $i < $metadataBlockCount; $i++) {
            if ($this->_metadataBlocks[$i]->getType() === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns all the metadata blocks as an associate array.
     *
     * @return Array
     */
    public function getMetadataBlocks()
    {
        return $this->_metadataBlocks;
    }

    /**
     * Returns an array of metadata blocks frames matching the given type or an empty array if no metadata blocks
     * matched the type.
     *
     * Please note that one may also use the shorthand $obj->type or $obj->getType(), where the type is the metadata
     * block name, to access the first metadata block with the given type.
     *
     * @param string $type The metadata block type.
     * @return Array
     */
    public function getMetadataBlocksByType($type)
    {
        $matches = array();
        $metadataBlockCount = count($this->_metadataBlocks);
        for ($i = 0; $i < $metadataBlockCount; $i++) {
            if ($this->_metadataBlocks[$i]->getType() === $type) {
                $matches[] = $this->_metadataBlocks[$i];
            }
        }
        return $matches;
    }

    /**
     * Magic function so that $obj->X() or $obj->getX() will work, where X is the name of the metadata block. If there
     * is no metadata block by the given name, an exception is thrown.
     *
     * @param string $name The metadata block name.
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (preg_match('/^(?:get)([A-Z].*)$/', $name, $matches)) {
            $name = lcfirst($matches[1]);
        }
        if (defined($constant = 'self::' . strtoupper(preg_replace('/(?<=[a-z])[A-Z]/', '_$0', $name)))) {
            $metadataBlocks = $this->getMetadataBlocksByType(constant($constant));
            if (isset($metadataBlocks[0])) {
                return $metadataBlocks[0];
            }
        }
        if (!empty($this->_comments[strtoupper($name)])) {
           return $this->_comments[strtoupper($name)][0];
        }
        require_once 'Zend/Media/Flac/Exception.php';
        throw new Zend_Media_Flac_Exception('Unknown metadata block: ' . strtoupper($name));
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param string $name The metadata block name.
     * @return mixed
     */
    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func
                (array($this, 'get' . ucfirst($name)));
        }
        if (defined($constant = 'self::' . strtoupper(preg_replace('/(?<=[a-z])[A-Z]/', '_$0', $name)))) {
            $metadataBlocks = $this->getMetadataBlocksByType(constant($constant));
            if (isset($metadataBlocks[0])) {
                return $metadataBlocks[0];
            }
        }
        require_once 'Zend/Media/Flac/Exception.php';
        throw new Zend_Media_Flac_Exception('Unknown metadata block or field: ' . $name);
    }

    /**
     * Magic function so that assignments with $obj->value will work.
     *
     * @param string $name  The field name.
     * @param string $value The field value.
     * @return mixed
     */
    public function __set($name, $value)
    {
        if (method_exists($this, 'set' . ucfirst(strtolower($name)))) {
            call_user_func
                (array($this, 'set' . ucfirst(strtolower($name))), $value);
        } else {
            require_once('Zend/Media/Flac/Exception.php');
            throw new Zend_Media_Flac_Exception('Unknown field: ' . $name);
        }
    }
}
