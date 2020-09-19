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
 * @version    $Id: ExtendedStreamProperties.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Extended Stream Properties Object</i> defines additional optional
 * properties and characteristics of a digital media stream that are not
 * described in the <i>Stream Properties Object</i>.
 *
 * Typically, the basic <i>Stream Properties Object</i> is present in the
 * <i>Header Object</i>, and the <i>Extended Stream Properties Object</i> is
 * present in the <i>Header Extension Object</i>. Sometimes, however, the
 * <i>Stream Properties Object</i> for a stream may be embedded inside the
 * <i>Extended Stream Properties Object</i> for that stream. This approach
 * facilitates the creation of backward-compatible content.
 *
 * This object has an optional provision to include application-specific or
 * implementation-specific data attached to the payloads of each digital media
 * sample stored within a <i>Data Packet</i>. This data can be looked at as
 * digital media sample properties and is stored in the <i>Replicated Data</i>
 * field of a payload header. The <i>Payload Extension Systems</i> fields of the
 * <i>Extended Stream Properties Object</i> describes what this data is and is
 * necessary for that data to be parsed, if present.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: ExtendedStreamProperties.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_ExtendedStreamProperties
    extends Zend_Media_Asf_Object
{
    /**
     * Indicates, if set, that this digital media stream, if sent over a
     * network, must be carried over a reliable data communications transport
     * mechanism. This should be set for streams that cannot recover after a
     * lost media object.
     */
    const RELIABLE = 1;

    /**
     * This flag should be set only if the stream is seekable, either by using
     * an index object or by estimating according to bit rate (as can sometimes
     * be done with audio). This flag pertains to this stream only rather than
     * to the entire file.
     */
    const SEEKABLE = 2;

    /**
     * Indicates, if set, that the stream does not contain any cleanpoints. A
     * cleanpoint is any point at which playback could begin without having seen
     * the previous media objects. For streams that use key frames, the key
     * frames would be the cleanpoints.
     */
    const NO_CLEANPOINT = 4;

    /**
     * Specifies, if set, that when a stream is joined in mid-transmission, all
     * information from the most recent cleanpoint up to the current time should
     * be sent before normal streaming begins at the current time. The default
     * behavior (when this flag is not set) is to send only the data starting at
     * the current time. This flag should only be set for streams that are
     * coming from a live source.
     */
    const RESEND_LIVE_CLEANPOINTS = 8;

    const AUDIO_MEDIA = 'f8699e40-5b4d-11cf-a8fd-00805f5c442b';
    const VIDEO_MEDIA = 'bc19efc0-5b4d-11cf-a8fd-00805f5c442b';
    const COMMAND_MEDIA = '59dacfc0-59e6-11d0-a3ac-00a0c90348f6';
    const JFIF_MEDIA = 'b61be100-5b4e-11cf-a8fD-00805f5c442b';
    const DEGRADABLE_JPEG_MEDIA = '35907dE0-e415-11cf-a917-00805f5c442b';
    const FILE_TRANSFER_MEDIA = '91bd222c-f21c-497a-8b6d-5aa86bfc0185';
    const BINARY_MEDIA = '3afb65e2-47ef-40f2-ac2c-70a90d71d343';

    const NO_ERROR_CORRECTION = '20fb5700-5b55-11cf-a8fd-00805f5c442b';
    const AUDIO_SPREAD = 'bfc3cd50-618f-11cf-8bb2-00aa00b4e220';

    const PAYLOAD_EXTENSION_SYSTEM_TIMECODE =
        '399595ec-8667-4e2d-8fdb-98814ce76c1e';
    const PAYLOAD_EXTENSION_SYSTEM_FILE_NAME =
        'e165ec0e-19ed-45d7-b4a7-25cbd1e28e9b';
    const PAYLOAD_EXTENSION_SYSTEM_CONTENT_TYPE =
        'd590dc20-07bc-436c-9cf7-f3bbfbf1a4dc';
    const PAYLOAD_EXTENSION_SYSTEM_PIXEL_ASPECT_RATIO =
        '1b1ee554-f9ea-4bc8-821a-376b74e4c4b8';
    const PAYLOAD_EXTENSION_SYSTEM_SAMPLE_DURATION =
        'c6bd9450-867f-4907-83a3-c77921b733ad';
    const PAYLOAD_EXTENSION_SYSTEM_ENCRYPTION_SAMPLE_ID =
        '6698b84e-0afa-4330-aeb2-1c0a98d7a44d';

    /** @var integer */
    private $_startTime;

    /** @var integer */
    private $_endTime;

    /** @var integer */
    private $_dataBitrate;

    /** @var integer */
    private $_bufferSize;

    /** @var integer */
    private $_initialBufferFullness;

    /** @var integer */
    private $_alternateDataBitrate;

    /** @var integer */
    private $_alternateBufferSize;

    /** @var integer */
    private $_alternateInitialBufferFullness;

    /** @var integer */
    private $_maximumObjectSize;

    /** @var integer */
    private $_flags;

    /** @var integer */
    private $_streamNumber;

    /** @var integer */
    private $_streamLanguageIndex;

    /** @var integer */
    private $_averageTimePerFrame;

    /** @var Array */
    private $_streamNames = array();

    /** @var Array */
    private $_payloadExtensionSystems = array();

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

        $this->_startTime = $this->_reader->readInt64LE();
        $this->_endTime = $this->_reader->readInt64LE();
        $this->_dataBitrate = $this->_reader->readUInt32LE();
        $this->_bufferSize = $this->_reader->readUInt32LE();
        $this->_initialBufferFullness = $this->_reader->readUInt32LE();
        $this->_alternateDataBitrate = $this->_reader->readUInt32LE();
        $this->_alternateBufferSize = $this->_reader->readUInt32LE();
        $this->_alternateInitialBufferFullness = $this->_reader->readUInt32LE();
        $this->_maximumObjectSize = $this->_reader->readUInt32LE();
        $this->_flags = $this->_reader->readUInt32LE();
        $this->_streamNumber = $this->_reader->readUInt16LE();
        $this->_streamLanguageIndex = $this->_reader->readUInt16LE();
        $this->_averageTimePerFrame = $this->_reader->readInt64LE();
        $streamNameCount = $this->_reader->readUInt16LE();
        $payloadExtensionSystemCount = $this->_reader->readUInt16LE();
        for ($i = 0; $i < $streamNameCount; $i++) {
            $streamName =
                array('languageIndex' => $this->_reader->readUInt16LE());
            $streamNameLength = $this->_reader->readUInt16LE();
            $streamName['streamName'] = iconv
                ('utf-16le', $this->getOption('encoding'),
                 $this->_reader->readString16($streamNameLength));
            $this->_streamNames[] = $streamName;
        }
        for ($i = 0; $i < $payloadExtensionSystemCount; $i++) {
            $payloadExtensionSystem = array
                ('extensionSystemId' => $this->_reader->readGuid(),
                 'extensionDataSize' => $this->_reader->readUInt16LE());
            $extensionSystemInfoLength = $this->_reader->readUInt32LE();
            $payloadExtensionSystem['extensionSystemInfo'] = iconv
                ('utf-16le', $this->getOption('encoding'),
                 $this->_reader->readString16($extensionSystemInfoLength));
            $this->_payloadExtensionSystems[] = $payloadExtensionSystem;
        }
    }

    /**
     * Returns the presentation time of the first object, indicating where this
     * digital media stream starts within the context of the timeline of the ASF
     * file as a whole. This time value corresponds to presentation times as
     * they appear in the data packets (adjusted by the preroll). This field is
     * given in units of milliseconds and can optionally be set to 0, in which
     * case it will be ignored.
     *
     * @return integer
     */
    public function getStartTime() 
    {
        return $this->_startTime; 
    }

    /**
     * Sets the presentation time of the first object, indicating where this
     * digital media stream starts within the context of the timeline of the ASF
     * file as a whole. This time value corresponds to presentation times as
     * they appear in the data packets (adjusted by the preroll).
     *
     * The given value must be in units of milliseconds or optionally be set to
     * 0, in which case the field will be ignored.
     *
     * @param integer $startTime The presentation time of the first object.
     */
    public function setStartTime($startTime) 
    {
        $this->_startTime = $startTime; 
    }

    /**
     * Returns the presentation time of the last object plus the duration of
     * play, indicating where this digital media stream ends within the context
     * of the timeline of the ASF file as a whole. This time value corresponds
     * to presentation times as they appear in the data packets (adjusted by the
     * preroll). This field is given in units of milliseconds and can optionally
     * be set to 0, in which case it will be ignored.
     *
     * @return integer
     */
    public function getEndTime() 
    {
        return $this->_endTime; 
    }

    /**
     * Sets the presentation time of the last object plus the duration of play,
     * indicating where this digital media stream ends within the context of the
     * timeline of the ASF file as a whole. This time value corresponds to
     * presentation times as they appear in the data packets (adjusted by the
     * preroll).
     *
     * The given value must be given in units of milliseconds or optionally be
     * set to 0, in which case the field will be ignored.
     *
     * @param integer $endTime The presentation time of the last object plus the
     *        duration of play.
     */
    public function setEndTime($endTime) 
    {
        $this->_endTime = $endTime; 
    }

    /**
     * Returns the leak rate R, in bits per second, of a leaky bucket that
     * contains the data portion of the stream without overflowing, excluding
     * all ASF Data Packet overhead. The size of the leaky bucket is specified
     * by the value of the <i>Buffer Size</i> field. This field has a non-zero
     * value.
     *
     * @return integer
     */
    public function getDataBitrate() 
    {
        return $this->_dataBitrate; 
    }

    /**
     * Sets the leak rate R, in bits per second, of a leaky bucket that
     * contains the data portion of the stream without overflowing, excluding
     * all ASF Data Packet overhead. The size of the leaky bucket is specified
     * by the value of the <i>Buffer Size</i> field.
     *
     * This field must be given a non-zero value.
     *
     * @param integer $dataBitrate The leak rate.
     */
    public function setDataBitrate($dataBitrate)
    {
        $this->_dataBitrate = $dataBitrate;
    }

    /**
     * Returns the size B, in milliseconds, of the leaky bucket used in the
     * <i>Data Bitrate</i> definition.
     *
     * @return integer
     */
    public function getBufferSize() 
    {
        return $this->_bufferSize; 
    }

    /**
     * Sets the size B, in milliseconds, of the leaky bucket used in the
     * <i>Data Bitrate</i> definition.
     *
     * @param integer $bufferSize The size.
     */
    public function setBufferSize($bufferSize)
    {
        $this->_bufferSize = $bufferSize;
    }

    /**
     * Returns the initial fullness, in milliseconds, of the leaky bucket used
     * in the <i>Data Bitrate</i> definition. This is the fullness of the buffer
     * at the instant before the first bit in the stream is dumped into the
     * bucket. Typically, this value is set to 0. This value shall not exceed
     * the value in the <i>Buffer Size</i> field.
     *
     * @return integer
     */
    public function getInitialBufferFullness()
    {
        return $this->_initialBufferFullness;
    }

    /**
     * Sets the initial fullness, in milliseconds, of the leaky bucket used in
     * the <i>Data Bitrate</i> definition. This is the fullness of the buffer at
     * the instant before the first bit in the stream is dumped into the bucket.
     * Typically, this value is set to 0. This value shall not exceed the value
     * in the <i>Buffer Size</i> field.
     *
     * @param integer $initialBufferFullness The initial fullness.
     */
    public function setInitialBufferFullness($initialBufferFullness)
    {
        $this->_initialBufferFullness = $initialBufferFullness;
    }

    /**
     * Returns the leak rate RAlt, in bits per second, of a leaky bucket that
     * contains the data portion of the stream without overflowing, excluding
     * all ASF <i>Data Packet</i> overhead. The size of the leaky bucket is
     * specified by the value of the <i>Alternate Buffer Size</i> field. This
     * value is relevant in most scenarios where the bit rate is not exactly
     * constant, but it is especially useful for streams that have highly
     * variable bit rates. This field can optionally be set to the same value
     * as the <i>Data Bitrate</i> field.
     *
     * @return integer
     */
    public function getAlternateDataBitrate()
    {
        return $this->_alternateDataBitrate;
    }

    /**
     * Sets the leak rate RAlt, in bits per second, of a leaky bucket that
     * contains the data portion of the stream without overflowing, excluding
     * all ASF <i>Data Packet</i> overhead. The size of the leaky bucket is
     * specified by the value of the <i>Alternate Buffer Size</i> field. This
     * value is relevant in most scenarios where the bit rate is not exactly
     * constant, but it is especially useful for streams that have highly
     * variable bit rates. This field can optionally be set to the same value
     * as the <i>Data Bitrate</i> field.
     *
     * @param integer $alternateDataBitrate The alternate leak rate.
     */
    public function setAlternateDataBitrate($alternateDataBitrate)
    {
        $this->_alternateDataBitrate = $alternateDataBitrate;
    }

    /**
     * Returns the size BAlt, in milliseconds, of the leaky bucket used in the
     * <i>Alternate Data Bitrate</i> definition. This value is relevant in most
     * scenarios where the bit rate is not exactly constant, but it is
     * especially useful for streams that have highly variable bit rates. This
     * field can optionally be set to the same value as the <i>Buffer Size</i>
     * field.
     *
     * @return integer
     */
    public function getAlternateBufferSize()
    {
        return $this->_alternateBufferSize;
    }

    /**
     * Sets the size BAlt, in milliseconds, of the leaky bucket used in the
     * <i>Alternate Data Bitrate</i> definition. This value is relevant in most
     * scenarios where the bit rate is not exactly constant, but it is
     * especially useful for streams that have highly variable bit rates. This
     * field can optionally be set to the same value as the <i>Buffer Size</i>
     * field.
     *
     * @param integer $alternateBufferSize
     */
    public function setAlternateBufferSize($alternateBufferSize)
    {
        $this->_alternateBufferSize = $alternateBufferSize;
    }

    /**
     * Returns the initial fullness, in milliseconds, of the leaky bucket used
     * in the <i>Alternate Data Bitrate</i> definition. This is the fullness of
     * the buffer at the instant before the first bit in the stream is dumped
     * into the bucket. Typically, this value is set to 0. This value does not
     * exceed the value of the <i>Alternate Buffer Size</i> field.
     *
     * @return integer
     */
    public function getAlternateInitialBufferFullness()
    {
        return $this->_alternateInitialBufferFullness;
    }

    /**
     * Sets the initial fullness, in milliseconds, of the leaky bucket used in
     * the <i>Alternate Data Bitrate</i> definition. This is the fullness of the
     * buffer at the instant before the first bit in the stream is dumped into
     * the bucket. Typically, this value is set to 0. This value does not exceed
     * the value of the <i>Alternate Buffer Size</i> field.
     *
     * @param integer $alternateInitialBufferFullness The alternate initial
     *        fullness.
     */
    public function setAlternateInitialBufferFullness
        ($alternateInitialBufferFullness)
    {
        $this->_alternateInitialBufferFullness =
            $alternateInitialBufferFullness;
    }

    /**
     * Returns the maximum size of the largest sample stored in the data packets
     * for a stream. A value of 0 means unknown.
     *
     * @return integer
     */
    public function getMaximumObjectSize()
    {
        return $this->_maximumObjectSize;
    }

    /**
     * Sets the maximum size of the largest sample stored in the data packets
     * for a stream. A value of 0 means unknown.
     *
     * @param integer $maximumObjectSize The maximum size of the largest sample.
     */
    public function setMaximumObjectSize($maximumObjectSize)
    {
        $this->_maximumObjectSize = $maximumObjectSize;
    }

    /**
     * Returns the average time duration, measured in 100-nanosecond units, of
     * each frame. This number should be rounded to the nearest integer. This
     * field can optionally be set to 0 if the average time per frame is unknown
     * or unimportant. It is recommended that this field be set for video.
     *
     * @return integer
     */
    public function getAverageTimePerFrame()
    {
        return $this->_averageTimePerFrame;
    }

    /**
     * Sets the average time duration, measured in 100-nanosecond units, of
     * each frame. This number should be rounded to the nearest integer. This
     * field can optionally be set to 0 if the average time per frame is unknown
     * or unimportant. It is recommended that this field be set for video.
     *
     * @param integer $averageTimePerFrame The average time duration.
     */
    public function setAverageTimePerFrame($averageTimePerFrame)
    {
        $this->_averageTimePerFrame = $averageTimePerFrame;
    }

    /**
     * Returns the number of this stream. 0 is an invalid stream number (that
     * is, other <i>Header Objects</i> use stream number 0 to refer to the
     * entire file as a whole rather than to a specific media stream within the
     * file). Valid values are between 1 and 127.
     *
     * @return integer
     */
    public function getStreamNumber()
    {
        return $this->_streamNumber;
    }

    /**
     * Sets the number of this stream. 0 is an invalid stream number (that is,
     * other <i>Header Objects</i> use stream number 0 to refer to the entire
     * file as a whole rather than to a specific media stream within the file).
     * Valid values are between 1 and 127.
     *
     * @param integer $streamNumber The number of this stream.
     */
    public function setStreamNumber($streamNumber)
    {
        $this->_streamNumber = $streamNumber;
    }

    /**
     * Returns the language, if any, which the content of the stream uses or
     * assumes. Refer to the {@link LanguageList Language List Object}
     * description for the details concerning how the <i>Stream Language
     * Index</i> and <i>Language Index</i> fields should be used. Note that this
     * is an index into the languages listed in the <i>Language List Object</i>
     * rather than a language identifier.
     *
     * @return integer
     */
    public function getStreamLanguageIndex()
    {
        return $this->_streamLanguageIndex;
    }

    /**
     * Sets the language, if any, which the content of the stream uses or
     * assumes. Refer to the {@link LanguageList Language List Object}
     * description for the details concerning how the <i>Stream Language
     * Index</i> and <i>Language Index</i> fields should be used. Note that this
     * is an index into the languages listed in the <i>Language List Object</i>
     * rather than a language identifier.
     *
     * @param integer $streamLanguageIndex The language index.
     */
    public function setStreamLanguageIndex($streamLanguageIndex)
    {
        $this->_streamLanguageIndex = $streamLanguageIndex;
    }

    /**
     * Returns an array of Stream Names. Each stream name instance is
     * potentially localized into a specific language. The <i>Language Index</i>
     * field indicates the language in which the <i>Stream Name</i> has been
     * written.
     *
     * The array entry contains the following keys:
     *   o languageIndex -- The language index
     *   o streamName -- The localized stream name
     *
     * @return Array
     */
    public function getStreamNames()
    {
        return $this->_streamNames;
    }

    /**
     * Sets the array of stream names. Each stream name instance is potentially
     * localized into a specific language. The <i>Language Index</i> field
     * indicates the language in which the <i>Stream Name</i> has been written.
     *
     * The array entries are to contain the following keys:
     *   o languageIndex -- The language index
     *   o streamName -- The localized stream name
     *
     * @param Array $streamNames The array of stream names
     */
    public function setStreamNames($streamNames)
    {
        $this->_streamNames = $streamNames;
    }

    /**
     * Returns an array of payload extension systems. Payload extensions provide
     * a way for content creators to specify kinds of data that will appear in
     * the payload header for every payload from this stream. This system is
     * used when stream properties must be conveyed at the media object level.
     * The <i>Replicated Data</i> bytes in the payload header will contain these
     * properties in the order in which the <i>Payload Extension Systems</i>
     * appear in this object. A <i>Payload Extension System</i> must appear in
     * the <i>Extended Stream Properties Object</i> for each type of
     * per-media-object properties that will appear with the payloads for this
     * stream.
     *
     * The array entry contains the following keys:
     *   o extensionSystemId -- Specifies a unique identifier for the extension
     *     system.
     *   o extensionDataSize -- Specifies the fixed size of the extension data
     *     for this system that will appear in the replicated data alongside
     *     every payload for this stream. If this extension system uses
     *     variable-size data, then this should be set to 0xffff. Note, however,
     *     that replicated data length is limited to 255 bytes, which limits the
     *     total size of all extension systems for a particular stream.
     *   o extensionSystemInfo -- Specifies additional information to describe
     *     this extension system (optional).
     *
     * @return Array
     */
    public function getPayloadExtensionSystems()
    {
        return $this->_payloadExtensionSystems;
    }

    /**
     * Sets an array of payload extension systems. Payload extensions provide a
     * way for content creators to specify kinds of data that will appear in the
     * payload header for every payload from this stream. This system is used
     * when stream properties must be conveyed at the media object level. The
     * <i>Replicated Data</i> bytes in the payload header will contain these
     * properties in the order in which the <i>Payload Extension Systems</i>
     * appear in this object. A <i>Payload Extension System</i> must appear in
     * the <i>Extended Stream Properties Object</i> for each type of
     * per-media-object properties that will appear with the payloads for this
     * stream.
     *
     * The array enties are to contain the following keys:
     *   o extensionSystemId -- Specifies a unique identifier for the extension
     *     system.
     *   o extensionDataSize -- Specifies the fixed size of the extension data
     *     for this system that will appear in the replicated data alongside
     *     every payload for this stream. If this extension system uses
     *     variable-size data, then this should be set to 0xffff. Note, however,
     *     that replicated data length is limited to 255 bytes, which limits the
     *     total size of all extension systems for a particular stream.
     *   o extensionSystemInfo -- Specifies additional information to describe
     *     this extension system (optional).
     *
     * @param Array $payloadExtensionSystems The array of payload extension
     *        systems.
     */
    public function setPayloadExtensionSystems($payloadExtensionSystems)
    {
        $this->_payloadExtensionSystems = $payloadExtensionSystems;
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

        $streamNameCount = count($this->_streamNames);
        $streamNameWriter = new Zend_Io_StringWriter();
        for ($i = 0; $i < $streamNameCount; $i++) {
            $streamNameWriter
                ->writeUInt16LE($this->_streamNames['languageIndex'])
                ->writeUInt16LE(strlen($streamName = iconv
                    ($this->getOption('encoding'), 'utf-16le',
                     $this->_streamNames['streamName']) . "\0\0"))
                ->writeString16($streamName);
        }
        
        $payloadExtensionSystemCount = count($this->_payloadExtensionSystems);
        $payloadExtensionSystemWriter = new Zend_Io_StringWriter();
        for ($i = 0; $i < $payloadExtensionSystemCount; $i++) {
            $payloadExtensionSystemWriter
                ->writeGuid($this->_streamNames['extensionSystemId'])
                ->writeUInt16LE($this->_streamNames['extensionDataSize'])
                ->writeUInt16LE(strlen($extensionSystemInfo = iconv
                    ($this->getOption('encoding'), 'utf-16le',
                     $this->_streamNames['extensionSystemInfo']) . "\0\0"))
                ->writeString16($extensionSystemInfo);
        }


        $this->setSize
            (24 /* for header */ + 64 + $streamNameWriter->getSize() +
             $payloadExtensionSystemWriter->getSize());


        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeInt64LE($this->_startTime)
               ->writeInt64LE($this->_endTime)
               ->writeUInt32LE($this->_dataBitrate)
               ->writeUInt32LE($this->_bufferSize)
               ->writeUInt32LE($this->_initialBufferFullness)
               ->writeUInt32LE($this->_alternateDataBitrate)
               ->writeUInt32LE($this->_alternateBufferSize)
               ->writeUInt32LE($this->_alternateInitialBufferFullness)
               ->writeUInt32LE($this->_maximumObjectSize)
               ->writeUInt32LE($this->_flags)
               ->writeUInt16LE($this->_streamNumber)
               ->writeUInt16LE($this->_streamLanguageIndex)
               ->writeInt64LE($this->_averageTimePerFrame)
               ->writeUInt16LE($streamNameCount)
               ->writeUInt16LE($payloadExtensionSystemCount)
               ->write($streamNameWriter->toString())
               ->write($payloadExtensionSystemWriter->toString());
    }
}
