<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:download-fake-pictures',
    description: 'Download pictures from copyright free websites.',
)]
class DownloadFakePicturesCommand extends Command
{
    public function __construct(
        private HttpClientInterface $client
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        //! Clearing each folder
        $io->section('Removing current pictures');

        $finder = new Finder();
        $fileSystem = new Filesystem();

        $folders = [
            'userPictures',
        ];

        foreach ($folders as $folder) {
            $files = $finder->files()->notName('0.svg')->in("public/assets/images/$folder");
            $fileSystem->remove($files);
        }

        $io->info('Removing pictures => Ok!');

        //! User pictures
        //? https://i.pravatar.cc/200?img={number} will return an image

        $io->section('Downloading user pictures');

        $ppProgressBar = new ProgressBar($output, 20);

        for ($i=1; $i < 21; $i++) { 
            $content = file_get_contents("https://i.pravatar.cc/200?img=" . $i);
    
            //Store in the filesystem.
            $fp = fopen("public/assets/images/userPictures/" . $i . ".jfif", "w");
            fwrite($fp, $content);
            fclose($fp);

            $ppProgressBar->advance();
        }

        $io->info('User pictures => Ok!');

        $io->success([
            'Success, the following pictures were downloaded :',
            '20 user pictures,',
        ]);
        return Command::SUCCESS;
    }
}