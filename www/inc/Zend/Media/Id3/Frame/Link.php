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
 * @version    $Id: Link.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Frame.php';
/**#@-*/

/**
 * The <i>Linked information</i> frame is used to keep information duplication
 * as low as possible by linking information from another ID3v2 tag that might
 * reside in another audio file or alone in a binary file. It is recommended
 * that this method is only used when the files are stored on a CD-ROM or other
 * circumstances when the risk of file separation is low.
 *
 * Data should be retrieved from the first tag found in the file to which this
 * link points. There may be more than one LINK frame in a tag, but only one
 * with the same contents.
 *
 * A linked frame is to be considered as part of the tag and has the same
 * restrictions as if it was a physical part of the tag (i.e. only one
 * {@link Zend_Media_Id3_Frame_Rvrb RVRB} frame allowed, whether it's linked or
 * not).
 *
 * Frames that may be linked and need no additional data are
 * {@link Zend_Media_Id3_Frame_Aspi ASPI},
 * {@link Zend_Media_Id3_Frame_Etco ETCO},
 * {@link Zend_Media_Id3_Frame_Equ2 EQU2},
 * {@link Zend_Media_Id3_Frame_Mcdi MCDI},
 * {@link Zend_Media_Id3_Frame_Mllt MLLT},
 * {@link Zend_Media_Id3_Frame_Owne OWNE},
 * {@link Zend_Media_Id3_Frame_Rva2 RVA2},
 * {@link Zend_Media_Id3_Frame_Rvrb RVRB},
 * {@link Zend_Media_Id3_Frame_Sytc SYTC}, the text information frames (ie
 * frames descendats of {@link Zend_Media_Id3_TextFrame}) and the URL
 * link frames (ie frames descendants of
 * {@link Zend_Media_Id3_LinkFrame}).
 *
 * The {@link Zend_Media_Id3_Frame_Aenc AENC},
 * {@link Zend_Media_Id3_Frame_Apic APIC},
 * {@link Zend_Media_Id3_Frame_Geob GEOB}
 * and {@link Zend_Media_Id3_Frame_Txxx TXXX} frames may be linked with the
 * content descriptor as additional ID data.
 *
 * The {@link Zend_Media_Id3_Frame_User USER} frame may be linked with the
 * language field as additional ID data.
 *
 * The {@link Zend_Media_Id3_Frame_Priv PRIV} frame may be linked with the owner
 * identifier as additional ID data.
 *
 * The {@link Zend_Media_Id3_Frame_Comm COMM},
 * {@link Zend_Media_Id3_Frame_Sylt SYLT} and
 * {@link Zend_Media_Id3_Frame_Uslt USLT} frames may be linked with three bytes
 * of language descriptor directly followed by a content descriptor as
 * additional ID data.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Link.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Id3_Frame_Link extends Zend_Media_Id3_Frame
{
    /** @var string */
    private $_target;

    /** @var string */
    private $_url;

    /** @var string */
    private $_qualifier;

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

        $this->_target = $this->_reader->read(4);
        list($this->_url, $this->_qualifier) =
            $this->_explodeString8
                ($this->_reader->read($this->_reader->getSize()), 2);
    }

    /**
     * Returns the target tag identifier.
     *
     * @return string
     */
    public function getTarget() 
    {
        return $this->_target; 
    }

    /**
     * Sets the target tag identifier.
     *
     * @param string $target The target tag identifier.
     */
    public function setTarget($target) 
    {
        $this->_target = $target; 
    }

    /**
     * Returns the target tag URL.
     *
     * @return string
     */
    public function getUrl() 
    {
        return $this->_url; 
    }

    /**
     * Sets the target tag URL.
     *
     * @param string $url The target URL.
     */
    public function setUrl($url) 
    {
        $this->_url = $url; 
    }

    /**
     * Returns the additional data to identify further the tag.
     *
     * @return string
     */
    public function getQualifier() 
    {
        return $this->_qualifier; 
    }

    /**
     * Sets the additional data to be used in tag identification.
     *
     * @param string $identifier The qualifier.
     */
    public function setQualifier($qualifier)
    {
        $this->_qualifier = $qualifier;
    }

    /**
     * Writes the frame raw data without the header.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    protected function _writeData($writer)
    {
        $writer->writeString8(substr($this->_target, 0, 4), 4)
               ->writeString8($this->_url, 1)
               ->writeString8($this->_qualifier);
    }
}
