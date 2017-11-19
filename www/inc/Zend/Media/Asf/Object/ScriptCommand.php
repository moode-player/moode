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
 * @version    $Id: ScriptCommand.php 177 2010-03-09 13:13:34Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Media/Asf/Object.php';
/**#@-*/

/**
 * The <i>Script Command Object</i> provides a list of type/parameter pairs of
 * strings that are synchronized to the ASF file's timeline. Types can include
 * URL or FILENAME values. Other type values may also be freely defined and
 * used. The semantics and treatment of this set of types are defined by the
 * local implementations. The parameter value is specific to the type field. You
 * can use this type/parameter pairing for many purposes, including sending URLs
 * to be launched by a client into an HTML frame (in other words, the URL type)
 * or launching another ASF file for the chained continuous play of audio or
 * video presentations (in other words, the FILENAME type). This object is also
 * used as a method to stream text, as well as to provide script commands that
 * you can use to control elements within the client environment.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ASF
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com) 
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: ScriptCommand.php 177 2010-03-09 13:13:34Z svollbehr $
 */
final class Zend_Media_Asf_Object_ScriptCommand extends Zend_Media_Asf_Object
{
    /** @var string */
    private $_reserved;

    /** @var Array */
    private $_commands = array();

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

        $this->_reserved = $this->_reader->readGuid();
        $commandsCount = $this->_reader->readUInt16LE();
        $commandTypesCount = $this->_reader->readUInt16LE();
        $commandTypes = array();
        for ($i = 0; $i < $commandTypesCount; $i++) {
            $commandTypeNameLength = $this->_reader->readUInt16LE();
            $commandTypes[] = iconv
                ('utf-16le', $this->getOption('encoding'),
                 $this->_reader->readString16($commandTypeNameLength * 2));
        }
        for ($i = 0; $i < $commandsCount; $i++) {
            $command = array
                ('presentationTime' => $this->_reader->readUInt32LE(),
                 'type' => $commandTypes[$this->_reader->readUInt16LE()]);
            $commandNameLength = $this->_reader->readUInt16LE();
            $command['name'] = iconv
                ('utf-16le', $this->getOption('encoding'),
                 $this->_reader->readString16($commandNameLength * 2));
            $this->_commands[] = $command;
        }
    }

    /**
     * Returns an array of index entries. Each entry consists of the following
     * keys.
     *
     *   o presentationTime -- Specifies the presentation time of the command,
     *     in milliseconds.
     *
     *   o type -- Specifies the type of this command.
     *
     *   o name -- Specifies the name of this command.
     *
     * @return Array
     */
    public function getCommands() 
    {
        return $this->_commands; 
    }

    /**
     * Sets the array of index entries. Each entry is to consist of the
     * following keys.
     *
     *   o presentationTime -- Specifies the presentation time of the command,
     *     in milliseconds.
     *
     *   o type -- Specifies the type of this command.
     *
     *   o name -- Specifies the name of this command.
     *
     * @param Array $commands The array of index entries.
     */
    public function setCommands($commands) 
    {
        $this->_commands = $commands; 
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
        
        $commandTypes = array();
        foreach ($this->_commands as $command) {
            if (!in_array($command['type'], $commandTypes)) {
                $commandTypes[] = $command['type'];
            }
        }

        $commandTypesCount = count($commandTypes);
        $commandTypesWriter = new Zend_Io_StringWriter();
        for ($i = 0; $i < $commandTypesCount; $i++) {
            $commandTypesWriter
                ->writeUInt16LE
                    (strlen($commandType = iconv
                     ($this->getOption('encoding'), 'utf-16le',
                        $commandTypes[$i])) / 2)
                ->write($commandType);
        }

        $commandsCount = count($this->_commands);
        $commandsWriter = new Zend_Io_StringWriter();
        for ($i = 0; $i < $commandsCount; $i++) {
            $commandsWriter
                ->writeUInt32LE($this->_commands[$i]['presentationTime'])
                ->writeUInt16LE
                    (array_search($this->_commands[$i]['type'], $commandTypes))
                ->writeUInt16LE
                    (strlen($command = iconv
                     ($this->getOption('encoding'), 'utf-16le',
                        $this->_commands[$i]['name'])) / 2)
                ->write($command);
        }

        $this->setSize
            (24 /* for header */ + 20 + $commandTypesWriter->getSize() +
             $commandsWriter->getSize());

        $writer->writeGuid($this->getIdentifier())
               ->writeInt64LE($this->getSize())
               ->writeGuid($this->_reserved)
               ->writeUInt16LE($commandsCount)
               ->writeUInt16LE($commandTypesCount)
               ->write($commandTypesWriter->toString())
               ->write($commandsWriter->toString());
    }
}
