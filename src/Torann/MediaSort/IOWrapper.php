<?php namespace Torann\MediaSort;

use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

use Torann\MediaSort\File\UploadedFile;
use Torann\MediaSort\Exceptions\FileException;

class IOWrapper {

    /**
     * The current media object being processed.
     *
     * @var \Torann\MediaSort\Manager
     */
    public $media;

    /**
     * Constructor method
     *
     * @param \Torann\MediaSort\Manager $media
     */
    function __construct($media)
    {
        $this->media = $media;
    }

    /**
	 * Build an UploadedFile object using various file input types.
	 *
	 * @param  mixed  $file
	 * @return \Torann\MediaSort\File\UploadedFile
	 */
	public function make($file)
	{
		if ($file instanceof SymfonyUploadedFile) {
			return $this->createFromObject($file);
		}

		if (is_array($file)) {
			return $this->createFromArray($file);
		}

		if (array_key_exists('scheme', parse_url($file))) {
			return $this->createFromUrl($file);
		}

		return $this->createFromString($file);
	}

	/**
	 * Build a \Torann\MediaSort\File\UploadedFile object from
	 * a Symfony\Component\HttpFoundation\File\UploadedFile object.
	 *
	 * @param  \Symfony\Component\HttpFoundation\File\UploadedFile $file
	 * @return \Torann\MediaSort\File\UploadedFile
     * @throws \Torann\MediaSort\Exceptions\FileException
	 */
	protected function createFromObject(SymfonyUploadedFile $file)
	{
		$path = $file->getPathname();
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $size = $file->getClientSize();
        $error = $file->getError();

        $uploadFile = new UploadedFile($path, $originalName, $mimeType, $size, $error);

        if (!$uploadFile->isValid()) {
			throw new FileException($uploadFile->getErrorMessage($uploadFile->getError()));
		}

        return $uploadFile;
	}

	/**
	 * Build a Torann\MediaSort\File\UploadedFile object from the
	 * raw php $_FILES array date.
	 *
	 * @param  array $file
	 * @return \Torann\MediaSort\File\UploadedFile
	 */
	protected function createFromArray($file)
	{
		return new UploadedFile($file['tmp_name'], $file['name'], $file['type'], $file['size'], $file['error']);
	}

	/**
	 * Fetch a remote file using a string URL and convert it into
	 * an instance of Torann\MediaSort\File\UploadedFile.
	 *
	 * @param  string $file
	 * @return \Torann\MediaSort\File\UploadedFile
	 */
	protected function createFromUrl($file)
	{
		$ch = curl_init ($file);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$rawFile = curl_exec($ch);
		curl_close ($ch);

		// Create a file path for the file by storing it on disk.
		$filePath = tempnam(sys_get_temp_dir(), 'STP');
		file_put_contents($filePath, $rawFile);

		// Get the original name of the file
		$name = pathinfo($file)['basename'];

		// Get the mime type of the file
		$sizeInfo = getimagesizefromstring($rawFile);
		$mime = $sizeInfo['mime'];

		// Get the length of the file
		if (function_exists('mb_strlen')) {
			$size = mb_strlen($rawFile, '8bit');
		}
        else {
			$size = strlen($rawFile);
		}

		return new UploadedFile($filePath, $name, $mime, $size, 0);
	}

	/**
	 * Fetch a local file using a string location and convert it into
	 * an instance of Torann\MediaSort\File\UploadedFile.
	 *
	 * @param  string $file
	 * @return \Torann\MediaSort\File\UploadedFile
	 */
	protected function createFromString($file)
	{
        return new UploadedFile($file, basename($file));
	}

}