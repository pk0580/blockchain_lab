package chain

import (
	"bytes"

	"github.com/btcsuite/btcd/btcutil/base58"
	ethcrypto "github.com/ethereum/go-ethereum/crypto"
	"github.com/tyler-smith/go-bip32"
)

// tronAdapter разделяет схему пар ключей secp256k1 с Ethereum.
// Структура адреса:
//   payload = 0x41 || last20(keccak256(uncompressed_pubkey_without_0x04))
//   checksum = first4(sha256(sha256(payload)))
//   address = base58(payload || checksum)
//
// Результатом является строка Base58Check из 34 символов, начинающаяся с 'T'.
type tronAdapter struct{}

func newTronAdapter() *tronAdapter { return &tronAdapter{} }

const tronPrefix byte = 0x41

func (a *tronAdapter) Family() Family { return FamilyTron }

func (a *tronAdapter) Address(child *bip32.Key) (string, error) {
	pub, err := uncompressedPubkey(child)
	if err != nil {
		return "", err
	}
	hash := ethcrypto.Keccak256(pub[1:])[12:]
	payload := append([]byte{tronPrefix}, hash...)
	return base58.CheckEncode(payload[1:], payload[0]), nil
}

func (a *tronAdapter) IsValid(address string) bool {
	if len(address) != 34 || address[0] != 'T' {
		return false
	}
	decoded, version, err := base58.CheckDecode(address)
	if err != nil {
		return false
	}
	if version != tronPrefix {
		return false
	}
	if len(decoded) != 20 {
		return false
	}
	// Отклонять пустой (нулевой) полезный контент (burn-style).
	if bytes.Equal(decoded, make([]byte, 20)) {
		return false
	}
	return true
}
