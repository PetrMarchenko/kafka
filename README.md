# Kafka Symfony example — With Zookeeper and KRaft

This is a ready-to-run Kafka example built with Symfony and Docker.

It allows you to **quickly spin up Kafka with Zookeeper or Kafka in KRaft mode** and see in practice how message producers and consumers work.

---

## 🚀 Quick Start

### Installation

1️⃣ **Clone the repository**

```bash
git clone https://github.com/PetrMarchenko/kafka.git
cd kafka
```

2️⃣ **Switch to <commit>**
```bash
git switch <commit> //Kafka + Zookeeper setup
```

3️⃣ **Start Docker services**
```bash
docker compose -f docker/docker-compose.yml up -d --build 
```

4️⃣ **Enter the PHP container**
```bash
docker exec -it php bash
```

5️⃣ **Copy environment config**
```bash
cp .env.dist .env
```

6️⃣ **Install Composer dependencies**
```bash
composer install
```


## ⚡ Kafka + Zookeeper / KRaft setup

### How to Use (inside the PHP container)

**Send a test message to Kafka**
```bash
php bin/console app:multiple-test-outbox --total=1000000 --batch=5 --delay=0.001
```

**Consume messages from Kafka**
```bash
php bin/console app:kafka:consume
```

## ⚡ Outbox Pattern + Debezium + Kafka Connect

How can we guarantee that an event is published to Kafka only if the database transaction is committed?

Our solution: Outbox Pattern + Debezium + Kafka Connect

### 📦Setup Guide
See full setup instructions here: 📄 [docs/outbox-pattern.md](docs/outbox-pattern.md)

### Usage (from inside the PHP container)

**✅ Insert an event into Postgres**
```bash
php php bin/console app:test-outbox
```

**📡 Consume messages from Kafka**
```bash
php bin/console app:kafka:consume
```
