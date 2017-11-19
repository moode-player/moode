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
 * @version    $Id: Id3v2.php 273 2012-08-21 17:22:52Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Id3/Object.php';
require_once 'Zend/Media/Id3/Header.php';
/**#@-*/

/**
 * This class represents a file containing ID3v2 header as described in
 * {@link http://www.id3.org/id3v2.4.0-structure ID3v2 structure document}.
 *
 * ID3v2 is a general tagging format for audio, which makes it possible to store
 * meta data about the audio inside the audio file itself. The ID3 tag is mainly
 * targeted at files encoded with MPEG-1/2 layer I, MPEG-1/2 layer II, MPEG-1/2
 * layer III and MPEG-2.5, but may work with other types of encoded audio or as
 * a stand alone format for audio meta data.
 *
 * ID3v2 is designed to be as flexible and expandable as possible to meet new
 * meta information needs that might arise. To achieve that ID3v2 is constructed
 * as a container for several information blocks, called frames, whose format
 * need not be known to the software that encounters them. Each frame has an
 * unique and predefined identifier which allows software to skip unknown
 * frames.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Id3v2.php 273 2012-08-21 17:22:52Z svollbehr $
 */
final class Zend_Media_Id3v2 extends Zend_Media_Id3_Object
{
    /** @var Zend_Media_Id3_Header */
    private $_header;

    /** @var Zend_Media_Id3_ExtendedHeader */
    private $_extendedHeader;

    /** @var Zend_Media_Id3_Header */
    private $_footer;

    /** @var Array */
    private $_frames = array();

    /** @var string */
    private $_filename = null;

    /**
     * Constructs the Zend_Media_Id3v2 class with given file and options. The
     * options array may also be given as the only parameter.
     *
     * The following options are currently recognized:
     *   o encoding -- Indicates the encoding that all the texts are presented
     *     with. See the documentation of iconv for supported values. Please
     *     note that write operations do not convert string and thus encodings
     *     are limited to those supported by the {@link Zend_Media_Id3_Encoding}
     *     interface.
     *   o version -- The ID3v2 tag version to use in write operation. This
     *     option is automatically set when a tag is read from a file and
     *     defaults to version 4.0 for tag write.
     *   o compat -- Normally unsynchronization is handled automatically behind
     *     the scenes. However, current versions of Windows operating system and
     *     Windows Media Player, just to name a few, do not support ID3v2.4 tags
     *     nor ID3v2.3 tags with unsynchronization. Hence, for compatibility
     *     reasons, this option is made available to disable automatic tag level
     *     unsynchronization scheme that version 3.0 supports.
     *   o readonly -- Indicates that the tag is read from a temporary file or
     *     another source it cannot be written back to. The tag can, however,
     *     still be written to another file.
     *
     * @todo  Only limited subset of flags are processed.
     * @todo  Utilize the SEEK frame and search for a footer to find the tag
     * @todo  Utilize the LINK frame to fetch frames from other sources
     * @param string|resource|Zend_Io_Reader $filename The path to the file,
     *  file descriptor of an opened file, or a {@link Zend_Io_Reader} instance.
     * @param Array                          $options  The options array.
     * @throws Zend_Media_Id3_Exception if given file descriptor is not valid
     */
    public function __construct($filename = null, $options = array())
    {
        parent::__construct(null, $options);

        if (is_array($filename)) {
            $options = $filename;
            $filename = null;
        }

        if ($filename === null) {
            $this->_header = new Zend_Media_Id3_Header(null, $options);
            return;
        }

        if ($filename instanceof Zend_Io_Reader) {
            $this->_reader = &$filename;
        } else {
            require_once 'Zend/Io/FileReader.php';
            try {
                $this->_reader = new Zend_Io_FileReader($filename);
            } catch (Zend_Io_Exception $e) {
                $this->_reader = null;
                require_once 'Zend/Media/Id3/Exception.php';
                throw new Zend_Media_Id3_Exception($e->getMessage());
            }
            if (is_string($filename) && !isset($options['readonly'])) {
                $this->_filename = $filename;
            }
        }

        $startOffset = $this->_reader->getOffset();

        if ($this->_reader->read(3) != 'ID3') {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception
                ('File does not contain ID3v2 tag');
        }

        $this->_header = new Zend_Media_Id3_Header($this->_reader, $options);

        $tagSize = $this->_header->getSize();

        if ($this->_header->getVersion() < 3 ||
            $this->_header->getVersion() > 4) {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception
                ('File does not contain ID3v2 tag of supported version: v2.' .
                 $this->_header->getVersion());
        }
        if ($this->_header->getVersion() < 4 &&
            $this->_header->hasFlag(Zend_Media_Id3_Header::UNSYNCHRONISATION)) {
            $data = $this->_reader->read($this->_header->getSize());
            require_once 'Zend/Io/StringReader.php';
            $this->_reader = new Zend_Io_StringReader
                ($this->_decodeUnsynchronisation($data));
            $tagSize = $this->_reader->getSize();
        }
        $this->clearOption('unsynchronisation');
        if ($this->_header->hasFlag(Zend_Media_Id3_Header::UNSYNCHRONISATION)) {
            $this->setOption('unsynchronisation', true);
        }
        if ($this->_header->hasFlag(Zend_Media_Id3_Header::EXTENDED_HEADER)) {
            require_once 'Zend/Media/Id3/ExtendedHeader.php';
            $this->_extendedHeader =
                new Zend_Media_Id3_ExtendedHeader($this->_reader, $options);
        }
        if ($this->_header->hasFlag(Zend_Media_Id3_Header::FOOTER)) {
            // skip footer, and rather copy header
            $this->_footer = &$this->_header;
        }

        while (true) {
            $offset = $this->_reader->getOffset();

            // Jump off the loop if we reached the end of the tag
            if ($offset - $startOffset - 10 >= $tagSize -
                ($this->hasFooter() ? 10 : 0) - 10 /* header */) {
                break;
            }

            // Jump off the loop if we reached padding
            if (ord($identifier = $this->_reader->read(1)) === 0) {
                break;
            }

            $identifier .= $this->_reader->read(3);

            // Jump off the loop if we reached invalid entities. This fix is
            // just to make things work. Utility called MP3ext does not seem
            // to know what it is doing as it uses padding to write its
            // version information there.
            if ($identifier == 'MP3e') {
                break;
            }

            $this->_reader->setOffset($offset);
            if (@fopen($file = 'Zend/Media/Id3/Frame/' .
                       ucfirst(strtolower($identifier)) . '.php', 'r',
                       true) !== false) {
                require_once($file);
            }
            if (class_exists
                ($classname = 'Zend_Media_Id3_Frame_' .
                     ucfirst(strtolower($identifier)))) {
                $frame = new $classname($this->_reader, $options);
            } else {
                require_once 'Zend/Media/Id3/Frame/Unknown.php';
                $frame =
                    new Zend_Media_Id3_Frame_Unknown($this->_reader, $options);
            }

            if (!isset($this->_frames[$frame->getIdentifier()])) {
                $this->_frames[$frame->getIdentifier()] = array();
            }
            $this->_frames[$frame->getIdentifier()][] = $frame;
        }
    }

    /**
     * Returns the header object.
     *
     * @return Zend_Media_Id3_Header
     */
    public function getHeader()
    {
        return $this->_header;
    }

    /**
     * Checks whether there is an extended header present in the tag. Returns
     * <var>true</var> if the header is present, <var>false</var> otherwise.
     *
     * @return boolean
     */
    public function hasExtendedHeader()
    {
        if ($this->_header) {
            return $this->_header->hasFlag
                (Zend_Media_Id3_Header::EXTENDED_HEADER);
        }
        return false;
    }

    /**
     * Returns the extended header object if present, or <var>false</var>
     * otherwise.
     *
     * @return Zend_Media_Id3_ExtendedHeader|false
     */
    public function getExtendedHeader()
    {
        if ($this->hasExtendedHeader()) {
            return $this->_extendedHeader;
        }
        return false;
    }

    /**
     * Sets the extended header object.
     *
     * @param Zend_Media_Id3_ExtendedHeader $extendedHeader The header object
     */
    public function setExtendedHeader($extendedHeader)
    {
        if (is_subclass_of($extendedHeader, 'Zend_Media_Id3_ExtendedHeader')) {
            $this->_header->flags =
                $this->_header->flags | Zend_Media_Id3_Header::EXTENDED_HEADER;
            $this->_extendedHeader->setOptions($this->getOptions());
            $this->_extendedHeader = $extendedHeader;
        } else {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception('Invalid argument');
        }
    }

    /**
     * Checks whether there is a frame given as an argument defined in the tag.
     * Returns <var>true</var> if one ore more frames are present,
     * <var>false</var> otherwise.
     *
     * @param string $identifier The frame name.
     * @return boolean
     */
    public function hasFrame($identifier)
    {
        return isset($this->_frames[$identifier]);
    }

    /**
     * Returns all the frames the tag contains as an associate array. The frame
     * identifiers work as keys having an array of frames as associated value.
     *
     * @return Array
     */
    public function getFrames()
    {
        return $this->_frames;
    }

    /**
     * Returns an array of frames matching the given identifier or an empty
     * array if no frames matched the identifier.
     *
     * The identifier may contain wildcard characters '*' and '?'. The asterisk
     * matches against zero or more characters, and the question mark matches
     * any single character.
     *
     * Please note that one may also use the shorthand $obj->identifier to
     * access the first frame with the identifier given. Wildcards cannot be
     * used with the shorthand method.
     *
     * @param string $identifier The frame name.
     * @return Array
     */
    public function getFramesByIdentifier($identifier)
    {
        $matches = array();
        $searchPattern = '/^' .
            str_replace(array('*', '?'), array('.*', '.'), $identifier) . '$/i';
        foreach ($this->_frames as $identifier => $frames) {
            if (preg_match($searchPattern, $identifier)) {
                foreach ($frames as $frame) {
                    $matches[] = $frame;
                }
            }
        }
        return $matches;
    }

    /**
     * Removes any frames matching the given object identifier.
     *
     * The identifier may contain wildcard characters '*' and '?'. The asterisk
     * matches against zero or more characters, and the question mark matches
     * any single character.
     *
     * One may also use the shorthand unset($obj->identifier) to achieve the
     * same result. Wildcards cannot be used with the shorthand method.
     *
     * @param string $identifier The frame name.
     */
    public final function removeFramesByIdentifier($identifier)
    {
        $searchPattern = '/^' .
            str_replace(array('*', '?'), array('.*', '.'), $identifier) . '$/i';
        foreach ($this->_frames as $identifier => $frames) {
            if (preg_match($searchPattern, $identifier)) {
                foreach ($frames as $key => $value) {
                    unset($this->_frames[$identifier][$key]);
                }
            }
        }
    }

    /**
     * Adds a new frame to the tag and returns it.
     *
     * @param Zend_Media_Id3_Frame $frame The frame to add.
     * @return Zend_Media_Id3_Frame
     */
    public function addFrame($frame)
    {
        $frame->setOptions($this->getOptions());
        $frame->setEncoding
            ($this->getOption('encoding', $this->getOption('version', 4) < 4 ?
             Zend_Media_Id3_Encoding::ISO88591 : Zend_Media_Id3_Encoding::UTF));
        if (!$this->hasFrame($frame->getIdentifier())) {
            $this->_frames[$frame->getIdentifier()] = array();
        }
        return $this->_frames[$frame->getIdentifier()][] = $frame;
    }

    /**
     * Remove the given frame from the tag.
     *
     * @param Zend_Media_Id3_Frame $frame The frame to remove.
     */
    public function removeFrame($frame)
    {
        if (!$this->hasFrame($frame->getIdentifier())) {
            return;
        }
        foreach ($this->_frames[$frame->getIdentifier()] as $key => $value) {
            if ($frame === $value) {
                unset($this->_frames[$frame->getIdentifier()][$key]);
            }
        }
    }

    /**
     * Checks whether there is a footer present in the tag. Returns
     * <var>true</var> if the footer is present, <var>false</var> otherwise.
     *
     * @return boolean
     */
    public function hasFooter()
    {
        return $this->_header->hasFlag(Zend_Media_Id3_Header::FOOTER);
    }

    /**
     * Returns the footer object if present, or <var>false</var> otherwise.
     *
     * @return Zend_Media_Id3_Header|false
     */
    public function getFooter()
    {
        if ($this->hasFooter()) {
            return $this->_footer;
        }
        return false;
    }

    /**
     * Sets whether the tag should have a footer defined.
     *
     * @param boolean $useFooter Whether the tag should have a footer
     */
    public function setFooter($useFooter)
    {
        if ($useFooter) {
            $this->_header->setFlags
                ($this->_header->getFlags() | Zend_Media_Id3_Header::FOOTER);
            $this->_footer = &$this->_header;
        } else {
            /* Count footer bytes towards the tag size, so it gets removed or
               overridden upon re-write */
            if ($this->hasFooter()) {
                $this->_header->setSize($this->_header->getSize() + 10);
            }

            $this->_header->setFlags
                ($this->_header->getFlags() & ~Zend_Media_Id3_Header::FOOTER);
            $this->_footer = null;
        }
    }

    /**
     * Writes the possibly altered ID3v2 tag back to the file where it was read.
     * If the class was constructed without a file name, one can be provided
     * here as an argument. Regardless, the write operation will override
     * previous tag information, if found.
     *
     * If write is called on a tag without any frames to it, current tag is
     * removed from the file altogether.
     *
     * @param string|Zend_Io_Writer $filename The optional path to the file, use
     *                                        null to save to the same file.
     */
    public function write($filename)
    {
        if ($filename === null && ($filename = $this->_filename) === null) {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception('No file given to write to');
        } else if ($filename !== null && $filename instanceof Zend_Io_Writer) {
            require_once 'Zend/Io/Writer.php';
            $this->_writeData($filename);
            return;
        } else if ($filename !== null && $this->_filename !== null &&
                   realpath($filename) != realpath($this->_filename) &&
                   !copy($this->_filename, $filename)) {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception
                ('Unable to copy source to destination: ' .
                 realpath($this->_filename) . '->' . realpath($filename));
        }

        if (($fd = fopen
             ($filename, file_exists($filename) ? 'r+b' : 'wb')) === false) {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception
                ('Unable to open file for writing: ' . $filename);
        }

        $hasNoFrames = true;
        foreach ($this->_frames as $identifier => $instances) {
            if (count($instances) > 0) {
                $hasNoFrames = false;
                break;
            }
        }
        if ($hasNoFrames === true) {
            $this->remove(new Zend_Io_Reader($fd));
            return;
        }

        if ($this->_reader !== null) {
            $oldTagSize = 10 /* header */ + $this->_header->getSize();
        } else {
            $reader = new Zend_Io_Reader($fd);
            if ($reader->read(3) == 'ID3') {
                $header = new Zend_Media_Id3_Header($reader);
                $oldTagSize = 10 /* header */ + $header->getSize();
            } else {
                $oldTagSize = 0;
            }
        }
        require_once 'Zend/Io/StringWriter.php';
        $tag = new Zend_Io_StringWriter();
        $this->_writeData($tag);
        $tagSize = $tag->getSize();

        if ($tagSize > $oldTagSize) {
            fseek($fd, 0, SEEK_END);
            $oldFileSize = ftell($fd);
            ftruncate
                ($fd, $newFileSize = $tagSize - $oldTagSize + $oldFileSize);
            for ($i = 1, $cur = $oldFileSize; $cur > 0; $cur -= 1024, $i++) {
              if ($cur >= 1024) {
                fseek($fd, -(($i * 1024) +
                      ($newFileSize - $oldFileSize)), SEEK_END);
                $buffer = fread($fd, 1024);
                fseek($fd, -($i * 1024), SEEK_END);
                $bytes = fwrite($fd, $buffer, 1024);
              } else {
                fseek($fd, 0);
                $buffer = fread($fd, $cur);
                fseek($fd, $newFileSize - $oldFileSize);
                $bytes = fwrite($fd, $buffer, $cur);
              }
            }
            if (($remaining = $oldFileSize % 1024) != 0) {
                // huh?
            }
            fseek($fd, 0, SEEK_END);
        }
        fseek($fd, 0);
        for ($i = 0; $i < $tagSize; $i += 1024) {
            fseek($tag->getFileDescriptor(), $i);
            $bytes = fwrite($fd, fread($tag->getFileDescriptor(), 1024));
        }
        fclose($fd);

        $this->_filename = $filename;
    }

    /**
     * Writes the tag data.
     *
     * @param Zend_Io_Writer $writer The writer object.
     * @return void
     */
    private function _writeData($writer)
    {
        $this->clearOption('unsynchronisation');

        $buffer = new Zend_Io_StringWriter();
        foreach ($this->_frames as $frames) {
            foreach ($frames as $frame) {
                $frame->write($buffer);
            }
        }
        $frameData = $buffer->toString();
        $frameDataLength = strlen($frameData);
        $paddingLength = 0;

        // ID3v2.4.0 supports frame level unsynchronisation while
        // ID3v2.3.0 supports only tag level unsynchronisation.
        if ($this->getOption('version', 4) < 4 &&
                $this->getOption('compat', false) !== true) {
            $frameData = $this->_encodeUnsynchronisation($frameData);
            if (($len = strlen($frameData)) != $frameDataLength) {
                $frameDataLength = $len;
                $this->_header->setFlags
                    ($this->_header->getFlags() |
                     Zend_Media_Id3_Header::UNSYNCHRONISATION);
            } else {
                $this->_header->setFlags
                    ($this->_header->getFlags() &
                     ~Zend_Media_Id3_Header::UNSYNCHRONISATION);
            }
        }

        // The tag padding is calculated as follows. If the tag can be written
        // in the space of the previous tag, the remaining space is used for
        // padding. If there is no previous tag or the new tag is bigger than
        // the space taken by the previous tag, the padding is a constant
        // 4096 bytes.
        if ($this->hasFooter() === false) {
            if ($this->_reader !== null &&
                $frameDataLength < $this->_header->getSize()) {
                $paddingLength = $this->_header->getSize() - $frameDataLength;
            } else {
                $paddingLength = 4096;
            }
        }

        /* ID3v2.4.0 CRC calculated w/ padding */
        if ($this->getOption('version', 4) >= 4) {
            $frameData =
                str_pad($frameData, $frameDataLength += $paddingLength, "\0");
        }

        $extendedHeaderData = '';
        $extendedHeaderDataLength = 0;
        if ($this->hasExtendedHeader()) {
            $this->_extendedHeader->setPadding($paddingLength);
            if ($this->_extendedHeader->hasFlag
                (Zend_Media_Id3_ExtendedHeader::CRC32)) {
                $crc = crc32($frameData);
                if ($crc & 0x80000000) {
                    $crc = -(($crc ^ 0xffffffff) + 1);
                }
                $this->_extendedHeader->setCrc($crc);
            }
            $buffer = new Zend_Io_StringWriter();
            $this->_extendedHeader->write($buffer);
            $extendedHeaderData = $buffer->toString();
            $extendedHeaderDataLength = strlen($extendedHeaderData);
        }

        /* ID3v2.3.0 CRC calculated w/o padding */
        if ($this->getOption('version', 4) < 4) {
            $frameData =
                str_pad($frameData, $frameDataLength += $paddingLength, "\0");
        }

        $this->_header->setSize($extendedHeaderDataLength + $frameDataLength);

        $writer->write('ID3');
        $this->_header->write($writer);
        $writer->write($extendedHeaderData);
        $writer->write($frameData);
        if ($this->hasFooter()) {
            $writer->write('3DI');
            $this->_footer->write($writer);
        }
    }

    /**
     * Removes the ID3v2 tag altogether.
     *
     * @param string $filename The path to the file.
     */
    public static function remove($filename)
    {
        if ($filename instanceof Zend_Io_Reader) {
            $reader = &$filename;
        } else {
            require_once 'Zend/Io/FileReader.php';
            $reader = new Zend_Io_FileReader($filename, 'r+b');
        }

        $fileSize = $reader->getSize();
        if ($reader->read(3) == 'ID3') {
            $header = new Zend_Media_Id3_Header($reader);
            $tagSize = 10 /* header */ + $header->getSize();
        } else return;

        $fd = $reader->getFileDescriptor();
        for ($i = 0; $tagSize + ($i * 1024) < $fileSize; $i++) {
            fseek($fd, $tagSize + ($i * 1024));
            $buffer = fread($fd, 1024);
            fseek($fd, ($i * 1024));
            $bytes = fwrite($fd, $buffer, 1024);
        }
        ftruncate($fd, $fileSize - $tagSize);
    }

    /**
     * Magic function so that $obj->value will work. The method will attempt to
     * return the first frame that matches the identifier.
     *
     * If there is no frame or field with given name, the method will attempt to
     * create a frame with given identifier.
     *
     * If none of these work, an exception is thrown.
     *
     * @param string $name The frame or field name.
     * @return mixed
     */
    public function __get($name)
    {
        if (!empty($this->_frames[strtoupper($name)])) {
            return $this->_frames[strtoupper($name)][0];
        }
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func
                (array($this, 'get' . ucfirst($name)));
        }
        if (@fopen($filename = 'Zend/Media/Id3/Frame/' . ucfirst($name) .
                   '.php', 'r', true) !== false) {
            require_once $filename;
        }
        if (class_exists
            ($classname = 'Zend_Media_Id3_Frame_' . ucfirst($name))) {
            return $this->addFrame(new $classname());
        }
        require_once 'Zend/Media/Id3/Exception.php';
        throw new Zend_Media_Id3_Exception('Unknown frame/field: ' . $name);
    }

    /**
     * Magic function so that isset($obj->value) will work. This method checks
     * whether the frame matching the identifier exists.
     *
     * @param string $name The frame identifier.
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->_frames[strtoupper($name)]);
    }

    /**
     * Magic function so that unset($obj->value) will work. This method removes
     * all the frames matching the identifier.
     *
     * @param string $name The frame identifier.
     */
    public function __unset($name)
    {
        unset($this->_frames[strtoupper($name)]);
    }
}
