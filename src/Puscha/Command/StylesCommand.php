<?php

namespace Puscha\Command;

use JMS\Serializer\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use JMS\Serializer\SerializerBuilder;
use Puscha\Model\Config;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validation;

class StylesCommand extends Command
{
    protected function configure()
    {
        $this->setName('styles')
            ->setDescription('Tests styles.')
            ->setHelp('.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>info</info>');
        $output->writeln('<comment>comment</comment>');
        $output->writeln('<question>question</question>');
        $output->writeln('<error>error</error>');

        $io = new SymfonyStyle($input, $output);
        $io->title('Title lorem Ipsum Dolor Sit Amet');
        $io->section('Section lorem Ipsum Dolor Sit Amet');
        $io->text(array(
            'Text lorem ipsum dolor sit amet',
            'Consectetur adipiscing elit',
            'Aenean sit amet arcu vitae sem faucibus porta',
        ));
        $io->listing(array(
            'Element #1 Lorem ipsum dolor sit amet',
            'Element #2 Lorem ipsum dolor sit amet',
            'Element #3 Lorem ipsum dolor sit amet',
        ));
        $io->table(
            array('Header 1', 'Header 2'),
            array(
                array('Cell 1-1', 'Cell 1-2'),
                array('Cell 2-1', 'Cell 2-2'),
                array('Cell 3-1', 'Cell 3-2'),
            )
        );
        $io->note('Note lorem ipsum dolor sit amet');
        $io->caution('Caution lorem ipsum dolor sit amet');
        $io->success('Success lorem ipsum dolor sit amet');
        $io->warning('Warning lorem ipsum dolor sit amet');
        $io->error('Error lorem ipsum dolor sit amet');
        $io->error(array(
            'Multi error lorem ipsum dolor sit amet',
            'Consectetur adipiscing elit',
        ));
        $io->ask('Ask what is your name?');
    }
}
