<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Préférences de notification par utilisateur (clés stockées en JSON sur {@see \App\Entity\User}).
 */
enum NotificationPreference: string
{
    case PronosticReminderEmail = 'pronostic_reminder_email';
    case PronosticReminderPush = 'pronostic_reminder_push';
    case ForumMentionPush = 'forum_mention_push';
    case ButeurGoalEmail = 'buteur_goal_email';
    case ButeurGoalPush = 'buteur_goal_push';
    case AdminMessageEmail = 'admin_message_email';
    case AdminMessagePush = 'admin_message_push';
    case TeamRecapEmail = 'team_recap_email';

    public function label(): string
    {
        return match ($this) {
            self::PronosticReminderEmail => 'Relances pronostic par e-mail',
            self::PronosticReminderPush => 'Relances pronostic par notification push',
            self::ForumMentionPush => 'Mentions sur le forum (push)',
            self::ButeurGoalEmail => 'But de mon buteur par e-mail',
            self::ButeurGoalPush => 'But de mon buteur par notification push',
            self::AdminMessageEmail => 'Messages de l’organisateur par e-mail',
            self::AdminMessagePush => 'Messages de l’organisateur par push',
            self::TeamRecapEmail => 'Récap d’équipe (tous les 2 jours)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PronosticReminderEmail => 'Si vous n’avez pas encore pronostiqué un ou plusieurs matchs de la même journée : un seul e-mail avant le premier match concerné, listant tous les matchs oubliés — match entre 10h et minuit, relance 1 h avant ; match entre 0h et 10h, relance la veille à 22h (heure de Paris).',
            self::PronosticReminderPush => 'Même logique que la relance e-mail, sur les appareils où vous avez activé les notifications push (bouton ci-dessous). Si le push échoue, l’e-mail peut prendre le relais si vous l’avez laissé activé.',
            self::ForumMentionPush => 'Quand un joueur vous cite avec @votre surnom sur le forum, vous recevez une alerte sur cet appareil (et une entrée dans le centre de notifications du site).',
            self::ButeurGoalEmail => 'Dès qu’un but officiel est enregistré pour le joueur que vous avez choisi comme buteur, vous recevez un e-mail avec le détail du match et des points gagnés.',
            self::ButeurGoalPush => 'Même alerte que l’e-mail buteur, en notification push sur cet appareil.',
            self::AdminMessageEmail => 'Annonces ou consignes envoyées manuellement par l’équipe LPF depuis l’administration.',
            self::AdminMessagePush => 'Même contenu que les messages organisateur, en notification push.',
            self::TeamRecapEmail => 'Tous les 2 jours vers 9 h 30 (heure de Paris), si des matchs se sont terminés : bilan fun de votre équipe, star du pronostic, points, BigBalls, buteurs, classement et jokers.',
        };
    }

    public function channel(): string
    {
        return str_ends_with($this->value, '_push') ? 'push' : 'email';
    }

    public function category(): string
    {
        return match ($this) {
            self::PronosticReminderEmail, self::PronosticReminderPush => 'pronostic',
            self::ForumMentionPush => 'forum',
            self::ButeurGoalEmail, self::ButeurGoalPush => 'buteur',
            self::AdminMessageEmail, self::AdminMessagePush => 'organisateur',
            self::TeamRecapEmail => 'recap',
        };
    }

    public function categoryLabel(): string
    {
        return match ($this->category()) {
            'pronostic' => 'Relances pronostic',
            'forum' => 'Forum',
            'buteur' => 'Alertes buteur',
            'organisateur' => 'Messages organisateur',
            'recap' => 'Récap d’équipe',
        };
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    /**
     * @return list<self>
     */
    public static function forCategory(string $category): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $p): bool => $p->category() === $category,
        ));
    }

    /**
     * @return list<string>
     */
    public static function categoryOrder(): array
    {
        return ['pronostic', 'recap', 'forum', 'buteur', 'organisateur'];
    }
}
