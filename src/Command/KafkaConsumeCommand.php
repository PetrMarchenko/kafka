<?php

namespace App\Command;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:kafka:consume',
    description: 'Read messages from Kafka',
)]
class KafkaConsumeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', 'kafka1:9092');
        $conf->set('group.id', 'my-consumer-group');
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('client.id', 'kafka-consumer-4');

        $consumer = new KafkaConsumer($conf);

        $consumer->subscribe(['my-replicated-topic']);

        $output->writeln("📡 Listening to topic 'my-replicated-topic'... Press Ctrl+C to exit");

        while (true) {
            $message = $consumer->consume(120 * 1000);

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $data = json_decode($message->payload, true);
                    $output->writeln("✅ Message received:");
                    $output->writeln("  User #{$data['userId']} logged in at {$data['loggedInAt']}");
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    break;

                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    $output->writeln("⏰ Consumer timed out waiting for a message...");
                    break;

                default:
                    $output->writeln("❌ Kafka error: {$message->errstr()}");
                    break;
            }
        }

        return Command::SUCCESS;
    }
}
