<?php

namespace MT\Bundle\MediaBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RecreateThumbnailsCommand extends ContainerAwareCommand
{
    const NEWLINE = true;

    /** @var \Doctrine\ORM\EntityManager */
    private $em;

    protected function configure()
    {
        parent::configure();
        $this->setName('mt-media:recreate-thumbnails-command');
        $this->addArgument('entity-name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->em = $this->getContainer()->get('doctrine')->getEntityManager();

        $this->recreateThumbnails();

        $this->output->writeln('End of ' . $this->getName());
    }

    private function recreateThumbnails()
    {
        $entityName = $this->input->getArgument('entity-name');
        $repository = $this->em->getRepository($entityName);
        if (!$repository) {
            throw new \MT\Bundle\MediaBundle\Exception('Invalid entity name');
        }

        $allImages = $repository->findAll();
        foreach ($allImages as $image) {
            /* @var $image \MT\Bundle\MediaBundle\Entity\Image */
            try {
                $image->recreateThumbnails();
            }
            catch (\Exception $e) {
                var_dump($e);
                continue;
            }
            $this->output->writeln('Done image ID ' . $image->getId());
        }

    }
}
