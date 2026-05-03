<?php

namespace Guild\Manager\Cli\Command;

use Guild\Manager\Service\Guild\GuildWorkflow;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;

/** CLI: запись репутации (с пересчётом уровня/кэша влияния по логике OperationManager). */
class AddReputation extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('guild-manager:add-reputation')
            ->setDescription('Add or remove reputation via GuildWorkflow.')
            ->addArgument('guild-id', InputArgument::REQUIRED, 'Guild ID.')
            ->addArgument('actor-user-id', InputArgument::REQUIRED, 'User ID of actor.')
            ->addArgument('region-key', InputArgument::REQUIRED, 'aramidis|union|korzus')
            ->addArgument('character-name', InputArgument::REQUIRED, 'Character name.')
            ->addArgument('source-url', InputArgument::REQUIRED, 'Source URL (https://...).')
            ->addArgument('amount', InputArgument::REQUIRED, 'Amount as positive integer.')
            ->addArgument('type', InputArgument::OPTIONAL, 'gain|loss', 'gain')
            ->addArgument('faction-name', InputArgument::OPTIONAL, 'Faction name.', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $guildId = (int)$input->getArgument('guild-id');
        $actorUserId = (int)$input->getArgument('actor-user-id');
        $regionKey = strtolower((string)$input->getArgument('region-key'));
        $characterName = (string)$input->getArgument('character-name');
        $sourceUrl = (string)$input->getArgument('source-url');
        $amount = (int)$input->getArgument('amount');
        $type = strtolower((string)$input->getArgument('type'));
        $factionName = (string)$input->getArgument('faction-name');

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
            $log = $workflow->removeReputation(
                $guild,
                $actor,
                $regionKey,
                $characterName,
                $sourceUrl,
                $amount,
                $factionName
            );
        } else {
            $log = $workflow->addReputation(
                $guild,
                $actor,
                $regionKey,
                $characterName,
                $sourceUrl,
                $amount,
                $factionName
            );
        }

        $output->writeln("Reputation operation saved. Log: {$log->reputation_log_id}, guild: {$guild->guild_id}");
        return 0;
    }
}
