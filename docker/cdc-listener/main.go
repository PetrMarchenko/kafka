// This Go application connects to PostgreSQL in logical replication mode,
// listens to WAL changes using the "wal2json" plugin,
// transforms them to a Debezium-like format, and sends them to Kafka.

package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"os"
	"time"

	"github.com/jackc/pglogrepl"
	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgconn"
	"github.com/jackc/pgx/v5/pgproto3"
	"github.com/segmentio/kafka-go"
)

// Configuration constants
const (
	outputPlugin = "wal2json"
	slotName     = "slot_wal2json"
	connStr      = "postgres://postgres:postgres@postgres:5432/app?replication=database"
	kafkaTopic   = "app.public.outbox"
)

var kafkaWriter *kafka.Writer

// initKafka initializes the Kafka writer with basic configuration.
func initKafka() {
	kafkaWriter = kafka.NewWriter(kafka.WriterConfig{
		Brokers:  []string{os.Getenv("KAFKA_BROKER")},
		Topic:    kafkaTopic,
		Balancer: &kafka.LeastBytes{},
	})
}

// main is the entry point. It initializes Kafka, connects to Postgres, starts logical replication, and processes WAL changes.
func main() {
	initKafka()
	defer kafkaWriter.Close()

	pgxConn, sysident := connectAndStartReplication()
	defer pgxConn.Close(context.Background())
	consumeChanges(pgxConn.PgConn(), &sysident)
}

// connectAndStartReplication connects to Postgres and starts replication from the specified slot.
func connectAndStartReplication() (*pgx.Conn, pglogrepl.IdentifySystemResult) {
	pgxConn, err := pgx.Connect(context.Background(), connStr)
	if err != nil {
		log.Fatalf("failed to connect to postgres: %v", err)
	}

	conn := pgxConn.PgConn()

	sysident, err := pglogrepl.IdentifySystem(context.Background(), conn)
	if err != nil {
		log.Fatalf("IdentifySystem failed: %v", err)
	}

	fmt.Printf("SystemID: %s Timeline: %d XLogPos: %s DBName: %s\n",
		sysident.SystemID, sysident.Timeline, sysident.XLogPos, sysident.DBName)

	err = pglogrepl.StartReplication(context.Background(), conn, slotName, sysident.XLogPos, pglogrepl.StartReplicationOptions{
		PluginArgs: []string{
			`"pretty-print" 'true'`,
			`"include-lsn" 'true'`,
			`"include-timestamp" 'true'`,
		},
	})
	if err != nil {
		log.Fatalf("StartReplication failed: %v", err)
	}

	fmt.Println("Replication started...")
	return pgxConn, sysident
}

// consumeChanges continuously receives replication messages and processes XLogData records.
func consumeChanges(conn *pgconn.PgConn, sysident *pglogrepl.IdentifySystemResult) {
	var lastLSN pglogrepl.LSN = sysident.XLogPos
	statusTimeout := 10 * time.Second

	for {
		ctx, cancel := context.WithTimeout(context.Background(), statusTimeout)
		msg, err := conn.ReceiveMessage(ctx)
		cancel()
		if err != nil {
			if pgconn.Timeout(err) {
				_ = pglogrepl.SendStandbyStatusUpdate(context.Background(), conn, pglogrepl.StandbyStatusUpdate{
					WALWritePosition: lastLSN,
					ReplyRequested:   false,
				})
				continue
			}
			log.Fatalf("ReceiveMessage failed: %v", err)
		}

		copyData, ok := msg.(*pgproto3.CopyData)
		if !ok || len(copyData.Data) == 0 {
			continue
		}

		switch copyData.Data[0] {
		case pglogrepl.PrimaryKeepaliveMessageByteID:
			continue
		case pglogrepl.XLogDataByteID:
			xlogData, err := pglogrepl.ParseXLogData(copyData.Data[1:])
			if err != nil {
				log.Printf("ParseXLogData failed: %v", err)
				continue
			}
			lastLSN = xlogData.WALStart + pglogrepl.LSN(len(xlogData.WALData))
			handleMessage(xlogData.WALData)
		default:
			log.Printf("Unknown message type: %v", copyData.Data[0])
		}
	}
}

// Change represents a single parsed change from WAL
// handleMessage parses a WAL change event and dispatches parsed changes to Kafka.
// handleMessage parses and dispatches all changes contained in a WAL message.
func handleMessage(data []byte) {
	var msg map[string]interface{}
	if err := json.Unmarshal(data, &msg); err != nil {
		log.Printf("Failed to decode JSON: %v", err)
		return
	}

	changesRaw, ok := msg["change"].([]interface{})
	if !ok {
		log.Printf("No 'change' field in message")
		return
	}

	for _, raw := range changesRaw {
		change, ok := raw.(map[string]interface{})
		if !ok {
			continue
		}

		row := make(map[string]interface{})
		names, _ := change["columnnames"].([]interface{})
		values, _ := change["columnvalues"].([]interface{})
		for i := range names {
			key := fmt.Sprintf("%v", names[i])
			if i < len(values) {
				row[key] = values[i]
			}
		}

		innerPayload, err := json.Marshal(row)
		if err != nil {
			log.Printf("Failed to marshal row: %v", err)
			continue
		}

		wrapped := map[string]interface{}{
			"payload": map[string]interface{}{
				"after": map[string]interface{}{
					"payload": string(innerPayload),
				},
			},
		}

		final, err := json.Marshal(wrapped)
		if err != nil {
			log.Printf("Failed to wrap payload: %v", err)
			continue
		}

		dispatchChange(row, final)
	}
}

// dispatchChange sends the final message to Kafka. This function is decoupled from parsing logic.
func dispatchChange(row map[string]interface{}, message []byte) {
	err := kafkaWriter.WriteMessages(context.Background(), kafka.Message{
		Key:   []byte(fmt.Sprintf("%v", row["aggregate_id"])),
		Value: message,
	})
	if err != nil {
		log.Printf("Kafka write error: %v", err)
	} else {
		log.Printf("Sent to Kafka: %s", message)
	}
}
