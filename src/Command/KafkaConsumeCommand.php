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
    description: 'Read messages from Kafka (outbox topic)',
)]
class KafkaConsumeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', 'kafka:9092');
        $conf->set('group.id', 'my-consumer-group');
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('client.id', 'kafka-consumer-1');

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe(['app.public.outbox']);

        $output->writeln("📡 Listening to topic 'app.public.outbox'... Press Ctrl+C to exit");

        while (true) {
            $message = $consumer->consume(120 * 1000);

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $event = json_decode($message->payload, true);

                    if (!isset($event['payload']['after'])) {
                        $output->writeln("ℹ️ Received non-payload message or schema change.");
                        return Command::SUCCESS;
                    }

                    $after = $event['payload']['after'];
                    $payload = json_decode($after['payload'], true);

                    $output->writeln("✅ New event:");
                    $output->writeln(json_encode($payload, JSON_PRETTY_PRINT));

                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    break;

                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    $output->writeln("⏰ Consumer timed out...");
                    break;

                default:
                    $output->writeln("❌ Kafka error: {$message->errstr()}");
                    break;
            }
        }

        return Command::SUCCESS;
    }
}
