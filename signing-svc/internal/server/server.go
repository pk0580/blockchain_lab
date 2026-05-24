// Package server настраивает HTTP-роутер. Сами обработчики находятся в
// handlers.go.
package server

import (
	"log/slog"
	"net/http"
	"time"

	"github.com/blockchain-lab/signing-svc/internal/auth"
	"github.com/blockchain-lab/signing-svc/internal/chain"
	"github.com/blockchain-lab/signing-svc/internal/config"
	"github.com/blockchain-lab/signing-svc/internal/seed"
	"github.com/go-chi/chi/v5"
	"github.com/go-chi/chi/v5/middleware"
)

// New собирает полностью настроенный *http.Server.
func New(cfg *config.Config, log *slog.Logger, store seed.Store) *http.Server {
	h := &handlers{
		log:      log,
		seeds:    store,
		registry: chain.NewRegistry(),
	}

	r := chi.NewRouter()
	r.Use(middleware.RequestID)
	r.Use(middleware.RealIP)
	r.Use(middleware.Recoverer)
	r.Use(middleware.Timeout(15 * time.Second))

	// Без аутентификации.
	r.Get("/v1/health", h.health)

	// Защищено Bearer-токеном.
	r.Group(func(r chi.Router) {
		r.Use(auth.BearerMiddleware(cfg.BearerToken))
		r.Post("/v1/seeds", h.createSeed)
		r.Post("/v1/addresses/derive", h.derive)
		r.Post("/v1/addresses/validate", h.validate)

		// Phase 8.2 — educational ephemeral endpoints. Возвращают приватный
		// ключ в открытом виде; никогда не использовать для реальных средств.
		r.Post("/v1/playground/keypair", h.playgroundKeypair)
		r.Post("/v1/playground/sign", h.playgroundSign)
		r.Post("/v1/playground/decode", h.playgroundDecode)
	})

	return &http.Server{
		Addr:              cfg.ListenAddr,
		Handler:           r,
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       10 * time.Second,
		WriteTimeout:      15 * time.Second,
		IdleTimeout:       60 * time.Second,
	}
}
