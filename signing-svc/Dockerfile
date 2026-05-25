FROM golang:1.23-alpine AS build

WORKDIR /src

RUN apk add --no-cache git ca-certificates

COPY . .

# go.sum is generated on the fly (we don't ship one yet); a checked-in
# go.sum should be added once the dep graph stabilises.
RUN go mod tidy

RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build -trimpath -ldflags='-s -w' -o /out/signing-svc ./cmd/server

# ---------------------------------------------------------------------------
FROM alpine:3.20

RUN apk add --no-cache ca-certificates tzdata \
    && addgroup -g 1000 svc \
    && adduser -D -u 1000 -G svc -s /sbin/nologin svc \
    && mkdir -p /var/lib/signing-svc/seeds \
    && chown -R svc:svc /var/lib/signing-svc

COPY --from=build /out/signing-svc /usr/local/bin/signing-svc

USER svc
WORKDIR /var/lib/signing-svc

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/signing-svc"]
