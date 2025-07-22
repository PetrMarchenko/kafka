<?php

namespace App\Command;

use DateTime;
use RdKafka\Conf;
use RdKafka\Producer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:kafka:produce',
    description: 'Send a message to Kafka',
)]
class KafkaProduceCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', 'kafka:9092');

        $producer = new Producer($conf);

        $topicName = 'my-replicated-topic';
        $topic = $producer->newTopic($topicName);

        $data = [
            'userId' => rand(100, 999),
            'loggedInAt' => (new DateTime())->format('Y-m-d H:i:s'),
        ];

        $message = json_encode($data);
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $message);
        $producer->flush(1000);
        $output->writeln("✅ Message sent: $message");

        return Command::SUCCESS;
    }
}
