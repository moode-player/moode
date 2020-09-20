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
 * @version    $Id: Id3v1.php 217 2011-05-02 19:09:58Z svollbehr $
 */

/**#@+ @ignore */
require_once 'Zend/Io/FileReader.php';
/**#@-*/

/**
 * This class represents a file containing ID3v1 headers as described in
 * {@link http://www.id3.org/id3v2-00 The ID3-Tag Specification Appendix}.
 *
 * @category   Zend
 * @package    Zend_Media
 * @subpackage ID3
 * @author     Sven Vollbehr <sven@vollbehr.eu>
 * @author     Ryan Butterfield <buttza@gmail.com>
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Id3v1.php 217 2011-05-02 19:09:58Z svollbehr $
 */
final class Zend_Media_Id3v1
{
    /** @var string */
    private $_title;

    /** @var string */
    private $_artist;

    /** @var string */
    private $_album;

    /** @var string */
    private $_year;

    /** @var string */
    private $_comment;

    /** @var integer */
    private $_track;

    /** @var integer */
    private $_genre = 255;

    /**
     * The genre list.
     *
     * @var Array
     */
    public static $genres = array
        ('Blues', 'Classic Rock', 'Country', 'Dance', 'Disco', 'Funk', 'Grunge',
         'Hip-Hop', 'Jazz', 'Metal', 'New Age', 'Oldies', 'Other', 'Pop', 'R&B',
         'Rap', 'Reggae', 'Rock', 'Techno', 'Industrial', 'Alternative', 'Ska',
         'Death Metal', 'Pranks', 'Soundtrack', 'Euro-Techno', 'Ambient',
         'Trip-Hop', 'Vocal', 'Jazz+Funk', 'Fusion', 'Trance', 'Classical',
         'Instrumental', 'Acid', 'House', 'Game', 'Sound Clip', 'Gospel',
         'Noise', 'AlternRock', 'Bass', 'Soul', 'Punk', 'Space', 'Meditative',
         'Instrumental Pop', 'Instrumental Rock', 'Ethnic', 'Gothic',
         'Darkwave', 'Techno-Industrial', 'Electronic', 'Pop-Folk', 'Eurodance',
         'Dream', 'Southern Rock', 'Comedy', 'Cult', 'Gangsta', 'Top 40',
         'Christian Rap', 'Pop/Funk', 'Jungle', 'Native American', 'Cabaret',
         'New Wave', 'Psychadelic', 'Rave', 'Showtunes', 'Trailer', 'Lo-Fi',
         'Tribal', 'Acid Punk', 'Acid Jazz', 'Polka', 'Retro', 'Musical',
         'Rock & Roll', 'Hard Rock', 'Folk', 'Folk-Rock', 'National Folk',
         'Swing', 'Fast Fusion', 'Bebob', 'Latin', 'Revival', 'Celtic',
         'Bluegrass', 'Avantgarde', 'Gothic Rock', 'Progressive Rock',
         'Psychedelic Rock', 'Symphonic Rock', 'Slow Rock', 'Big Band',
         'Chorus', 'Easy Listening', 'Acoustic', 'Humour', 'Speech', 'Chanson',
         'Opera', 'Chamber Music', 'Sonata', 'Symphony', 'Booty Bass', 'Primus',
         'Porn Groove', 'Satire', 'Slow Jam', 'Club', 'Tango', 'Samba',
         'Folklore', 'Ballad', 'Power Ballad', 'Rhythmic Soul', 'Freestyle',
         'Duet', 'Punk Rock', 'Drum Solo', 'A capella', 'Euro-House',
         'Dance Hall', 255 => 'Unknown');

    /** @var Zend_Io_Reader */
    private $_reader;

    /** @var string */
    private $_filename = null;

    /**
     * Constructs the Id3v1 class with given file. The file is not mandatory
     * argument and may be omitted as a new tag can be written to a file also by
     * giving the filename to the {@link #write} method of this class.
     *
     * @param string|resource|Zend_Io_Reader $filename The path to the file,
     *  file descriptor of an opened file, or a {@link Zend_Io_Reader} instance.
     * @throws Zend_Media_Id3_Exception if given file descriptor is not valid
     */
    public function __construct($filename = null)
    {
        if ($filename === null) {
            return;
        }
        if ($filename instanceof Zend_Io_Reader) {
            $this->_reader = &$filename;
        } else {
            $this->_filename = $filename;
            require_once('Zend/Io/FileReader.php');
            try {
                $this->_reader = new Zend_Io_FileReader($filename);
            } catch (Zend_Io_Exception $e) {
                $this->_reader = null;
                require_once 'Zend/Media/Id3/Exception.php';
                throw new Zend_Media_Id3_Exception($e->getMessage());
            }
        }

        if ($this->_reader->getSize() < 128) {
            $this->_reader = null;
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception
                ('File does not contain ID3v1 tag');
        }
        $this->_reader->setOffset(-128);
        if ($this->_reader->read(3) != 'TAG') {
            $this->_reader = null;
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception
                ('File does not contain ID3v1 tag');
        }

        $this->_title   = $this->_reader->readString8(30, " \0");
        $this->_artist  = $this->_reader->readString8(30, " \0");
        $this->_album   = $this->_reader->readString8(30, " \0");
        $this->_year    = $this->_reader->readString8(4);
        $this->_comment = $this->_reader->readString8(28);

        /* ID3v1.1 support for tracks */
        $v11_null = $this->_reader->read(1);
        $v11_track = $this->_reader->read(1);
        if (ord($v11_null) == 0 && ord($v11_track) != 0) {
            $this->_track   = ord($v11_track);
        } else {
            $this->_comment = rtrim
                ($this->_comment . $v11_null . $v11_track, " \0");
        }

        $this->_genre   = $this->_reader->readInt8();
    }

    /**
     * Returns the title field.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * Sets a new value for the title field. The field cannot exceed 30
     * characters in length.
     *
     * @param string $title The title.
     */
    public function setTitle($title)
    {
        $this->_title = $title;
    }

    /**
     * Returns the artist field.
     *
     * @return string
     */
    public function getArtist()
    {
        return $this->_artist;
    }

    /**
     * Sets a new value for the artist field. The field cannot exceed 30
     * characters in length.
     *
     * @param string $artist The artist.
     */
    public function setArtist($artist)
    {
        $this->_artist = $artist;
    }

    /**
     * Returns the album field.
     *
     * @return string
     */
    public function getAlbum()
    {
        return $this->_album;
    }

    /**
     * Sets a new value for the album field. The field cannot exceed 30
     * characters in length.
     *
     * @param string $album The album.
     */
    public function setAlbum($album)
    {
        $this->_album = $album;
    }

    /**
     * Returns the year field.
     *
     * @return string
     */
    public function getYear()
    {
        return $this->_year;
    }

    /**
     * Sets a new value for the year field. The field cannot exceed 4
     * characters in length.
     *
     * @param string $year The year.
     */
    public function setYear($year)
    {
        $this->_year = $year;
    }

    /**
     * Returns the comment field.
     *
     * @return string
     */
    public function getComment()
    {
        return $this->_comment;
    }

    /**
     * Sets a new value for the comment field. The field cannot exceed 30
     * characters in length.
     *
     * @param string $comment The comment.
     */
    public function setComment($comment)
    {
        $this->_comment = $comment;
    }

    /**
     * Returns the track field.
     *
     * @since ID3v1.1
     * @return integer
     */
    public function getTrack()
    {
        return $this->_track;
    }

    /**
     * Sets a new value for the track field. By setting this field you enforce
     * the 1.1 version to be used.
     *
     * @since ID3v1.1
     * @param integer $track The track number.
     */
    public function setTrack($track)
    {
        $this->_track = $track;
    }

    /**
     * Returns the genre.
     *
     * @return string
     */
    public function getGenre()
    {
        if (isset(self::$genres[$this->_genre]))
            return self::$genres[$this->_genre];
        else
            return self::$genres[255]; // unknown
    }

    /**
     * Sets a new value for the genre field. The value may either be a numerical
     * code representing one of the genres, or its string variant.
     *
     * The genre is set to unknown (code 255) in case the string is not found
     * from the static {@link $genres} array of this class.
     *
     * @param integer $genre The genre.
     */
    public function setGenre($genre)
    {
        if ((is_numeric($genre) && $genre >= 0 && $genre <= 255) ||
            ($genre = array_search($genre, self::$genres)) !== false)
            $this->_genre = $genre;
        else
            $this->_genre = 255; // unknown
    }

    /**
     * Writes the possibly altered ID3v1 tag back to the file where it was read.
     * If the class was constructed without a file name, one can be provided
     * here as an argument. Regardless, the write operation will override
     * previous tag information, if found.
     *
     * @param string $filename The optional path to the file.
     * @throws Zend_Media_Id3_Exception if there is no file to write the tag to
     */
    public function write($filename = null)
    {
        if ($filename === null && ($filename = $this->_filename) === null) {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception
                ('No file given to write the tag to');
        }

        require_once('Zend/Io/FileWriter.php');
        try {
            $writer = new Zend_Io_FileWriter($filename);
            $offset = $writer->getSize();
            if ($this->_reader !== null) {
                $offset = -128;
            } else {
                $reader = new Zend_Io_Reader($writer->getFileDescriptor());
                $reader->setOffset(-128);
                if ($reader->read(3) == 'TAG')
                    $offset = -128;
            }
            $writer->setOffset($offset);
            $writer->writeString8('TAG')
                   ->writeString8(substr($this->_title,  0, 30), 30)
                   ->writeString8(substr($this->_artist, 0, 30), 30)
                   ->writeString8(substr($this->_album,  0, 30), 30)
                   ->writeString8(substr($this->_year,   0,  4),  4);
            if ($this->_track) {
                $writer->writeString8(substr($this->_comment, 0, 28), 28)
                       ->writeInt8(0)
                       ->writeInt8($this->_track);
            } else {
                $writer->writeString8(substr($this->_comment, 0, 30), 30);
            }
            $writer->writeInt8($this->_genre);
            $writer->flush();
        } catch (Zend_Io_Exception $e) {
            require_once 'Zend/Media/Id3/Exception.php';
            throw new Zend_Media_Id3_Exception($e->getMessage());
        }

        $this->_filename = $filename;
    }

    /**
     * Removes the ID3v1 tag altogether.
     *
     * @param string $filename The path to the file.
     */
    public static function remove($filename)
    {
        $reader = new Zend_Io_FileReader($filename, 'r+b');
        $reader->setOffset(-128);
        if ($reader->read(3) == 'TAG') {
            ftruncate($reader->getFileDescriptor(), $reader->getSize() - 128);
        }
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param string $name The field name.
     * @return mixed
     */
    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst(strtolower($name)))) {
            return call_user_func
                (array($this, 'get' . ucfirst(strtolower($name))));
        } else {
            require_once('Zend/Media/Id3/Exception.php');
            throw new Zend_Media_Id3_Exception('Unknown field: ' . $name);
        }
    }

    /**
     * Magic function so that assignments with $obj->value will work.
     *
     * @param string $name  The field name.
     * @param string $value The field value.
     * @return mixed
     */
    public function __set($name, $value)
    {
        if (method_exists($this, 'set' . ucfirst(strtolower($name)))) {
            call_user_func
                (array($this, 'set' . ucfirst(strtolower($name))), $value);
        } else {
            require_once('Zend/Media/Id3/Exception.php');
            throw new Zend_Media_Id3_Exception('Unknown field: ' . $name);
        }
    }
}
