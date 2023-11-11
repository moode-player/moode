<?php
/**
 * Unofficial Zend extension to handle DSD files.
 * This class reads the DSD header chunk.
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

final class ZendEx_Media_Dsd_Header
{
/**
 *   offset  size  description
 *       00     4  file signature, 'DSD '
 *       04     8  this block size
 *       12     8  file size
 *       20     8  metadata address
 */

    private $_size = 0; // 28 bytes
    private $_filesize = 0;
    private $_metadata_address = 0;

    public function __construct($reader = null)
    {
        if ($reader === null)
            return;

        $this->_size = $reader->readInt64LE();
        $this->_filesize = $reader->readInt64LE();
        $this->_metadata_address = $reader->readInt64LE();
    }

    public function getSize() 
    {
         return $this->_size; 
    }

    public function getFileSize() 
    {
         return $this->_filesize; 
    }

    public function getMetadataAddress() 
    {
         return $this->_metadata_address;
    }
}
