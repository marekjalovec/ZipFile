<?php

/**
 * Class ZipFile
 * Pass multiple files in one ZIP archive to the output stream with minimum memory footprint
 *
 * Created using ".ZIP File Format Specification" by PKWARE Inc.
 * http://www.pkware.com/documents/casestudies/APPNOTE.TXT
 *
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2013 Marek Jalovec (marek.jalovec@gmail.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

class ZipFile {

	private $filePathList = array();
	private $localFileHeaderPosition = 0;
	private $localFileHeaderPositionMap = array();
	private $centralDirectoryHeaderSize = 0;

	private $cacheCRC32 = array();
	private $cacheDateTime = array();

	/**
	 * Create object with path list
	 *
	 * @param array $filePathList
	 */
	public function __construct(array $filePathList = null)
	{
		if ($filePathList && is_array($filePathList)) {
			foreach ($filePathList as $filePath) {
				if (!file_exists($filePath)) throw new Exception(sprintf('File path %s does not exist.', $filePath));
				$this->filePathList[] = $filePath;
			}
		}

		return $this;
	}

	/**
	 * Add file to ZIP archive
	 *
	 * @param string $filePath
	 * @throws Exception
	 */
	public function addFile($filePath)
	{
		if (!file_exists($filePath)) throw new Exception(sprintf('File path %s does not exist.', $filePath));
		$this->filePathList[] = $filePath;
	}

	/**
	 * Pass ZIP archive to output stream
	 *
	 * Format:
	 *   [local file header 1]
	 *   [encryption header 1]                      -- ignored; file is not encrypted
	 *   [file data 1]
	 *   [data descriptor 1]                        -- disabled; causes problem with Archive Utility on OS X;
	 *   .                                             MUST exist if bit 3 in general purpose flag is set
	 *   .
	 *   .
	 *   [local file header n]
	 *   [encryption header n]
	 *   [file data n]
	 *   [data descriptor n]
	 *   [archive decryption header]                -- ignored; file is not encrypted
	 *   [archive extra data record]                -- ignored; file is not encrypted
	 *   [central directory header 1]
	 *   .
	 *   .
	 *   .
	 *   [central directory header n]
	 *   [zip64 end of central directory record]    -- ignored; file is not in zip64 format
	 *   [zip64 end of central directory locator]   -- ignored; file is not in zip64 format
	 *   [end of central directory record]
	 *
	 * @param string $fileName
	 */
	public function output($fileName)
	{
		error_reporting(0);
		header('Content-Type: application/zip');
		header('Content-Transfer-Encoding: Binary');
		header('Content-Disposition: attachment; filename='.$fileName.'');

		clearstatcache();
		foreach ($this->filePathList as $filePath) {
			$this->outputLocalFileHeader($filePath);
			$this->outputFileData($filePath);
			// $this->outputDataDescriptor($filePath);
		}
		foreach ($this->filePathList as $filePath) {
			$this->outputCentralDirectoryHeader($filePath);
		}
		$this->outputEndOfCentralDirectory();

		// die();
	}

	/**
	 * Output "local file header"
	 *
	 * Format:
	 *   local file header signature     4 bytes  (0x04034b50)
	 *   version needed to extract       2 bytes
	 *   general purpose bit flag        2 bytes
	 *   compression method              2 bytes
	 *   last mod file time              2 bytes
	 *   last mod file date              2 bytes
	 *   crc-32                          4 bytes
	 *   compressed size                 4 bytes
	 *   uncompressed size               4 bytes
	 *   file name length                2 bytes
	 *   extra field length              2 bytes
	 *   file name (variable size)
	 *   extra field (variable size)
	 *
	 * @param string $filePath
	 */
	private function outputLocalFileHeader($filePath)
	{
		$versionNeeded = 10;
		$flag = 0;
		$compression = 0;
		list($date, $time) = $this->getDateTime($filePath);
		$crc = $this->getCRC32($filePath);
		$fileSize = filesize($filePath);
		$fileName = basename($filePath);
		$extraLength = 0;

		$data  = pack("VvvvvvVVVvv", 0x04034b50,
			$versionNeeded, $flag, $compression,
			$time, $date, $crc, $fileSize, $fileSize,
			mb_strlen($fileName), $extraLength);
		$data .= $fileName;

		$this->localFileHeaderPositionMap[$filePath] = $this->localFileHeaderPosition;
		$this->localFileHeaderPosition += mb_strlen($data);

		echo $data;
	}

	/**
	 * Output "file data"
	 *
	 * @param string $filePath
	 */
	private function outputFileData($filePath)
	{
		readfile($filePath);

		$this->localFileHeaderPosition += filesize($filePath);
	}

	/**
	 * Output "data descriptor"
	 * (!) Not used due to OS X's Archive Utility bug
	 *
	 * Format:
	 *   data descriptor                 4 bytes  (0x08074b50)
	 *   crc-32                          4 bytes
	 *   compressed size                 4 bytes
	 *   uncompressed size               4 bytes
	 *
	 * @param string $filePath
	 */
	private function outputDataDescriptor($filePath)
	{
		$crc = $this->getCRC32($filePath);
		$fileSize = filesize($filePath);

		$data = pack("VVVV", 0x08074b50,
			$crc, $fileSize, $fileSize);

		$this->localFileHeaderPosition += mb_strlen($data);

		echo $data;
	}

	/**
	 * Output "central directory header"
	 *
	 * Format:
	 *   central file header signature   4 bytes  (0x02014b50)
	 *   version made by                 2 bytes
	 *   version needed to extract       2 bytes
	 *   general purpose bit flag        2 bytes
	 *   compression method              2 bytes
	 *   last mod file time              2 bytes
	 *   last mod file date              2 bytes
	 *   crc-32                          4 bytes
	 *   compressed size                 4 bytes
	 *   uncompressed size               4 bytes
	 *   file name length                2 bytes
	 *   extra field length              2 bytes
	 *   file comment length             2 bytes
	 *   disk number start               2 bytes
	 *   internal file attributes        2 bytes
	 *   external file attributes        4 bytes
	 *   relative offset of local header 4 bytes
	 *   file name (variable size)
	 *   extra field (variable size)
	 *   file comment (variable size)
	 *
	 * @param string $filePath
	 */
	private function outputCentralDirectoryHeader($filePath)
	{
		$versionMadeBy = 20;
		$versionNeeded = 10;
		$flag = 0;
		$compression = 0;
		list($date, $time) = $this->getDateTime($filePath);
		$crc = $this->getCRC32($filePath);
		$fileSize = filesize($filePath);
		$fileName = basename($filePath);
		$extraLength = 0;
		$commentLength = 0;
		$disk = 0;
		$internal = 0;
		$external = 0;

		$data  = pack("VvvvvvvVVVvvvvvVV", 0x02014b50,
			$versionMadeBy, $versionNeeded, $flag,
			$compression, $time, $date, $crc, $fileSize,
			$fileSize, mb_strlen($fileName), $extraLength,
			$commentLength, $disk, $internal, $external,
			$this->localFileHeaderPositionMap[$filePath]);
		$data .= $fileName;

		$this->centralDirectoryHeaderSize += mb_strlen($data);

		echo $data;
	}

	/**
	 * Output "end of central directory"
	 *
	 * Format:
	 *   end of central dir signature    4 bytes  (0x06054b50)
	 *   number of this disk             2 bytes
	 *   number of the disk with the
	 *   start of the central directory  2 bytes
	 *   total number of entries in the
	 *   central directory on this disk  2 bytes
	 *   total number of entries in
	 *   the central directory           2 bytes
	 *   size of the central directory   4 bytes
	 *   offset of start of central
	 *   directory with respect to
	 *   the starting disk number        4 bytes
	 *   .ZIP file comment length        2 bytes
	 *   .ZIP file comment       (variable size)
	 */
	private function outputEndOfCentralDirectory()
	{
		$disk = 0;
		$commentLength = 0;

		$data = pack("VvvvvVVv", 0x06054b50,
			$disk, $disk, count($this->filePathList),
			count($this->filePathList), $this->centralDirectoryHeaderSize,
			$this->localFileHeaderPosition, $commentLength);

		echo $data;
	}

	/**
	 * Get file hash using crc32b
	 *
	 * @param string $filePath
	 * @return integer
	 */
	private function getCRC32($filePath)
	{
		if (empty($this->cacheCRC32[$filePath])) {
			$hash = hash_file('crc32b', $filePath);
			$array = unpack('N', pack('H*', $hash));
			$this->cacheCRC32[$filePath] = $array[1];
		}

		return $this->cacheCRC32[$filePath];
	}

	/**
	 * Get file modification date and time
	 *
	 * @param string $filePath
	 * @return array
	 */
	private function getDateTime($filePath)
	{
		if (empty($this->cacheDateTime[$filePath])) {
			$mtime = getdate(filemtime($filePath));
			$date = (($mtime['year'] - 1980) << 9) + ($mtime['mon'] << 5) + $mtime['mday'];
			$time = ($mtime['hours'] << 11) + ($mtime['minutes'] << 5) + $mtime['seconds'] / 2;
			$this->cacheDateTime[$filePath] = array($date, $time);
		}

		return $this->cacheDateTime[$filePath];
	}

}