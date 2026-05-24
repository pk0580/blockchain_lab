package playground

import (
	"strings"
	"testing"
)

func TestSign_ProducesValidLooking(t *testing.T) {
	// Test vector: privkey = 32 bytes 0x01..0x20, message = "Hello"
	priv := "0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f20"
	msg := "48656c6c6f" // "Hello"

	res, err := Sign(priv, msg)
	if err != nil {
		t.Fatalf("Sign failed: %v", err)
	}
	if len(res.DigestHex) != 64 {
		t.Errorf("digest hex must be 64 chars, got %d", len(res.DigestHex))
	}
	if len(res.RHex) != 64 {
		t.Errorf("r hex must be 64 chars, got %d", len(res.RHex))
	}
	if len(res.SHex) != 64 {
		t.Errorf("s hex must be 64 chars, got %d", len(res.SHex))
	}
	if !strings.HasPrefix(res.SignatureDER, "30") {
		t.Errorf("DER signature should start with 30 (SEQUENCE), got %s", res.SignatureDER[:2])
	}
}

func TestSign_RejectsShortPrivateKey(t *testing.T) {
	_, err := Sign("abcd", "01")
	if err == nil {
		t.Fatal("expected error for short private key")
	}
}

func TestSign_RejectsBadHex(t *testing.T) {
	_, err := Sign("zz"+strings.Repeat("0", 62), "00")
	if err == nil {
		t.Fatal("expected error for non-hex private key")
	}
}
