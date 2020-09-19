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
 * @version    $Id: Tsrc.php 273 2012-08-21 17:22:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/TextFrame.php';
/**#@-*/

/**
 * The <i>TSRC</i> frame should contain the International Standard Recording
 * Code or ISRC (12 characters).
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Tsrc.php 273 2012-08-21 17:22:52Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Tsrc extends Zend_Media_Id3_TextFrame
{
    /** @var string */
    private $_country;

    /** @var string */
    private $_registrant;

    /** @var string */
    private $_year;

    /** @var string */
    private $_uniqueNumber;

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

        if ($this->_reader === null) {
            return;
        }

        $this->_country = substr($this->getText(), 0, 2);
        $this->_registrant = substr($this->getText(), 2, 3);
        $this->_year = substr($this->getText(), 5, 2);
        $this->_uniqueNumber = substr($this->getText(), 7, 5);
    }

    /**
     * Returns the appropriate for the registrant the two-character ISO 3166-1
     * alpha-2 country code.
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->_country;
    }

    /**
     * Sets the country.
     *
     * @param string $country The two-character ISO 3166-1 alpha-2 country code.
     */
    public function setCountry($country)
    {
        $this->_country = $country;
    }

    /**
     * Returns the three character alphanumeric registrant code, uniquely
     * identifying the organisation which registered the ISRC code.
     *
     * @return string
     */
    public function getRegistrant()
    {
        return $this->_registrant;
    }

    /**
     * Sets the registrant.
     *
     * @param string $registrant The three character alphanumeric registrant
     *  code.
     */
    public function setRegistrant($registrant)
    {
        $this->_registrant = $registrant;
    }

    /**
     * Returns the year of registration.
     *
     * @return integer
     */
    public function getYear()
    {
        $year = intval($this->_year);
        if ($year > 50) {
            return 1900 + $year;
        } else {
            return 2000 + $year;
        }
    }

    /**
     * Sets the year.
     *
     * @param integer $year The year of registration.
     */
    public function setYear($year)
    {
        $this->_year = substr(strval($year), 2, 2);
    }

    /**
     * Returns the unique number identifying the particular sound recording.
     *
     * @return integer
     */
    public function getUniqueNumber()
    {
        return intval($this->_uniqueNumber);
    }

    /**
     * Sets the unique number.
     *
     * @param integer $uniqueNumber The unique number identifying the
     *  particular sound recording.
     */
    public function setUniqueNumber($uniqueNumber)
    {
        $this->_uniqueNumber =
            str_pad(strval($uniqueNumber), 5, "0", STR_PAD_LEFT);
    }

    /**
     * Returns the whole ISRC code in the form
     * "country-registrant-year-unique number".
     *
     * @return string
     */
    public function getIsrc()
    {
        return $this->_country . "-" . $this->_registrant . "-" .
            $this->_year . "-" . $this->_uniqueNumber;
    }

    /**
     * Sets the ISRC code in the form "country-registrant-year-unique number".
     *
     * @param string $isrc The ISRC code.
     */
    public function setIsrc($isrc)
    {
        list($this->_country,
             $this->_registrant,
             $this->_year,
             $this->_uniqueNumber) = preg_split('/-/', $isrc);
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $this->setText
            ($this->_country . $this->_registrant . $this->_year .
             $this->_uniqueNumber, Zend_Media_Id3_Encoding::ISO88591);
        parent::_writeData($writer);
    }
}
