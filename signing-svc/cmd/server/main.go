// Command server запускает сервис подписи blockchain-lab.
//
// Выполняется в отдельном процессе, чтобы приватные ключи никогда не находились в одном
// адресном пространстве с кодом оркестрации (см. docs/architecture/decisions/0002-go-signing-service.md
// и 0004-no-wallet-core-yet.md).
package main

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/blockchain-lab/signing-svc/internal/config"
	"github.com/blockchain-lab/signing-svc/internal/logger"
	"github.com/blockchain-lab/signing-svc/internal/seed"
	"github.com/blockchain-lab/signing-svc/internal/server"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		slog.Error("config load failed", "err", err)
		os.Exit(1)
	}

	log := logger.New(cfg.LogLevel)
	slog.SetDefault(log)

	store, err := seed.NewFileStore(cfg.SeedDir, cfg.SeedEncryptionKey)
	if err != nil {
		log.Error("seed store init failed", "err", err)
		os.Exit(1)
	}

	srv := server.New(cfg, log, store)

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	go func() {
		log.Info("signing-svc listening", "addr", cfg.ListenAddr)
		if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			log.Error("server exited", "err", err)
			stop()
		}
	}()

	<-ctx.Done()
	log.Info("shutdown signal received")

	shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	if err := srv.Shutdown(shutdownCtx); err != nil {
		log.Error("graceful shutdown failed", "err", err)
		os.Exit(1)
	}
	log.Info("signing-svc stopped")
}
