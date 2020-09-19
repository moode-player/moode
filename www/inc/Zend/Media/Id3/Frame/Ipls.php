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
 * @subpackage ID3
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Ipls.php 177 2010-03-09 13:13:34Z svollbehr $
 * @deprecated ID3v2.3.0
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
require_once 'Zend/Media/Id3/Encoding.php';
/**#@-*/

/**
 * The <i>Involved people list</i> is a frame containing the names of those
 * involved, and how they were involved. There may only be one IPLS frame in
 * each tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Ipls.php 177 2010-03-09 13:13:34Z svollbehr $
 * @deprecated ID3v2.3.0
 */
final class Zend_Media_Id3_Frame_Ipls extends Zend_Media_Id3_Frame
    implements Zend_Media_Id3_Encoding
{
    /** @var integer */
    private $_encoding;

    /** @var Array */
    private $_people = array();

    /**
     * Constructs the class with given parameters and parses object related
     * data.
     *
     * @param Zend_Io_Reader $reader The reader object.
     * @param Array $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        $this->setEncoding
            ($this->getOption('encoding', Zend_Media_Id3_Encoding::UTF8));

        if ($this->_reader === null) {
            return;
        }

        $data = array();
        $encoding = $this->_reader->readUInt8();
        switch ($encoding) {
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                $data = $this->_explodeString16
                    ($this->_reader->read($this->_reader->getSize()));
                foreach ($data as &$str)
                    $str = $this->_convertString($str, $encoding);
                break;
            case self::UTF8:
                // break intentionally omitted
            default:
                $data = $this->_convertString
                    ($this->_explodeString8
                     ($this->_reader->read($this->_reader->getSize())),
                     $encoding);
                break;
        }

        for ($i = 0; $i < count($data) - 1; $i += 2) {
            $this->_people[] = array($data[$i] => @$data[$i + 1]);
        }
    }

    /**
     * Returns the text encoding.
     *
     * All the strings read from a file are automatically converted to the
     * character encoding specified with the <var>encoding</var> option. See
     * {@link Zend_Media_Id3v2} for details. This method returns that character
     * encoding, or any value set after read, translated into a string form
     * regarless if it was set using a {@link Zend_Media_Id3_Encoding} constant
     * or a string.
     *
     * @return integer
     */
    public function getEncoding()
    {
        return $this->_translateIntToEncoding($this->_encoding);
    }

    /**
     * Sets the text encoding.
     *
     * All the string written to the frame are done so using given character
     * encoding. No conversions of existing data take place upon the call to
     * this method thus all texts must be given in given character encoding.
     *
     * The character encoding parameter takes either a
     * {@link Zend_Media_Id3_Encoding} constant or a character set name string
     * in the form accepted by iconv. The default character encoding used to
     * write the frame is 'utf-8'.
     *
     * @see Zend_Media_Id3_Encoding
     * @param integer $encoding The text encoding.
     */
    public function setEncoding($encoding)
    {
        $this->_encoding = $this->_translateEncodingToInt($encoding);
    }

    /**
     * Returns the involved people list as an array. For each person, the array
     * contains an entry, which too is an associate array with involvement as
     * its key and involvee as its value.
     *
     * @return Array
     */
    public function getPeople() 
    {
        return $this->_people; 
    }

    /**
     * Adds a person with his involvement.
     *
     * @return string
     */
    public function addPerson($involvement, $person)
    {
        $this->_people[] = array($involvement => $person);
    }

    /**
     * Sets the involved people list array. For each person, the array must
     * contain an associate array with involvement as its key and involvee as
     * its value.
     *
     * @param Array $people The involved people list.
     */
    public function setPeople($people) 
    {
        $this->_people = $people; 
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeUInt8($this->_encoding);
        foreach ($this->_people as $entry) {
            foreach ($entry as $key => $val) {
                switch ($this->_encoding) {
                    case self::UTF16LE:
                        $writer->writeString16
                            ($key, Zend_Io_Writer::LITTLE_ENDIAN_ORDER, 1)
                               ->writeString16
                            ($val, Zend_Io_Writer::LITTLE_ENDIAN_ORDER, 1);
                        break;
                    case self::UTF16:
                        // break intentionally omitted
                    case self::UTF16BE:
                        $writer->writeString16($key, null, 1)
                               ->writeString16($val, null, 1);
                        break;
                    default:
                        $writer->writeString8($key, 1)
                               ->writeString8($val, 1);
                        break;
                }
            }
        }
    }
}
