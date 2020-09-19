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
 * @version    $Id: LanguageList.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Language List Object</i> contains an array of Unicode-based language
 * IDs. All other header objects refer to languages through zero-based positions
 * in this array.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: LanguageList.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_LanguageList extends Zend_Media_Asf_Object
{
    /** @var Array */
    private $_languages = array();

    /**
     * Constructs the class with given parameters and reads object related data
     * from the ASF file.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     * @param Array          $options The options array.
     */
    public function __construct($reader = null, &$options = array())
    {
        parent::__construct($reader, $options);

        if ($reader === null) {
            return;
        }

        $languageIdRecordsCount = $this->_reader->readUInt16LE();
        for ($i = 0; $i < $languageIdRecordsCount; $i++) {
            $languageIdLength = $this->_reader->readInt8();
            $languageId = $this->_reader->readString16($languageIdLength);
            $this->_languages[] = iconv
                ('utf-16le', $this->getOption('encoding'), $languageId);
        }
    }

    /**
     * Returns the array of language ids.
     *
     * @return Array
     */
    public function getLanguages() 
    {
        return $this->_languages; 
    }

    /**
     * Sets the array of language ids.
     *
     * @param Array $languages The array of language ids.
     */
    public function setLanguages($languages) 
    {
        $this->_languages = $languages; 
    }

    /**
     * Writes the object data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    public function write($writer)
    {
        require_once 'Zend/Io/StringWriter.php';
        $languageIdRecordsCount = count($this->_languages);
        $languageIdRecordsWriter = new Zend_Io_StringWriter();
        for ($i = 0; $i < $languageIdRecordsCount; $i++) {
            $languageIdRecordsWriter
                ->writeInt8(strlen($languageId = iconv
                    ($this->getOption('encoding'), 'utf-16le',
                     $this->_languages[$i]) . "\0\0"))
                ->writeString16($languageId);
        }

        $this->setSize
            (24 /* for header */ + 2 + $languageIdRecordsWriter->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeUInt16LE($languageIdRecordsCount)
               ->write($languageIdRecordsWriter->toString());
    }
}
