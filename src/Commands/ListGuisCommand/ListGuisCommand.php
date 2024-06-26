<?php

declare(strict_types=1);

namespace Pool_CLI\Commands\ListGuisCommand;

use Pool_CLI\Helper\Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListGuisCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('list_guis')
            ->setDescription('show all guis in chosen project')
            ->setHelp('lookup on pool-documentation/pool-cli how to list all GUIs in project');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('<info>Show routes of Project</info>');

        $projectDirs = Helper::getProjectDirs(SRC_DIR, 'guis');
        $project = $io->choice('Of which project you want to see all routes?', $projectDirs);
        $projectDir = SRC_DIR . '/' . $project . '/guis';

        $guis = [];
        $dirIterator = new \DirectoryIterator($projectDir);
        foreach ($dirIterator as $items) {
            if ($items->isDir() && !$items->isDot()) {
                $fileIterator = new \DirectoryIterator($projectDir . '/' . $items->getFilename());
                // todo: maybe not 100% accurate! -> not sure if .html file in gui-folder = schemas exists..
                $hasHtml = false;
                foreach ($fileIterator as $item) {
                    if ($item->isFile() && $item->getExtension() === 'html') {
                        $hasHtml = true;
                    }
                }
                $guis[$items->getFilename()]['gui'] = $items->getFilename();
                $guis[$items->getFilename()]['hasSchema'] = $hasHtml ? 'yes' : 'no';
            }
        }

        $io->table(
            ['GUI', 'has schema', '?'],
            $guis
        );

        return Command::SUCCESS;
    }
}
