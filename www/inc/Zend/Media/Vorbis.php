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
 * @version    $Id: Vorbis.php 239 2011-06-04 09:35:48Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Io/Reader.php';
require_once 'Zend/Media/Vorbis/Header/Identification.php';
require_once 'Zend/Media/Vorbis/Header/Comment.php';
require_once 'Zend/Media/Vorbis/Header/Setup.php';
/**#@-*/

/**
 * This class represents a file containing Vorbis bitstream as described in
 * {@link http://xiph.org/vorbis/doc/Vorbis_I_spec.pdf Vorbis I specification}.
 *
 * Vorbis is a general purpose perceptual audio CODEC intended to allow maximum encoder exibility, thus allowing it to
 * scale competitively over an exceptionally wide range of bitrates. At the high quality/bitrate end of the scale (CD
 * or DAT rate stereo, 16/24 bits) it is in the same league as MPEG-2 and MPC. Similarly, the 1.0 encoder can encode
 * high-quality CD and DAT rate stereo at below 48kbps without resampling to a lower rate. Vorbis is also intended for
 * lower and higher sample rates (from 8kHz telephony to 192kHz digital masters) and a range of channel representations
 * (monaural, polyphonic, stereo, quadraphonic, 5.1, ambisonic, or up to 255 discrete channels).
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Vorbis
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Vorbis.php 239 2011-06-04 09:35:48Z svollbehr $
 * @todo       Setup header is not yet supported
 */
final class Zend_Media_Vorbis
{
    /** @var Zend_Io_Reader */
    private $_reader;

    /** @var string */
    private $_filename = null;

    /** @var Zend_Media_Vorbis_Header_Identification */
    private $_identificationHeader;

    /** @var Zend_Media_Vorbis_Header_Comment */
    private $_commentHeader;

    /** @var Zend_Media_Vorbis_Header_Setup */
    private $_setupHeader;

    /**
     * Constructs the Zend_Media_Vorbis class with given file.
     *
     * @param string|resource|Zend_Io_Reader $filename The path to the file,
     *  file descriptor of an opened file, or a {@link Zend_Io_Reader} instance.
     * @throws Zend_Io_Exception if an error occur in stream handling.
     * @throws Zend_Media_Vorbis_Exception if an error occurs in vorbis bitstream reading.
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
                require_once 'Zend/Media/Vorbis/Exception.php';
                throw new Zend_Media_Vorbis_Exception($e->getMessage());
            }
        }

        $this->_identificationHeader = new Zend_Media_Vorbis_Header_Identification($this->_reader);
        $this->_commentHeader = new Zend_Media_Vorbis_Header_Comment($this->_reader);
        $this->_setupHeader = new Zend_Media_Vorbis_Header_Setup($this->_reader);
    }

    /**
     * Returns the identification header.
     *
     * @return Zend_Media_Vorbis_Header_Identification
     */
    public function getIdentificationHeader()
    {
        return $this->_identificationHeader;
    }

    /**
     * Returns the comment header.
     *
     * @return Zend_Media_Vorbis_Header_Comment
     */
    public function getCommentHeader()
    {
        return $this->_commentHeader;
    }

    /**
     * Returns the setup header.
     *
     * @return Zend_Media_Vorbis_Header_Setup
     */
    public function getSetupHeader()
    {
        return $this->_setupHeader;
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param string $name The field name.
     * @return mixed
     */
    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array($this, 'get' . ucfirst($name)));
        } else {
            require_once('Zend/Media/Vorbis/Exception.php');
            throw new Zend_Media_Vorbis_Exception('Unknown field: ' . $name);
        }
    }
}
