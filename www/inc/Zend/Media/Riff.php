<?php
/**
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Riff.php 257 2012-01-26 05:30:58Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Riff/ContainerChunk.php';
/**#@-*/

/**
 * This class represents a file in Resource Interchange File Format as described in Multimedia Programming Interface
 * and Data Specifications 1.0 by Microsoft Corporation (April 15, 1994; Revision: 3.0).
 *
 * The Resource Interchange File Format (RIFF), a tagged file structure, is a general specification upon which many file
 * formats can be defined. The main advantage of RIFF is its extensibility; file formats based on RIFF can be
 * future-proofed, as format changes can be ignored by existing applications. The RIFF file format is suitable for the
 * following multimedia tasks:
 *  o Playing back multimedia data
 *  o Recording multimedia data
 *  o Exchanging multimedia data between applications and across platforms
 *
 * The structure of a RIFF file is similar to the structure of an Electronic Arts IFF file. RIFF is not actually a file
 * format itself (since it does not represent a specific kind of information), but its name contains the words
 * interchange file format in recognition of its roots in IFF. Refer to the EA IFF definition document, EA IFF 85
 * Standard for Interchange Format Files, for a list of reasons to use a tagged file format. The following is current
 * (as per revision 3.0 of the specification) list of registered RIFF types.
 *  o PAL  -- RIFF Palette Format
 *  o RDIB -- RIFF Device Independent Bitmap Format
 *  o RMID -- RIFF MIDI Format
 *  o RMMP -- RIFF Multimedia Movie File Format
 *  o WAVE -- Waveform Audio Format
 * 
 * @category   Zend
 * @package    Zend_Media
 * @subpackage Riff
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2011 Sven Vollbehr
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Riff.php 257 2012-01-26 05:30:58Z svollbehr $
 */
final class Zend_Media_Riff extends Zend_Media_Riff_ContainerChunk
{
    /** @var string */
    private $_filename = null;

    /**
     * Constructs the class with given file.
     *
     * @param string|resource|Zend_Io_Reader $filename The path to the file, file descriptor of an opened file, or a
     *  {@link Zend_Io_Reader} instance.
     * @throws Zend_Media_Riff_Exception if given file descriptor is not valid or an error occurs in stream handling.
     */
    public function __construct($filename)
    {
        if ($filename instanceof Zend_Io_Reader) {
            $reader = &$filename;
        } else {
            $this->_filename = $filename;
            require_once('Zend/Io/FileReader.php');
            try {
                $reader = new Zend_Io_FileReader($filename);
            } catch (Zend_Io_Exception $e) {
                require_once 'Zend/Media/Riff/Exception.php';
                throw new Zend_Media_Riff_Exception($e->getMessage());
            }
        }

        parent::__construct($reader);
    }
}
