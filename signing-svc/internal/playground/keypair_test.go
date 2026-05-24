package playground

import (
	"strings"
	"testing"
)

// Известный BIP-39 mnemonic — фиксируется здесь для детерминированного теста.
// Ожидаемые значения захвачены из реального выхода tyler-smith/go-bip32 +
// btcutil + go-ethereum по пути m/44'/0'/0'/0/0. Если значения меняются —
// значит мы случайно поменяли алгоритм derivation; это load-bearing.
const (
	knownMnemonic    = "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about"
	expectedPrivKey  = "e284129cc0922579a535bbf4d1a3b25773090d28c909bc0fed73b5e0222cc372"
	expectedBtcAddr  = "bc1qmxrw6qdh5g3ztfcwm0et5l8mvws4eva24kmp8m"
	expectedEthAddr  = "0x030821f53C79461511bCf64367eCc9D013060408"
	expectedTronAddr = "TAFEmhbjjnTAnqRag2w9ke9J2cYiWzvyHr"
)

func TestGenerateKeypair_DeterministicFromKnownMnemonic(t *testing.T) {
	kp, err := GenerateKeypair(knownMnemonic)
	if err != nil {
		t.Fatalf("GenerateKeypair failed: %v", err)
	}
	if kp.Mnemonic != knownMnemonic {
		t.Errorf("Mnemonic mismatch: got %q, want %q", kp.Mnemonic, knownMnemonic)
	}
	if kp.PrivateKeyHex != expectedPrivKey {
		t.Errorf("Private key mismatch:\n  got  %s\n  want %s", kp.PrivateKeyHex, expectedPrivKey)
	}
	if kp.BitcoinAddress != expectedBtcAddr {
		t.Errorf("BTC address mismatch:\n  got  %s\n  want %s", kp.BitcoinAddress, expectedBtcAddr)
	}
	if !strings.EqualFold(kp.EthereumAddress, expectedEthAddr) {
		t.Errorf("ETH address mismatch:\n  got  %s\n  want %s", kp.EthereumAddress, expectedEthAddr)
	}
	if kp.TronAddress != expectedTronAddr {
		t.Errorf("Tron address mismatch:\n  got  %s\n  want %s", kp.TronAddress, expectedTronAddr)
	}
}

func TestGenerateKeypair_RandomMnemonicIsValid12Words(t *testing.T) {
	kp, err := GenerateKeypair("")
	if err != nil {
		t.Fatalf("GenerateKeypair failed: %v", err)
	}
	words := strings.Fields(kp.Mnemonic)
	if len(words) != 12 {
		t.Errorf("expected 12-word mnemonic for 128-bit entropy, got %d", len(words))
	}
	if len(kp.PrivateKeyHex) != 64 {
		t.Errorf("expected 64-char private key hex, got %d", len(kp.PrivateKeyHex))
	}
	if !strings.HasPrefix(kp.BitcoinAddress, "bc1") {
		t.Errorf("expected mainnet bech32 prefix, got %q", kp.BitcoinAddress)
	}
	if !strings.HasPrefix(kp.EthereumAddress, "0x") {
		t.Errorf("expected 0x prefix on ETH address, got %q", kp.EthereumAddress)
	}
	if !strings.HasPrefix(kp.TronAddress, "T") {
		t.Errorf("expected 'T' prefix on Tron address, got %q", kp.TronAddress)
	}
}

func TestGenerateKeypair_RejectsInvalidMnemonic(t *testing.T) {
	_, err := GenerateKeypair("definitely not a valid bip39 mnemonic at all")
	if err == nil {
		t.Fatal("expected error for invalid mnemonic, got nil")
	}
}
