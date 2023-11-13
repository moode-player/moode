<?php
/**
 * Unofficial Zend extension to extract ID3v2 tags from DSD files.
 * This class exclusively reads the metadata offset from the DSD header chunk
 * of a DSD file, it then initializes an instance of id3v2 Zend class at such
 * offset.
 * (C) 2022 @Nutul (albertonarduzzi@gmail.com)
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once 'Zend/Io/Reader.php';

final class ZendEx_Media_Dsd
{
    private $_reader = null;
    private $_filename = null;
    private $_header = null;
    private $_id3v2 = null;

    public function __construct($filename = null, $options = array())
    {
        if ($filename instanceof Zend_Io_Reader) {
            $this->_reader = &$filename;
        } else {
            $this->_filename = $filename;
            require_once 'Zend/Io/FileReader.php';
            try {
                $this->_reader = new Zend_Io_FileReader($filename);
            } catch (Zend_Io_Exception $e) {
                $this->_reader = null;
                require_once 'Zend/Media/Dsd/Exception.php';
                throw new ZendEx_Media_Dsd_Exception($e->getMessage());
            }
        }
        // first check for the file signature
        if ($this->_reader->read(4) != 'DSD ') {
            require_once 'Zend/Media/Dsd/Exception.php';
            throw new ZendEx_Media_Dsd_Exception('Not a valid DSD bitstream');
        }
        // then, attempt to read the header
        require_once 'Zend/Media/Dsd/Header.php';
        $this->_header = new ZendEx_Media_Dsd_Header($this->_reader);
        $metedata_address = $this->_header->getMetadataAddress();
        if ($metedata_address == 0) {
            require_once 'Zend/Media/Dsd/Exception.php';
            throw new ZendEx_Media_Dsd_Exception('DSD bitstream does not contain any ID3v2 tag');
        } else {
            $this->_reader->setOffset($metedata_address);
        }
        require_once 'Zend/Media/Id3v2.php';
        // we're OK, pass the control over to the id3v2 handler
        try {
            $this->_id3v2 = new Zend_Media_Id3v2($this->_reader, $options);
        } catch (Zend_Media_Id3_Exception $e) {
            require_once 'Zend/Media/Dsd/Exception.php';
            throw new ZendEx_Media_Dsd_Exception($e->getMessage());
        }
    }

    public function id3v2()
    {
        return $this->_id3v2;
    }
}
