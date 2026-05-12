<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Country;
use App\Repository\CountryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:fifa-qualified-teams',
    description: 'Importe la liste des équipes qualifiées (source FIFA) dans les pays.',
)]
final class ImportFifaQualifiedTeamsCommand extends Command
{
    /**
     * @var list<string>
     */
    private const QUALIFIED_TEAMS = [
        'Canada', 'Mexico', 'United States',
        'Panama', 'Curacao', 'Haiti',
        'Argentina', 'Brazil', 'Colombia', 'Ecuador', 'Paraguay', 'Uruguay',
        'Algeria', 'Cape Verde', 'Egypt', 'Ghana', 'Ivory Coast', 'Morocco', 'Senegal', 'South Africa', 'Tunisia',
        'Australia', 'Iran', 'Japan', 'Jordan', 'Uzbekistan', 'Qatar', 'Saudi Arabia', 'South Korea',
        'New Zealand',
        'Iraq', 'DR Congo',
        'Austria', 'Belgium', 'Bosnia and Herzegovina', 'Croatia', 'Czechia', 'England', 'France', 'Germany',
        'Netherlands', 'Norway', 'Portugal', 'Scotland', 'Spain', 'Sweden', 'Switzerland', 'Turkiye',
    ];

    public function __construct(
        private readonly CountryRepository $countryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $created = 0;
        $updated = 0;

        foreach (self::QUALIFIED_TEAMS as $teamName) {
            $country = $this->countryRepository->findOneBy(['nom' => $teamName]);
            if (!$country instanceof Country) {
                $country = new Country();
                $country->setNom($teamName);
                $this->entityManager->persist($country);
                $created++;
            } else {
                $updated++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Import terminé. Pays créés: %d, déjà existants: %d, total traité: %d.',
            $created,
            $updated,
            count(self::QUALIFIED_TEAMS),
        ));

        return Command::SUCCESS;
    }
}
