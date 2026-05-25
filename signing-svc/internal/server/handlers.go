package server

import (
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"os"

	"github.com/blockchain-lab/signing-svc/internal/chain"
	"github.com/blockchain-lab/signing-svc/internal/seed"
)

type handlers struct {
	log      *slog.Logger
	seeds    seed.Store
	registry *chain.Registry
}

// --- /v1/health -------------------------------------------------------------

func (h *handlers) health(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]string{"status": "ok"})
}

// --- /v1/seeds  POST {reference}  ------------------------------------------

type createSeedReq struct {
	Reference string `json:"reference"`
	Mnemonic  string `json:"mnemonic,omitempty"` // опционально — импорт существующей
}

type createSeedResp struct {
	Reference string `json:"reference"`
	Created   bool   `json:"created"`
}

// createSeed обрабатывает POST /v1/seeds — создаёт новый сид (или импортирует
// существующую мнемонику) и сохраняет в зашифрованный файл .sealed.
//
// ⚠️ Идемпотентность по reference: если сид уже существует — возвращаем 200
// {created: false}, мнемонику НЕ перегенерируем. Это критично для надёжности
// вызывающей стороны: CreateHdSeedAction в Laravel может ретраиться (GUIDE.md,
// Урок 2 «Почему именно такой порядок ‘сначала signing-svc, потом БД’»).
//
// Возвращаемые статусы:
//
//	201 Created — новый сид создан;
//	200 OK      — сид с таким reference уже существовал;
//	400         — невалидный reference / битая мнемоника;
//	500         — ошибка хранилища.
func (h *handlers) createSeed(w http.ResponseWriter, r *http.Request) {
	var req createSeedReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeErr(w, http.StatusBadRequest, "bad_request", err.Error())
		return
	}
	if req.Reference == "" {
		writeErr(w, http.StatusBadRequest, "missing_reference", "reference is required")
		return
	}
	// Идемпотентность: проверяем по имени файла, не пытаясь даже расшифровать.
	exists, err := h.seeds.Exists(req.Reference)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "store_error", err.Error())
		return
	}
	if exists {
		writeJSON(w, http.StatusOK, createSeedResp{Reference: req.Reference, Created: false})
		return
	}
	// Два режима: либо генерим новую мнемонику (BIP-39, 256 бит энтропии),
	// либо импортируем переданную (для миграции/восстановления).
	var s *seed.Seed
	if req.Mnemonic == "" {
		s, err = seed.Generate(req.Reference, "")
	} else {
		s, err = seed.FromMnemonic(req.Reference, req.Mnemonic, "")
	}
	if err != nil {
		writeErr(w, http.StatusBadRequest, "bad_mnemonic", err.Error())
		return
	}
	if err := h.seeds.Save(s); err != nil {
		writeErr(w, http.StatusInternalServerError, "store_error", err.Error())
		return
	}
	h.log.Info("seed created", "reference", s.Reference)
	writeJSON(w, http.StatusCreated, createSeedResp{Reference: s.Reference, Created: true})
}

// --- /v1/addresses/derive  POST  -------------------------------------------

type deriveReq struct {
	SeedReference string `json:"seed_reference"`
	Family        string `json:"family"`
	Path          string `json:"path"`
}

type deriveResp struct {
	Address string `json:"address"`
	Family  string `json:"family"`
	Path    string `json:"path"`
}

func (h *handlers) derive(w http.ResponseWriter, r *http.Request) {
	var req deriveReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeErr(w, http.StatusBadRequest, "bad_request", err.Error())
		return
	}
	if req.SeedReference == "" || req.Family == "" || req.Path == "" {
		writeErr(w, http.StatusBadRequest, "missing_fields", "seed_reference, family, path are required")
		return
	}
	family, err := chain.FamilyFromString(req.Family)
	if err != nil {
		writeErr(w, http.StatusBadRequest, "unknown_family", err.Error())
		return
	}
	s, err := h.seeds.Load(req.SeedReference)
	if err != nil {
		if errors.Is(err, os.ErrNotExist) {
			writeErr(w, http.StatusNotFound, "seed_not_found", "no such seed reference")
			return
		}
		writeErr(w, http.StatusInternalServerError, "store_error", err.Error())
		return
	}
	child, err := chain.DerivePath(s.Master, req.Path)
	if err != nil {
		writeErr(w, http.StatusBadRequest, "bad_path", err.Error())
		return
	}
	d, err := h.registry.Derivator(family)
	if err != nil {
		writeErr(w, http.StatusBadRequest, "unknown_family", err.Error())
		return
	}
	addr, err := d.Address(child)
	if err != nil {
		writeErr(w, http.StatusInternalServerError, "derive_error", err.Error())
		return
	}
	writeJSON(w, http.StatusOK, deriveResp{Address: addr, Family: string(family), Path: req.Path})
}

// --- /v1/addresses/validate  POST  -----------------------------------------

type validateReq struct {
	Family  string `json:"family"`
	Address string `json:"address"`
}

type validateResp struct {
	Valid bool `json:"valid"`
}

func (h *handlers) validate(w http.ResponseWriter, r *http.Request) {
	var req validateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeErr(w, http.StatusBadRequest, "bad_request", err.Error())
		return
	}
	family, err := chain.FamilyFromString(req.Family)
	if err != nil {
		writeErr(w, http.StatusBadRequest, "unknown_family", err.Error())
		return
	}
	v, err := h.registry.Validator(family)
	if err != nil {
		writeErr(w, http.StatusBadRequest, "unknown_family", err.Error())
		return
	}
	writeJSON(w, http.StatusOK, validateResp{Valid: v.IsValid(req.Address)})
}
