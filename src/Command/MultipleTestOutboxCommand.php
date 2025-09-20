<?php

namespace App\Command;

use App\Entity\OutboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:multiple-test-outbox',
    description: 'Adds test events to the outbox gradually with optional parameters'
)]
class MultipleTestOutboxCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('total', null, InputOption::VALUE_OPTIONAL, 'Total number of events to insert', 1000)
            ->addOption('batch', null, InputOption::VALUE_OPTIONAL, 'Number of events per batch', 100)
            ->addOption('delay', null, InputOption::VALUE_OPTIONAL, 'Delay in seconds between batches', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $total = (int) $input->getOption('total');
        $batchSize = (int) $input->getOption('batch');
        $delay = (int) $input->getOption('delay');

        $id = 0;

        $start = hrtime(true);

        for ($i = 1; $i <= $total; $i++) {
            $id++;

            $event = new OutboxMessage(
                aggregateType: 'order',
                aggregateId: 'order-' . $i,
                type: 'order_created',
                payload: ['amount' => rand(10, 1000), 'currency' => 'USD', 'id' => $id]
            );

            $this->em->persist($event);

            if ($i % $batchSize === 0) {
                $this->em->flush();
                $this->em->clear();

                $used = round(memory_get_usage(true) / 1024 / 1024, 2);
                $output->writeln("✅ Inserted $i messages... memory: {$used} MB");

                if ($delay > 0) {
                    usleep($delay * 1_000_000);
                }
            }
        }

        if ($total % $batchSize !== 0) {
            $this->em->flush();
            $this->em->clear();
        }

        $end = hrtime(true);
        $executionTime = ($end - $start) / 1e9;

        $output->writeln('Total execution time: ' . $executionTime . ' s');

        $output->writeln("<info>🎉 Successfully inserted $total messages into the outbox.</info>");

        return Command::SUCCESS;
    }
}
