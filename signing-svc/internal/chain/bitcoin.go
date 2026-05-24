package chain

import (
	"errors"
	"fmt"

	"github.com/btcsuite/btcd/btcutil"
	"github.com/btcsuite/btcd/chaincfg"
	"github.com/tyler-smith/go-bip32"
)

// bitcoinAdapter выводит адреса P2WPKH (native segwit, bech32) в mainnet.
// Testnet / regtest используют то же семейство, но другой HRP — Фаза 4 подключает
// выбор сети через агрегат Chain. На данный момент мы используем mainnet, чтобы
// вывод был тестируемым на опубликованных векторах BIP-44.
type bitcoinAdapter struct {
	params *chaincfg.Params
}

func newBitcoinAdapter() *bitcoinAdapter {
	return &bitcoinAdapter{params: &chaincfg.MainNetParams}
}

func (a *bitcoinAdapter) Family() Family { return FamilyBitcoin }

func (a *bitcoinAdapter) Address(child *bip32.Key) (string, error) {
	if child == nil {
		return "", errors.New("nil child key")
	}
	pubKeyBytes := child.PublicKey().Key
	hash160 := btcutil.Hash160(pubKeyBytes)

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
