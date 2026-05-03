<?php

namespace Guild\Manager\Cli\Command;

use Guild\Manager\Service\Guild\Aggregator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;

/** CLI `guild-manager:recalc`: пересчёт followers_total, treasury, organization_level, influence_cache через Aggregator. */
class Recalc extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('guild-manager:recalc')
            ->setDescription('Recalculate guild aggregates for one guild or all guilds.')
            ->addArgument('guild-id', InputArgument::OPTIONAL, 'Guild ID. If omitted, recalc all.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $guildIdArg = $input->getArgument('guild-id');
        /** @var Aggregator $aggregator */
        $aggregator = $this->app->service('Guild\Manager:Guild\Aggregator');

        if ($guildIdArg !== null && $guildIdArg !== '') {
            $guildId = (int)$guildIdArg;
            /** @var \Guild\Manager\Entity\Guild|null $guild */
            $guild = $this->app->em()->find('Guild\Manager:Guild', $guildId);
            if (!$guild) {
                $output->writeln('Guild not found.');
                return 1;
            }

            $aggregator->recalculateAll($guild, false);
            $aggregator->recalculateInfluenceCache($guild);
            $guild->last_update = \XF::$time;
            $guild->save();

            $output->writeln("Recalculated guild {$guild->guild_id}.");
            return 0;
        }

        $finder = $this->app->finder('Guild\Manager:Guild')->order('guild_id');
        $total = 0;
        foreach ($finder->fetch() as $guild) {
            $aggregator->recalculateAll($guild, false);
            $aggregator->recalculateInfluenceCache($guild);
            $guild->last_update = \XF::$time;
            $guild->save();
            $total++;
        }

        $output->writeln("Recalculated {$total} guild(s).");
        return 0;
    }
}
