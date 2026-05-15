<?php

namespace App\Service;

use App\Entity\GameMatch;
use App\Enum\ReminderDeliveryMode;
use App\Enum\ReminderTrigger;
use App\Repository\GameMatchRepository;
use App\Repository\MatchReminderLogRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MatchPronosticReminderService
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly UserRepository $userRepository,
        private readonly MatchReminderLogRepository $matchReminderLogRepository,
        private readonly MatchPushReminderPlanner $planner,
        private readonly MatchReminderMessageFactory $messageFactory,
        private readonly PlayerReminderDispatcher $playerReminderDispatcher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     matchesChecked: int,
     *     matchesReminded: int,
     *     playersTargeted: int,
     *     pushSent: int,
     *     pushFailed: int,
     *     emailsSent: int,
     *     emailsFailed: int,
     *     dryRun: bool,
     * }
     */
    public function processDueReminders(?\DateTimeImmutable $now = null, bool $dryRun = false): array
    {
        $now ??= new \DateTimeImmutable();

        $summary = [
            'matchesChecked' => 0,
            'matchesReminded' => 0,
            'playersTargeted' => 0,
            'pushSent' => 0,
            'pushFailed' => 0,
            'emailsSent' => 0,
            'emailsFailed' => 0,
            'dryRun' => $dryRun,
        ];

        $matches = $this->gameMatchRepository->findScheduledMatchesPendingPushReminder($now);

        foreach ($matches as $match) {
            ++$summary['matchesChecked'];

            $kickoff = $match->getDateHeure();
            if (!$kickoff instanceof \DateTimeImmutable) {
                continue;
            }

            if (!$this->planner->isReminderDue($kickoff, $now)) {
                continue;
            }

            $users = $this->userRepository->findPlayersWithoutPronosticForMatch($match);
            [$title, $body] = $this->messageFactory->buildForMatch($match, $kickoff);
            $url = '/matchs';

            foreach ($users as $user) {
                $userId = $user->getId();
                if (null === $userId) {
                    continue;
                }

                if ($this->matchReminderLogRepository->hasSuccessfulAutoReminder($match, $userId)) {
                    continue;
                }

                ++$summary['playersTargeted'];

                if ($dryRun) {
                    continue;
                }

                $result = $this->playerReminderDispatcher->dispatchToUser(
                    $user,
                    $title,
                    $body,
                    $url,
                    ReminderDeliveryMode::PreferPush,
                    ReminderTrigger::Auto,
                    $match,
                );

                if (\App\Enum\ReminderChannel::Push === $result['channel']) {
                    $summary['pushSent'] += $result['pushSent'];
                    $summary['pushFailed'] += $result['pushFailed'];
                } elseif ($result['success']) {
                    ++$summary['emailsSent'];
                } else {
                    ++$summary['emailsFailed'];
                }
            }

            if (!$dryRun) {
                $match->setPushReminderSentAt($now);
            }

            ++$summary['matchesReminded'];
        }

        if (!$dryRun && $summary['matchesReminded'] > 0) {
            $this->entityManager->flush();
        }

        return $summary;
    }
}
