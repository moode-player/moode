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
 * @subpackage ASF
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Asf.php 272 2012-03-29 19:53:29Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object/Container.php';
/**#@-*/

/**
 * This class represents a file in Advanced Systems Format (ASF) as described in
 * {@link http://go.microsoft.com/fwlink/?LinkId=31334 The Advanced Systems
 * Format (ASF) Specification}. It is a file format that can contain various
 * types of information ranging from audio and video to script commands and
 * developer defined custom streams.
 *
 * The ASF file consists of code blocks that are called content objects. Each
 * of these objects have a format of their own. They may contain other objects
 * or other specific data. Each supported object has been implemented as their
 * own classes to ease the correct use of the information.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Elias Haapamäki <elias.haapamaki@turunhelluntaisrk.fi>
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Asf.php 272 2012-03-29 19:53:29Z svollbehr $
 */
class Zend_Media_Asf extends Zend_Media_Asf_Object_Container
{
    /** @var string */
    private $_filename;

    /**
     * Constructs the ASF class with given file and options.
     *
     * The following options are currently recognized:
     *   o encoding -- Indicates the encoding that all the texts are presented
     *     with. By default this is set to utf-8. See the documentation of iconv
     *     for accepted values.
     *   o readonly -- Indicates that the file is read from a temporary location
     *     or another source it cannot be written back to.
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
                require_once 'Zend/Media/Id3/Exception.php';
                throw new Zend_Media_Asf_Exception($e->getMessage());
            }
            if (is_string($filename) && !isset($options['readonly'])) {
                $this->_filename = $filename;
            }
        }
        $this->setOptions($options);
        if ($this->getOption('encoding', null) === null) {
            $this->setOption('encoding', 'utf-8');
        }
        $this->setOffset(0);
        $this->setSize($this->_reader->getSize());
        $this->constructObjects
            (array
             (self::HEADER => 'Header',
                self::DATA => 'Data',
                self::SIMPLE_INDEX => 'SimpleIndex',
                self::INDEX => 'Index',
                self::MEDIA_OBJECT_INDEX => 'MediaObjectIndex',
                self::TIMECODE_INDEX => 'TimecodeIndex'));
    }

    /**
     * Returns the mandatory header object contained in this file.
     *
     * @return Zend_Media_Asf_Object_Header
     */
    public function getHeader()
    {
        $header = $this->getObjectsByIdentifier(self::HEADER);
        return $header[0];
    }

    /**
     * Returns the mandatory data object contained in this file.
     *
     * @return Zend_Media_Asf_Object_Data
     */
    public function getData()
    {
        $data = $this->getObjectsByIdentifier(self::DATA);
        return $data[0];
    }

    /**
     * Returns an array of index objects contained in this file.
     *
     * @return Array
     */
    public function getIndices()
    {
        return $this->getObjectsByIdentifier
            (self::SIMPLE_INDEX . '|' . self::INDEX . '|' .
             self::MEDIA_OBJECT_INDEX . '|' . self::TIMECODE_INDEX);
    }

    /**
     * Writes the changes to given media file. All object offsets must be
     * assumed to be invalid after the write operation.
     *
     * @param string $filename The optional path to the file, use null to save
     *                         to the same file.
     */
    public function write($filename)
    {
        if ($filename === null && ($filename = $this->_filename) === null) {
            require_once 'Zend/Media/Asf/Exception.php';
            throw new Zend_Media_Asf_Exception
                ('No file given to write to');
        } else if ($filename !== null && $this->_filename !== null &&
                   realpath($filename) != realpath($this->_filename) &&
                   !copy($this->_filename, $filename)) {
            require_once 'Zend/Media/Asf/Exception.php';
            throw new Zend_Media_Asf_Exception
                ('Unable to copy source to destination: ' .
                 realpath($this->_filename) . '->' . realpath($filename));
        }

        if (($fd = fopen
             ($filename, file_exists($filename) ? 'r+b' : 'wb')) === false) {
            require_once 'Zend/Media/Asf/Exception.php';
            throw new Zend_Media_Asf_Exception
                ('Unable to open file for writing: ' . $filename);
        }

        $header = $this->getHeader();
        $headerLengthOld = $header->getSize();
        $header->removeObjectsByIdentifier(Zend_Media_Asf_Object::PADDING);
        $header->headerExtension->removeObjectsByIdentifier
            (Zend_Media_Asf_Object::PADDING);

        require_once 'Zend/Io/StringWriter.php';
        $buffer = new Zend_Io_StringWriter();
        $header->write($buffer);
        $headerData = $buffer->toString();
        $headerLengthNew = $header->getSize();

        // Fits right in
        if ($headerLengthOld == $headerLengthNew) {
        }

        // Fits with adjusted padding
        else if ($headerLengthOld >= $headerLengthNew + 24 /* for header */) {
            $header->headerExtension->padding->setSize
                ($headerLengthOld - $headerLengthNew);
            $buffer = new Zend_Io_StringWriter();
            $header->write($buffer);
            $headerData = $buffer->toString();
            $headerLengthNew = $header->getSize();
        }

        // Must expand
        else {
            $header->headerExtension->padding->setSize(4096);
            $buffer = new Zend_Io_StringWriter();
            $header->write($buffer);
            $headerData = $buffer->toString();
            $headerLengthNew = $header->getSize();

            fseek($fd, 0, SEEK_END);
            $oldFileSize = ftell($fd);
            ftruncate
                ($fd, $newFileSize = $headerLengthNew - $headerLengthOld +
                 $oldFileSize);
             for ($i = 1, $cur = $oldFileSize; $cur > 0; $cur -= 1024, $i++) {
                if ($cur >= 1024) {
                    fseek($fd, -(($i * 1024) +
                            ($newFileSize - $oldFileSize)), SEEK_END);
                    $buffer = fread($fd, 1024);
                    fseek($fd, -($i * 1024), SEEK_END);
                    fwrite($fd, $buffer, 1024);
                } else {
                    fseek($fd, 0);
                    $buffer = fread($fd, $cur);
                    fseek($fd, $newFileSize - $oldFileSize);
                    fwrite($fd, $buffer, $cur);
                }
            }
        }

        fseek($fd, 0);
        fwrite($fd, $headerData, $headerLengthNew);
        fclose($fd);
    }
}
