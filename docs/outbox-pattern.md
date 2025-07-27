## ⚡ Outbox Pattern + Debezium + Kafka Connect

How can we guarantee that an event is published to Kafka only if the database transaction is committed?

Our solution: Outbox Pattern + Debezium + Kafka Connect

✅ Events are written to an outbox table as part of the main PostgreSQL transaction

✅ Debezium reads changes from PostgreSQL's WAL (Write-Ahead Log) and pushes them to Kafka

✅ A Symfony-based Kafka consumer reads events from Kafka and processes them

## 🚀 Quick Start

1️⃣ **Switch to the example commit**
```bash
git switch <commit>  # Outbox pattern + Debezium + Kafka Connect
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

6️⃣ **Register Debezium connector**
```bash
curl -X POST http://kafka-connect:8083/connectors \
  -H "Content-Type: application/json" \
  -d '{
    "name": "outbox-connector",
    "config": {
      "connector.class": "io.debezium.connector.postgresql.PostgresConnector",
      "plugin.name": "pgoutput",
      "database.hostname": "postgres",
      "database.port": "5432",
      "database.user": "postgres",
      "database.password": "postgres",
      "database.dbname": "app",
      "database.server.name": "app_server",
      "topic.prefix": "app",
      "table.include.list": "public.outbox",
      "slot.name": "outbox_slot",
      "publication.autocreate.mode": "filtered",
      "tombstones.on.delete": "false",
      "key.converter": "org.apache.kafka.connect.storage.StringConverter",
      "value.converter": "org.apache.kafka.connect.json.JsonConverter"
    }
  }'
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
