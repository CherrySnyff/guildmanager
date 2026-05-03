<?php

namespace Guild\Manager\Cli\Command;

use Guild\Manager\Service\Guild\GuildWorkflow;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;

/** CLI: запись в журнал последователей и пересчёт агрегатов. */
class AddFollowers extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('guild-manager:add-followers')
            ->setDescription('Add or remove followers via GuildWorkflow.')
            ->addArgument('guild-id', InputArgument::REQUIRED, 'Guild ID.')
            ->addArgument('actor-user-id', InputArgument::REQUIRED, 'User ID of actor.')
            ->addArgument('character-name', InputArgument::REQUIRED, 'Character name.')
            ->addArgument('source-url', InputArgument::REQUIRED, 'Source URL (https://...).')
            ->addArgument('amount', InputArgument::REQUIRED, 'Amount as positive integer.')
            ->addArgument('type', InputArgument::OPTIONAL, 'gain|loss', 'gain')
            ->addArgument('event-date-text', InputArgument::OPTIONAL, 'Text date/event marker.', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $guildId = (int)$input->getArgument('guild-id');
        $actorUserId = (int)$input->getArgument('actor-user-id');
        $characterName = (string)$input->getArgument('character-name');
        $sourceUrl = (string)$input->getArgument('source-url');
        $amount = (int)$input->getArgument('amount');
        $type = strtolower((string)$input->getArgument('type'));
        $eventDateText = (string)$input->getArgument('event-date-text');

        /** @var \Guild\Manager\Entity\Guild|null $guild */
        $guild = $this->app->em()->find('Guild\Manager:Guild', $guildId);
        if (!$guild) {
            $output->writeln('Guild not found.');
            return 1;
        }

        /** @var \XF\Entity\User|null $actor */
        $actor = $this->app->em()->find('XF:User', $actorUserId);
        if (!$actor) {
            $output->writeln('Actor user not found.');
            return 1;
        }

        /** @var GuildWorkflow $workflow */
        $workflow = $this->app->service('Guild\Manager:Guild\GuildWorkflow');
        if ($type === 'loss') {
            $log = $workflow->removeFollowers($guild, $actor, $characterName, $sourceUrl, $amount, $eventDateText);
        } else {
            $log = $workflow->addFollowers($guild, $actor, $characterName, $sourceUrl, $amount, $eventDateText);
        }

        $output->writeln("Follower operation saved. Log: {$log->follower_log_id}, guild: {$guild->guild_id}");
        return 0;
    }
}
