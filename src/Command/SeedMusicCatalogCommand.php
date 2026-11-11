<?php

namespace App\Command;

use App\Service\MusicCatalogService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-musicbrainz',
    description: 'Importe une sélection de musique variée depuis MusicBrainz.',
)]
final class SeedMusicCatalogCommand extends Command
{
    /**
     * @var list<array{artist: string, album: string, genre: string}>
     */
    private const SEED_ALBUMS = [
        ['artist' => 'Miles Davis', 'album' => 'Kind of Blue', 'genre' => 'Jazz'],
        ['artist' => 'The Beatles', 'album' => 'Abbey Road', 'genre' => 'Rock'],
        ['artist' => 'Michael Jackson', 'album' => 'Thriller', 'genre' => 'Pop'],
        ['artist' => 'Daft Punk', 'album' => 'Discovery', 'genre' => 'Electronic'],
        ['artist' => 'Lauryn Hill', 'album' => 'The Miseducation of Lauryn Hill', 'genre' => 'Hip-Hop'],
        ['artist' => 'Amy Winehouse', 'album' => 'Back to Black', 'genre' => 'Soul'],
        ['artist' => 'Bob Marley & The Wailers', 'album' => 'Legend', 'genre' => 'Reggae'],
        ['artist' => 'Buena Vista Social Club', 'album' => 'Buena Vista Social Club', 'genre' => 'Latin'],
        ['artist' => 'Radiohead', 'album' => 'OK Computer', 'genre' => 'Alternative'],
        ['artist' => 'Portishead', 'album' => 'Dummy', 'genre' => 'Trip-Hop'],
        ['artist' => 'Björk', 'album' => 'Homogenic', 'genre' => 'Art Pop'],
        ['artist' => 'Kraftwerk', 'album' => 'The Man-Machine', 'genre' => 'Electronic'],
        ['artist' => 'John Coltrane', 'album' => 'Blue Train', 'genre' => 'Jazz'],
        ['artist' => 'Marvin Gaye', 'album' => "What's Going On", 'genre' => 'Soul'],
        ['artist' => 'Nina Simone', 'album' => 'I Put a Spell on You', 'genre' => 'Blues'],
        ['artist' => 'Adele', 'album' => '21', 'genre' => 'Pop'],
        ['artist' => 'Beyoncé', 'album' => 'Lemonade', 'genre' => 'R&B'],
        ['artist' => 'Taylor Swift', 'album' => '1989', 'genre' => 'Pop'],
        ['artist' => 'Kendrick Lamar', 'album' => 'DAMN.', 'genre' => 'Hip-Hop'],
        ['artist' => 'Kanye West', 'album' => 'My Beautiful Dark Twisted Fantasy', 'genre' => 'Hip-Hop'],
        ['artist' => 'Frank Ocean', 'album' => 'Blonde', 'genre' => 'R&B'],
        ['artist' => 'Billie Eilish', 'album' => 'When We All Fall Asleep, Where Do We Go?', 'genre' => 'Pop'],
        ['artist' => 'The Weeknd', 'album' => 'After Hours', 'genre' => 'R&B'],
        ['artist' => 'Dua Lipa', 'album' => 'Future Nostalgia', 'genre' => 'Dance-Pop'],
        ['artist' => 'Arctic Monkeys', 'album' => 'AM', 'genre' => 'Indie Rock'],
        ['artist' => 'Tame Impala', 'album' => 'Currents', 'genre' => 'Psychedelic Rock'],
        ['artist' => 'Florence + the Machine', 'album' => 'Lungs', 'genre' => 'Indie Pop'],
        ['artist' => 'Lana Del Rey', 'album' => 'Norman Fucking Rockwell!', 'genre' => 'Alternative'],
        ['artist' => 'Lorde', 'album' => 'Melodrama', 'genre' => 'Art Pop'],
        ['artist' => 'SZA', 'album' => 'SOS', 'genre' => 'R&B'],
        ['artist' => 'Tyler, the Creator', 'album' => 'IGOR', 'genre' => 'Hip-Hop'],
        ['artist' => 'Childish Gambino', 'album' => 'Awaken, My Love!', 'genre' => 'Funk'],
        ['artist' => 'Janelle Monáe', 'album' => 'The ArchAndroid', 'genre' => 'Funk'],
        ['artist' => 'Rosalía', 'album' => 'El Mal Querer', 'genre' => 'Flamenco Pop'],
        ['artist' => 'Bad Bunny', 'album' => 'Un Verano Sin Ti', 'genre' => 'Reggaeton'],
        ['artist' => 'Coldplay', 'album' => 'A Rush of Blood to the Head', 'genre' => 'Alternative Rock'],
        ['artist' => 'Muse', 'album' => 'Simulation Theory', 'genre' => 'Alternative Rock'],
        ['artist' => 'Gorillaz', 'album' => 'Demon Days', 'genre' => 'Alternative'],
        ['artist' => 'The xx', 'album' => 'xx', 'genre' => 'Indie Pop'],
        ['artist' => 'Bon Iver', 'album' => 'For Emma, Forever Ago', 'genre' => 'Indie Folk'],
        ['artist' => 'Fleet Foxes', 'album' => 'Helplessness Blues', 'genre' => 'Indie Folk'],
        ['artist' => 'Vampire Weekend', 'album' => 'Modern Vampires of the City', 'genre' => 'Indie Rock'],
        ['artist' => 'Hozier', 'album' => 'Hozier', 'genre' => 'Blues Rock'],
        ['artist' => 'St. Vincent', 'album' => 'Strange Mercy', 'genre' => 'Art Rock'],
        ['artist' => 'FKA twigs', 'album' => 'LP1', 'genre' => 'Experimental Pop'],
    ];

    public function __construct(
        private readonly MusicCatalogService $musicCatalogService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'Nombre maximum d’albums à importer (par défaut : toute la sélection).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = $this->parseLimit($input->getOption('limit'), count(self::SEED_ALBUMS));
        $albums = array_slice(self::SEED_ALBUMS, 0, $limit);
        $failed = 0;

        $io->title('Import MusicBrainz');
        $io->text(sprintf(
            '%d album(s) seront traité(s). Les artistes et albums déjà présents seront réutilisés.',
            count($albums),
        ));

        foreach ($albums as $index => $seed) {
            $label = sprintf('%s — %s', $seed['artist'], $seed['album']);
            $io->write(sprintf('[%d/%d] %s ... ', $index + 1, count($albums), $label));

            try {
                $this->musicCatalogService->findOrCreateAlbum(
                    $seed['artist'],
                    $seed['album'],
                    $seed['genre'],
                );
                $io->writeln('<info>OK</info>');
            } catch (\RuntimeException $exception) {
                ++$failed;
                $io->writeln('<error>ÉCHEC</error>');
                $io->text('  '.$exception->getMessage());
            }
        }

        if ($failed > 0) {
            $io->error(sprintf('%d album(s) n’ont pas pu être importé(s).', $failed));

            return Command::FAILURE;
        }

        $io->success('Import MusicBrainz terminé.');

        return Command::SUCCESS;
    }

    private function parseLimit(mixed $value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false) {
            throw new \InvalidArgumentException('L’option --limit doit être un entier supérieur ou égal à 1.');
        }

        return min($limit, $default);
    }
}
