<?php

namespace MT\Bundle\MediaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use MT\Bundle\MediaBundle\Exception as BundleException;

/**
 * @ORM\Table(name="mtmediabundle_file")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class File extends AbstractFileType
{
    protected function createFromFile(UploadedFile $uploadedFile)
    {
        parent::createFromFile($uploadedFile);
        copy($uploadedFile->getPathname(), $this->getAbsolutePath());
    }

    protected function createDirIfNotExists()
    {
        $dir = dirname($this->getAbsolutePath());
        if (!is_dir($dir)) {
            mkdir(dirname($this->getAbsolutePath()), 0777, true);
        }
    }

    /**
     * Get the absolute path from the root of the file system.
     */
    public function getAbsolutePath()
    {
        $webRoot = null;

        if (PHP_SAPI === 'cli') {
            global $kernel; /* @var $kernel \Symfony\Component\HttpKernel\Kernel */
            $webRoot = realpath($kernel->getRootDir() . '/../web');
        }
        elseif (isset($_SERVER['SCRIPT_FILENAME']) && is_dir(dirname($_SERVER['SCRIPT_FILENAME']))) {
            $webRoot = dirname($_SERVER['SCRIPT_FILENAME']);
        }
        elseif (isset($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT'])) {
            $webRoot = $_SERVER['DOCUMENT_ROOT'];
        }

        if (!is_dir($webRoot)) {
            throw new BundleException('Web dir not known');
        }

        return $webRoot . '/' . $this->path;
    }

    /**
     * Get web path from the root of the website domain.
     */
    public function getWebPath($absolute = false)
    {
        $path = $this->path;
        $path = str_replace('%size%', '%25size%25', $path);
        if ($absolute) {
            $protocol = (isset($_SERVER['HTTPS']) && (bool) $_SERVER['HTTPS'] ? 'https' : 'http');
            return $protocol . '://' . $_SERVER['HTTP_HOST'] . '/' . $path;
        }
        else {
            $path = '/' . ltrim($path, '/');
        }
        return $path;
    }

    /**
     * @ORM\PostRemove()
     */
    public function postRemove()
    {
        // Try to delete the file too.
        @unlink($this->getAbsolutePath());
    }

    public function fileExists()
    {
        return file_exists($this->getAbsolutePath());
    }
}
