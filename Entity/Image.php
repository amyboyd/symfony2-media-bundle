<?php

namespace MT\Bundle\MediaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\ImagineInterface;

/**
 * @ORM\MappedSuperclass
 * @ORM\HasLifecycleCallbacks
 */
abstract class Image extends AbstractFileType
{
    /**
     * @ORM\Column(name="original_width", type="smallint")
     */
    protected $originalWidth;

    /**
     * @ORM\Column(name="original_height", type="smallint")
     */
    protected $originalHeight;

    protected function createFromFile(UploadedFile $uploadedFile)
    {
        parent::createFromFile($uploadedFile);
        $this->createThumbnails($uploadedFile);
    }

    protected function createDirIfNotExists()
    {
        $dir = dirname($this->getAbsolutePath('original'));
        if (!is_dir($dir)) {
            mkdir(dirname($this->getAbsolutePath('original')), 0777, true);
        }
    }

    private function createThumbnails(UploadedFile $uploadedFile)
    {
        // Load the Imagine library.
        $loader = new UniversalClassLoader();
        $loader->registerNamespace('Imagine', \realpath(__DIR__ . '/../lib/imagine/lib'));
        $loader->register();

        // Create the different thumbnail sizes.
        $imagine = self::getImagineInterface();
        $image = $imagine->open($uploadedFile->getPathname()); /* @var $image \Imagine\Gd\Image */

        $size = $image->getSize();
        $this->originalWidth = $size->getWidth();
        $this->originalHeight = $size->getHeight();

        $image->strip(); // privacy
        foreach ($this->getAllSizesAndOriginal() as $size) {
            if ($size === 'original') {
                // Original.
                $image->save(str_replace('%size%', $size, $this->getAbsolutePath($size)));
            } else {
                // Thumbnail.
                list($width, $height) = explode('x', $size);
                $thumbnail = $image->thumbnail(
                    new \Imagine\Image\Box($width, $height), \Imagine\Image\ManipulatorInterface::THUMBNAIL_OUTBOUND
                );
                $thumbnail = $this->postResizeHook($thumbnail, $size, $imagine);
                $thumbnail->save(str_replace('%size%', $size, $this->getAbsolutePath($size)));
            }
        }
    }

    private static $hasRegisteredImagineNamespace = false;

    /**
     * @return \Imagine\Image\ImagineInterface
     */
    public static function getImagineInterface()
    {
        if (!self::$hasRegisteredImagineNamespace) {
            // Load the Imagine library.
            $loader = new UniversalClassLoader();
            $loader->registerNamespace('Imagine', \realpath(__DIR__ . '/../lib/imagine/lib'));
            $loader->register();
            self::$hasRegisteredImagineNamespace = true;
        }
        return new \Imagine\Gd\Imagine();
    }

    /**
     * Get the absolute path from the root of the file system.
     */
    public function getAbsolutePath($size)
    {
        $webRoot = null;

        if (PHP_SAPI === 'cli') {
            global $kernel; /* @var $kernel \Symfony\Component\HttpKernel\Kernel */
            $webRoot = realpath($kernel->getRootDir() . '/../web');
        } elseif (isset($_SERVER['SCRIPT_FILENAME']) && is_dir(dirname($_SERVER['SCRIPT_FILENAME']))) {
            $webRoot = dirname($_SERVER['SCRIPT_FILENAME']);
        } elseif (isset($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT'])) {
            $webRoot = $_SERVER['DOCUMENT_ROOT'];
        }

        if (!is_dir($webRoot)) {
            throw new \Exception('Web dir not known');
        }

        return $webRoot . '/' . str_replace('%size%', $size, $this->path);
    }

    /**
     * Get web path from the root of the website domain.
     */
    public function getWebPath($size, $absolute = false)
    {
        $path = str_replace('%size%', $size, $this->path);
        if ($absolute) {
            $protocol = (isset($_SERVER['HTTPS']) && (bool) $_SERVER['HTTPS'] ? 'https' : 'http');
            return $protocol . '://' . $_SERVER['HTTP_HOST'] . $path;
        } else {
            $path = '/' . ltrim($path, '/');
        }
        return $path;
    }

    /**
     * @ORM\PostRemove()
     */
    public function postRemove()
    {
        // Try to delete the files too.
        foreach ($this->getAllSizesAndOriginal() as $size) {
            if ($file = $this->getAbsolutePath($size)) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    private function getAllSizesAndOriginal()
    {
        return array_merge(array('original'), $this->getAllSizes());
    }

    abstract protected function getAllSizes();

    /**
     * @param \Imagine\Image\ManipulatorInterface $thumbnail
     * @param type $size
     * @param \Imagine\Image\ImagineInterface $imagine
     * @return ManipulatorInterface
     */
    protected function postResizeHook(ManipulatorInterface $thumbnail, $size, ImagineInterface $imagine)
    {
        // Override this if you want to.
        return $thumbnail;
    }

    public function getOriginalWidth()
    {
        return $this->originalWidth;
    }

    public function getOriginalHeight()
    {
        return $this->originalHeight;
    }

    public function fileExists($size)
    {
        return file_exists($this->getAbsolutePath($size));
    }
}
