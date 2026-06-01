<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RecapEmailBatch;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\NotificationPreference;
use App\Repository\GameMatchRepository;
use App\Repository\RecapEmailBatchRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class TeamRecapEmailService
{
    public function __construct(
        private readonly RecapEmailBatchRepository $recapEmailBatchRepository,
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly TeamRepository $teamRepository,
        private readonly TeamRecapBuilder $teamRecapBuilder,
        private readonly TeamRecapMailer $teamRecapMailer,
        private readonly UserNotificationPreferenceService $preferenceService,
        private readonly BiDailyRecapSchedule $schedule,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{
     *     skipped: bool,
     *     reason: ?string,
     *     period_start: ?string,
     *     period_end: ?string,
     *     matches_in_period: int,
     *     teams_notified: int,
     *     emails_sent: int,
     *     emails_failed: int
     * }
     */
    public function process(?\DateTimeImmutable $now = null, bool $dryRun = false, bool $force = false): array
    {
        $now ??= new \DateTimeImmutable();
        $lastSentAt = $this->recapEmailBatchRepository->findLatestSentAt();

        if (!$this->schedule->shouldSendNow($now, $lastSentAt, $force)) {
            return $this->summary(
                skipped: true,
                reason: 'Pas encore l’heure ou intervalle de 2 jours non écoulé.',
            );
        }

        $lastPeriodEnd = $this->recapEmailBatchRepository->findLatestPeriodEnd();
        [$periodStart, $periodEnd] = $this->schedule->resolvePeriod($now, $lastPeriodEnd);
        $matches = $this->gameMatchRepository->findFinishedFinalizedInPeriod($periodStart, $periodEnd);

        if ([] === $matches) {
            return $this->summary(
                skipped: true,
                reason: 'Aucun match terminé sur la période.',
                periodStart: $periodStart,
                periodEnd: $periodEnd,
            );
        }

        $teams = $this->teamRepository->findAllWithMembersAndPlayers();
        $emailsSent = 0;
        $emailsFailed = 0;
        $teamsNotified = 0;

        foreach ($teams as $team) {
            if (!$team instanceof Team || null === $team->getId()) {
                continue;
            }

            $recap = $this->teamRecapBuilder->buildForTeam($team, $periodStart, $periodEnd, $matches);
            if (null === $recap) {
                continue;
            }

            $recap['team_id'] = (int) $team->getId();
            ++$teamsNotified;

            foreach ($team->getMembers() as $member) {
                if (!$member instanceof TeamMember) {
                    continue;
                }

                $player = $member->getPlayer();
                if (!$player instanceof User || !$player->isCotisationPayee()) {
                    continue;
                }

                if (!$this->preferenceService->isEnabled($player, NotificationPreference::TeamRecapEmail)) {
                    continue;
                }

                if ($dryRun) {
                    ++$emailsSent;
                    continue;
                }

                try {
                    $this->teamRecapMailer->send($player, (string) $member->getNickname(), $recap);
                    ++$emailsSent;
                } catch (\Throwable $e) {
                    ++$emailsFailed;
                    $this->logger->error('Échec envoi récap d’équipe.', [
                        'user_email' => $player->getEmail(),
                        'team_id' => $team->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!$dryRun) {
            $batch = (new RecapEmailBatch())
                ->setPeriodStart($periodStart)
                ->setPeriodEnd($periodEnd)
                ->setEmailsSent($emailsSent)
                ->setTeamsNotified($teamsNotified)
                ->setMatchesInPeriod(\count($matches))
                ->setDryRun(false);
            $this->entityManager->persist($batch);
            $this->entityManager->flush();
        }

        return $this->summary(
            skipped: false,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            matchesInPeriod: \count($matches),
            teamsNotified: $teamsNotified,
            emailsSent: $emailsSent,
            emailsFailed: $emailsFailed,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(
        bool $skipped,
        ?string $reason = null,
        ?\DateTimeImmutable $periodStart = null,
        ?\DateTimeImmutable $periodEnd = null,
        int $matchesInPeriod = 0,
        int $teamsNotified = 0,
        int $emailsSent = 0,
        int $emailsFailed = 0,
    ): array {
        return [
            'skipped' => $skipped,
            'reason' => $reason,
            'period_start' => $periodStart?->format(\DateTimeInterface::ATOM),
            'period_end' => $periodEnd?->format(\DateTimeInterface::ATOM),
            'matches_in_period' => $matchesInPeriod,
            'teams_notified' => $teamsNotified,
            'emails_sent' => $emailsSent,
            'emails_failed' => $emailsFailed,
        ];
    }
}
