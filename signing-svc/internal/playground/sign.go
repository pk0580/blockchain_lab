package playground

import (
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"fmt"

	"github.com/btcsuite/btcd/btcec/v2"
	"github.com/btcsuite/btcd/btcec/v2/ecdsa"
)

// SignResult — результат демо-подписания. Включает digest (двойной SHA-256
// от message bytes — стандарт Bitcoin) + DER-кодированную подпись + raw r/s.
type SignResult struct {
	DigestHex    string `json:"digest_hex"`
	RHex         string `json:"r_hex"`
	SHex         string `json:"s_hex"`
	SignatureDER string `json:"signature_der_hex"`
	Algorithm    string `json:"algorithm"`
}

// Sign подписывает сообщение по схеме Bitcoin: digest = sha256d(message_bytes),
// затем ECDSA-подпись на secp256k1. message_hex может быть произвольным —
// playground не интерпретирует его как tx.
func Sign(privateKeyHex, messageHex string) (*SignResult, error) {
	privBytes, err := hex.DecodeString(privateKeyHex)
	if err != nil {
		return nil, fmt.Errorf("private_key_hex: %w", err)
	}
	if len(privBytes) != 32 {
		return nil, errors.New("private_key_hex must be 32 bytes (64 hex chars)")
	}
	msgBytes, err := hex.DecodeString(messageHex)
	if err != nil {
		return nil, fmt.Errorf("message_hex: %w", err)
	}

	first := sha256.Sum256(msgBytes)
	digest := sha256.Sum256(first[:])

	priv, _ := btcec.PrivKeyFromBytes(privBytes)
	sig := ecdsa.Sign(priv, digest[:])
	derBytes := sig.Serialize()

	// Compact form: [header byte][32 R][32 S] — даёт нам raw r/s готовые к
	// показу студенту. Header (recovery id + 27) для playground не нужен,
	// мы режем его и показываем только числа.
	compact := ecdsa.SignCompact(priv, digest[:], true)
	if len(compact) != 65 {
		return nil, fmt.Errorf("unexpected compact signature length: %d", len(compact))
	}

	return &SignResult{
		DigestHex:    hex.EncodeToString(digest[:]),
		RHex:         hex.EncodeToString(compact[1:33]),
		SHex:         hex.EncodeToString(compact[33:65]),
		SignatureDER: hex.EncodeToString(derBytes),
		Algorithm:    "ECDSA/secp256k1 over sha256d(message)",
	}, nil
}
