package chain

import (
	"errors"
	"fmt"

	"github.com/btcsuite/btcd/btcutil"
	"github.com/btcsuite/btcd/chaincfg"
	"github.com/tyler-smith/go-bip32"
)

// bitcoinAdapter выводит адреса P2WPKH (native segwit, bech32) в mainnet.
// Testnet / regtest используют то же семейство, но другой HRP — выбор сети
// делается через агрегат Chain. На данный момент здесь зафиксирован mainnet,
// чтобы вывод был тестируемым на опубликованных векторах BIP-44.
//
// Алгоритм адреса (GUIDE.md, Урок 2 «Криптография за кулисами», вариант Bitcoin):
//
//	address = bech32(0, RIPEMD160(SHA256(pubkey)))
//
// Префикс зависит от сети: bc1q… (mainnet), tb1q… (testnet), bcrt1q… (regtest).
// Путь деривации для BIP-84: m/84'/0'/account'/change/index — см.
// App\Modules\Address\Domain\Service\DerivationPathFactory.
type bitcoinAdapter struct {
	params *chaincfg.Params
}

func newBitcoinAdapter() *bitcoinAdapter {
	return &bitcoinAdapter{params: &chaincfg.MainNetParams}
}

func (a *bitcoinAdapter) Family() Family { return FamilyBitcoin }

// Address выводит Bech32-адрес P2WPKH из дочернего HD-ключа.
// Шаги (GUIDE.md, Урок 2):
//  1. compressed pubkey = X || sign(Y) — 33 байта.
//  2. hash160 = RIPEMD160(SHA256(pubkey)) — 20 байт.
//  3. Bech32-encoding с witness version 0 → "bc1q…".
func (a *bitcoinAdapter) Address(child *bip32.Key) (string, error) {
	if child == nil {
		return "", errors.New("nil child key")
	}
	pubKeyBytes := child.PublicKey().Key
	// Hash160 = RIPEMD160(SHA256(pubkey)) — двойной hash для устойчивости к коллизиям.
	hash160 := btcutil.Hash160(pubKeyBytes)

	// witness version = 0 → P2WPKH; HRP берётся из params (bc / tb / bcrt / tb).
	witnessAddr, err := btcutil.NewAddressWitnessPubKeyHash(hash160, a.params)
	if err != nil {
		return "", fmt.Errorf("bech32 encode: %w", err)
	}
	return witnessAddr.EncodeAddress(), nil
}

func (a *bitcoinAdapter) IsValid(address string) bool {
	// Попробовать сети main + test + regtest (вызывающая сторона может не знать, какая именно).
	for _, params := range []*chaincfg.Params{
		&chaincfg.MainNetParams,
		&chaincfg.TestNet3Params,
		&chaincfg.RegressionNetParams,
		&chaincfg.SigNetParams,
	} {
		if _, err := btcutil.DecodeAddress(address, params); err == nil {
			return true
		}
	}
	return false
}
