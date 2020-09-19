<?php
/**
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Cset.php 257 2012-01-26 05:30:58Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Riff/Chunk.php';
/**#@-*/

/**
 * The <i>Character Set</i> chunk defines the code page and country, language, and dialect codes for the file. These
 * values can be overridden for specific file elements.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 * @version    $Id: Cset.php 257 2012-01-26 05:30:58Z svollbehr $
 */
final class Zend_Media_Riff_Chunk_Cset extends Zend_Media_Riff_Chunk
{
    /** @var integer */
    private $_codePage;

    /** @var integer */
    private $_countryCode;

    /** @var integer */
    private $_language;

    /** @var integer */
    private $_dialect;

    /**
     * Constructs the class with given parameters and options.
     *
     * @param Zend_Io_Reader $reader  The reader object.
     */
    public function __construct($reader)
    {
        parent::__construct($reader);
        $this->_codePage = $this->_reader->readUInt16LE();
        $this->_countryCode = $this->_reader->readUInt16LE();
        $this->_language = $this->_reader->readUInt16LE();
        $this->_dialect = $this->_reader->readUInt16LE();
    }

    /**
     * Returns the code page used for file elements. If the CSET chunk is not present, or if this field has value zero,
     * assume standard ISO-8859-1 code page (identical to code page 1004 without code points defined in hex columns 0,
     * 1, 8, and 9).
     *
     * @return integer
     */
    public final function getCodePage()
    {
        return $this->_codePage;
    }

    /**
     * Sets the code page used for file elements. Value can be one of the following.
     *   o 000 None (ignore this field)
     *   o 001 USA
     *   o 002 Canada
     *   o 003 Latin America
     *   o 030 Greece
     *   o 031 Netherlands
     *   o 032 Belgium
     *   o 033 France
     *   o 034 Spain
     *   o 039 Italy
     *   o 041 Switzerland
     *   o 043 Austria
     *   o 044 United Kingdom
     *   o 045 Denmark
     *   o 046 Sweden
     *   o 047 Norway
     *   o 049 West Germany
     *   o 052 Mexico
     *   o 055 Brazil
     *   o 061 Australia
     *   o 064 New Zealand
     *   o 081 Japan
     *   o 082 Korea
     *   o 086 People’s Republic of China
     *   o 088 Taiwan
     *   o 090 Turkey
     *   o 351 Portugal
     *   o 352 Luxembourg
     *   o 354 Iceland
     *   o 358 Finland
     * 
     * @param string $type The code page used for file elements.
     */
    public final function setCodePage($codePage)
    {
        $this->_codePage = $codePage;
    }

    /**
     * Returns the country code used for file elements. See the file format specification for a list of currently
     * defined country codes. If the CSET chunk is not present, or if this field has value zero, assume USA (country
     * code 001).
     *
     * @return integer
     */
    public final function getCountryCode()
    {
        return $this->_countryCode;
    }

    /**
     * Sets the country code used for file elements.
     *
     * @param string $type The country code used for file elements.
     */
    public final function setCountryCode($countryCode)
    {
        $this->_countryCode = $countryCode;
    }

    /**
     * Returns the language used for file elements. See the file format specification for a list of language codes.
     * If the CSET chunk is not present, or if these fields have value zero, assume US English (language code 9,
     * dialect code 1).
     *
     * @return integer
     */
    public final function getLanguage()
    {
        return $this->_language;
    }

    /**
     * Sets the language used for file elements.
     *
     * @param string $type The language used for file elements.
     */
    public final function setLanguage($language)
    {
        $this->_language = $language;
    }

    /**
     * Returns the dialect used for file elements. See the file format specification for a list of dialect codes.
     * If the CSET chunk is not present, or if these fields have value zero, assume US English (language code 9,
     * dialect code 1).
     *
     * @return integer
     */
    public final function getDialect()
    {
        return $this->_dialect;
    }

    /**
     * Sets the dialect used for file elements.
     *
     * @param string $type The dialect used for file elements.
     */
    public final function setDialect($dialect)
    {
        $this->_dialect = $dialect;
    }
}
