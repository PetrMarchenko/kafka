<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'outbox')]
class OutboxMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\Column(type: 'string')]
    private string $aggregateType;

    #[ORM\Column(type: 'string')]
    private string $aggregateId;

    #[ORM\Column(type: 'string')]
    private string $type;

    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $aggregateType,
        string $aggregateId,
        string $type,
        array $payload
    ) {
        $this->id = Uuid::v4();
        $this->aggregateType = $aggregateType;
        $this->aggregateId = $aggregateId;
        $this->type = $type;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }
}
