<?php

declare(strict_types=1);

namespace App\Command\AwesomeBot;

use App\DTO\BadgeDto;
use App\DTO\MagazineDto;
use App\Entity\Magazine;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\BadgeManager;
use App\Service\MagazineManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JetBrains\PhpStorm\Pure;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(name: 'kbin:awesome-bot:magazine:create')]
class AwesomeBotMagazine extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly MagazineManager $magazineManager,
        private readonly BadgeManager $badgeManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('This command allows you to create awesome-bot magazine.')
            ->addArgument('username', InputArgument::REQUIRED)
            ->addArgument('magazine_name', InputArgument::REQUIRED)
            ->addArgument('magazine_title', InputArgument::REQUIRED)
            ->addArgument('url', InputArgument::REQUIRED)
            ->addArgument('tags', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = $this->userRepository->findOneByUsername($input->getArgument('username'));
        $magazine = $this->magazineRepository->findOneByName($input->getArgument('magazine_name'));

        if($magazine) {
            return Command::INVALID;
        }

        if (!$user) {
            $io->error('User doesn\'t exist.');

            return Command::FAILURE;
        }

        try {
            $dto = new MagazineDto();
            $dto->name = $input->getArgument('magazine_name');
            $dto->title = $input->getArgument('magazine_title');
            $dto->description = 'Powered by '.$input->getArgument('url');
            $dto->user = $user;

            $magazine = $this->magazineManager->create($dto, $user);

            $this->createBadges(
                $magazine,
                $input->getArgument('url'),
                $input->getArgument('tags') ? explode(',', $input->getArgument('tags')) : []
            );
        } catch (\Exception $e) {
            $io->error('Can\'t create magazine');
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    #[Pure]
    private function createBadges(Magazine $magazine, string $url, array $tags): Collection
    {
        $browser = new HttpBrowser(HttpClient::create());
        $crawler = $browser->request('GET', $url);

        $content = $crawler->filter('.markdown-body')->first()->children();

        $labels = [];
        foreach ($content as $elem) {
            if (in_array($elem->nodeName, $tags)) {
                $labels[] = $elem->nodeValue;
            }
        }

        $badges = [];
        foreach ($labels as $label) {
            $this->badgeManager->create(
                (new BadgeDto())->create($magazine, $label)
            );
        }

        return new ArrayCollection($badges);
    }
}
