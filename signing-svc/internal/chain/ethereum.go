package chain

import (
	"errors"
	"regexp"

	ethcommon "github.com/ethereum/go-ethereum/common"
	ethcrypto "github.com/ethereum/go-ethereum/crypto"
	"github.com/tyler-smith/go-bip32"
)

// evmAdapter обрабатывает Ethereum и любую EVM-совместимую сеть (Polygon,
// Arbitrum, Base, Optimism, …). Формат адреса у них идентичен —
// различается только chain_id, используемый при подписании транзакции
// (EIP-155 replay protection).
//
// Алгоритм адреса (GUIDE.md, Урок 2 «Криптография за кулисами», вариант EVM):
//
//	address = "0x" + last20(keccak256(uncompressed_pubkey_without_0x04_prefix))
//
// Поверх — EIP-55 checksum-encoding (смешанный регистр шестнадцатеричных цифр).
// Путь деривации BIP-44: m/44'/60'/account'/change/index (SLIP-44 coin = 60).
type evmAdapter struct{}

func newEvmAdapter() *evmAdapter { return &evmAdapter{} }

func (a *evmAdapter) Family() Family { return FamilyEvm }

// uncompressedPubkey возвращает 65-байтовый несжатый публичный ключ SECP256K1
// (префикс 0x04 + X + Y), из которого выводятся адреса как Ethereum, так и Tron.
func uncompressedPubkey(child *bip32.Key) ([]byte, error) {
	if child == nil {
		return nil, errors.New("nil child key")
	}
	priv, err := ethcrypto.ToECDSA(child.Key)
	if err != nil {
		return nil, err
	}
	return ethcrypto.FromECDSAPub(&priv.PublicKey), nil
}

// Address выводит EVM-адрес из дочернего HD-ключа (GUIDE.md, Урок 2):
//
//  1. uncompressed pubkey = 0x04 || X || Y (65 байт).
//  2. addr = last20(keccak256(pubkey без префикса 0x04))  →  20 байт.
//  3. checksum-encoding по EIP-55 (.Hex() ставит регистр).
func (a *evmAdapter) Address(child *bip32.Key) (string, error) {
	pub, err := uncompressedPubkey(child)
	if err != nil {
		return "", err
	}
	// pub[1:] отбрасывает префиксный байт 0x04 (uncompressed marker), который не
	// участвует в вычислении адреса.
	addrBytes := ethcrypto.Keccak256(pub[1:])[12:]
	return ethcommon.BytesToAddress(addrBytes).Hex(), nil // EIP-55 checksum encoding
}

var evmAddrRegexp = regexp.MustCompile(`^0x[0-9a-fA-F]{40}$`)

func (a *evmAdapter) IsValid(address string) bool {
	if !evmAddrRegexp.MatchString(address) {
		return false
	}
	// Отклонять полностью нулевые адреса для сжигания (burn addresses) на раннем этапе —
	// это не синтаксическая ошибка, но частый источник ошибок пользователей.
	if address == "0x0000000000000000000000000000000000000000" {
		return false
	}
	// Если входные данные используют смешанный регистр, проверить контрольную сумму EIP-55.
	if address != ethcommon.HexToAddress(address).Hex() &&
		hasMixedCase(address[2:]) {
		return false
	}
	return true
}

func hasMixedCase(s string) bool {
	hasUpper, hasLower := false, false
	for _, r := range s {
		switch {
		case r >= 'a' && r <= 'f':
			hasLower = true
		case r >= 'A' && r <= 'F':
			hasUpper = true
		}
	}
	return hasUpper && hasLower
}
