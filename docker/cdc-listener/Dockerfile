FROM golang:1.24

WORKDIR /app

COPY . .

RUN go mod init cdc-listener && go mod tidy || true

CMD ["tail", "-f", "/dev/null"]
