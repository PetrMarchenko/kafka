<?php

namespace App\Command;

use App\Entity\OutboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-outbox',
    description: 'Adds a test event to the outbox'
)]
class TestOutboxCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $event = new OutboxMessage(
            aggregateType: 'order',
            aggregateId: 'order-123',
            type: 'order_created',
            payload: ['amount' => 100, 'currency' => 'USD']
        );

        $this->em->persist($event);
        $this->em->flush();

        $output->writeln('<info>✅ The event was successfully written to the outbox.</info>');
        return Command::SUCCESS;
    }
}
