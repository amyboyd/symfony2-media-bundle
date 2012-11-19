<?php

namespace MT\Bundle\MediaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile as File;
use Symfony\Component\ClassLoader\UniversalClassLoader;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\ImagineInterface;

/**
 * @ORM\MappedSuperclass
 * @ORM\HasLifecycleCallbacks
 */
abstract class Image
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Not used anywhere, but saved incase it is needed in the future.
     * @var $originalName
     * @ORM\Column(name="original_name", type="string", length=255)
     */
    private $originalName;

    /**
     * Sprintf %size% with the correct file size.
     * @var $path;
     * @ORM\Column(type="string",length=255,nullable=true)
     */
    private $path;

    /**
     * @var \DateTime $createdAt
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @param mixed $source URL or \Symfony\Component\HttpFoundation\File\UploadedFile
     * @throws \Exception if $source is not supported.
     */
    public function __construct($source)
    {
        if ($source instanceof File) {
            $this->createFromFile($source);
        }
        elseif (is_string($source) && strpos($source, 'http') === 0) {
            $this->createFromUrl($source);
        }
        else {
            throw new \Exception('Unsupported source');
        }
    }

    private function createFromFile(File $uploadedFile)
    {
        $this->originalName = $uploadedFile->getClientOriginalName();

        // Remove the file extension, and sanitize so only a-z A-Z 0-9 chars are allowed.
        $path = $uploadedFile->getClientOriginalName();
        $path = preg_replace('/[^a-zA-Z0-9-_\.]/', '', $path);
        $path = preg_replace('/\.[a-zA-Z]{3}$/', '', $path);
        $path .= '-' . uniqid() . '-%size%.' . $this->getFileExtensionToSaveAs($uploadedFile);
        $path = trim($path, '/.');
        $this->path = 'MTMediaBundle/' . date('Ymd') . '/' . $path;

        $this->createDirIfNotExists();
        $this->createThumbnails($uploadedFile);

        $this->createdAt = new \DateTime();
    }

    private function createFromUrl($url)
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

        $file = new File($tmpFile, $filename, null, filesize($tmpFile));
        $this->createFromFile($file);
    }

    private function createDirIfNotExists()
    {
        $dir = dirname($this->getAbsolutePath('original'));
        if (!is_dir($dir)) {
            mkdir(dirname($this->getAbsolutePath('original')), 0777, true);
        }
    }

    private function createThumbnails(File $uploadedFile)
    {
        // Load the Imagine library.
        $loader = new UniversalClassLoader();
        $loader->registerNamespace('Imagine', \realpath(__DIR__.'/../lib/imagine/lib'));
        $loader->register();

        // Create the different thumbnail sizes.
        $imagine = self::getImagineInterface();
        $image = $imagine->open($uploadedFile->getPathname());
        $image->strip(); // privacy
        foreach ($this->getAllSizesAndOriginal() as $size) {
            if ($size === 'original') {
                // Original.
                $image->save(str_replace('%size%', $size, $this->getAbsolutePath($size)));
            }
            else {
                // Thumbnail.
                list($width, $height) = explode('x', $size);
                $thumbnail = $image->thumbnail(
                    new \Imagine\Image\Box($width, $height),
                    \Imagine\Image\ManipulatorInterface::THUMBNAIL_OUTBOUND
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
     * Get id
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
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
        }
        elseif (isset($_SERVER['SCRIPT_FILENAME']) && is_dir(dirname($_SERVER['SCRIPT_FILENAME']))) {
            $webRoot = dirname($_SERVER['SCRIPT_FILENAME']);
        }
        elseif (isset($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT'])) {
            $webRoot = $_SERVER['DOCUMENT_ROOT'];
        }

        if (!is_dir($webRoot)) {
            throw new Exception('Web dir not known');
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

    public function __toString()
    {
        return $this->originalName;
    }

    protected function getFileExtensionToSaveAs(File $file)
    {
        // Override this if you want to.
        return $file->guessExtension();
    }

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
}
