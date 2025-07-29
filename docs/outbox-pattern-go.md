## ⚡ Outbox Pattern + WAL Streaming + Kafka (Go)

How can we guarantee that an event is published to Kafka only if the database transaction is committed?

Our solution: Outbox Pattern + PostgreSQL Logical Replication + Go Publisher

✅ Events are written to an outbox table as part of the main PostgreSQL transaction

✅ A Go service reads changes directly from PostgreSQL's WAL using the wal2json plugin

✅ The Go service transforms the data and publishes it to Kafka

✅ A Symfony-based Kafka consumer reads and processes events from Kafka


## 🚀 Quick Start

1️⃣ **Switch to the example commit**
```bash
git switch <commit>  # Outbox pattern with Go WAL reader and Kafka
```

2️⃣ **Build and start Docker services**
```bash
docker compose -f docker/docker-compose.yml up -d --build 
```

3️⃣ **Install Composer dependencies**
```bash
composer install
```
4️⃣ **Add setting to .env**
```bash
DATABASE_URL="pgsql://postgres:postgres@postgres:5432/app?serverVersion=15&charset=utf8"
```

5️⃣ **Run database migrations**
```bash
php bin/console doctrine:migrations:migrate
```

6️⃣ Set up replication in Postgres
(You only need to do this once)
```bash
docker exec -it postgres psql -U postgres -d app
```
Then, in the psql shell:
```bash
-- 1. Create publication for the outbox table
CREATE PUBLICATION my_publication FOR TABLE outbox;

-- 2. Create logical replication slot (required by the Go CDC reader)
SELECT * FROM pg_create_logical_replication_slot('slot_wal2json', 'wal2json');
```

7️⃣ Run the Go CDC service
Then run:
```bash
go run main.go
```

### Usage (from inside the PHP container)

**✅ Insert an event into Postgres**
```bash
php php bin/console app:test-outbox
```

**📡 Consume messages from Kafka**
```bash
php bin/console app:kafka:consume
```
