package chain

import (
	"testing"

	"github.com/blockchain-lab/signing-svc/internal/seed"
)

// Canonical zero-mnemonic per BIP-39 spec. Widely-used in test vectors.
const zeroMnemonic = "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about"

func loadSeedT(t *testing.T) *seed.Seed {
	t.Helper()
	s, err := seed.FromMnemonic("test-seed", zeroMnemonic, "")
	if err != nil {
		t.Fatalf("seed: %v", err)
	}
	return s
}

func TestEvmDerivation(t *testing.T) {
	s := loadSeedT(t)
	r := NewRegistry()
	d, err := r.Derivator(FamilyEvm)
	if err != nil {
		t.Fatal(err)
	}
	child, err := DerivePath(s.Master, "m/44'/60'/0'/0/0")
	if err != nil {
		t.Fatalf("derive: %v", err)
	}
	addr, err := d.Address(child)
	if err != nil {
		t.Fatalf("address: %v", err)
	}
	// Widely-published ETH BIP-44 vector for the zero mnemonic.
	want := "0x9858EfFD232B4033E47d90003D41EC34EcaEda94"
	if addr != want {
		t.Fatalf("evm address mismatch:\n  want %s\n   got %s", want, addr)
	}
}

func TestBitcoinDerivation(t *testing.T) {
	s := loadSeedT(t)
	r := NewRegistry()
	d, err := r.Derivator(FamilyBitcoin)
	if err != nil {
		t.Fatal(err)
	}
	// BIP-84 (native segwit) uses m/84'/0'/0'/0/0; BIP-44 uses m/44'/0'/0'/0/0
	// (legacy). Our adapter encodes P2WPKH, so we use the BIP-84 path for an
	// apples-to-apples comparison against published vectors.
	child, err := DerivePath(s.Master, "m/84'/0'/0'/0/0")
	if err != nil {
		t.Fatalf("derive: %v", err)
	}
	addr, err := d.Address(child)
	if err != nil {
		t.Fatalf("address: %v", err)
	}
	want := "bc1qcr8te4kr609gcawutmrza0j4xv80jy8z306fyu"
	if addr != want {
		t.Fatalf("btc P2WPKH address mismatch:\n  want %s\n   got %s", want, addr)
	}
}

func TestTronDerivation(t *testing.T) {
	s := loadSeedT(t)
	r := NewRegistry()
	d, err := r.Derivator(FamilyTron)
	if err != nil {
		t.Fatal(err)
	}
	child, err := DerivePath(s.Master, "m/44'/195'/0'/0/0")
	if err != nil {
		t.Fatalf("derive: %v", err)
	}
	addr, err := d.Address(child)
	if err != nil {
		t.Fatalf("address: %v", err)
	}
	// This is the address our pipeline produces for the zero mnemonic at
	// m/44'/195'/0'/0/0. Cross-verification against a third-party Tron
	// wallet is a Phase-3 task; for now this serves as a regression anchor —
	// if the BIP-32 lib or keccak impl ever changes shape, this will fail.
	want := "TUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdH"
	if addr != want {
		t.Fatalf("tron address mismatch:\n  want %s\n   got %s", want, addr)
	}
	// Structural sanity: round-trip through our own validator.
	v, err := r.Validator(FamilyTron)
	if err != nil {
		t.Fatal(err)
	}
	if !v.IsValid(addr) {
		t.Fatalf("validator rejected freshly-derived address %q", addr)
	}
}

func TestValidators(t *testing.T) {
	cases := []struct {
		family Family
		good   []string
		bad    []string
	}{
		{
			family: FamilyEvm,
			good: []string{
				"0x9858EfFD232B4033E47d90003D41EC34EcaEda94",
				"0x9858effd232b4033e47d90003d41ec34ecaeda94", // all lower → no checksum check
			},
			bad: []string{
				"",
				"0x9858EFFD232B4033E47d90003D41EC34EcaEda94", // bad EIP-55 checksum
				"0x0000000000000000000000000000000000000000", // burn
				"0xnothex",
			},
		},
		{
			family: FamilyBitcoin,
			good: []string{
				"bc1qcr8te4kr609gcawutmrza0j4xv80jy8z306fyu",
				"1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa", // Genesis
			},
			bad: []string{"", "not-an-address"},
		},
		{
			family: FamilyTron,
			good:   []string{"TUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdH"},
			bad: []string{
				"",
				"TUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdI", // bad checksum
				"AUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdH", // wrong prefix
				"TUEZSdKsoDHQMeZwihtdoBi",            // too short
			},
		},
	}
	r := NewRegistry()
	for _, tc := range cases {
		t.Run(string(tc.family), func(t *testing.T) {
			v, err := r.Validator(tc.family)
			if err != nil {
				t.Fatal(err)
			}
			for _, g := range tc.good {
				if !v.IsValid(g) {
					t.Errorf("expected valid: %q", g)
				}
			}
			for _, b := range tc.bad {
				if v.IsValid(b) {
					t.Errorf("expected invalid: %q", b)
				}
			}
		})
	}
}
