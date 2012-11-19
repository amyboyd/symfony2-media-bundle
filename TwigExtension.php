<?php

namespace MT\Bundle\MediaBundle;

use Doctrine\ORM\EntityManager;
use Twig_Extension;
use Twig_Function_Method;
use MT\Bundle\MediaBundle\Entity\Image;

class TwigExtension extends Twig_Extension
{
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getFunctions()
    {
        return array(
            'mtmedia_image_tag' =>
                new Twig_Function_Method($this, 'imageTag', array('is_safe' => array('html'))),
        );
    }

    public function imageTag(Image $image = null, $size = null, $absolute = false, $alt = null)
    {
        if ($image) {
            return sprintf('<img src="%s" alt="%s" />',
                $image->getWebPath($size, $absolute),
                $alt);
        }
    }

    // Required by the framework:
    public function getName()
    {
        return 'mtmediaextension';
    }
}
