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
 * @version    $Id: Owne.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
require_once 'Zend/Media/Id3/Encoding.php';
/**#@-*/

/**
 * The <i>Ownership frame</i> might be used as a reminder of a made transaction
 * or, if signed, as proof. Note that the {@link Zend_Media_Id3_Frame_User USER}
 * and {@link Zend_Media_Id3_Frame_Town TOWN} frames are good to use in
 * conjunction with this one.
 *
 * There may only be one OWNE frame in a tag.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Owne.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Owne extends Zend_Media_Id3_Frame
    implements Zend_Media_Id3_Encoding
{
    /** @var integer */
    private $_encoding;

    /** @var string */
    private $_currency = 'EUR';

    /** @var string */
    private $_price;

    /** @var string */
    private $_date;

    /** @var string */
    private $_seller;

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

        $encoding = $this->_reader->readUInt8();
        $this->_currency = strtoupper($this->_reader->read(3));
        $offset = $this->_reader->getOffset();
        list ($this->_price) =
            $this->_explodeString8
                ($this->_reader->read($this->_reader->getSize()), 2);
        $this->_reader->setOffset($offset + strlen($this->_price) + 1);
        $this->_date = $this->_reader->read(8);
        $this->_seller = $this->_convertString
            ($this->_reader->read($this->_reader->getSize()), $encoding);
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
     * Returns the currency code, encoded according to
     * {@link http://www.iso.org/iso/support/faqs/faqs_widely_used_standards/widely_used_standards_other/currency_codes/currency_codes_list-1.htm
     * ISO 4217} alphabetic currency code.
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->_currency;
    }

    /**
     * Sets the currency used in transaction, encoded according to
     * {@link http://www.iso.org/iso/support/faqs/faqs_widely_used_standards/widely_used_standards_other/currency_codes/currency_codes_list-1.htm
     * ISO 4217} alphabetic currency code.
     *
     * @param string $currency The currency code.
     */
    public function setCurrency($currency)
    {
        $this->_currency = strtoupper($currency);
    }

    /**
     * Returns the price.
     *
     * @return double
     */
    public function getPrice()
    {
        return doubleval($this->_price);
    }

    /**
     * Sets the price.
     *
     * @param integer $price The price.
     */
    public function setPrice($price)
    {
        $this->_price = number_format($price, 2, '.', '');
    }

    /**
     * Returns the date describing for how long the price is valid.
     *
     * @internal The ID3v2 standard does not declare the time zone to be used
     *  in the date. Date must thus be expressed as GMT/UTC.
     * @return Zend_Date
     */
    public function getDate()
    {
        require_once 'Zend/Date.php';
        $date = new Zend_Date(0);
        $date->setTimezone('UTC');
        $date->set($this->_date, 'yyyyMMdd');
        return $date;
    }

    /**
     * Sets the date describing for how long the price is valid for.
     *
     * @internal The ID3v2 standard does not declare the time zone to be used
     *  in the date. Date must thus be expressed as GMT/UTC.
     * @param Zend_Date $date The date.
     */
    public function setDate($date)
    {
        require_once 'Zend/Date.php';
        if ($date === null) {
            $date = Zend_Date::now();
        }
        $date->setTimezone('UTC');
        $this->_date = $date->toString('yyyyMMdd');
    }

    /**
     * Returns the name of the seller.
     *
     * @return string
     */
    public function getSeller() 
    {
         return $this->_seller; 
    }

    /**
     * Sets the name of the seller using given encoding.
     *
     * @param string $seller The name of the seller.
     * @param integer $encoding The text encoding.
     */
    public function setSeller($seller, $encoding = null)
    {
        $this->_seller = $seller;
        if ($encoding !== null) {
            $this->setEncoding($encoding);
        }
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeUInt8($this->_encoding)
               ->write($this->_currency)
               ->writeString8($this->_price, 1)
               ->write($this->_date);
        switch ($this->_encoding) {
            case self::UTF16LE:
                $writer->writeString16
                    ($this->_seller, Zend_Io_Writer::LITTLE_ENDIAN_ORDER);
                break;
            case self::UTF16:
                // break intentionally omitted
            case self::UTF16BE:
                $writer->writeString16($this->_seller);
                break;
            default:
                $writer->writeString8($this->_seller);
                break;
        }
    }
}
