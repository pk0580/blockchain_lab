package chain

import (
	"bytes"

	"github.com/btcsuite/btcd/btcutil/base58"
	ethcrypto "github.com/ethereum/go-ethereum/crypto"
	"github.com/tyler-smith/go-bip32"
)

// tronAdapter разделяет схему пар ключей secp256k1 с Ethereum, но кодирует
// адрес иначе (см. GUIDE.md, Урок 2 «Криптография за кулисами», вариант Tron).
//
// Структура адреса:
//
//	payload  = 0x41 || last20(keccak256(uncompressed_pubkey_without_0x04))
//	checksum = first4(sha256(sha256(payload)))
//	address  = base58(payload || checksum)
//
// Результат — Base58Check-строка из 34 символов, всегда начинается с 'T'
// (потому что префикс 0x41 даёт первый символ "T" в base58).
// Путь деривации BIP-44: m/44'/195'/account'/change/index (SLIP-44 coin = 195).
type tronAdapter struct{}

func newTronAdapter() *tronAdapter { return &tronAdapter{} }

const tronPrefix byte = 0x41

func (a *tronAdapter) Family() Family { return FamilyTron }

// Address выводит Tron-адрес (GUIDE.md, Урок 2, вариант Tron):
//  1. keccak256(uncompressed_pubkey без 0x04)[12:] — та же 20-байтовая основа, что у EVM.
//  2. Добавляем префикс-байт 0x41 (отличает Tron от Ethereum при общей криптографии).
//  3. base58check кодирует payload вместе с double-sha256 checksum'ом.
func (a *tronAdapter) Address(child *bip32.Key) (string, error) {
	pub, err := uncompressedPubkey(child)
	if err != nil {
		return "", err
	}
	hash := ethcrypto.Keccak256(pub[1:])[12:]
	payload := append([]byte{tronPrefix}, hash...)
	// base58.CheckEncode требует payload без version-байта первым аргументом,
	// version отдельным — поэтому передаём срез [1:] и сам tronPrefix.
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
