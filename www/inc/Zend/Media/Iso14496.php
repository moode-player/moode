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
 * @subpackage ISO14496
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Iso14496.php 260 2012-03-05 19:06:21Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Iso14496/Box.php';
/**#@-*/

/**
 * This class represents a file in ISO base media file format as described in
 * ISO/IEC 14496 Part 12 standard.
 *
 * The ISO Base Media File Format is designed to contain timed media information
 * for a presentation in a flexible, extensible format that facilitates
 * interchange, management, editing, and presentation of the media. This
 * presentation may be local to the system containing the presentation, or may
 * be via a network or other stream delivery mechanism.
 *
 * The file structure is object-oriented; a file can be decomposed into
 * constituent objects very simply, and the structure of the objects inferred
 * directly from their type. The file format is designed to be independent of
 * any particular network protocol while enabling efficient support for them in
 * general.
 *
 * The ISO Base Media File Format is a base format for media file formats.
 *
 *
 * An overall view of the normal encapsulation structure is provided in the
 * following table.
 *
 * The table shows those boxes that may occur at the top-level in the left-most
 * column; indentation is used to show possible containment. Thus, for example,
 * a {@link Zend_Media_Iso14496_Box_Tkhd Track Header Box} is found in a
 * {@link Zend_Media_Iso14496_Box_Trak Track Box}, which is found in a
 * {@link Zend_Media_Iso14496_Box_Moov Movie Box}. Not all boxes need be used
 * in all files; the mandatory boxes are marked with bold typeface. See the
 * description of the individual boxes for a discussion of what must be assumed
 * if the optional boxes are not present.
 *
 * User data objects shall be placed only in
 * {@link Zend_Media_Iso14496_Box_Moov Movie} or
 * {@link Zend_Media_Iso14496_Box_Trak Track Boxes}, and objects using an
 * extended type may be placed in a wide variety of containers, not just the
 * top level.
 *
 * <ul>
 * <li><b>ftyp</b> -- <i>{@link Zend_Media_Iso14496_Box_Ftyp File Type Box}</i>;
 *     file type and compatibility
 * <li>pdin -- <i>{@link Zend_Media_Iso14496_Box_Pdin Progressive Download
 *     Information Box}</i>
 * <li><b>moov</b> -- <i>{@link Zend_Media_Iso14496_Box_Moov Movie Box}</i>;
 *     container for all the metadata
 *   <ul>
 *   <li><b>mvhd</b> -- <i>{@link Zend_Media_Iso14496_Box_Mvhd Movie Header
 *       Box}</i>; overall declarations
 *   <li><b>trak</b> -- <i>{@link Zend_Media_Iso14496_Box_Trak Track Box}</i>;
 *       container for an individual track or stream
 *     <ul>
 *     <li><b>tkhd</b> -- <i>{@link Zend_Media_Iso14496_Box_Tkhd Track Header
 *         Box}</i>; overall information about the track
 *     <li>tref -- <i>{@link Zend_Media_Iso14496_Box_Tref Track Reference
 *         Box}</i>
 *     <li>edts -- <i>{@link Zend_Media_Iso14496_Box_Edts Edit Box}</i>
 *       <ul>
 *       <li>elst -- <i>{@link Zend_Media_Iso14496_Box_Elst Edit List Box}</i>
 *       </ul>
 *     <li><b>mdia</b> -- <i>{@link Zend_Media_Iso14496_Box_Mdia Media Box}</i>
 *       <ul>
 *       <li><b>mdhd</b> -- <i>{@link Zend_Media_Iso14496_Box_Mdhd Media Header
 *           Box}</i>; overall information about the media
 *       <li><b>hdlr</b> -- <i>{@link Zend_Media_Iso14496_Box_Hdlr Handler
 *           Reference Box}</i>; declares the media type
 *       <li><b>minf</b> -- <i>{@link Zend_Media_Iso14496_Box_Minf Media
 *           Information Box}</i>
 *         <ul>
 *         <li>vmhd -- <i>{@link Zend_Media_Iso14496_Box_Vmhd Video Media Header
 *             Box}</i>; overall information (video track only)
 *         <li>smhd -- <i>{@link Zend_Media_Iso14496_Box_Smhd Sound Media Header
 *             Box}</i>; overall information (sound track only)
 *         <li>hmhd -- <i>{@link Zend_Media_Iso14496_Box_Hmhd Hint Media Header
 *             Box}</i>; overall information (hint track only)
 *         <li>nmhd -- <i>{@link Zend_Media_Iso14496_Box_Nmhd Null Media Header
 *             Box}</i>; overall information (some tracks only)
 *         <li><b>dinf</b> -- <i>{@link Zend_Media_Iso14496_Box_Dinf Data
 *             Information Box}</i>
 *           <ul>
 *           <li><b>dref</b> -- <i>{@link Zend_Media_Iso14496_Box_Dref Data
 *               Reference Box}</i>
 *           </ul>
 *         <li><b>stbl</b> -- <i>{@link Zend_Media_Iso14496_Box_Stbl Sample
 *               Table Box}</i>
 *           <ul>
 *           <li><b>stsd</b> -- <i>{@link Zend_Media_Iso14496_Box_Stsd Sample
 *               Descriptions Box}</i>
 *           <li><b>stts</b> -- <i>{@link Zend_Media_Iso14496_Box_Stts Decoding
 *               Time To Sample Box}</i>
 *           <li>ctts -- <i>{@link Zend_Media_Iso14496_Box_Ctts Composition Time
 *               To Sample Box}</i>
 *           <li><b>stsc</b> -- <i>{@link Zend_Media_Iso14496_Box_Stsc Sample To
 *               Chunk Box}</i>
 *           <li>stsz -- <i>{@link Zend_Media_Iso14496_Box_Stsz Sample Size
 *               Box}</i>
 *           <li>stz2 -- <i>{@link Zend_Media_Iso14496_Box_Stz2 Compact Sample
 *               Size Box}</i>
 *           <li><b>stco</b> -- <i>{@link Zend_Media_Iso14496_Box_Stco Chunk
 *               Offset Box}</i>; 32-bit
 *           <li>co64 -- <i>{@link Zend_Media_Iso14496_Box_Co64 Chunk Ooffset
 *               Box}</i>; 64-bit
 *           <li>stss -- <i>{@link Zend_Media_Iso14496_Box_Stss Sync Sample
 *               Table Box}</i>
 *           <li>stsh -- <i>{@link Zend_Media_Iso14496_Box_Stsh Shadow Sync
 *               Sample Table Box}</i>
 *           <li>padb -- <i>{@link Zend_Media_Iso14496_Box_Padb Padding Bits
 *               Box}</i>
 *           <li>stdp -- <i>{@link Zend_Media_Iso14496_Box_Stdp Sample
 *               Degradation Priority Box}</i>
 *           <li>sdtp -- <i>{@link Zend_Media_Iso14496_Box_Sdtp Independent and
 *               Disposable Samples Box}</i>
 *           <li>sbgp -- <i>{@link Zend_Media_Iso14496_Box_Sbgp Sample To Group
 *               Box}</i>
 *           <li>sgpd -- <i>{@link Zend_Media_Iso14496_Box_Sgpd Sample Group
 *               Description}</i>
 *           <li>subs -- <i>{@link Zend_Media_Iso14496_Box_Subs Sub-Sample
 *               Information Box}</i>
 *           </ul>
 *         </ul>
 *       </ul>
 *     </ul>
 *   <li>mvex -- <i>{@link Zend_Media_Iso14496_Box_Mvex Movie Extends Box}</i>
 *     <ul>
 *     <li>mehd -- <i>{@link Zend_Media_Iso14496_Box_Mehd Movie Extends Header
 *         Box}</i>
 *     <li><b>trex</b> -- <i>{@link Zend_Media_Iso14496_Box_Trex Track Extends
 *         Box}</i>
 *     </ul>
 *   <li>ipmc -- <i>{@link Zend_Media_Iso14496_Box_Ipmc IPMP Control Box}</i>
 *   </ul>
 * <li>moof -- <i>{@link Zend_Media_Iso14496_Box_Moof Movie Fragment Box}</i>
 *   <ul>
 *   <li><b>mfhd</b> -- <i>{@link Zend_Media_Iso14496_Box_Mfhd Movie Fragment
 *       Header Box}</i>
 *   <li>traf -- <i>{@link Zend_Media_Iso14496_Box_Traf Track Fragment Box}</i>
 *     <ul>
 *     <li><b>tfhd</b> -- <i>{@link Zend_Media_Iso14496_Box_Tfhd Track Fragment
 *         Header Box}</i>
 *     <li>trun -- <i>{@link Zend_Media_Iso14496_Box_Trun Track Fragment
 *         Run}</i>
 *     <li>sdtp -- <i>{@link Zend_Media_Iso14496_Box_Sdtp Independent and
 *         Disposable Samples}</i>
 *     <li>sbgp -- <i>{@link Zend_Media_Iso14496_Box_Sbgp !SampleToGroup
 *         Box}</i>
 *     <li>subs -- <i>{@link Zend_Media_Iso14496_Box_Subs Sub-Sample Information
 *         Box}</i>
 *     </ul>
 *   </ul>
 * <li>mfra -- <i>{@link Zend_Media_Iso14496_Box_Mfra Movie Fragment Random
 *     Access Box}</i>
 *   <ul>
 *   <li>tfra -- <i>{@link Zend_Media_Iso14496_Box_Tfra Track Fragment Random
 *       Access Box}</i>
 *   <li><b>mfro</b> -- <i>{@link Zend_Media_Iso14496_Box_Mfro Movie Fragment
 *       Random Access Offset Box}</i>
 *   </ul>
 * <li>mdat -- <i>{@link Zend_Media_Iso14496_Box_Mdat Media Data Box}</i>
 * <li>free -- <i>{@link Zend_Media_Iso14496_Box_Free Free Space Box}</i>
 * <li>skip -- <i>{@link Zend_Media_Iso14496_Box_Skip Free Space Box}</i>
 *   <ul>
 *   <li>udta -- <i>{@link Zend_Media_Iso14496_Box_Udta User Data Box}</i>
 *     <ul>
 *     <li>cprt -- <i>{@link Zend_Media_Iso14496_Box_Cprt Copyright Box}</i>
 *     </ul>
 *   </ul>
 * <li>meta -- <i>{@link Zend_Media_Iso14496_Box_Meta The Meta Box}</i>
 *   <ul>
 *   <li><b>hdlr</b> -- <i>{@link Zend_Media_Iso14496_Box_Hdlr Handler Reference
 *       Box}</i>; declares the metadata type
 *   <li>dinf -- <i>{@link Zend_Media_Iso14496_Box_Dinf Data Information
 *       Box}</i>
 *     <ul>
 *     <li>dref -- <i>{@link Zend_Media_Iso14496_Box_Dref Data Reference
 *         Box}</i>; declares source(s) of metadata items
 *     </ul>
 *   <li>ipmc -- <i>{@link Zend_Media_Iso14496_Box_Ipmc IPMP Control Box}</i>
 *   <li>iloc -- <i>{@link Zend_Media_Iso14496_Box_Iloc Item Location Box}</i>
 *   <li>ipro -- <i>{@link Zend_Media_Iso14496_Box_Ipro Item Protection Box}</i>
 *     <ul>
 *     <li>sinf -- <i>{@link Zend_Media_Iso14496_Box_Sinf Protection Scheme
 *         Information Box}</i>
 *       <ul>
 *       <li>frma -- <i>{@link Zend_Media_Iso14496_Box_Frma Original Format
 *           Box}</i>
 *       <li>imif -- <i>{@link Zend_Media_Iso14496_Box_Imif IPMP Information
 *           Box}</i>
 *       <li>schm -- <i>{@link Zend_Media_Iso14496_Box_Schm Scheme Type Box}</i>
 *       <li>schi -- <i>{@link Zend_Media_Iso14496_Box_Schi Scheme Information
 *           Box}</i>
 *       </ul>
 *     </ul>
 *   <li>iinf -- <i>{@link Zend_Media_Iso14496_Box_Iinf Item Information
 *       Box}</i>
 *     <ul>
 *     <li>infe -- <i>{@link Zend_Media_Iso14496_Box_Infe Item Information Entry
 *         Box}</i>
 *     </ul>
 *   <li>xml -- <i>{@link Zend_Media_Iso14496_Box_Xml XML Box}</i>
 *   <li>bxml -- <i>{@link Zend_Media_Iso14496_Box_Bxml Binary XML Box}</i>
 *   <li>pitm -- <i>{@link Zend_Media_Iso14496_Box_Pitm Primary Item Reference
 *       Box}</i>
 *   </ul>
 * </ul>
 *
 * There are two non-standard extensions to the ISO 14496 standard that add the
 * ability to include file meta information. Both the boxes reside under
 * moov.udta.meta.
 *
 * <ul>
 * <li><i>moov</i> -- <i>{@link Zend_Media_Iso14496_Box_Moov Movie Box}</i>;
 *     container for all the metadata
 * <li><i>udta</i> -- <i>{@link Zend_Media_Iso14496_Box_Udta User Data Box}</i>
 * <li><i>meta</i> -- <i>{@link Zend_Media_Iso14496_Box_Meta The Meta Box}</i>
 *   <ul>
 *   <li>ilst -- <i>{@link Zend_Media_Iso14496_Box_Ilst The iTunes/iPod Tag
 *       Container Box}</i>
 *   <li>id32 -- <i>{@link Zend_Media_Iso14496_Box_Id32 The ID3v2 Box}</i>
 *   </ul>
 * </ul>
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ISO14496
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Iso14496.php 260 2012-03-05 19:06:21Z svollbehr $
 */
final class Zend_Media_Iso14496 extends Zend_Media_Iso14496_Box
{
    /** @var string */
    private $_filename;

    /** @var boolean */
    private $_autoClose = false;

    /**
     * Constructs the Zend_Media_Iso14496 class with given file and options.
     *
     * The following options are currently recognized:
     *   o base -- Indicates that only boxes with the given base path are parsed
     *     from the ISO base media file. Parsing all boxes can possibly have a
     *     significant impact on running time. Base path is a list of nested
     *     boxes separated by a dot. The use of base option implies readonly
     *     option.
     *   o readonly -- Indicates that the file is read from a temporary location
     *     or another source it cannot be written back to.
     *
     * @param string|resource|Zend_Io_Reader $filename The path to the file,
     *  file descriptor of an opened file, or a {@link Zend_Io_Reader} instance.
     * @param Array                          $options  The options array.
     */
    public function __construct($filename, $options = array())
    {
        if (isset($options['base'])) {
            $options['readonly'] = true;
        }
        if ($filename instanceof Zend_Io_Reader) {
            $this->_reader = &$filename;
        } else {
            require_once 'Zend/Io/FileReader.php';
            try {
                $this->_reader = new Zend_Io_FileReader($filename);
                $this->_autoClose = true;
            } catch (Zend_Io_Exception $e) {
                $this->_reader = null;
                require_once 'Zend/Media/Iso14496/Exception.php';
                throw new Zend_Media_Iso14496_Exception($e->getMessage());
            }
            if (is_string($filename) && !isset($options['readonly'])) {
                $this->_filename = $filename;
            }
        }
        $this->setOptions($options);
        $this->setOffset(0);
        $this->setSize($this->_reader->getSize());
        $this->setType('file');
        $this->setContainer(true);
        $this->constructBoxes();
    }

    /**
     * Closes down the reader.
     */
    public function __destruct()
    {
        parent::__destruct();
        if ($this->_autoClose === true && $this->_reader !== null) {
            $this->_reader->close();
        }
    }

    /**
     * Writes the changes back to given media file.
     *
     * The write operation commits only changes made to the Movie Box. It
     * further changes the order of the Movie Box and Media Data Box in a way
     * compatible for progressive download from a web page.
     *
     * All box offsets must be assumed to be invalid after the write operation.
     *
     * @param string $filename The optional path to the file, use null to save
     *                         to the same file.
     */
    public function write($filename)
    {
        if ($filename === null && ($filename = $this->_filename) === null) {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception
                ('No file given to write to');
        } else if ($filename !== null && $this->_filename !== null &&
                   realpath($filename) != realpath($this->_filename) &&
                   !copy($this->_filename, $filename)) {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception
                ('Unable to copy source to destination: ' .
                 realpath($this->_filename) . '->' . realpath($filename));
        }

        if (($fd = fopen
             ($filename, file_exists($filename) ? 'r+b' : 'wb')) === false) {
            require_once 'Zend/Media/Iso14496/Exception.php';
            throw new Zend_Media_Iso14496_Exception
                ('Unable to open file for writing: ' . $filename);
        }

        /* Calculate file size */
        fseek($fd, 0, SEEK_END);
        $oldFileSize = ftell($fd);
        $oldMoovSize = $this->moov->getSize();
        $this->moov->udta->meta->free->setSize(8);
        $this->moov->udta->meta->hdlr->setHandlerType('mdir');
        $newFileSize = $oldFileSize - $oldMoovSize + $this->moov->getHeapSize();

        /* Calculate free space size */
        if ($oldFileSize < $newFileSize ||
                $this->mdat->getOffset() < $this->moov->getOffset()) {
            // Add constant 4096 bytes for free space to be used later
            $this->moov->udta->meta->free->setSize(8 /* header */ + 4096);
            ftruncate($fd, $newFileSize += 4096);
        } else {
            // Adjust free space to fill up the rest of the space
            $this->moov->udta->meta->free->setSize
                (8 + $oldFileSize - $newFileSize);
            $newFileSize = $oldFileSize;
        }

        /* Calculate positions */
        if ($this->mdat->getOffset() < $this->moov->getOffset()) {
            $start = $this->mdat->getOffset();
            $until = $this->moov->getOffset();
            $where = $newFileSize;
            $delta = $this->moov->getHeapSize();
        } else {
            $start = $this->moov->getOffset();
            $until = $oldFileSize;
            $where = $newFileSize;
            $delta = $newFileSize - $oldFileSize;
        }

        /* Move data to the end of the file */
        if ($newFileSize != $oldFileSize) {
            for ($i = 1, $cur = $until; $cur > $start; $cur -= 1024, $i++) {
                fseek
                    ($fd, $until - (($i * 1024) +
                     ($excess = $cur - 1024 > $start ?
                      0 : $cur - $start - 1024)));
                $buffer = fread($fd, 1024);
                fseek($fd, $where - (($i * 1024) + $excess));
                fwrite($fd, $buffer, 1024);
            }
        }


        /* Update stco/co64 to correspond the data move */
        foreach ($this->moov->getBoxesByIdentifier('trak') as $trak) {
            $chunkOffsetBox =
                (isset($trak->mdia->minf->stbl->stco) ?
                 $trak->mdia->minf->stbl->stco :
                 $trak->mdia->minf->stbl->co64);
            $chunkOffsetTable = $chunkOffsetBox->getChunkOffsetTable();
            $chunkOffsetTableCount = count($chunkOffsetTable);
            for ($i = 1; $i <= $chunkOffsetTableCount; $i++) {
                $chunkOffsetTable[$i] += $delta;
            }
            $chunkOffsetBox->setChunkOffsetTable($chunkOffsetTable);
        }

        /* Write moov box */
        fseek($fd, $start);
        $this->moov->write(new Zend_Io_Writer($fd));
        fclose($fd);
    }
}
