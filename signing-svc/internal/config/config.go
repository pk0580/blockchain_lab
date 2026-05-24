package config

import (
	"encoding/hex"
	"errors"
	"fmt"
	"os"
	"strings"
)

// Config содержит конфигурацию среды выполнения, загруженную из переменных окружения.
type Config struct {
	ListenAddr        string
	LogLevel          string
	BearerToken       string // общий секрет, который должны предоставить вызывающие стороны (Фаза 7 → mTLS)
	SeedDir           string // директория, содержащая запечатанные HD-сиды
	SeedEncryptionKey []byte // 32 байта, закодированные в hex в окружении
}

// Load считывает конфигурацию из окружения и проверяет ее.
func Load() (*Config, error) {
	cfg := &Config{
		ListenAddr:  envOr("SIGNING_SVC_LISTEN_ADDR", ":8080"),
		LogLevel:    envOr("SIGNING_SVC_LOG_LEVEL", "info"),
		BearerToken: os.Getenv("SIGNING_SVC_BEARER_TOKEN"),
		SeedDir:     envOr("SIGNING_SVC_SEED_DIR", "/var/lib/signing-svc/seeds"),
	}

	if cfg.BearerToken == "" {
		return nil, errors.New("SIGNING_SVC_BEARER_TOKEN is required")
	}
	if len(cfg.BearerToken) < 32 {
		return nil, errors.New("SIGNING_SVC_BEARER_TOKEN must be at least 32 chars")
	}

	keyHex := strings.TrimSpace(os.Getenv("SIGNING_SVC_SEED_ENCRYPTION_KEY"))
	if keyHex == "" {
		return nil, errors.New("SIGNING_SVC_SEED_ENCRYPTION_KEY is required (32 bytes hex)")
	}
	key, err := hex.DecodeString(keyHex)
	if err != nil {
		return nil, fmt.Errorf("SIGNING_SVC_SEED_ENCRYPTION_KEY is not valid hex: %w", err)
	}
	if len(key) != 32 {
		return nil, fmt.Errorf("SIGNING_SVC_SEED_ENCRYPTION_KEY must decode to 32 bytes, got %d", len(key))
	}
	cfg.SeedEncryptionKey = key

	return cfg, nil
}

func envOr(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
