<?php

namespace MT\Bundle\MediaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
// @todo - use Symfony\Component\HttpFoundation\File\File instead of UploadedFile.
use Symfony\Component\HttpFoundation\File\UploadedFile;
use MT\Bundle\MediaBundle\Exception as BundleException;

abstract class AbstractFileType
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="original_name", type="string", length=255)
     */
    protected $originalName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $path;

    /**
     * @var \DateTime $createdAt
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $createdAt;

    /**
     * @param mixed $source URL or \Symfony\Component\HttpFoundation\File\UploadedFile
     * @throws BundleException if $source is not supported.
     */
    public function __construct($source)
    {
        if ($source instanceof UploadedFile) {
            $this->createFromFile($source);
        }
        elseif (is_string($source) && strpos($source, 'http') === 0) {
            $this->createFromUrl($source);
        }
        elseif (is_string($source) && file_exists($source)) {
            $this->createFromFilePath($source);
        }
        else {
            throw new BundleException('Unsupported source');
        }
    }

    protected function createFromFile(UploadedFile $uploadedFile)
    {
        $this->originalName = $uploadedFile->getClientOriginalName();

        // Remove the file extension, and sanitize so only a-z A-Z 0-9 chars are allowed.
        $path = $uploadedFile->getClientOriginalName();
        $path = preg_replace('/[^a-zA-Z0-9-_\.]/', '', $path);
        $path = preg_replace('/\.[a-zA-Z]{3}$/', '', $path);
        // %size% is a left-over from when the library was only for images.
        // A better name now would be %variant%.
        $path .= '-' . uniqid() . '-%size%.' . $this->getFileExtensionToSaveAs($uploadedFile);
        $path = trim($path, '/.');
        $this->path = 'MTMediaBundle/' . date('Ymd') . '/' . $path;

        $this->createDirIfNotExists();

        $this->createdAt = new \DateTime();
    }

    protected function createFromUrl($url)
    {
        $filename = parse_url($url, PHP_URL_PATH);
        $filename = explode('/', $filename);
        $filename = $filename[count($filename) - 1];

        // Save the URL contents to a temporary file.
        $tmpDir = '/tmp/MTMediaBundle/';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }
        $tmpFile = $tmpDir . rand() . '-' . $filename;
        file_put_contents($tmpFile, file_get_contents($url));

        $file = new UploadedFile($tmpFile, $filename, null, filesize($tmpFile));
        $this->createFromFile($file);
    }

    protected function createFromFilePath($path)
    {
        $filename = explode('/', $path);
        $filename = $filename[count($filename) - 1];

        $file = new UploadedFile($path, $filename, null, filesize($path));
        $this->createFromFile($file);
    }

    abstract protected function createDirIfNotExists();
    
    /**
     * Get id
     * @return integer
     */
    final public function getId()
    {
        return $this->id;
    }

    final public function getCreatedAt()
    {
        return $this->createdAt;
    }

    final public function __toString()
    {
        return $this->originalName;
    }

    /**
     * Override this if you want to.
     */
    protected function getFileExtensionToSaveAs(UploadedFile $file)
    {
        return $file->guessExtension();
    }
}
