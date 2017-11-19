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
 * @version    $Id: Ps.php 208 2010-12-28 13:48:09Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Bit/Twiddling.php';
require_once 'Zend/Media/Mpeg/Object.php';
/**#@-*/

/**
 * This class represents a MPEG Program Stream encoded file as described in
 * MPEG-1 Systems (ISO/IEC 11172-1) and MPEG-2 Systems (ISO/IEC 13818-1)
 * standards.
 *
 * The Program Stream is a stream definition which is tailored for communicating
 * or storing one program of coded data and other data in environments where
 * errors are very unlikely, and where processing of system coding, e.g. by
 * software, is a major consideration.
 *
 * This class only supports the parsing of the play duration.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage MPEG
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Ps.php 208 2010-12-28 13:48:09Z svollbehr $
 * @todo       Full implementation
 */
final class Zend_Media_Mpeg_Ps extends Zend_Media_Mpeg_Object
{
    /** @var integer */
    private $_length;

    /**
     * Constructs the class with given file and options.
     *
     * @param string|resource|Zend_Io_Reader $filename The path to the file,
     *  file descriptor of an opened file, or a {@link Zend_Io_Reader} instance.
     * @param Array                          $options  The options array.
     */
    public function __construct($filename, $options = array())
    {
        if ($filename instanceof Zend_Io_Reader) {
            $this->_reader = &$filename;
        } else {
            require_once 'Zend/Io/FileReader.php';
            try {
                $this->_reader = new Zend_Io_FileReader($filename);
            } catch (Zend_Io_Exception $e) {
                $this->_reader = null;
                require_once 'Zend/Media/Mpeg/Exception.php';
                throw new Zend_Media_Mpeg_Exception($e->getMessage());
            }
        }
        $this->setOptions($options);

        $startCode = 0;
        $startTime = 0;
        $pictureCount = 0;
        $pictureRate = 0;
        $rates = array ( 0, 23.976, 24, 25, 29.97, 30, 50, 59.94, 60 );
        $foundSeqHdr = false;
        $foundGOP = false;

        do {
            do {
                $startCode = $this->nextStartCode();
            } while ($startCode != 0x1b3 && $startCode != 0x1b8);

            if ($startCode == 0x1b3 /* sequence_header_code */ &&
                    $pictureRate == 0) {
                $i1 = $this->_reader->readUInt32BE();
                $i2 = $this->_reader->readUInt32BE();
                if (!Zend_Bit_Twiddling::testAllBits($i2, 0x2000)) {
                    require_once 'Zend/Media/Mpeg/Exception.php';
                    throw new Zend_Media_Mpeg_Exception
                        ('File does not contain a valid MPEG Program Stream (Invalid mark)');
                }
                $pictureRate = $rates[Zend_Bit_Twiddling::getValue($i1, 4, 8)];
                $foundSeqHdr = true;
            }
            if ($startCode == 0x1b8 /* group_start_code */) {
                $tmp = $this->_reader->readUInt32BE();
                $startTime =
                    (($tmp >> 26) & 0x1f) * 60 * 60 * 1000 /* hours */ +
                    (($tmp >> 20) & 0x3f) * 60 * 1000 /* minutes */ +
                    (($tmp >> 13) & 0x3f) * 1000 /* seconds */ +
                    (int)(1 / $pictureRate * (($tmp >> 7) & 0x3f) * 1000);
                $foundGOP = true;
            }
        } while (!$foundSeqHdr || !$foundGOP);

        $this->_reader->setOffset($this->_reader->getSize());

        do {
            if (($startCode = $this->prevStartCode()) == 0x100) {
                $pictureCount++;
            }
        } while ($startCode != 0x1b8);

        $this->_reader->skip(4);
        $tmp = $this->_reader->readUInt32BE();
        $this->_length =
            (((($tmp >> 26) & 0x1f) * 60 * 60 * 1000 /* hours */ +
              (($tmp >> 20) & 0x3f) * 60 * 1000 /* minutes */ +
              (($tmp >> 13) & 0x3f) * 1000 /* seconds */ +
             (int)(1 / $pictureRate * (($tmp >> 7) & 0x3f) * 1000)) -
                 $startTime +
             (int)(1 / $pictureRate * $pictureCount * 1000)) / 1000;
    }

    /**
     * Returns the exact playtime in seconds.
     *
     * @return integer
     */
    public function getLength() 
    {
        return $this->_length; 
    }

    /**
     * Returns the exact playtime given in seconds as a string in the form of
     * [hours:]minutes:seconds.milliseconds.
     *
     * @param integer $seconds The playtime in seconds.
     * @return string
     */
    public function getFormattedLength()
    {
        return $this->formatTime($this->getLength());
    }
}
