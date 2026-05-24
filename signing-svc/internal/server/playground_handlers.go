package server

import (
	"encoding/json"
	"net/http"
	"strings"

	"github.com/blockchain-lab/signing-svc/internal/playground"
)

// --- /v1/playground/keypair  POST  ----------------------------------------

type playgroundKeypairReq struct {
	Mnemonic string `json:"mnemonic,omitempty"`
}

func (h *handlers) playgroundKeypair(w http.ResponseWriter, r *http.Request) {
	var req playgroundKeypairReq
	if r.ContentLength > 0 {
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			writeErr(w, http.StatusBadRequest, "bad_request", err.Error())
			return
		}
	}
	kp, err := playground.GenerateKeypair(req.Mnemonic)
	if err != nil {
		writeErr(w, http.StatusBadRequest, "keypair_failed", err.Error())
		return
	}
	writeJSON(w, http.StatusOK, kp)
}

// --- /v1/playground/sign  POST  -------------------------------------------

type playgroundSignReq struct {
	PrivateKeyHex string `json:"private_key_hex"`
	MessageHex    string `json:"message_hex"`
}

func (h *handlers) playgroundSign(w http.ResponseWriter, r *http.Request) {
	var req playgroundSignReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeErr(w, http.StatusBadRequest, "bad_request", err.Error())
		return
	}
	if req.PrivateKeyHex == "" || req.MessageHex == "" {
		writeErr(w, http.StatusBadRequest, "missing_fields", "private_key_hex and message_hex are required")
		return
	}
	res, err := playground.Sign(req.PrivateKeyHex, req.MessageHex)
	if err != nil {
		writeErr(w, http.StatusBadRequest, "sign_failed", err.Error())
		return
	}
	writeJSON(w, http.StatusOK, res)
}

// --- /v1/playground/decode  POST  -----------------------------------------

type playgroundDecodeReq struct {
	Chain  string `json:"chain"`
	RawHex string `json:"raw_hex"`
}

func (h *handlers) playgroundDecode(w http.ResponseWriter, r *http.Request) {
	var req playgroundDecodeReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeErr(w, http.StatusBadRequest, "bad_request", err.Error())
		return
	}
	if req.Chain == "" || req.RawHex == "" {
		writeErr(w, http.StatusBadRequest, "missing_fields", "chain and raw_hex are required")
		return
	}
	switch strings.ToLower(req.Chain) {
	case "bitcoin", "btc":
		res, err := playground.DecodeBitcoin(req.RawHex)
		if err != nil {
			writeErr(w, http.StatusBadRequest, "decode_failed", err.Error())
			return
		}
		writeJSON(w, http.StatusOK, res)
	case "ethereum", "eth", "evm":
		res, err := playground.DecodeEthereum(req.RawHex)
		if err != nil {
			writeErr(w, http.StatusBadRequest, "decode_failed", err.Error())
			return
		}
		writeJSON(w, http.StatusOK, res)
	default:
		writeErr(w, http.StatusBadRequest, "unknown_chain", "chain must be 'bitcoin' or 'ethereum'")
	}
}
